<?php

defined( 'ABSPATH' ) || exit;

function prom_xml_importer_add_admin_menu() {
	add_menu_page(
		'Оновлення XML',
		'Оновлення XML',
		'manage_options',
		'prom-xml-importer-update',
		'prom_xml_importer_update_page',
		'dashicons-update',
		60
	);

	add_submenu_page(
		'prom-xml-importer-update',
		'Імпорт XML',
		'Імпорт XML',
		'manage_options',
		'prom-xml-importer-import',
		'prom_xml_importer_import_page'
	);

	add_submenu_page(
		'prom-xml-importer-update',
		'Налаштування вигрузки',
		'Налаштування вигрузки',
		'manage_options',
		'prom-xml-importer-export',
		'prom_xml_importer_export_page'
	);
}
add_action( 'admin_menu', 'prom_xml_importer_add_admin_menu' );

function prom_xml_importer_update_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Prom XML Importer – Оновлення', 'xml-prom' ); ?></h1>
		
		<?php settings_errors(); ?>
		
		<form method="post" action="options.php">
			<?php
			settings_fields( 'prom_xml_importer_settings' );
			do_settings_sections( 'prom-xml-importer' );
			submit_button( 'Save Settings' );
			?>
		</form>
		
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'prom_xml_importer_action', 'prom_xml_importer_nonce' ); ?>
			<input type="hidden" name="action" value="prom_xml_importer_action">
			
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Background Processing', 'xml-prom' ); ?></th>
					<td>
						<label>
							<input type="radio" name="use_background" value="yes" checked>
							<?php esc_html_e( 'Run in background (recommended for large XML files)', 'xml-prom' ); ?>
						</label>
						<br>
						<label>
							<input type="radio" name="use_background" value="no">
							<?php esc_html_e( 'Run immediately', 'xml-prom' ); ?>
						</label>
					</td>
				</tr>
			</table>
			
			<p>
				<input type="submit" name="run_script" class="button button-primary" value="<?php esc_attr_e( 'Update Stock Status', 'xml-prom' ); ?>" style="margin-right: 10px;">
				<input type="submit" name="prom_xml_importer_stop" class="button button-secondary" value="<?php esc_attr_e( 'Stop Cron Jobs', 'xml-prom' ); ?>">
			</p>
		</form>
		
		<?php
		// Display cron status
		$next_run   = wp_next_scheduled( 'prom_update_stock_cron' );
		$interval   = get_option( 'prom_xml_update_interval', 'hourly' );
		$bg_pending = wp_next_scheduled( 'prom_single_update_event' );

		echo '<div class="prom-xml-status">';
		echo '<h3>' . esc_html__( 'Status', 'xml-prom' ) . '</h3>';

		if ( $next_run ) {
			echo '<p>' . esc_html__( 'Automatic updates: ', 'xml-prom' ) . '<span class="active">✅ ' . esc_html__( 'Active', 'xml-prom' ) . '</span></p>';
			echo '<p>' . esc_html__( 'Next scheduled update: ', 'xml-prom' ) . date_i18n( 'j F Y, H:i', $next_run ) . '</p>';
			echo '<p>' . esc_html__( 'Update interval: ', 'xml-prom' ) . esc_html( $interval ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Automatic updates: ', 'xml-prom' ) . '<span class="inactive">❌ ' . esc_html__( 'Inactive', 'xml-prom' ) . '</span></p>';
		}

		if ( $bg_pending ) {
			echo '<p>' . esc_html__( 'Background update: ', 'xml-prom' ) . '<span class="pending">⏳ ' . esc_html__( 'Pending', 'xml-prom' ) . '</span></p>';
			echo '<p>' . esc_html__( 'Scheduled for: ', 'xml-prom' ) . date_i18n( 'F j, Y, g:i a', $bg_pending ) . '</p>';
		}

		echo '</div>';

		// Add some basic styling
		?>
		<style>
			.prom-xml-status {
				margin-top: 20px;
				background: #fff;
				padding: 15px;
				border: 1px solid #ccd0d4;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			.prom-xml-status h3 {
				margin-top: 0;
			}
			.prom-xml-status .active {
				color: green;
				font-weight: bold;
			}
			.prom-xml-status .inactive {
				color: red;
				font-weight: bold;
			}
			.prom-xml-status .pending {
				color: orange;
				font-weight: bold;
			}
		</style>
	</div>
	<?php
}

