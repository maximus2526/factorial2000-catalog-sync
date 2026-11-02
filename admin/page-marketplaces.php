<?php
/**
 * Marketplaces admin page: export WooCommerce products to Prom.ua (and later other marketplaces).
 *
 * Premium-only file (see the @fs_premium_only header of the main plugin file).
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

use F2000CS\Marketplace_Feed;
use F2000CS\Marketplace_Form;
use F2000CS\Marketplace_Job;
use F2000CS\Marketplace_Registry;
use F2000CS\Marketplace_Storage;
use F2000CS\Marketplace_Text;
use F2000CS\Prom_Categories;
use F2000CS\Prom_Feed;
use F2000CS\Prom_Recommendations;

/**
 * Human readable schedule names.
 *
 * @return array<string,string>
 */
function f2000cs_marketplace_schedule_labels(): array {
	return array(
		'off'           => __( 'Вручну (без розкладу)', 'factorial2000-catalog-sync' ),
		'hourly'        => __( 'Щогодини', 'factorial2000-catalog-sync' ),
		'6_times_daily' => __( '6 разів на день', 'factorial2000-catalog-sync' ),
		'4_times_daily' => __( '4 рази на день', 'factorial2000-catalog-sync' ),
		'twicedaily'    => __( 'Двічі на день', 'factorial2000-catalog-sync' ),
		'daily'         => __( 'Щодня', 'factorial2000-catalog-sync' ),
	);
}

/**
 * Description paragraph under a field.
 *
 * @param array<string,mixed> $field Field schema.
 * @return void
 */
function f2000cs_marketplace_field_description( array $field ): void {
	if ( ! empty( $field['description'] ) ) {
		echo '<p class="description">' . esc_html( (string) $field['description'] ) . '</p>';
	}
}

/**
 * Select with presets plus an optional "custom" choice that reveals a free input.
 *
 * @param string              $key   Field key.
 * @param array<string,mixed> $field Field schema.
 * @param mixed               $value Current stored value.
 * @return void
 */
function f2000cs_marketplace_render_preset( string $key, array $field, $value ): void {
	$options = (array) ( $field['options'] ?? array() );
	$custom  = $field['custom'] ?? null;
	$current = Marketplace_Form::selected_preset( (string) $value, $options );

	// Without a custom option an unknown stored value falls back to the first preset.
	if ( ! $custom && Marketplace_Form::CUSTOM === $current ) {
		$current = (string) array_key_first( $options );
	}

	$id          = 'f2000cs-mp-' . $key;
	$custom_type = 'number' === ( $custom['type'] ?? 'text' ) ? 'number' : 'text';
	?>
	<select id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" class="f2000cs-mp-preset" data-custom-input="<?php echo esc_attr( $id ); ?>-custom">
		<?php foreach ( $options as $option => $label ) : ?>
			<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $current, (string) $option ); ?>><?php echo esc_html( (string) $label ); ?></option>
		<?php endforeach; ?>
		<?php if ( $custom ) : ?>
			<option value="<?php echo esc_attr( Marketplace_Form::CUSTOM ); ?>" <?php selected( $current, Marketplace_Form::CUSTOM ); ?>><?php echo esc_html( (string) ( $custom['label'] ?? __( 'Своє значення…', 'factorial2000-catalog-sync' ) ) ); ?></option>
		<?php endif; ?>
	</select>
	<?php if ( $custom ) : ?>
		<input type="<?php echo esc_attr( $custom_type ); ?>" id="<?php echo esc_attr( $id ); ?>-custom" name="mp_custom[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( Marketplace_Form::CUSTOM === $current ? (string) $value : '' ); ?>" class="regular-text f2000cs-mp-custom" <?php echo 'number' === $custom_type ? 'min="' . esc_attr( (string) ( $custom['min'] ?? 0 ) ) . '" max="' . esc_attr( (string) ( $custom['max'] ?? '' ) ) . '"' : ''; ?> <?php echo Marketplace_Form::CUSTOM === $current ? '' : 'hidden'; ?>>
	<?php endif; ?>
	<?php
}

/**
 * Select2 with free input («tags»): pick a suggestion or type a new value.
 *
 * @param string            $name        Field name.
 * @param string            $value       Current value.
 * @param array<int,string> $options     Suggestions.
 * @param string            $placeholder Placeholder.
 * @return void
 */
function f2000cs_marketplace_render_tag_select( string $name, string $value, array $options, string $placeholder ): void {
	if ( '' !== $value ) {
		$options[] = $value;
	}

	$options = array_values( array_unique( array_filter( array_map( 'strval', $options ), 'strlen' ) ) );
	?>
	<select name="<?php echo esc_attr( $name ); ?>" class="f2000cs-mp-tags" data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
		<option value=""></option>
		<?php foreach ( $options as $option ) : ?>
			<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $value, $option ); ?>><?php echo esc_html( $option ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Suggestions for a brand field: brands of the shop plus every name already saved.
 *
 * @param array<int, array{0:string,1:string}> $stored Saved rows.
 * @return array<int,string>
 */
function f2000cs_marketplace_brand_suggestions( array $stored ): array {
	$out = Marketplace_Form::load_brands();

	foreach ( $stored as $row ) {
		$out[] = $row[0];
		$out[] = $row[1];
	}

	return $out;
}

/**
 * Brand table: every brand of the shop with an optional «name in Prom» next to it.
 *
 * Brands go to Prom as they are on the site, so a row is filled only where the Prom
 * name differs. Stored aliases for brands the shop does not list (for example a brand
 * that comes from a product attribute) stay as editable rows, plus one empty row.
 *
 * @param string              $key   Field key.
 * @param array<string,mixed> $field Field schema.
 * @param mixed               $value Current stored value.
 * @return void
 */
function f2000cs_marketplace_render_brand_pairs( string $key, array $field, $value ): void {
	$stored = array();

	foreach ( Marketplace_Form::text_to_rows( (string) $value ) as $row ) {
		$stored[ Marketplace_Text::lower( $row[0] ) ] = $row;
	}

	$brands = Marketplace_Form::load_brands();
	$rows   = array();

	foreach ( $brands as $brand ) {
		$lower  = Marketplace_Text::lower( $brand );
		$rows[] = array( $brand, isset( $stored[ $lower ] ) ? $stored[ $lower ][1] : '', true );
		unset( $stored[ $lower ] );
	}

	foreach ( $stored as $row ) {
		$rows[] = array( $row[0], $row[1], false );
	}

	$rows[] = array( '', '', false );

	$suggest = f2000cs_marketplace_brand_suggestions( Marketplace_Form::text_to_rows( (string) $value ) );
	?>
	<?php if ( count( $brands ) > 8 ) : ?>
		<p><input type="search" class="regular-text f2000cs-mp-brand-filter" placeholder="<?php esc_attr_e( 'Пошук бренду…', 'factorial2000-catalog-sync' ); ?>"></p>
	<?php endif; ?>
	<div style="max-height:420px;overflow:auto;max-width:720px;border:1px solid #c3c4c7">
		<table class="widefat striped f2000cs-mp-pairs f2000cs-mp-brand-table" data-key="<?php echo esc_attr( $key ); ?>">
			<thead>
				<tr>
					<th><?php echo esc_html( (string) ( $field['left_label'] ?? '' ) ); ?></th>
					<th><?php echo esc_html( (string) ( $field['right_label'] ?? '' ) ); ?></th>
					<th style="width:40px"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $i => $row ) : ?>
					<tr data-name="<?php echo esc_attr( Marketplace_Text::lower( $row[0] ) ); ?>">
						<td>
							<?php if ( $row[2] ) : ?>
								<?php echo esc_html( $row[0] ); ?>
								<input type="hidden" name="mp_rows[<?php echo esc_attr( $key ); ?>][<?php echo (int) $i; ?>][l]" value="<?php echo esc_attr( $row[0] ); ?>">
							<?php else : ?>
								<?php f2000cs_marketplace_render_tag_select( 'mp_rows[' . $key . '][' . (int) $i . '][l]', $row[0], $suggest, __( 'Інший бренд…', 'factorial2000-catalog-sync' ) ); ?>
							<?php endif; ?>
						</td>
						<td><input type="text" class="regular-text" name="mp_rows[<?php echo esc_attr( $key ); ?>][<?php echo (int) $i; ?>][r]" value="<?php echo esc_attr( $row[1] ); ?>" placeholder="<?php echo $row[2] ? esc_attr( $row[0] ) : ''; ?>"></td>
						<td>
						<?php
						if ( ! $row[2] ) :
							?>
							<button type="button" class="button-link f2000cs-mp-row-remove" aria-label="<?php esc_attr_e( 'Видалити рядок', 'factorial2000-catalog-sync' ); ?>">✕</button><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p><button type="button" class="button f2000cs-mp-row-add" data-target="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '+ Додати інший бренд', 'factorial2000-catalog-sync' ); ?></button></p>
	<?php
}

