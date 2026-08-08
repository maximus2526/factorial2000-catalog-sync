<?php
/**
 * Export settings admin page.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Export settings page
 */
function f2000cs_export_page() {
	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		echo '<div class="wrap">';
		f2000cs_render_admin_page_title( __( 'Налаштування вигрузки', 'factorial2000-catalog-sync' ) );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Налаштування вигрузки доступні лише у Pro версії.', 'factorial2000-catalog-sync' );
		echo ' <a href="' . esc_url( f2000cs_get_upgrade_url() ) . '">' . esc_html__( 'Оновити до Pro', 'factorial2000-catalog-sync' ) . '</a>';
		echo '</p></div></div>';
		return;
	}

	if ( isset( $_POST['create_filtered_xml'], $_POST['f2000cs_export_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_export_nonce'] ) ), 'f2000cs_export_filter' ) ) {
		$sku_prefix = isset( $_POST['sku_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['sku_prefix'] ) ) : '';

		if ( isset( $_FILES['xml_file']['error'], $_FILES['xml_file']['name'], $_FILES['xml_file']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $_FILES['xml_file']['error'] ) {
			$uploaded_name = sanitize_file_name( wp_unslash( $_FILES['xml_file']['name'] ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Server-generated upload path; unslashing would corrupt Windows paths.
			$uploaded_tmp = sanitize_text_field( $_FILES['xml_file']['tmp_name'] );

			$file_type      = wp_check_filetype( $uploaded_name );
			$file_extension = strtolower( pathinfo( $uploaded_name, PATHINFO_EXTENSION ) );

			// More flexible validation - check both methods
			$is_xml_file = ( $file_type['ext'] === 'xml' ) || ( $file_extension === 'xml' );

			if ( ! $is_xml_file ) {
				add_settings_error(
					'f2000cs_export',
					'export_error',
					'❌ Помилка: Файл повинен мати розширення .xml.',
					'error'
				);
			} else {
				$min_price = isset( $_POST['min_price'] ) ? floatval( wp_unslash( $_POST['min_price'] ) ) : 0;

				require_once plugin_dir_path( __FILE__ ) . '../includes/class-xml-export-filter.php';
				$export_filter = new \F2000CS\XML_Export_Filter( $uploaded_tmp, $sku_prefix, $min_price );
				$result        = $export_filter->create_filtered_xml();

				if ( $result['success'] ) {
					$message = '✅ Очищений XML створено! Видалено ' . $result['removed_count'] . ' товарів';
					if ( $min_price > 0 ) {
						$message .= ' (включаючи товари дешевше ' . number_format( $min_price, 2 ) . ' грн)';
					}
					$message .= '. <a href="' . esc_url( $result['download_url'] ) . '" class="button button-primary">Завантажити XML</a>';

					add_settings_error(
						'f2000cs_export',
						'export_success',
						$message,
						'updated'
					);
				} else {
					add_settings_error(
						'f2000cs_export',
						'export_error',
						'❌ Помилка: ' . $result['error'],
						'error'
					);
				}
			}
		} else {
			add_settings_error(
				'f2000cs_export',
				'export_error',
				'❌ Помилка: Будь ласка, виберіть XML файл для завантаження',
				'error'
			);
		}
	}

	$current_xml_url    = get_option( 'f2000cs_url', '' );
	$current_sku_prefix = get_option( 'f2000cs_sku_prefix_1', 'NEW_' );
	?>
	
	<div class="wrap">
		<?php f2000cs_render_admin_page_title( __( 'Налаштування вигрузки', 'factorial2000-catalog-sync' ) ); ?>
		
		<?php settings_errors( 'f2000cs_export' ); ?>

		<div class="f2000cs-export-tools">
			<div class="card f2000cs-export-filter-card">
				<h2>🔍 Фільтр XML вигрузки</h2>
				<p>Створити новий XML файл без товарів, які вже є на сайті. Це дозволить імпортувати тільки нові товари.</p>

				<form method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'f2000cs_export_filter', 'f2000cs_export_nonce' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="xml_file"><?php esc_html_e( 'XML файл', 'factorial2000-catalog-sync' ); ?></label>
							</th>
							<td>
								<input type="file"
										id="xml_file"
										name="xml_file"
										accept=".xml"
										required />
								<p class="description"><?php esc_html_e( 'Завантажте XML файл з товарами', 'factorial2000-catalog-sync' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="sku_prefix"><?php esc_html_e( 'SKU префікс', 'factorial2000-catalog-sync' ); ?></label>
							</th>
							<td>
								<input type="text"
										id="sku_prefix"
										name="sku_prefix"
										value="<?php echo esc_attr( $current_sku_prefix ); ?>"
										class="regular-text"
										placeholder="NEW_" />
								<p class="description"><?php esc_html_e( 'Префікс SKU товарів на сайті (наприклад: NEW_)', 'factorial2000-catalog-sync' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="min_price"><?php esc_html_e( 'Мінімальна ціна', 'factorial2000-catalog-sync' ); ?></label>
							</th>
							<td>
								<input type="number"
										id="min_price"
										name="min_price"
										value="<?php echo esc_attr( isset( $_POST['min_price'] ) ? sanitize_text_field( wp_unslash( $_POST['min_price'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Re-populating a field for display only. ?>"
										class="regular-text"
										step="0.01"
										min="0"
										placeholder="0.00" />
								<p class="description"><?php esc_html_e( 'Мінімальна ціна товару для включення в вигрузку (залиште порожнім, щоб не фільтрувати за ціною)', 'factorial2000-catalog-sync' ); ?></p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<input type="submit"
								name="create_filtered_xml"
								class="button button-primary"
								value="<?php esc_attr_e( 'Створити очищений XML', 'factorial2000-catalog-sync' ); ?>" />
					</p>
				</form>

				<div class="f2000cs-export-help">
					<h3>ℹ️ Як це працює</h3>
					<ol>
						<li><strong>Завантаження файлу:</strong> Ви завантажуєте XML файл з товарами</li>
						<li><strong>Аналіз сайту:</strong> Система знаходить всі товари на сайті з вказаним SKU префіксом</li>
						<li><strong>Порівняння з XML:</strong> Порівнює SKU товарів з сайту з SKU в завантаженому XML файлі</li>
						<li><strong>Фільтрація:</strong> Видаляє з XML всі товари, які вже є на сайті</li>
						<li><strong>Створення файлу:</strong> Генерує новий XML файл тільки з новими товарами</li>
					</ol>

					<h4>📊 Статистика</h4>
					<?php
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off admin statistic, caching not required.
					$site_products_count = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(DISTINCT pm.meta_value) 
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						WHERE p.post_type IN ('product', 'product_variation')
						AND p.post_status IN ('publish', 'draft', 'private')
						AND pm.meta_key = '_sku'
						AND pm.meta_value LIKE %s",
							$wpdb->esc_like( $current_sku_prefix ) . '%'
						)
					);
					?>
					<p><strong>Товарів на сайті з префіксом "<?php echo esc_html( $current_sku_prefix ); ?>":</strong> <?php echo intval( $site_products_count ); ?></p>
				</div>
			</div>

			<div class="f2000cs-export-tools__main">
				<?php f2000cs_xml_editor_render_card(); ?>
				<?php f2000cs_render_export_recent_files_card(); ?>
			</div>
		</div>
	</div>
	
	<?php
}

/**
 * Recent XML files from uploads/f2000cs-exports/, newest first.
 *
 * Download URLs go through admin-post (capability-gated), not public uploads.
 *
 * @param int $limit Max files to return.
 * @return array<int, array{name:string,url:string,size:int,mtime:int,kind:string}>
 */
function f2000cs_get_recent_export_files( int $limit = 12 ): array {
	$dir = f2000cs_ensure_exports_dir();
	if ( ! is_dir( $dir ) ) {
		return array();
	}

	$paths = glob( $dir . '/*.xml' );
	if ( ! is_array( $paths ) || empty( $paths ) ) {
		return array();
	}

	usort(
		$paths,
		static function ( $a, $b ) {
			return (int) filemtime( $b ) <=> (int) filemtime( $a );
		}
	);

	$out = array();

	foreach ( array_slice( $paths, 0, max( 1, $limit ) ) as $path ) {
		$name = basename( $path );
		$url  = f2000cs_get_export_download_url( $name );
		if ( '' === $url ) {
			continue;
		}

		$kind = 'other';
		if ( 0 === strpos( $name, 'filtered-xml-' ) ) {
			$kind = 'filter';
		} elseif ( 0 === strpos( $name, 'editor-xml-' ) ) {
			$kind = 'editor';
		}

		$out[] = array(
			'name'  => $name,
			'url'   => $url,
			'size'  => (int) filesize( $path ),
			'mtime' => (int) filemtime( $path ),
			'kind'  => $kind,
		);
	}

	return $out;
}

/**
 * Human-readable file size for admin UI.
 *
 * @param int $bytes File size in bytes.
 * @return string
 */
function f2000cs_format_export_filesize( int $bytes ): string {
	if ( $bytes < 1024 ) {
		return $bytes . ' B';
	}
	if ( $bytes < 1024 * 1024 ) {
		return round( $bytes / 1024, 1 ) . ' KB';
	}
	return round( $bytes / ( 1024 * 1024 ), 2 ) . ' MB';
}

/**
 * Card: recent generated XML downloads + when to use which tool.
 *
 * @return void
 */
function f2000cs_render_export_recent_files_card() {
	$files = f2000cs_get_recent_export_files( 12 );
	?>
	<div class="card f2000cs-export-recent-card">
		<h2>📁 Останні згенеровані XML</h2>
		<p class="description">
			<?php esc_html_e( 'Файли з фільтра та редактора зберігаються в uploads/f2000cs-exports/ і завантажуються лише для адміністраторів Pro (через захищене посилання).', 'factorial2000-catalog-sync' ); ?>
		</p>

		<?php if ( empty( $files ) ) : ?>
			<p class="f2000cs-export-recent__empty">
				<?php esc_html_e( 'Поки немає згенерованих файлів. Створіть очищений XML у фільтрі або сформуйте вигрузку в редакторі.', 'factorial2000-catalog-sync' ); ?>
			</p>
		<?php else : ?>
			<table class="widefat striped f2000cs-export-recent__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Файл', 'factorial2000-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'Тип', 'factorial2000-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'Розмір', 'factorial2000-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'Дата', 'factorial2000-catalog-sync' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $files as $file ) : ?>
						<?php
						$kind_label = __( 'Інше', 'factorial2000-catalog-sync' );
						if ( 'filter' === $file['kind'] ) {
							$kind_label = __( 'Фільтр', 'factorial2000-catalog-sync' );
						} elseif ( 'editor' === $file['kind'] ) {
							$kind_label = __( 'Редактор', 'factorial2000-catalog-sync' );
						}
						?>
						<tr>
							<td><code><?php echo esc_html( $file['name'] ); ?></code></td>
							<td><?php echo esc_html( $kind_label ); ?></td>
							<td><?php echo esc_html( f2000cs_format_export_filesize( $file['size'] ) ); ?></td>
							<td><?php echo esc_html( date_i18n( 'j M Y, H:i', $file['mtime'] ) ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $file['url'] ); ?>" download>
									<?php esc_html_e( 'Завантажити', 'factorial2000-catalog-sync' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<div class="f2000cs-export-help">
			<h3>💡 Коли що використовувати</h3>
			<ul>
				<li><strong><?php esc_html_e( 'Фільтр', 'factorial2000-catalog-sync' ); ?>:</strong> <?php esc_html_e( 'прибрати з вигрузки товари, які вже є на сайті (за SKU-префіксом) — зручно перед імпортом лише новинок.', 'factorial2000-catalog-sync' ); ?></li>
				<li><strong><?php esc_html_e( 'Редактор', 'factorial2000-catalog-sync' ); ?>:</strong> <?php esc_html_e( 'зібрати окрему вигрузку з категорій/товарів великого фіду (без порівняння з базою сайту).', 'factorial2000-catalog-sync' ); ?></li>
			</ul>
		</div>
	</div>
	<?php
}