function prom_xml_importer_import_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Prom XML Importer – Імпорт', 'xml-prom' ); ?></h1>
		<form id="xml-import-form" enctype="multipart/form-data">
			<?php wp_nonce_field( 'prom_xml_import_action', 'prom_xml_import_nonce' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="import_xml_file"><?php esc_html_e( 'Виберіть XML файл для імпорту', 'xml-prom' ); ?></label></th>
					<td><input type="file" name="import_xml_file" id="import_xml_file" accept=".xml" required></td>
				</tr>
			<tr valign="top">
				<th scope="row"><label for="import_sku_prefix"><?php esc_html_e( 'SKU Prefix', 'xml-prom' ); ?></label></th>
				<td><input type="text" name="import_sku_prefix" id="import_sku_prefix" placeholder="<?php esc_attr_e( 'Наприклад: NEW_', 'xml-prom' ); ?>" required></td>
			</tr>
		</table>
		<div style="margin-bottom: 15px;">
			<label for="new_category">
				<input type="checkbox" name="new_category" id="new_category" value="1">
				<?php esc_html_e( 'Додавати неіснуючі товари в категорію New', 'xml-prom' ); ?>
			</label>
		</div>
		
		<div style="margin-bottom: 20px;">
			<label style="font-weight: bold; display: block; margin-bottom: 10px;">
				<?php esc_html_e( 'Режим імпорту:', 'xml-prom' ); ?>
			</label>
			<div style="margin-left: 20px;">
				<label style="display: block; margin-bottom: 8px;">
					<input type="radio" name="import_mode" value="simple" checked>
					<?php esc_html_e( 'Прості продукти (тільки товари БЕЗ group_id)', 'xml-prom' ); ?>
				</label>
				<label style="display: block;">
					<input type="radio" name="import_mode" value="variable">
					<?php esc_html_e( 'Варіативні продукти (тільки товари З group_id, з вибором атрибутів)', 'xml-prom' ); ?>
				</label>
			</div>
		</div>

		<div style="margin-bottom: 20px;">
			<button type="button" id="analyze-xml" class="button button-secondary" style="display: none;">
				<?php esc_html_e( 'Проаналізувати XML', 'xml-prom' ); ?>
			</button>
			<button type="button" id="start-import" class="button button-primary">
				<?php esc_html_e( 'Імпортувати', 'xml-prom' ); ?>
			</button>
			<button type="button" id="stop-import" class="button button-secondary" style="display: none;">
				<?php esc_html_e( 'Зупинити', 'xml-prom' ); ?>
			</button>
		</div>
		</form>

		<!-- Контейнер для аналізу груп -->
		<div id="groups-analysis-container" style="display: none; margin-top: 30px; border: 1px solid #ccc; padding: 20px; background: #f9f9f9;">
			<h3><?php esc_html_e( 'Вибір варіаційних атрибутів', 'xml-prom' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Для кожної групи товарів виберіть атрибут який буде використовуватись для створення варіацій:', 'xml-prom' ); ?></p>
			<div id="analysis-status" style="margin: 15px 0;"></div>
			<div id="groups-list" style="margin-top: 20px; max-height: 600px; overflow-y: auto;"></div>
			<button type="button" id="start-import-with-selection" class="button button-primary" style="margin-top: 20px; display: none;">
				<?php esc_html_e( 'Імпортувати з вибраними атрибутами', 'xml-prom' ); ?>
			</button>
		</div>

		<div id="import-progress-container" style="display: none;">
			<h3><?php esc_html_e( 'Прогрес імпорту', 'xml-prom' ); ?></h3>
			<progress id="import-progress" value="0" max="100" style="width: 100%;"></progress>
			<div id="import-status"></div>
		</div>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function($) {
			let stopImport = false;
			let groupsData = {}; // Зберігаємо дані груп та вибрані атрибути

			// Перемикач режиму імпорту
			$('input[name="import_mode"]').on('change', function() {
				const mode = $(this).val();
				if (mode === 'variable') {
					$('#analyze-xml').show();
					$('#start-import').hide();
					$('#groups-analysis-container').hide();
				} else {
					$('#analyze-xml').hide();
					$('#start-import').show();
					$('#groups-analysis-container').hide();
				}
			});

			// Аналіз XML для варіативних продуктів
			$('#analyze-xml').on('click', function() {
				const fileInput = $('#import_xml_file')[0];
				const skuPrefix = $('#import_sku_prefix').val().trim();

				if (!fileInput.files.length) {
					alert('<?php esc_html_e( 'Будь ласка, виберіть XML файл.', 'xml-prom' ); ?>');
					return;
				}

				if (!skuPrefix) {
					alert('<?php esc_html_e( 'Будь ласка, введіть SKU Prefix.', 'xml-prom' ); ?>');
					$('#import_sku_prefix').focus();
					return;
				}

				const formData = new FormData($('#xml-import-form')[0]);
				formData.append('action', 'prom_xml_analyze_groups');
				formData.append('sku_prefix', skuPrefix);

				$('#analysis-status').html('<p><?php esc_html_e( 'Аналіз XML файлу...', 'xml-prom' ); ?></p>');
				$('#groups-analysis-container').show();
				$('#groups-list').empty();
				$(this).prop('disabled', true);

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if (response.success) {
							groupsData = response.data.groups;
							displayGroups(response.data.groups);
							$('#analysis-status').html('<p style="color: green;"><?php esc_html_e( 'Аналіз завершено! Знайдено груп:', 'xml-prom' ); ?> ' + Object.keys(groupsData).length + '</p>');
							$('#start-import-with-selection').show();
						} else {
							$('#analysis-status').html('<p style="color: red;">' + response.data.message + '</p>');
						}
						$('#analyze-xml').prop('disabled', false);
					},
					error: function() {
						$('#analysis-status').html('<p style="color: red;"><?php esc_html_e( 'Помилка при аналізі XML.', 'xml-prom' ); ?></p>');
						$('#analyze-xml').prop('disabled', false);
					}
				});
			});

			// Відображення груп з атрибутами
			function displayGroups(groups) {
				const $container = $('#groups-list');
				$container.empty();

				Object.keys(groups).forEach(function(groupId) {
					const group = groups[groupId];
					const $groupBox = $('<div>').css({
						'border': '1px solid #ddd',
						'padding': '15px',
						'margin-bottom': '15px',
						'background': 'white'
					});

					// Заголовок з фото
					const $header = $('<div>').css({
						'display': 'flex',
						'align-items': 'flex-start',
						'margin-bottom': '10px'
					});

					if (group.image) {
						const $img = $('<img>').attr('src', group.image).css({
							'width': '80px',
							'height': '80px',
							'object-fit': 'cover',
							'margin-right': '15px',
							'border': '1px solid #ddd'
						});
						$header.append($img);
					}

					const $info = $('<div>');
					$info.append($('<h4>').text(group.name).css('margin', '0 0 5px 0'));
					$info.append($('<p>').html('<strong><?php esc_html_e( 'Group ID:', 'xml-prom' ); ?></strong> ' + groupId).css('margin', '0 0 5px 0'));
					
					// Елемент для динамічного відображення кількості варіацій
					const $variationsInfo = $('<p>').attr('id', 'variations_count_' + groupId).css('margin', '0');
					$variationsInfo.html('<strong><?php esc_html_e( 'Варіацій в XML:', 'xml-prom' ); ?></strong> ' + group.variations_count);
					$info.append($variationsInfo);
					
					// Додамо інфо про розраховану кількість варіацій
					const $calculatedInfo = $('<p>').attr('id', 'calculated_count_' + groupId).css({'margin': '5px 0 0 0', 'color': '#2271b1', 'font-weight': 'bold'});
					$info.append($calculatedInfo);
					
					$header.append($info);

					$groupBox.append($header);

					// Атрибути для вибору
					if (group.attributes && group.attributes.length > 0) {
						const $attrLabel = $('<p>').html('<strong><?php esc_html_e( 'Виберіть варіаційні атрибути:', 'xml-prom' ); ?></strong>').css('margin', '10px 0 5px 0');
						$groupBox.append($attrLabel);
						
						// Підказка
						const $hint = $('<p>').html('<em style="color: #666; font-size: 12px;"><?php esc_html_e( 'Примітка: Можна вибрати тільки атрибути що варіюються (мають різні значення між варіаціями)', 'xml-prom' ); ?></em>').css('margin', '5px 0 10px 0');
						$groupBox.append($hint);

						// Ініціалізуємо масив вибраних атрибутів - вибираємо тільки варіаційні
						if (!groupsData[groupId].selected_attributes) {
							// Знаходимо перший варіаційний атрибут
							const firstVaryingAttr = group.attributes.find(function(a) { return a.is_varying; });
							groupsData[groupId].selected_attributes = firstVaryingAttr ? [firstVaryingAttr.name] : [];
						}

						group.attributes.forEach(function(attr, index) {
							const checkboxId = 'attr_' + groupId + '_' + index;
							const $checkboxWrapper = $('<div>').css('margin', '5px 0 5px 20px');
							
							// Вибираємо за замовчуванням тільки якщо це перший варіаційний атрибут
							const isDefaultSelected = attr.is_varying && groupsData[groupId].selected_attributes.includes(attr.name);
							
							const $checkbox = $('<input>').attr({
								'type': 'checkbox',
								'name': 'group_attr_' + groupId + '[]',
								'id': checkboxId,
								'value': attr.name,
								'checked': isDefaultSelected,
								'disabled': !attr.is_varying // Вимикаємо чекбокси для не-варіаційних атрибутів
							}).on('change', function() {
								// Оновлюємо масив вибраних атрибутів
								const selected = [];
								$('input[name="group_attr_' + groupId + '[]"]:checked').each(function() {
									selected.push($(this).val());
								});
								groupsData[groupId].selected_attributes = selected;
								
								// Оновлюємо розрахунок варіацій
								updateVariationsCount(groupId);
							});

							const $label = $('<label>').attr('for', checkboxId).css({
								'margin-left': '5px',
								'cursor': attr.is_varying ? 'pointer' : 'not-allowed',
								'color': attr.is_varying ? 'inherit' : '#999',
								'opacity': attr.is_varying ? '1' : '0.6'
							});

							// Показуємо чи варіюється атрибут
							const varyingText = attr.is_varying ? ' ✓ (варіюється)' : ' ✗ (не варіюється)';
							$label.text(attr.name + varyingText + ' (' + attr.values.join(', ') + ')');

							$checkboxWrapper.append($checkbox).append($label);
							$groupBox.append($checkboxWrapper);
						});
					} else {
						$groupBox.append($('<p>').text('<?php esc_html_e( 'Немає атрибутів що відрізняються', 'xml-prom' ); ?>').css('color', 'orange'));
					}

					$container.append($groupBox);
					
					// Початковий розрахунок варіацій
					updateVariationsCount(groupId);
				});
			}

			// Функція для розрахунку кількості варіацій
			function updateVariationsCount(groupId) {
				const group = groupsData[groupId];
				if (!group || !group.selected_attributes || group.selected_attributes.length === 0) {
					$('#calculated_count_' + groupId).html('');
					return;
				}

				// Розраховуємо добуток кількості значень тільки ВАРІАЦІЙНИХ атрибутів
				let totalVariations = 1;
				const selectedAttrNames = group.selected_attributes;
				const attrInfo = [];

				group.attributes.forEach(function(attr) {
					if (selectedAttrNames.includes(attr.name) && attr.is_varying) {
						totalVariations *= attr.values.length;
						attrInfo.push(attr.name + ': ' + attr.values.length);
					}
				});

				// Показуємо розрахунок
				if (attrInfo.length > 1) {
					$('#calculated_count_' + groupId).html(
						'<strong><?php esc_html_e( 'Буде створено варіацій:', 'xml-prom' ); ?></strong> ' + 
						totalVariations + ' (' + attrInfo.join(' × ') + ')'
					);
				} else if (attrInfo.length === 1) {
					$('#calculated_count_' + groupId).html(
						'<strong><?php esc_html_e( 'Буде створено варіацій:', 'xml-prom' ); ?></strong> ' + totalVariations
					);
				} else {
					$('#calculated_count_' + groupId).html(
						'<strong style="color: orange;"><?php esc_html_e( 'Увага: вибрані атрибути не варіюються - буде створено 1 варіацію', 'xml-prom' ); ?></strong>'
					);
				}
			}

			$('#start-import').on('click', function() {
				// Check if SKU prefix is provided
				var skuPrefix = $('#import_sku_prefix').val().trim();
				if (!skuPrefix) {
					alert('<?php esc_html_e( 'Будь ласка, введіть SKU Prefix перед початком імпорту.', 'xml-prom' ); ?>');
					$('#import_sku_prefix').focus();
					return;
				}

			stopImport = false;
			var formData = new FormData($('#xml-import-form')[0]);
			formData.append('action', 'prom_xml_import_action');
			formData.append('new_category', $('#new_category').is(':checked') ? '1' : '0');
			formData.append('import_variations', '0'); // Завжди прості товари для цієї кнопки
			formData.append('sku_prefix', skuPrefix);

				$('#import-progress-container').show();
				$('#stop-import').show();
				$('#start-import').prop('disabled', true);

				function importChunk(offset = 0) {
					if (stopImport) {
						$('#import-status').text('<?php esc_html_e( 'Імпорт зупинено.', 'xml-prom' ); ?>');
						$('#stop-import').hide();
						$('#start-import').prop('disabled', false);
						return;
					}

					formData.set('offset', offset);

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						success: function(response) {
							if (response.success) {
								const { imported, total, finished } = response.data;

								const progress = (imported / total) * 100;
								$('#import-progress').val(progress);
								$('#import-status').text(imported + ' / ' + total + ' <?php esc_html_e( 'товарів імпортовано', 'xml-prom' ); ?>');

								if (!finished) {
									importChunk(imported);
								} else {
									$('#import-status').text('<?php esc_html_e( 'Імпорт завершено!', 'xml-prom' ); ?>');
									$('#stop-import').hide();
									$('#start-import').prop('disabled', false);
								}
							} else {
								alert('<?php esc_html_e( 'Помилка: ', 'xml-prom' ); ?>' + response.data.message);
								$('#stop-import').hide();
								$('#start-import').prop('disabled', false);
							}
						},
						error: function() {
							alert('<?php esc_html_e( 'Сталася помилка під час імпорту.', 'xml-prom' ); ?>');
							$('#stop-import').hide();
							$('#start-import').prop('disabled', false);
						}
					});
				}

				importChunk();
			});

			$('#stop-import').on('click', function() {
				stopImport = true;
			});

			// Імпорт з вибраними атрибутами
			$('#start-import-with-selection').on('click', function() {
				const skuPrefix = $('#import_sku_prefix').val().trim();
				
				// Збираємо вибрані атрибути
				const selectedAttributes = {};
				let hasEmptySelection = false;
				
				Object.keys(groupsData).forEach(function(groupId) {
					const selected = groupsData[groupId].selected_attributes || [];
					selectedAttributes[groupId] = selected;
					
					if (selected.length === 0) {
						hasEmptySelection = true;
					}
				});

				// Перевіряємо чи всі групи мають вибрані атрибути
				if (hasEmptySelection) {
					alert('<?php esc_html_e( 'Будь ласка, виберіть хоча б один атрибут для кожної групи товарів.', 'xml-prom' ); ?>');
					return;
				}

				stopImport = false;
				const formData = new FormData($('#xml-import-form')[0]);
				formData.append('action', 'prom_xml_import_action');
				formData.append('new_category', $('#new_category').is(':checked') ? '1' : '0');
				formData.append('import_variations', '1');
				formData.append('sku_prefix', skuPrefix);
				formData.append('selected_attributes', JSON.stringify(selectedAttributes));

				$('#import-progress-container').show();
				$('#stop-import').show();
				$('#start-import-with-selection').prop('disabled', true);
				$('#groups-analysis-container').hide();

				function importChunk(offset = 0) {
					if (stopImport) {
						$('#import-status').text('<?php esc_html_e( 'Імпорт зупинено.', 'xml-prom' ); ?>');
						$('#stop-import').hide();
						$('#start-import-with-selection').prop('disabled', false);
						return;
					}

					formData.set('offset', offset);

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						success: function(response) {
							if (response.success) {
								const imported = response.data.imported;
								const total = response.data.total;
								const progress = (imported / total) * 100;

								$('#import-progress').val(progress);
								$('#import-status').html('<?php esc_html_e( 'Імпортовано:', 'xml-prom' ); ?> ' + imported + ' / ' + total);

								if (!response.data.finished && !stopImport) {
									importChunk(imported);
								} else {
									$('#import-status').html('<?php esc_html_e( 'Імпорт завершено! Імпортовано:', 'xml-prom' ); ?> ' + imported + ' <?php esc_html_e( 'товарів', 'xml-prom' ); ?>');
									$('#stop-import').hide();
									$('#start-import-with-selection').prop('disabled', false);
								}
							} else {
								alert('<?php esc_html_e( 'Помилка:', 'xml-prom' ); ?> ' + response.data.message);
								$('#stop-import').hide();
								$('#start-import-with-selection').prop('disabled', false);
							}
						},
						error: function() {
							alert('<?php esc_html_e( 'Сталася помилка під час імпорту.', 'xml-prom' ); ?>');
							$('#stop-import').hide();
							$('#start-import-with-selection').prop('disabled', false);
						}
					});
				}

				importChunk();
			});
		});
	</script>
	<?php
}