/**
 * Repeater of "left → right" rows (stored as left=right lines).
 *
 * @param string              $key   Field key.
 * @param array<string,mixed> $field Field schema.
 * @param mixed               $value Current stored value.
 * @return void
 */
function f2000cs_marketplace_render_pairs( string $key, array $field, $value ): void {
	if ( 'brands' === ( $field['left_suggest'] ?? '' ) && Marketplace_Form::load_brands() ) {
		f2000cs_marketplace_render_brand_pairs( $key, $field, $value );
		return;
	}

	$rows   = Marketplace_Form::text_to_rows( (string) $value );
	$rows[] = array( '', '' );

	$suggest = f2000cs_marketplace_brand_suggestions( Marketplace_Form::text_to_rows( (string) $value ) );
	?>
	<table class="widefat striped f2000cs-mp-pairs" data-key="<?php echo esc_attr( $key ); ?>" style="max-width:720px">
		<thead>
			<tr>
				<th><?php echo esc_html( (string) ( $field['left_label'] ?? '' ) ); ?></th>
				<th><?php echo esc_html( (string) ( $field['right_label'] ?? '' ) ); ?></th>
				<th style="width:40px"></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $i => $row ) : ?>
				<tr>
					<td><?php f2000cs_marketplace_render_tag_select( 'mp_rows[' . $key . '][' . (int) $i . '][l]', $row[0], $suggest, (string) ( $field['left_label'] ?? '' ) ); ?></td>
					<td><input type="text" class="regular-text" name="mp_rows[<?php echo esc_attr( $key ); ?>][<?php echo (int) $i; ?>][r]" value="<?php echo esc_attr( $row[1] ); ?>"></td>
					<td><button type="button" class="button-link f2000cs-mp-row-remove" aria-label="<?php esc_attr_e( 'Видалити рядок', 'factorial2000-catalog-sync' ); ?>">✕</button></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p><button type="button" class="button f2000cs-mp-row-add" data-target="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '+ Додати відповідність', 'factorial2000-catalog-sync' ); ?></button></p>
	<?php
}

/**
 * Table "store category → Prom portal category id" plus a box for custom rules.
 *
 * @param string              $key   Field key.
 * @param array<string,mixed> $field Field schema.
 * @param mixed               $value Current stored rules.
 * @return void
 */
