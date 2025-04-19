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
				<input type="submit" name="run_script" class="button button-primary" value="<?php esc_attr_e( 'Run Auto Stock Status', 'xml-prom' ); ?>" style="margin-right: 10px;">
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
			</table>
			<button type="button" id="start-import" class="button button-primary"><?php esc_html_e( 'Імпортувати', 'xml-prom' ); ?></button>
			<button type="button" id="stop-import" class="button button-secondary" style="display: none;"><?php esc_html_e( 'Зупинити', 'xml-prom' ); ?></button>
		</form>

		<div id="import-progress-container" style="display: none;">
			<h3><?php esc_html_e( 'Прогрес імпорту', 'xml-prom' ); ?></h3>
			<progress id="import-progress" value="0" max="100" style="width: 100%;"></progress>
			<div id="import-status"></div>
		</div>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function($) {
			let stopImport = false;

			$('#start-import').on('click', function() {
				stopImport = false;
				var formData = new FormData($('#xml-import-form')[0]);
				formData.append('action', 'prom_xml_import_action');
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
		});
	</script>
	<?php
}

function prom_xml_importer_settings_init() {
	register_setting( 'prom_xml_importer_settings', 'prom_xml_url' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_update_interval' );
	register_setting( 'prom_xml_importer_settings', 'telegram_user_ids' );
	register_setting( 'prom_xml_importer_settings', 'telegram_token_id' );

	add_settings_section( 'prom_xml_importer_section', __( 'Основні налаштування', 'xml-prom' ), null, 'prom-xml-importer' );

	add_settings_field( 'prom_xml_url', __( 'URL XML файлу', 'xml-prom' ), 'prom_xml_importer_url_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'prom_xml_update_interval', __( 'Інтервал оновлення', 'xml-prom' ), 'prom_xml_importer_interval_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'telegram_user_ids', __( 'Telegram User IDs', 'xml-prom' ), 'prom_xml_importer_telegram_user_ids_render', 'prom-xml-importer', 'prom_xml_importer_section' );
	add_settings_field( 'telegram_token_id', __( 'Telegram Token ID', 'xml-prom' ), 'prom_xml_importer_telegram_token_id_render', 'prom-xml-importer', 'prom_xml_importer_section' );
}
add_action( 'admin_init', 'prom_xml_importer_settings_init' );

function prom_xml_importer_url_render() {
	$url = get_option( 'prom_xml_url', '' );
	?>
	<input type="text" name="prom_xml_url" value="<?php echo esc_attr( $url ); ?>" style="width: 100%;">
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
	<input type="text" name="telegram_user_ids" value="<?php echo esc_attr( $user_ids ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Введіть Telegram User IDs, розділені комою.', 'xml-prom' ); ?></p>
	<?php
}

function prom_xml_importer_telegram_token_id_render() {
	$token_id = get_option( 'telegram_token_id', '' );
	?>
	<input type="text" name="telegram_token_id" value="<?php echo esc_attr( $token_id ); ?>" style="width: 100%;">
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
		$file_path = $_FILES['import_xml_file']['tmp_name'];
		$offset    = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

		$xml_parser = new XML_Parser( $file_path );
		try {
			$result = $xml_parser->import_products( $offset, 1 );
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
		$xml_url = get_option( 'prom_xml_url', '' );

		if ( $xml_url ) {
			// For production: Use background processing for large XML files
			$bg_option = isset( $_POST['use_background'] ) ? $_POST['use_background'] : 'no';

			if ( $bg_option === 'yes' ) {
				// Run in background
				if ( prom_trigger_background_sync( $xml_url ) ) {
					add_settings_error(
						'prom_xml_importer_settings',
						'background_sync_started',
						__( 'Stock update has been scheduled to run in the background.', 'xml-prom' ),
						'updated'
					);
				}
			} else {
				// Run immediately
				try {
					// Increase time limit for direct execution
					if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
						@set_time_limit( 300 ); // 5 minutes
					}

					$updater = new XML_Stock_Updater( $xml_url );
					$updater->update_products_stock_status();

					add_settings_error(
						'prom_xml_importer_settings',
						'settings_updated',
						__( 'Stock update completed successfully.', 'xml-prom' ),
						'updated'
					);
				} catch ( Exception $e ) {
					add_settings_error(
						'prom_xml_importer_settings',
						'update_error',
						__( 'Error updating stock: ', 'xml-prom' ) . $e->getMessage(),
						'error'
					);
				}
			}
		} else {
			add_settings_error(
				'prom_xml_importer_settings',
				'missing_url',
				__( 'Please configure an XML URL first.', 'xml-prom' ),
				'error'
			);
		}

		// Ensure cron is active
		if ( ! wp_next_scheduled( 'prom_update_stock_cron' ) ) {
			Cron_Job::deactivate();
			Cron_Job::activate();
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

?>