function prom_xml_importer_settings_init() {
	register_setting( 'prom_xml_importer_settings', 'prom_xml_url' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_url_1' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_url_2' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_url_3' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_url_4' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_url_5' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_sku_prefix_1' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_skip_price_1' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_sku_prefix_2' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_skip_price_2' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_sku_prefix_3' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_skip_price_3' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_sku_prefix_4' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_skip_price_4' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_sku_prefix_5' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_skip_price_5' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_update_interval' );
	register_setting( 'prom_xml_importer_settings', 'telegram_user_ids' );
	register_setting( 'prom_xml_importer_settings', 'telegram_token_id' );

	add_settings_section( 'prom_xml_importer_section', __( 'Основні налаштування', 'xml-prom' ), null, 'prom-xml-importer' );

	add_settings_field( 'prom_xml_url', __( 'URL XML файлу 1', 'xml-prom' ), 'prom_xml_importer_url_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_sku_prefix_1', __( 'SKU Prefix 1', 'xml-prom' ), 'prom_xml_importer_sku_prefix_1_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_skip_price_1', __( 'Не оновлювати ціну 1', 'xml-prom' ), 'prom_xml_importer_skip_price_1_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_url_2', __( 'URL XML файлу 2', 'xml-prom' ), 'prom_xml_importer_url_2_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_sku_prefix_2', __( 'SKU Prefix 2', 'xml-prom' ), 'prom_xml_importer_sku_prefix_2_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_skip_price_2', __( 'Не оновлювати ціну 2', 'xml-prom' ), 'prom_xml_importer_skip_price_2_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_url_3', __( 'URL XML файлу 3', 'xml-prom' ), 'prom_xml_importer_url_3_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_sku_prefix_3', __( 'SKU Prefix 3', 'xml-prom' ), 'prom_xml_importer_sku_prefix_3_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_skip_price_3', __( 'Не оновлювати ціну 3', 'xml-prom' ), 'prom_xml_importer_skip_price_3_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_url_4', __( 'URL XML файлу 4', 'xml-prom' ), 'prom_xml_importer_url_4_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_sku_prefix_4', __( 'SKU Prefix 4', 'xml-prom' ), 'prom_xml_importer_sku_prefix_4_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_skip_price_4', __( 'Не оновлювати ціну 4', 'xml-prom' ), 'prom_xml_importer_skip_price_4_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_url_5', __( 'URL XML файлу 5', 'xml-prom' ), 'prom_xml_importer_url_5_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_sku_prefix_5', __( 'SKU Prefix 5', 'xml-prom' ), 'prom_xml_importer_sku_prefix_5_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_skip_price_5', __( 'Не оновлювати ціну 5', 'xml-prom' ), 'prom_xml_importer_skip_price_5_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_update_interval', __( 'Інтервал оновлення', 'xml-prom' ), 'prom_xml_importer_interval_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'telegram_user_ids', __( 'Telegram User IDs', 'xml-prom' ), 'prom_xml_importer_telegram_user_ids_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'telegram_token_id', __( 'Telegram Token ID', 'xml-prom' ), 'prom_xml_importer_telegram_token_id_render', 'prom-xml-importer', 'prom_xml_importer_section' );
}
add_action( 'admin_init', 'prom_xml_importer_settings_init' );