function f2000cs_marketplace_render_category_map( string $key, array $field, $value ): void {
	$categories = Marketplace_Form::load_categories();
	$split      = Marketplace_Form::split_rules( (string) $value, $categories );

	if ( ! $categories ) {
		echo '<em>' . esc_html__( 'У магазині ще немає категорій товарів.', 'factorial2000-catalog-sync' ) . '</em>';
	} else {
		?>
		<p><input type="search" class="regular-text f2000cs-mp-cat-filter" placeholder="<?php esc_attr_e( 'Пошук категорії…', 'factorial2000-catalog-sync' ); ?>"></p>
		<div style="max-height:420px;overflow:auto;max-width:960px;border:1px solid #c3c4c7">
			<table class="widefat striped f2000cs-mp-cat-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Категорія магазину', 'factorial2000-catalog-sync' ); ?></th>
						<th style="width:70px"><?php esc_html_e( 'Товарів', 'factorial2000-catalog-sync' ); ?></th>
						<th style="width:50%"><?php esc_html_e( 'Категорія Prom', 'factorial2000-catalog-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $categories as $cat ) : ?>
						<tr data-name="<?php echo esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $cat['path'], 'UTF-8' ) : strtolower( $cat['path'] ) ); ?>">
							<td style="padding-left:<?php echo (int) ( 10 + 16 * $cat['depth'] ); ?>px"><?php echo esc_html( basename( str_replace( ' > ', '/', $cat['path'] ) ) ); ?></td>
							<td><?php echo (int) $cat['count']; ?></td>
							<td>
								<?php
								f2000cs_prom_category_input(
									'mp_map[' . $key . '][' . (int) $cat['id'] . ']',
									(string) ( $split['by_id'][ $cat['id'] ] ?? '' ),
									array(
										'class'       => 'f2000cs-mp-cat-input',
										'placeholder' => '—',
									)
								);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
	?>
	<p class="description"><?php echo f2000cs_prom_category_reference_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?></p>
	<details style="margin-top:8px">
		<summary><?php esc_html_e( 'Свої правила (для досвідчених)', 'factorial2000-catalog-sync' ); ?></summary>
		<textarea name="mp_extra[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text code" placeholder="кросівки=1234567"><?php echo esc_textarea( $split['extra'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'По одному правилу в рядку: частина шляху категорії=номер Prom, наприклад «одяг>куртки=1234567». Спрацьовує перше правило, що збіглося.', 'factorial2000-catalog-sync' ); ?></p>
	</details>
	<?php
}

/**
 * Prom category ids currently selected in the form settings.
 *
 * @param array<string,mixed> $settings Feed settings.
 * @return array<int,string>
 */
function f2000cs_marketplace_prom_portal_ids( array $settings ): array {
	$ids = array();

	$default = Prom_Categories::sanitize_id( $settings['default_portal_category_id'] ?? '' );

	if ( '' !== $default ) {
		$ids[] = $default;
	}

	foreach ( Marketplace_Feed::parse_pairs( (string) ( $settings['portal_category_rules'] ?? '' ) ) as $portal_id ) {
		$portal_id = Prom_Categories::sanitize_id( $portal_id );

		if ( '' !== $portal_id ) {
			$ids[] = $portal_id;
		}
	}

	return array_values( array_unique( $ids ) );
}

/**
 * Summary above the recommended-attributes table.
 *
 * @param array<int,string>                  $ids Prom category ids.
 * @param array<string,array<string,string>> $map Mapping.
 * @return string
 */
function f2000cs_marketplace_prom_attribute_summary( array $ids, array $map ): string {
	$categories = Prom_Recommendations::categories( $ids );

	if ( array() === $categories ) {
		return __( 'Категорії Prom ще не вибрані.', 'factorial2000-catalog-sync' );
	}

	$unique  = Prom_Recommendations::unique_attributes( $categories );
	$with    = 0;
	$auto    = 0;
	$manual  = 0;
	$missing = 0;

	foreach ( $categories as $category ) {
		if ( array() !== $category['attrs'] ) {
			++$with;
		}

		foreach ( $category['attrs'] as $attr ) {
			$selected = (string) ( $map[ $category['id'] ][ $attr['name'] ] ?? '' );
			$match    = Prom_Recommendations::match( $attr['name'], $attr['name_ru'] );

			if ( '' !== $selected ) {
				if ( '' !== $match && $selected === $match ) {
					++$auto;
				} else {
					++$manual;
				}
			} elseif ( '' !== $match ) {
				++$auto;
			} else {
				++$missing;
			}
		}
	}

	return sprintf(
		/* translators: 1: selected categories, 2: categories with recommendations, 3: unique characteristics, 4: automatic matches, 5: manual mappings, 6: missing site attributes */
		__( '%1$d категорій · %2$d з рекомендаціями · %3$d характеристик · %4$d авто · %5$d вручну · %6$d без відповідника', 'factorial2000-catalog-sync' ),
		count( $categories ),
		$with,
		count( $unique ),
		$auto,
		$manual,
		$missing
	);
}

/**
 * Select of site attributes for one recommended characteristic.
 *
 * @param string $category_id Prom category id.
 * @param string $prom_name   Prom characteristic name.
 * @param string $selected    Stored site key.
 * @param string $auto        Auto-matched site key.
 * @return string
 */
function f2000cs_marketplace_prom_attribute_select( string $category_id, string $prom_name, string $selected, string $auto ): string {
	$site    = Prom_Recommendations::site_attributes();
	$in_list = isset( $site[ $selected ] );
	$custom  = 0 === strpos( $selected, 'custom:' );
	$html    = '<select class="f2000cs-mp-attr-select" data-category="' . esc_attr( $category_id ) . '" data-prom="' . esc_attr( $prom_name ) . '" data-auto="' . esc_attr( $auto ) . '">';
	$html   .= '<option value="">' . esc_html__( '— не вибрано —', 'factorial2000-catalog-sync' ) . '</option>';
	$html   .= '<optgroup label="' . esc_attr__( 'Глобальні атрибути', 'factorial2000-catalog-sync' ) . '">';

	foreach ( $site as $key => $attr ) {
		if ( 'global' !== ( $attr['source'] ?? '' ) ) {
			continue;
		}

		$html .= '<option value="' . esc_attr( $key ) . '" ' . selected( $selected, $key, false ) . '>' . esc_html( (string) $attr['label'] ) . '</option>';
	}

	$html .= '</optgroup>';

	if ( '' !== $selected && ! $in_list ) {
		$label = $custom ? substr( $selected, 7 ) : $selected;

		$html .= '<optgroup label="' . esc_attr__( 'Кастомні атрибути', 'factorial2000-catalog-sync' ) . '">';
		$html .= '<option value="' . esc_attr( $selected ) . '" selected>' . esc_html( $label . ' (кастомний)' ) . '</option>';
		$html .= '</optgroup>';
	}

	$html .= '</select>';

	return $html;
}

/**
 * Rows of the recommended-attributes table.
 *
 * @param array<int,string>                  $ids Prom category ids.
 * @param array<string,array<string,string>> $map Mapping.
 * @return string
 */
function f2000cs_marketplace_prom_attribute_rows( array $ids, array $map ): string {
	$categories = Prom_Recommendations::categories( $ids );

	if ( array() === $categories ) {
		return '<tr><td colspan="4" class="f2000cs-prom-empty">' . esc_html__( 'Спочатку виберіть категорії Prom у розділі «Категорії Prom».', 'factorial2000-catalog-sync' ) . '</td></tr>';
	}

	$html = '';

	foreach ( $categories as $category ) {
		$attrs   = (array) $category['attrs'];
		$matched = 0;
		$search  = Marketplace_Text::lower( $category['path'] . ' ' . $category['id'] );

		foreach ( $attrs as $attr ) {
			$selected = (string) ( $map[ $category['id'] ][ $attr['name'] ] ?? '' );
			$auto     = Prom_Recommendations::match( $attr['name'], $attr['name_ru'] );

			if ( '' !== $selected || '' !== $auto ) {
				++$matched;
			}
		}

		$html .= '<tr class="f2000cs-prom-group" data-f2000cs-prom-group data-search="' . esc_attr( $search ) . '">';
		$html .= '<td colspan="4"><strong>' . esc_html( $category['path'] ) . '</strong> <span class="f2000cs-prom-count">' . esc_html( (string) $matched ) . '/' . esc_html( (string) count( $attrs ) ) . '</span></td>';
		$html .= '</tr>';

		if ( array() === $attrs ) {
			$html .= '<tr class="f2000cs-prom-empty-row" data-f2000cs-prom-empty data-search="' . esc_attr( $search ) . '"><td></td><td colspan="3">' . esc_html__( 'Prom не має рекомендацій для цієї категорії.', 'factorial2000-catalog-sync' ) . '</td></tr>';
			continue;
		}

		foreach ( $attrs as $attr ) {
			$selected = (string) ( $map[ $category['id'] ][ $attr['name'] ] ?? '' );
			$auto     = Prom_Recommendations::match( $attr['name'], $attr['name_ru'] );
			$value    = '' !== $selected ? $selected : $auto;
			$status   = 'none';

			if ( '' !== $value ) {
				$status = '' === $selected || $selected === $auto ? 'auto' : 'manual';
			}

			$row_search = Marketplace_Text::lower( $category['path'] . ' ' . $category['id'] . ' ' . $attr['name'] . ' ' . $attr['name_ru'] );
			$status_lbl = array(
				'auto'   => __( 'Авто', 'factorial2000-catalog-sync' ),
				'manual' => __( 'Вручну', 'factorial2000-catalog-sync' ),
				'none'   => __( 'Немає', 'factorial2000-catalog-sync' ),
			);

			$html .= '<tr class="f2000cs-prom-row" data-f2000cs-prom-row data-category="' . esc_attr( $category['id'] ) . '" data-prom="' . esc_attr( $attr['name'] ) . '" data-auto="' . esc_attr( $auto ) . '" data-status="' . esc_attr( $status ) . '" data-search="' . esc_attr( $row_search ) . '">';
			$html .= '<td class="f2000cs-prom-cat"></td>';
			$html .= '<td><strong>' . esc_html( $attr['name'] ) . '</strong>';

			if ( '' !== $attr['name_ru'] && $attr['name_ru'] !== $attr['name'] ) {
				$html .= '<br><small>' . esc_html( $attr['name_ru'] ) . '</small>';
			}

			$html .= '</td>';
			$html .= '<td>' . f2000cs_marketplace_prom_attribute_select( $category['id'], $attr['name'], $selected, $auto ) . '</td>';
			$html .= '<td><span class="f2000cs-prom-status is-' . esc_attr( $status ) . '" data-f2000cs-status>' . esc_html( $status_lbl[ $status ] ) . '</span></td>';
			$html .= '</tr>';
		}
	}

	return $html;
}

/**
 * Hidden mapping field, opener button and the modal with the table.
 *
 * @param string              $key      Field key.
 * @param array<string,mixed> $field    Field schema.
 * @param mixed               $value    Stored value.
 * @param array<string,mixed> $settings All feed settings.
 * @return void
 */
function f2000cs_marketplace_render_prom_attribute_map( string $key, array $field, $value, array $settings ): void {
	$ids     = f2000cs_marketplace_prom_portal_ids( $settings );
	$map     = Prom_Recommendations::sanitize_map_array( $value );
	$json    = Prom_Recommendations::sanitize_json( $map );
	$summary = f2000cs_marketplace_prom_attribute_summary( $ids, $map );
	$dialog  = 'f2000cs-prom-attrs-dialog';
	?>
	<input type="hidden" id="f2000cs-mp-<?php echo esc_attr( $key ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $json ); ?>" data-f2000cs-prom-map>
	<p>
		<button type="button" class="button f2000cs-mp-open-attributes" data-f2000cs-target="<?php echo esc_attr( $dialog ); ?>"><?php esc_html_e( 'Заповнити характеристики категорії', 'factorial2000-catalog-sync' ); ?></button>
		<span class="f2000cs-prom-summary" data-f2000cs-prom-summary-field><?php echo esc_html( $summary ); ?></span>
	</p>
	<dialog id="<?php echo esc_attr( $dialog ); ?>" class="f2000cs-modal f2000cs-prom-attrs-dialog" aria-labelledby="<?php echo esc_attr( $dialog . '-title' ); ?>">
		<div class="f2000cs-modal__head">
			<h2 id="<?php echo esc_attr( $dialog . '-title' ); ?>"><?php esc_html_e( 'Заповнити характеристики категорій', 'factorial2000-catalog-sync' ); ?></h2>
			<button type="button" class="button-link f2000cs-modal__close" data-f2000cs-close aria-label="<?php esc_attr_e( 'Закрити', 'factorial2000-catalog-sync' ); ?>">&times;</button>
		</div>
		<div class="f2000cs-modal__body">
			<p><?php esc_html_e( 'Prom рекомендує для кожної категорії 2–4 характеристики. Вони впливають на фільтри каталогу. Ми зіставили назви з атрибутами сайту — перевірте мапінг. У фід піде назва Prom, значення братимуться з атрибутів товару.', 'factorial2000-catalog-sync' ); ?></p>
			<p class="description"><?php esc_html_e( 'Рекомендації не обов’язкові, але заповнені характеристики дають більше трафіку. Щоб значення потрапили у фільтри Prom, вони мають збігатися з довідником характеристик категорії. Атрибута немає на сайті? Створіть його в WooCommerce або залиште поле порожнім.', 'factorial2000-catalog-sync' ); ?></p>
			<div class="f2000cs-prom-toolbar">
				<input type="search" class="f2000cs-prom-search" placeholder="<?php esc_attr_e( 'Пошук категорії або характеристики', 'factorial2000-catalog-sync' ); ?>">
				<label><input type="checkbox" class="f2000cs-prom-only-empty"> <?php esc_html_e( 'Тільки незаповнені', 'factorial2000-catalog-sync' ); ?></label>
				<button type="button" class="button f2000cs-prom-auto"><?php esc_html_e( 'Підібрати автоматично', 'factorial2000-catalog-sync' ); ?></button>
				<span class="f2000cs-prom-summary" data-f2000cs-prom-summary><?php echo esc_html( $summary ); ?></span>
			</div>
			<div class="f2000cs-prom-table-wrap">
				<table class="widefat striped f2000cs-prom-attrs-table">
					<thead>
						<tr>
							<th class="f2000cs-prom-cat"><?php esc_html_e( 'Категорія Prom', 'factorial2000-catalog-sync' ); ?></th>
							<th><?php esc_html_e( 'Характеристика Prom', 'factorial2000-catalog-sync' ); ?></th>
							<th><?php esc_html_e( 'Наш атрибут', 'factorial2000-catalog-sync' ); ?></th>
							<th><?php esc_html_e( 'Статус', 'factorial2000-catalog-sync' ); ?></th>
						</tr>
					</thead>
					<tbody data-f2000cs-prom-rows><?php echo f2000cs_marketplace_prom_attribute_rows( $ids, $map ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?></tbody>
				</table>
			</div>
		</div>
		<div class="f2000cs-modal__foot">
			<button type="button" class="button f2000cs-prom-pull"><?php esc_html_e( 'Підтягнути категорії з полів', 'factorial2000-catalog-sync' ); ?></button>
			<div>
				<button type="button" class="button f2000cs-prom-cancel"><?php esc_html_e( 'Скасувати', 'factorial2000-catalog-sync' ); ?></button>
				<button type="button" class="button button-primary f2000cs-prom-save"><?php esc_html_e( 'Зберегти', 'factorial2000-catalog-sync' ); ?></button>
			</div>
		</div>
	</dialog>
	<?php
}

/**
 * AJAX: rebuild the recommended-attributes table from the current form values.
 *
 * @return void
 */
function f2000cs_marketplace_ajax_prom_rows() {
	check_ajax_referer( 'f2000cs_prom_attribute_rows', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
	}

	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ids'] ) ) : array();
	$ids = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $id ) {
						return Prom_Categories::sanitize_id( $id );
					},
					$ids
				),
				'strlen'
			)
		)
	);

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Prom_Recommendations::sanitize_map_array().
	$map = Prom_Recommendations::sanitize_map_array( isset( $_POST['map'] ) ? wp_unslash( $_POST['map'] ) : '' );

	wp_send_json_success(
		array(
			'rows'    => f2000cs_marketplace_prom_attribute_rows( $ids, $map ),
			'summary' => f2000cs_marketplace_prom_attribute_summary( $ids, $map ),
			'map'     => Prom_Recommendations::sanitize_json( $map ),
		)
	);
}
add_action( 'wp_ajax_f2000cs_marketplace_prom_rows', 'f2000cs_marketplace_ajax_prom_rows' );