function prom_xml_importer_url_render() {
	$url = get_option( 'prom_xml_url', '' );
	?>
	<input type="text" name="prom_xml_url" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/products1.xml', 'xml-prom' ); ?>" style="width: 100%;">
	<?php
}

function prom_xml_importer_sku_prefix_1_render() {
	$prefix = get_option( 'prom_xml_sku_prefix_1', '' );
	?>
	<input type="text" name="prom_xml_sku_prefix_1" value="<?php echo esc_attr( $prefix ); ?>" placeholder="<?php esc_attr_e( 'Наприклад: XML1_', 'xml-prom' ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Префікс для SKU товарів з цього XML файлу.', 'xml-prom' ); ?></p>
	<?php
}

function prom_xml_importer_skip_price_1_render() {
	$val = get_option( 'prom_xml_skip_price_1', '0' );
	?>
	<label>
		<input type="checkbox" name="prom_xml_skip_price_1" value="1" <?php checked( $val, '1' ); ?>>
		<?php esc_html_e( 'Не змінювати ціни при оновленні цього постачальника', 'xml-prom' ); ?>
	</label>
	<?php
}

function prom_xml_importer_url_2_render() {
	$url = get_option( 'prom_xml_url_2', '' );
	?>
	<input type="text" name="prom_xml_url_2" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/products2.xml', 'xml-prom' ); ?>" style="width: 100%;">
	<?php
}

function prom_xml_importer_sku_prefix_2_render() {
	$prefix = get_option( 'prom_xml_sku_prefix_2', '' );
	?>
	<input type="text" name="prom_xml_sku_prefix_2" value="<?php echo esc_attr( $prefix ); ?>" placeholder="<?php esc_attr_e( 'Наприклад: XML2_', 'xml-prom' ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Префікс для SKU товарів з цього XML файлу.', 'xml-prom' ); ?></p>
	<?php
}

function prom_xml_importer_skip_price_2_render() {
	$val = get_option( 'prom_xml_skip_price_2', '0' );
	?>
	<label>
		<input type="checkbox" name="prom_xml_skip_price_2" value="1" <?php checked( $val, '1' ); ?>>
		<?php esc_html_e( 'Не змінювати ціни при оновленні цього постачальника', 'xml-prom' ); ?>
	</label>
	<?php
}

function prom_xml_importer_url_3_render() {
	$url = get_option( 'prom_xml_url_3', '' );
	?>
	<input type="text" name="prom_xml_url_3" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/products3.xml', 'xml-prom' ); ?>" style="width: 100%;">
	<?php
}

function prom_xml_importer_sku_prefix_3_render() {
	$prefix = get_option( 'prom_xml_sku_prefix_3', '' );
	?>
	<input type="text" name="prom_xml_sku_prefix_3" value="<?php echo esc_attr( $prefix ); ?>" placeholder="<?php esc_attr_e( 'Наприклад: XML3_', 'xml-prom' ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Префікс для SKU товарів з цього XML файлу.', 'xml-prom' ); ?></p>
	<?php
}

function prom_xml_importer_skip_price_3_render() {
	$val = get_option( 'prom_xml_skip_price_3', '0' );
	?>
	<label>
		<input type="checkbox" name="prom_xml_skip_price_3" value="1" <?php checked( $val, '1' ); ?>>
		<?php esc_html_e( 'Не змінювати ціни при оновленні цього постачальника', 'xml-prom' ); ?>
	</label>
	<?php
}

function prom_xml_importer_url_4_render() {
	$url = get_option( 'prom_xml_url_4', '' );
	?>
	<input type="text" name="prom_xml_url_4" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/products4.xml', 'xml-prom' ); ?>" style="width: 100%;">
	<?php
}

function prom_xml_importer_sku_prefix_4_render() {
	$prefix = get_option( 'prom_xml_sku_prefix_4', '' );
	?>
	<input type="text" name="prom_xml_sku_prefix_4" value="<?php echo esc_attr( $prefix ); ?>" placeholder="<?php esc_attr_e( 'Наприклад: XML4_', 'xml-prom' ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Префікс для SKU товарів з цього XML файлу.', 'xml-prom' ); ?></p>
	<?php
}

function prom_xml_importer_skip_price_4_render() {
	$val = get_option( 'prom_xml_skip_price_4', '0' );
	?>
	<label>
		<input type="checkbox" name="prom_xml_skip_price_4" value="1" <?php checked( $val, '1' ); ?>>
		<?php esc_html_e( 'Не змінювати ціни при оновленні цього постачальника', 'xml-prom' ); ?>
	</label>
	<?php
}

function prom_xml_importer_url_5_render() {
	$url = get_option( 'prom_xml_url_5', '' );
	?>
	<input type="text" name="prom_xml_url_5" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/products5.xml', 'xml-prom' ); ?>" style="width: 100%;">
	<?php
}