/**
 * Render one settings field row.
 *
 * @param string              $key   Field key.
 * @param array<string,mixed> $field Field schema.
 * @param mixed               $value Current value.
 * @param array<string,mixed> $settings All feed settings (for conditional rows).
 * @return void
 */
function f2000cs_marketplace_render_field( string $key, array $field, $value, array $settings = array() ): void {
	$type = $field['type'] ?? 'text';
	$id   = 'f2000cs-mp-' . $key;

	$show_if       = isset( $field['show_if'] ) && is_array( $field['show_if'] ) ? $field['show_if'] : array();
	$show_if_field = isset( $show_if['field'] ) ? (string) $show_if['field'] : '';
	$show_if_value = isset( $show_if['value'] ) ? (string) $show_if['value'] : '';
	$row_class     = '' !== $show_if_field ? 'f2000cs-mp-show-if' : '';
	$row_hidden    = '' !== $show_if_field && (string) ( $settings[ $show_if_field ] ?? '' ) !== $show_if_value;
	$row_attrs     = '';

	if ( '' !== $show_if_field ) {
		$row_attrs .= ' data-show-if-field="' . esc_attr( $show_if_field ) . '" data-show-if-value="' . esc_attr( $show_if_value ) . '"';
	}

	if ( $row_hidden ) {
		$row_attrs .= ' style="display:none"';
	}
	?>
	<tr class="<?php echo esc_attr( $row_class ); ?>"<?php echo $row_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>>
		<th scope="row"><?php echo in_array( $type, array( 'pairs', 'category_map', 'prom_attribute_map' ), true ) ? esc_html( (string) ( $field['label'] ?? $key ) ) : '<label for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $field['label'] ?? $key ) ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></th>
		<td>
			<?php
			if ( 'preset' === $type ) {
				f2000cs_marketplace_render_preset( $key, $field, $value );
			} elseif ( 'pairs' === $type ) {
				f2000cs_marketplace_render_pairs( $key, $field, $value );
			} elseif ( 'category_map' === $type ) {
				f2000cs_marketplace_render_category_map( $key, $field, $value );
			} elseif ( 'prom_attribute_map' === $type ) {
				f2000cs_marketplace_render_prom_attribute_map( $key, $field, $value, $settings );
			} elseif ( 'prom_category' === $type ) {
				f2000cs_prom_category_input( 'mp[' . $key . ']', (string) $value, array( 'id' => $id ) );
				echo '<p class="description">' . f2000cs_prom_category_reference_link() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper.
			} elseif ( 'checkbox' === $type ) {
				?>
				<input type="hidden" name="mp[<?php echo esc_attr( $key ); ?>]" value="0">
				<label><input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( '1', (string) $value ); ?>> <?php echo esc_html( (string) ( $field['checkbox'] ?? __( 'Увімкнено', 'factorial2000-catalog-sync' ) ) ); ?></label>
				<?php
			} elseif ( 'textarea' === $type ) {
				?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" rows="4" class="large-text code"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php
			} elseif ( 'number' === $type ) {
				?>
				<input type="number" id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text" min="<?php echo esc_attr( (string) ( $field['min'] ?? 0 ) ); ?>" max="<?php echo esc_attr( (string) ( $field['max'] ?? '' ) ); ?>">
				<?php
			} else {
				?>
				<input type="<?php echo 'url' === $type ? 'url' : 'text'; ?>" id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text">
				<?php
			}

			f2000cs_marketplace_field_description( $field );
			?>
		</td>
	</tr>
	<?php
}