function prom_xml_importer_sku_prefix_5_render() {
	$prefix = get_option( 'prom_xml_sku_prefix_5', '' );
	?>
	<input type="text" name="prom_xml_sku_prefix_5" value="<?php echo esc_attr( $prefix ); ?>" placeholder="<?php esc_attr_e( 'Наприклад: XML5_', 'xml-prom' ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Префікс для SKU товарів з цього XML файлу.', 'xml-prom' ); ?></p>
	<?php
}

function prom_xml_importer_skip_price_5_render() {
	$val = get_option( 'prom_xml_skip_price_5', '0' );
	?>
	<label>
		<input type="checkbox" name="prom_xml_skip_price_5" value="1" <?php checked( $val, '1' ); ?>>
		<?php esc_html_e( 'Не змінювати ціни при оновленні цього постачальника', 'xml-prom' ); ?>
	</label>
	<?php
}

function prom_xml_importer_interval_render() {
	$interval = get_option( 'prom_xml_update_interval', 'hourly' );
	?>
	<select name="prom_xml_update_interval">
		<option value="5_minute" <?php selected( $interval, '5_minute' ); ?>><?php esc_html_e( 'Що 5 хв', 'xml-prom' ); ?></option>
		<option value="hourly" <?php selected( $interval, 'hourly' ); ?>><?php esc_html_e( 'Щогодини', 'xml-prom' ); ?></option>
		<option value="twicedaily" <?php selected( $interval, 'twicedaily' ); ?>><?php esc_html_e( 'Двічі на день', 'xml-prom' ); ?></option>
		<option value="daily" <?php selected( $interval, 'daily' ); ?>><?php esc_html_e( 'Щодня', 'xml-prom' ); ?></option>
	</select>
	<?php
}

function prom_xml_importer_telegram_user_ids_render() {
	$user_ids = get_option( 'telegram_user_ids', '' );
	?>
	<input type="text" name="telegram_user_ids" value="<?php echo esc_attr( $user_ids ); ?>" placeholder="<?php esc_attr_e( '123456789, 987654321', 'xml-prom' ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Введіть Telegram User IDs, розділені комою.', 'xml-prom' ); ?></p>
	<?php
}

function prom_xml_importer_telegram_token_id_render() {
	$token_id = get_option( 'telegram_token_id', '' );
	?>
	<input type="text" name="telegram_token_id" value="<?php echo esc_attr( $token_id ); ?>" placeholder="<?php esc_attr_e( '1234567890:ABCdefGHIjklMNOpqrsTUVwxyz', 'xml-prom' ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Введіть токен вашого Telegram бота.', 'xml-prom' ); ?></p>
	<?php
}

function prom_xml_importer_handle_import_action() {
	if ( ! isset( $_POST['prom_xml_import_nonce'] ) || ! wp_verify_nonce( $_POST['prom_xml_import_nonce'], 'prom_xml_import_action' ) ) {
		wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied' ) );
	}

	if ( isset( $_FILES['import_xml_file'] ) && $_FILES['import_xml_file']['error'] === UPLOAD_ERR_OK ) {
		$file_path         = $_FILES['import_xml_file']['tmp_name'];
		$offset            = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$new_category      = isset( $_POST['new_category'] ) && $_POST['new_category'] === '1';
		$import_variations = isset( $_POST['import_variations'] ) && $_POST['import_variations'] === '1';
		$sku_prefix        = isset( $_POST['sku_prefix'] ) ? sanitize_text_field( $_POST['sku_prefix'] ) : '';

		// Get selected attributes for manual mode
		$selected_attributes = array();
		if ( isset( $_POST['selected_attributes'] ) ) {
			$decoded = json_decode( stripslashes( $_POST['selected_attributes'] ), true );
			if ( is_array( $decoded ) ) {
				$selected_attributes = $decoded;
			}
		}

		// Setting import variations and attributes

		// Set temporary options for this import session
		set_transient( 'prom_xml_import_variations_temp', $import_variations ? '1' : '0', HOUR_IN_SECONDS );
		set_transient( 'prom_xml_selected_attributes_temp', $selected_attributes, HOUR_IN_SECONDS );

		$xml_parser = new XML_Parser( $file_path, $new_category, $sku_prefix );
		try {
			$result = $xml_parser->import_products( $offset, 1 );
			
			// Clear transients if import is finished
			if ( $result['finished'] ) {
				delete_transient( 'prom_xml_import_variations_temp' );
				delete_transient( 'prom_xml_selected_attributes_temp' );
			}
			
			wp_send_json_success(
				array(
					'imported' => $result['imported'] + $offset,
					'total'    => $result['total'],
					'finished' => $result['finished'],
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	} else {
		wp_send_json_error( array( 'message' => 'Помилка завантаження файлу.' ) );
	}
}
add_action( 'wp_ajax_prom_xml_import_action', 'prom_xml_importer_handle_import_action' );
add_action( 'wp_ajax_prom_xml_analyze_groups', 'prom_xml_importer_handle_analyze_groups' );

/**
 * Handle analyze groups action - scans XML and returns variable product groups
 */
function prom_xml_importer_handle_analyze_groups() {
	if ( ! isset( $_POST['prom_xml_import_nonce'] ) || ! wp_verify_nonce( $_POST['prom_xml_import_nonce'], 'prom_xml_import_action' ) ) {
		wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied' ) );
	}

	if ( isset( $_FILES['import_xml_file'] ) && $_FILES['import_xml_file']['error'] === UPLOAD_ERR_OK ) {
		$file_path = $_FILES['import_xml_file']['tmp_name'];

		try {
			$groups = prom_xml_analyze_variable_groups( $file_path );
			wp_send_json_success( array( 'groups' => $groups ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	} else {
		wp_send_json_error( array( 'message' => 'Помилка завантаження файлу.' ) );
	}
}

/**
 * Analyze XML file and extract variable product groups with their attributes
 *
 * @param string $file_path Path to XML file.
 * @return array Groups data.
 */
function prom_xml_analyze_variable_groups( string $file_path ): array {
	$reader = new XMLReader();

	if ( ! $reader->open( $file_path ) ) {
		throw new Exception( 'Failed to open XML file.' );
	}

	$groups = array();

	while ( $reader->read() ) {
		if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'offer' ) {
			continue;
		}

		$offer = simplexml_load_string( $reader->readOuterXML() );

		// Тільки offers з group_id
		if ( ! isset( $offer['group_id'] ) || empty( (string) $offer['group_id'] ) ) {
			continue;
		}

		$group_id = (string) $offer['group_id'];

		// Extract offer data
		$offer_data = array(
			'id'     => (string) $offer['id'],
			'name'   => (string) $offer->name,
			'image'  => isset( $offer->picture[0] ) ? (string) $offer->picture[0] : '',
			'attributes' => array(),
		);

		// Extract all attributes
		if ( isset( $offer->param ) ) {
			foreach ( $offer->param as $param ) {
				$attr_name  = (string) $param['name'];
				$attr_value = (string) $param;
				if ( ! empty( $attr_name ) && ! empty( $attr_value ) ) {
					$offer_data['attributes'][ $attr_name ] = $attr_value;
				}
			}
		}

		// Add to group
		if ( ! isset( $groups[ $group_id ] ) ) {
			$groups[ $group_id ] = array(
				'name'              => $offer_data['name'],
				'image'             => $offer_data['image'],
				'variations_count'  => 0,
				'variations'        => array(),
				'all_attributes'    => array(),
			);
		}

		$groups[ $group_id ]['variations'][] = $offer_data;
		$groups[ $group_id ]['variations_count']++;

		// Collect all attributes from all variations
		foreach ( $offer_data['attributes'] as $attr_name => $attr_value ) {
			if ( ! isset( $groups[ $group_id ]['all_attributes'][ $attr_name ] ) ) {
				$groups[ $group_id ]['all_attributes'][ $attr_name ] = array();
			}
			if ( ! in_array( $attr_value, $groups[ $group_id ]['all_attributes'][ $attr_name ], true ) ) {
				$groups[ $group_id ]['all_attributes'][ $attr_name ][] = $attr_value;
			}
		}
	}

	$reader->close();

	// Filter attributes - only those that vary between products
	foreach ( $groups as $group_id => &$group ) {
		$varying_attributes = array();

		foreach ( $group['all_attributes'] as $attr_name => $attr_values ) {
			// Показуємо ВСІ атрибути, а не тільки ті що варіюються
			// Атрибут варіюється якщо має більше 1 значення
			$is_varying = count( $attr_values ) > 1;
			
			$varying_attributes[] = array(
				'name'       => $attr_name,
				'values'     => $attr_values,
				'is_varying' => $is_varying,
			);
		}

		// Group attributes processed

		$group['attributes'] = $varying_attributes;
		unset( $group['all_attributes'] ); // Видаляємо тимчасові дані
		unset( $group['variations'] ); // Не передаємо всі варіації на фронтенд
	}

	// Фільтруємо групи - показуємо тільки ті що мають 2+ варіації
	$filtered_groups = array();
	foreach ( $groups as $group_id => $group ) {
		if ( $group['variations_count'] >= 2 ) {
			$filtered_groups[ $group_id ] = $group;
		} else {
			// Skipping group with only 1 variation
		}
	}
	
	// Filtered groups with 2+ variations

	return $filtered_groups;
}

/**
 * Handles the admin post actions for running the script and stopping cron jobs.
 *
 * @return void
 */
function prom_xml_importer_handle_action() {
	if ( ! isset( $_POST['prom_xml_importer_nonce'] ) || ! wp_verify_nonce( $_POST['prom_xml_importer_nonce'], 'prom_xml_importer_action' ) ) {
		wp_die( 'Nonce verification failed' );
	}

	if ( isset( $_POST['run_script'] ) ) {
		// Get all configured XML URLs
		$xml_urls = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$url = get_option( 'prom_xml_url' . ( $i === 1 ? '' : '_' . $i ), '' );
			if ( ! empty( $url ) ) {
				$xml_urls[ $i ] = $url;
			}
		}

		if ( ! empty( $xml_urls ) ) {
			// Check if we should run in background or immediately
			$bg_option = isset( $_POST['use_background'] ) ? $_POST['use_background'] : 'no';

			if ( $bg_option === 'yes' ) {
				// Run in background and ensure cron is active
				$started = false;
				foreach ( $xml_urls as $index => $xml_url ) {
					$sku_prefix = get_option( 'prom_xml_sku_prefix_' . $index, '' );
					if ( prom_trigger_background_sync( $xml_url, $sku_prefix ) ) {
						$started = true;
					}
				}

				if ( $started ) {
					add_settings_error(
						'prom_xml_importer_settings',
						'background_sync_started',
						__( 'Stock update has been scheduled to run in the background.', 'xml-prom' ),
						'updated'
					);
				}

				// Ensure cron is active for future scheduled runs
				if ( ! wp_next_scheduled( 'prom_update_stock_cron' ) ) {
					Cron_Job::deactivate();
					Cron_Job::activate();
				}
			} else {
				// Run immediately without scheduling cron jobs
				try {
					// Increase time limit for direct execution
					if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
						@set_time_limit( 300 ); // 5 minutes
					}

					$success_count = 0;
					$total_count   = count( $xml_urls );

					foreach ( $xml_urls as $index => $xml_url ) {
						try {
							$sku_prefix = get_option( 'prom_xml_sku_prefix_' . $index, '' );
							$skip_price = get_option( 'prom_xml_skip_price_' . $index, '0' );
							$updater    = new XML_Stock_Updater( $xml_url, $sku_prefix, ( $skip_price === '1' || $skip_price === 'yes' || $skip_price === 'on' ) );
							$updater->update_products_stock_status();
							++$success_count;
						} catch ( Exception $e ) {
							// Silent error handling
						}
					}

					if ( $success_count > 0 ) {
						add_settings_error(
							'prom_xml_importer_settings',
							'settings_updated',
							sprintf( __( 'Stock update completed successfully for %1$d out of %2$d XML files.', 'xml-prom' ), $success_count, $total_count ),
							'updated'
						);
					} else {
						add_settings_error(
							'prom_xml_importer_settings',
							'update_error',
							__( 'Failed to update stock for all XML files.', 'xml-prom' ),
							'error'
						);
					}
				} catch ( Exception $e ) {
					add_settings_error(
						'prom_xml_importer_settings',
						'update_error',
						__( 'Error updating stock: ', 'xml-prom' ) . $e->getMessage(),
						'error'
					);
				}

				// Do NOT schedule any cron tasks here - we want to run just once
			}
		} else {
			add_settings_error(
				'prom_xml_importer_settings',
				'missing_url',
				__( 'Please configure at least one XML URL first.', 'xml-prom' ),
				'error'
			);
		}
	}

	if ( isset( $_POST['prom_xml_importer_stop'] ) ) {
		wp_clear_scheduled_hook( 'prom_update_stock_cron' );
		wp_clear_scheduled_hook( 'prom_single_update_event' );

		add_settings_error(
			'prom_xml_importer_settings',
			'settings_updated',
			__( 'Cron jobs stopped.', 'xml-prom' ),
			'updated'
		);
	}

	// Redirect back to settings page
	wp_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
	exit;
}

add_action( 'admin_post_prom_xml_importer_action', 'prom_xml_importer_handle_action' );

/**
 * Export settings page
 */
function prom_xml_importer_export_page() {
	// Handle form submission
	if ( isset( $_POST['create_filtered_xml'] ) && wp_verify_nonce( $_POST['prom_xml_export_nonce'], 'prom_xml_export_filter' ) ) {
		$sku_prefix = sanitize_text_field( $_POST['sku_prefix'] );
		
		// Check if file was uploaded
		if ( isset( $_FILES['xml_file'] ) && $_FILES['xml_file']['error'] === UPLOAD_ERR_OK ) {
			$uploaded_file = $_FILES['xml_file'];
			
			// Validate file type
			$file_type = wp_check_filetype( $uploaded_file['name'] );
			$file_extension = strtolower( pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION ) );
			
			// Debug info for troubleshooting
			$debug_info = sprintf( 
				'Debug: wp_check_filetype ext="%s", pathinfo ext="%s", filename="%s"', 
				$file_type['ext'] ?? 'null', 
				$file_extension, 
				$uploaded_file['name'] 
			);
			
			// More flexible validation - check both methods
			$is_xml_file = ( $file_type['ext'] === 'xml' ) || ( $file_extension === 'xml' );
			
			if ( ! $is_xml_file ) {
				add_settings_error(
					'prom_xml_export',
					'export_error',
					'❌ Помилка: Файл повинен мати розширення .xml. ' . $debug_info,
					'error'
				);
			} else {
				// Create filtered XML
				require_once plugin_dir_path( __FILE__ ) . '../includes/class-xml-export-filter.php';
				$export_filter = new XML_Export_Filter( $uploaded_file['tmp_name'], $sku_prefix );
				$result = $export_filter->create_filtered_xml();
				
				if ( $result['success'] ) {
					add_settings_error(
						'prom_xml_export',
						'export_success',
						sprintf( '✅ Очищений XML створено! Видалено %d товарів. <a href="%s" class="button button-primary">Завантажити XML</a>', 
							$result['removed_count'], 
							$result['download_url'] 
						),
						'updated'
					);
				} else {
					add_settings_error(
						'prom_xml_export',
						'export_error',
						'❌ Помилка: ' . $result['error'],
						'error'
					);
				}
			}
		} else {
			add_settings_error(
				'prom_xml_export',
				'export_error',
				'❌ Помилка: Будь ласка, виберіть XML файл для завантаження',
				'error'
			);
		}
	}
	
	// Get current settings
	$current_xml_url = get_option( 'prom_xml_url', '' );
	$current_sku_prefix = get_option( 'prom_sku_prefix', 'NEW_' );
	?>
	
	<div class="wrap">
		<h1><?php esc_html_e( 'Налаштування вигрузки', 'xml-prom' ); ?></h1>
		
		<?php settings_errors( 'prom_xml_export' ); ?>
		
		<div class="card">
			<h2>🔍 Фільтр XML вигрузки</h2>
			<p>Створити новий XML файл без товарів, які вже є на сайті. Це дозволить імпортувати тільки нові товари.</p>
			
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'prom_xml_export_filter', 'prom_xml_export_nonce' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="xml_file"><?php esc_html_e( 'XML файл', 'xml-prom' ); ?></label>
						</th>
						<td>
							<input type="file" 
								   id="xml_file" 
								   name="xml_file" 
								   accept=".xml" 
								   required />
							<p class="description"><?php esc_html_e( 'Завантажте XML файл з товарами', 'xml-prom' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="sku_prefix"><?php esc_html_e( 'SKU префікс', 'xml-prom' ); ?></label>
						</th>
						<td>
							<input type="text" 
								   id="sku_prefix" 
								   name="sku_prefix" 
								   value="<?php echo esc_attr( $current_sku_prefix ); ?>" 
								   class="regular-text" 
								   placeholder="NEW_" />
							<p class="description"><?php esc_html_e( 'Префікс SKU товарів на сайті (наприклад: NEW_)', 'xml-prom' ); ?></p>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" 
						   name="create_filtered_xml" 
						   class="button button-primary" 
						   value="<?php esc_attr_e( 'Створити очищений XML', 'xml-prom' ); ?>" />
				</p>
			</form>
		</div>
		
		<div class="card">
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
			// Show current site statistics
			global $wpdb;
			$site_products_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.meta_value) 
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ('product', 'product_variation')
				AND p.post_status IN ('publish', 'draft', 'private')
				AND pm.meta_key = '_sku'
				AND pm.meta_value LIKE %s",
				$current_sku_prefix . '%'
			) );
			?>
			<p><strong>Товарів на сайті з префіксом "<?php echo esc_html( $current_sku_prefix ); ?>":</strong> <?php echo intval( $site_products_count ); ?></p>
		</div>
	</div>
	
	<?php
}

?>