/**
 * Marketplaces page.
 *
 * @return void
 */
function f2000cs_marketplaces_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		echo '<div class="wrap">';
		f2000cs_render_admin_page_title( __( 'Маркетплейси', 'factorial2000-catalog-sync' ) );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Вигрузка товарів на маркетплейси доступна лише у Pro версії.', 'factorial2000-catalog-sync' );
		echo ' <a href="' . esc_url( f2000cs_get_upgrade_url() ) . '">' . esc_html__( 'Оновити до Pro', 'factorial2000-catalog-sync' ) . '</a>';
		echo '</p></div></div>';
		return;
	}

	$feeds = Marketplace_Registry::all();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch.
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	$id   = isset( $feeds[ $tab ] ) ? $tab : (string) array_key_first( $feeds );
	$feed = $feeds[ $id ] ?? null;

	?>
	<div class="wrap f2000cs-marketplaces">
		<?php f2000cs_render_admin_page_title( __( 'Маркетплейси', 'factorial2000-catalog-sync' ) ); ?>

		<?php settings_errors( 'f2000cs_marketplace' ); ?>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $feeds as $feed_id => $item ) : ?>
				<a class="nav-tab <?php echo $feed_id === $id ? 'nav-tab-active' : ''; ?>" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => 'f2000cs-marketplaces',
							'tab'  => $feed_id,
						),
						admin_url( 'admin.php' )
					)
				);
				?>
									"><?php echo esc_html( $item->label() ); ?></a>
			<?php endforeach; ?>
			<span class="nav-tab" style="opacity:.55;cursor:default"><?php esc_html_e( 'Rozetka — незабаром', 'factorial2000-catalog-sync' ); ?></span>
			<span class="nav-tab" style="opacity:.55;cursor:default"><?php esc_html_e( 'Hotline — незабаром', 'factorial2000-catalog-sync' ); ?></span>
		</h2>

		<?php
		if ( ! $feed ) {
			echo '</div>';
			return;
		}

		$settings = Marketplace_Registry::settings( $id );
		$info     = Marketplace_Storage::info( $feed );
		$result   = Marketplace_Job::last_result( $id );
		$running  = Marketplace_Job::state( $id );
		$labels   = f2000cs_marketplace_schedule_labels();
		$fields   = $feed->fields();

		// Prom refuses prices in a currency it does not know.
		$feed_currency = Marketplace_Form::resolve( $settings['currency'] ?? '{wc_currency}' );
		?>
		<?php if ( $feed instanceof Prom_Feed && ! in_array( strtoupper( $feed_currency ), Prom_Feed::CURRENCIES, true ) ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: currency code, 2: supported currencies */
							__( 'Prom не приймає ціни у валюті «%1$s». Виберіть у налаштуваннях «Валюта цін» одну з підтримуваних: %2$s.', 'factorial2000-catalog-sync' ),
							$feed_currency,
							implode( ', ', Prom_Feed::CURRENCIES )
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div class="card" style="max-width:none">
			<h2><?php echo esc_html( $feed->label() ); ?></h2>
			<p class="description"><?php echo esc_html( $feed->description() ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Посилання на вигрузку', 'factorial2000-catalog-sync' ); ?></th>
					<td>
						<?php if ( $info['exists'] ) : ?>
							<?php $many = count( $info['parts'] ) > 1; ?>
							<?php foreach ( $info['parts'] as $part ) : ?>
								<p style="margin:0 0 6px">
									<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $part['url'] ); ?>" onclick="this.select()">
								</p>
							<?php endforeach; ?>
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: date and time, 2: file size */
										__( 'Оновлено: %1$s · %2$s.', 'factorial2000-catalog-sync' ),
										wp_date( 'd.m.Y H:i', $info['mtime'] ),
										$info['size'] < 1048576
											? number_format_i18n( max( 1, $info['size'] / 1024 ), 0 ) . ' ' . __( 'КБ', 'factorial2000-catalog-sync' )
											: number_format_i18n( $info['size'] / 1048576, 1 ) . ' ' . __( 'МБ', 'factorial2000-catalog-sync' )
									)
								);
								echo ' ';
								echo esc_html(
									$many
										? __( 'Каталог завеликий для одного файлу (Prom рекомендує до 180 МБ), тому він розбитий на кілька файлів. Додайте кожне посилання в кабінеті маркетплейсу окремим джерелом імпорту. Товари однієї групи завжди в одному файлі; перше посилання не змінюється.', 'factorial2000-catalog-sync' )
										: __( 'Вкажіть це посилання у кабінеті маркетплейсу як джерело імпорту.', 'factorial2000-catalog-sync' )
								);
								?>
							</p>
							<p class="description">
								<?php if ( $feed instanceof Prom_Feed ) : ?>
									<?php esc_html_e( 'У налаштуваннях імпорту Prom виберіть, що робити з товарами, яких немає у файлі («Немає в наявності», «Приховані» або «Залишити без змін»): неопубліковані товари сайту у файл не потрапляють.', 'factorial2000-catalog-sync' ); ?>
									<?php if ( $many ) : ?>
										<?php esc_html_e( 'Prom застосовує це до кожного джерела окремо.', 'factorial2000-catalog-sync' ); ?>
									<?php endif; ?>
								<?php else : ?>
									<?php esc_html_e( 'У Merchant Center: Products → Data sources → Add product source → Scheduled fetch. Вкажіть це посилання й розклад завантаження; назва файлу в Merchant Center має закінчуватися на .xml.', 'factorial2000-catalog-sync' ); ?>
								<?php endif; ?>
							</p>
						<?php else : ?>
							<em><?php esc_html_e( 'Вигрузку ще не згенеровано.', 'factorial2000-catalog-sync' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Остання генерація', 'factorial2000-catalog-sync' ); ?></th>
					<td>
						<?php if ( ! empty( $result['status'] ) ) : ?>
							<?php if ( 'done' === $result['status'] ) : ?>
								<?php
								if ( $feed instanceof Prom_Feed ) {
									echo esc_html(
										sprintf(
											/* translators: 1: offers, 2: categories, 3: skipped, 4: seconds */
											__( 'Товарів у вигрузці: %1$d · категорій: %2$d · пропущено: %3$d · %4$d с', 'factorial2000-catalog-sync' ),
											(int) ( $result['exported'] ?? 0 ),
											(int) ( $result['categories'] ?? 0 ),
											(int) ( $result['skipped'] ?? 0 ),
											(int) ( $result['duration'] ?? 0 )
										)
									);
								} else {
									echo esc_html(
										sprintf(
											/* translators: 1: items, 2: skipped, 3: seconds */
											__( 'Товарів у вигрузці: %1$d · пропущено: %2$d · %3$d с', 'factorial2000-catalog-sync' ),
											(int) ( $result['exported'] ?? 0 ),
											(int) ( $result['skipped'] ?? 0 ),
											(int) ( $result['duration'] ?? 0 )
										)
									);
								}
								?>
								<?php if ( ! empty( $result['excluded'] ) ) : ?>
									<p class="description">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: comma separated SKUs */
												__( 'Не вивантажено групи, у яких Prom не прийняв би різновиди з однаковими характеристиками (SKU батьківських товарів): %s', 'factorial2000-catalog-sync' ),
												implode( ', ', array_map( 'strval', (array) $result['excluded'] ) )
											)
										);
										?>
									</p>
								<?php endif; ?>
							<?php else : ?>
								<span style="color:#b32d2e"><?php echo esc_html( (string) ( $result['message'] ?? '' ) ); ?></span>
							<?php endif; ?>
							<?php f2000cs_marketplace_render_skip_reasons( $feed, (array) ( $result['reasons'] ?? array() ) ); ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p>
				<button type="button" class="button button-primary f2000cs-mp-build" data-feed="<?php echo esc_attr( $id ); ?>" data-resume="<?php echo $running ? '1' : '0'; ?>">
					<?php echo $running ? esc_html__( 'Продовжити генерацію', 'factorial2000-catalog-sync' ) : esc_html__( 'Згенерувати зараз', 'factorial2000-catalog-sync' ); ?>
				</button>
				<?php if ( ! $running && ! empty( $result['drop'] ) ) : ?>
					<button type="button" class="button f2000cs-mp-build" data-feed="<?php echo esc_attr( $id ); ?>" data-force="1">
						<?php esc_html_e( 'Скорочення очікуване — опублікувати', 'factorial2000-catalog-sync' ); ?>
					</button>
				<?php endif; ?>
				<span class="f2000cs-mp-progress" aria-live="polite" style="margin-left:10px"></span>
			</p>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="f2000cs_marketplace_save">
			<input type="hidden" name="feed" value="<?php echo esc_attr( $id ); ?>">
			<?php wp_nonce_field( 'f2000cs_marketplace_save', 'f2000cs_marketplace_nonce' ); ?>

			<h2><?php esc_html_e( 'Оновлення вигрузки', 'factorial2000-catalog-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="f2000cs-mp-schedule"><?php esc_html_e( 'Автоматично', 'factorial2000-catalog-sync' ); ?></label></th>
					<td>
						<select id="f2000cs-mp-schedule" name="mp[schedule]">
							<?php foreach ( $labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['schedule'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php if ( $feed instanceof Prom_Feed ) : ?>
								<?php esc_html_e( 'Файл перегенеровується у фоні через WP-Cron; поки триває генерація, маркетплейс бачить попередній файл. Prom забирає ціни й наявність за посиланням раз на 4 години (з 6:00 до 22:00), решту полів і нові товари — раз на добу вночі, тому оновлювати файл частіше немає сенсу.', 'factorial2000-catalog-sync' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Файл перегенеровується у фоні через WP-Cron; поки триває генерація, маркетплейс бачить попередній файл. Google Merchant завантажує файл за розкладом, який ви задали в джерелі даних (від раз на добу), тому оновлювати файл частіше немає сенсу.', 'factorial2000-catalog-sync' ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Після оновлення наявності', 'factorial2000-catalog-sync' ); ?></th>
					<td>
						<input type="hidden" name="mp[after_sync]" value="0">
						<label>
							<input type="checkbox" id="f2000cs-mp-after-sync" name="mp[after_sync]" value="1" <?php checked( ! empty( $settings['after_sync'] ) ); ?>>
							<?php esc_html_e( 'Оновлювати вигрузку після кожного оновлення наявності й цін', 'factorial2000-catalog-sync' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Після завершення синхронізації з постачальниками (за розкладом або кнопкою) вигрузка у фоні збирається заново з товарів сайту за правилами нижче, і за тим самим посиланням маркетплейс отримує актуальні ціни та наявність (при найближчому оновленні за посиланням).', 'factorial2000-catalog-sync' ); ?></p>
					</td>
				</tr>
			</table>

			<?php foreach ( $feed->groups() as $group => $title ) : ?>
				<?php
				$in_group = array_filter(
					$fields,
					static function ( $field ) use ( $group ) {
						return ( $field['group'] ?? 'basic' ) === $group;
					}
				);

				if ( ! $in_group ) {
					continue;
				}
				?>
				<h2><?php echo esc_html( $title ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $in_group as $key => $field ) : ?>
						<?php f2000cs_marketplace_render_field( (string) $key, $field, $settings[ $key ] ?? '', $settings ); ?>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>

			<?php submit_button( __( 'Зберегти налаштування', 'factorial2000-catalog-sync' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Collect the posted form into the raw array the feed sanitizes.
 *
 * Preset fields post a select and an optional custom text; repeaters post rows;
 * the category table posts one id per category plus a box of custom rules.
 *
 * @param \F2000CS\Marketplace_Feed $feed Feed.
 * @return array<string,mixed>
 */
function f2000cs_marketplace_collect_post( $feed ): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by the caller; every value is sanitized by the feed schema (Marketplace_Registry::save_settings()).
	$raw             = isset( $_POST['mp'] ) && is_array( $_POST['mp'] ) ? wp_unslash( $_POST['mp'] ) : array();
	$raw['__custom'] = isset( $_POST['mp_custom'] ) && is_array( $_POST['mp_custom'] ) ? wp_unslash( $_POST['mp_custom'] ) : array();
	$raw['__rows']   = isset( $_POST['mp_rows'] ) && is_array( $_POST['mp_rows'] ) ? wp_unslash( $_POST['mp_rows'] ) : array();
	$map             = isset( $_POST['mp_map'] ) && is_array( $_POST['mp_map'] ) ? wp_unslash( $_POST['mp_map'] ) : array();
	$extra           = isset( $_POST['mp_extra'] ) && is_array( $_POST['mp_extra'] ) ? wp_unslash( $_POST['mp_extra'] ) : array();
	// phpcs:enable

	foreach ( $feed->fields() as $key => $field ) {
		if ( 'category_map' !== ( $field['type'] ?? '' ) ) {
			continue;
		}

		$raw[ $key ] = Marketplace_Form::build_rules(
			isset( $map[ $key ] ) && is_array( $map[ $key ] ) ? array_map( 'strval', $map[ $key ] ) : array(),
			Marketplace_Form::load_categories(),
			isset( $extra[ $key ] ) ? (string) $extra[ $key ] : ''
		);
	}

	return $raw;
}

/**
 * Save the settings of a feed.
 *
 * @return void
 */
function f2000cs_marketplace_handle_save() {
	if ( ! isset( $_POST['f2000cs_marketplace_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_marketplace_nonce'] ) ), 'f2000cs_marketplace_save' ) ) {
		wp_die( esc_html__( 'Помилка перевірки безпеки (nonce). Оновіть сторінку.', 'factorial2000-catalog-sync' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) );
	}

	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		wp_die( esc_html__( 'Вигрузка на маркетплейси доступна лише у Pro версії.', 'factorial2000-catalog-sync' ) );
	}

	$id   = isset( $_POST['feed'] ) ? sanitize_key( wp_unslash( $_POST['feed'] ) ) : '';
	$feed = Marketplace_Registry::get( $id );

	if ( ! $feed ) {
		wp_die( esc_html__( 'Невідомий маркетплейс.', 'factorial2000-catalog-sync' ) );
	}

	Marketplace_Registry::save_settings( $id, f2000cs_marketplace_collect_post( $feed ) );
	Marketplace_Job::reschedule( $id );

	add_settings_error( 'f2000cs_marketplace', 'saved', __( 'Налаштування збережено.', 'factorial2000-catalog-sync' ), 'updated' );
	set_transient( 'settings_errors', get_settings_errors(), 30 );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'             => 'f2000cs-marketplaces',
				'tab'              => $id,
				'settings-updated' => 'true',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_f2000cs_marketplace_save', 'f2000cs_marketplace_handle_save' );

/**
 * Why products were left out of the last build, with a few SKUs each.
 *
 * @param Marketplace_Feed                                          $feed    Feed.
 * @param array<string,array{count:int,examples:array<int,string>}> $reasons Reasons.
 * @return void
 */
function f2000cs_marketplace_render_skip_reasons( Marketplace_Feed $feed, array $reasons ) {
	$reasons = array_filter(
		$reasons,
		static function ( $info ) {
			return (int) ( $info['count'] ?? 0 ) > 0;
		}
	);

	if ( ! $reasons ) {
		return;
	}

	uasort(
		$reasons,
		static function ( $a, $b ) {
			return (int) $b['count'] - (int) $a['count'];
		}
	);

	$labels = $feed->skip_reason_labels();
	?>
	<details class="f2000cs-mp-reasons">
		<summary><?php esc_html_e( 'Чому товари пропущено', 'factorial2000-catalog-sync' ); ?></summary>
		<ul>
			<?php foreach ( $reasons as $reason => $info ) : ?>
				<li>
					<strong><?php echo esc_html( $labels[ $reason ] ?? (string) $reason ); ?>:</strong>
					<?php echo esc_html( number_format_i18n( (int) $info['count'] ) ); ?>
					<?php if ( ! empty( $info['examples'] ) ) : ?>
						<span class="description">
							(<?php echo esc_html( implode( ', ', array_map( 'strval', (array) $info['examples'] ) ) ); ?><?php echo (int) $info['count'] > count( (array) $info['examples'] ) ? '…' : ''; ?>)
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</details>
	<?php
}

/**
 * AJAX: run one slice of a feed build.
 *
 * @return void
 */
function f2000cs_marketplace_ajax_step() {
	check_ajax_referer( 'f2000cs_marketplace_step', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
	}

	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		wp_send_json_error( array( 'message' => __( 'Вигрузка на маркетплейси доступна лише у Pro версії.', 'factorial2000-catalog-sync' ) ) );
	}

	$id = isset( $_POST['feed'] ) ? sanitize_key( wp_unslash( $_POST['feed'] ) ) : '';

	if ( ! Marketplace_Registry::get( $id ) ) {
		wp_send_json_error( array( 'message' => __( 'Невідомий маркетплейс.', 'factorial2000-catalog-sync' ) ) );
	}

	$restart = isset( $_POST['restart'] ) && '1' === $_POST['restart'];

	// Follow-up requests only continue a build: if another process finished it
	// meanwhile, report that result instead of starting a new build.
	$resume = isset( $_POST['resume'] ) && '1' === $_POST['resume'];

	// «The shrink is expected»: the next build is published whatever its size.
	if ( isset( $_POST['force'] ) && '1' === $_POST['force'] ) {
		update_option( Marketplace_Job::accept_drop_option( $id ), time(), false );
		$restart = true;
	}

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- Best effort.
	}

	$result = Marketplace_Job::step( $id, 20, null, $restart, $resume );

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_f2000cs_marketplace_step', 'f2000cs_marketplace_ajax_step' );

/**
 * Load the page script.
 *
 * @param string $hook Admin page hook suffix.
 * @return void
 */
function f2000cs_marketplace_enqueue( $hook ) {
	if ( false === strpos( (string) $hook, 'f2000cs-marketplaces' ) ) {
		return;
	}

	// Nested tables live inside .form-table cells, whose th/td rules (200px headers, big padding) would otherwise leak into them.
	wp_register_style( 'f2000cs-admin-marketplaces', false, array(), F2000CS_VERSION );
	wp_enqueue_style( 'f2000cs-admin-marketplaces' );
	wp_add_inline_style(
		'f2000cs-admin-marketplaces',
		'.f2000cs-marketplaces .form-table td table{margin:0}'
		. '.f2000cs-marketplaces .form-table td table th,.f2000cs-marketplaces .form-table td table td{width:auto;padding:8px 10px;vertical-align:middle;line-height:1.4}'
		. '.f2000cs-marketplaces .form-table td table input[type=text]{width:100%;max-width:none}'
		. '.f2000cs-marketplaces .f2000cs-mp-pairs .select2-container{width:100%!important;min-width:180px}'
		. '.f2000cs-marketplaces .form-table td .f2000cs-mp-cat-table input[type=text]{width:140px}'
		. '.f2000cs-marketplaces .f2000cs-mp-cat-table .f2000cs-prom-cat-wrap{display:block}'
		. '.f2000cs-marketplaces .f2000cs-mp-pairs,.f2000cs-marketplaces .f2000cs-mp-cat-table{table-layout:auto}'
		. '.f2000cs-marketplaces .f2000cs-mp-custom{margin-left:6px;vertical-align:middle}'
		. '.f2000cs-marketplaces .f2000cs-mp-custom[hidden]{display:none}'
		. '.f2000cs-marketplaces .form-table td select{max-width:100%}'
		. '.f2000cs-marketplaces .form-table td details textarea{margin-top:6px}'
		. '.f2000cs-marketplaces .card{padding:0.7em 2em 1em}'
		. '.f2000cs-marketplaces .f2000cs-prom-summary{color:#646970;margin-left:8px}'
		. '.f2000cs-marketplaces dialog.f2000cs-prom-attrs-dialog{width:min(1120px,94vw);max-height:88vh;padding:0;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 12px 40px rgba(0,0,0,.28);overflow:hidden}'
		. '.f2000cs-marketplaces dialog.f2000cs-prom-attrs-dialog[open]{display:flex}'
		. '.f2000cs-marketplaces dialog.f2000cs-prom-attrs-dialog::backdrop{background:rgba(0,0,0,.45)}'
		. '.f2000cs-marketplaces .f2000cs-modal__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #dcdcde;background:#f6f7f7}'
		. '.f2000cs-marketplaces .f2000cs-modal__head h2{margin:0;font-size:18px;line-height:1.3}'
		. '.f2000cs-marketplaces .f2000cs-modal__close{font-size:26px;line-height:1;text-decoration:none;color:#646970;cursor:pointer}'
		. '.f2000cs-marketplaces .f2000cs-modal__body{padding:14px 18px;overflow:auto;min-height:0}'
		. '.f2000cs-marketplaces .f2000cs-modal__body>p:first-child{margin-top:0}'
		. '.f2000cs-marketplaces .f2000cs-modal__foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 18px;border-top:1px solid #dcdcde;background:#f6f7f7}'
		. '.f2000cs-marketplaces .f2000cs-prom-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:12px 0}'
		. '.f2000cs-marketplaces .f2000cs-prom-toolbar .f2000cs-prom-search{min-width:240px;max-width:360px}'
		. '.f2000cs-marketplaces .f2000cs-prom-table-wrap{overflow:auto;max-height:56vh;border:1px solid #dcdcde}'
		. '.f2000cs-marketplaces .f2000cs-prom-attrs-table{margin:0;table-layout:fixed}'
		. '.f2000cs-marketplaces .f2000cs-prom-attrs-table th{position:sticky;top:0;z-index:1;background:#f6f7f7;font-weight:600}'
		. '.f2000cs-marketplaces .f2000cs-prom-attrs-table th:first-child{width:28%}'
		. '.f2000cs-marketplaces .f2000cs-prom-attrs-table th:last-child{width:110px}'
		. '.f2000cs-marketplaces .f2000cs-prom-attrs-table td{vertical-align:middle}'
		. '.f2000cs-marketplaces .f2000cs-prom-group td{background:#f0f6fc;border-top:1px solid #c3c4c7;font-weight:600}'
		. '.f2000cs-marketplaces .f2000cs-prom-group:first-child td{border-top:0}'
		. '.f2000cs-marketplaces .f2000cs-prom-count{color:#646970;font-weight:400;margin-left:6px}'
		. '.f2000cs-marketplaces .f2000cs-prom-status{display:inline-block;padding:2px 7px;border-radius:10px;font-size:12px;font-weight:600;white-space:nowrap}'
		. '.f2000cs-marketplaces .f2000cs-prom-status.is-auto{color:#0f834d;background:#e6f6ec}'
		. '.f2000cs-marketplaces .f2000cs-prom-status.is-manual{color:#135e96;background:#e5f1f8}'
		. '.f2000cs-marketplaces .f2000cs-prom-status.is-none{color:#b32d2e;background:#fbeaea}'
		. '.f2000cs-marketplaces .f2000cs-mp-attr-select{width:100%;max-width:100%}'
		. '.f2000cs-marketplaces .f2000cs-prom-attrs-table .select2-container{width:100%!important}'
		. '.f2000cs-marketplaces .f2000cs-prom-empty,.f2000cs-marketplaces .f2000cs-prom-empty-row td{color:#646970;font-style:italic}'
		. '@media (max-width:782px){.f2000cs-marketplaces dialog.f2000cs-prom-attrs-dialog{width:96vw}.f2000cs-marketplaces .f2000cs-prom-table-wrap{max-height:52vh}.f2000cs-marketplaces .f2000cs-prom-attrs-table{min-width:720px}.f2000cs-marketplaces .f2000cs-modal__foot{flex-wrap:wrap}}'
	);

	wp_enqueue_script(
		'f2000cs-admin-marketplaces',
		F2000CS_URL . 'assets/js/admin-marketplaces.js',
		array( 'jquery', 'selectWoo' ),
		f2000cs_asset_version( 'assets/js/admin-marketplaces.js' ),
		true
	);

	wp_localize_script(
		'f2000cs-admin-marketplaces',
		'f2000csMarketplaces',
		array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'f2000cs_marketplace_step' ),
			'promRowsAction' => 'f2000cs_marketplace_prom_rows',
			'promRowsNonce'  => wp_create_nonce( 'f2000cs_prom_attribute_rows' ),
			'i18n'           => array(
				'running'    => __( 'Формуємо вигрузку…', 'factorial2000-catalog-sync' ),
				'offers'     => __( 'товарів', 'factorial2000-catalog-sync' ),
				'done'       => __( 'Готово!', 'factorial2000-catalog-sync' ),
				'waiting'    => __( 'Генерація вже триває, чекаємо…', 'factorial2000-catalog-sync' ),
				'failed'     => __( 'Помилка. Спробуйте ще раз.', 'factorial2000-catalog-sync' ),
				'pulling'    => __( 'Підтягуємо категорії…', 'factorial2000-catalog-sync' ),
				'pullFailed' => __( 'Не вдалося підтягнути категорії.', 'factorial2000-catalog-sync' ),
				'auto'       => __( 'Авто', 'factorial2000-catalog-sync' ),
				'manual'     => __( 'Вручну', 'factorial2000-catalog-sync' ),
				'none'       => __( 'Немає', 'factorial2000-catalog-sync' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'f2000cs_marketplace_enqueue' );
