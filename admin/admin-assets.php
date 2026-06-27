<?php

defined( 'ABSPATH' ) || exit;

/**
 * Check whether current admin screen belongs to this plugin.
 */
function f2cs_is_plugin_admin_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	return $screen && strpos( $screen->id, 'f2cs-' ) !== false;
}

/**
 * Get current plugin admin page slug.
 */
function f2cs_get_admin_page_slug() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check used only to decide which assets to enqueue.
	return isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
}

/**
 * Translations for the import admin script.
 */
function f2cs_get_import_i18n() {
	return array(
		'selectFile'           => __( 'Будь ласка, виберіть XML файл.', 'factorial2000-catalog-sync' ),
		'enterSkuPrefix'       => __( 'Будь ласка, введіть SKU Prefix.', 'factorial2000-catalog-sync' ),
		'analyzing'            => __( 'Аналіз XML файлу...', 'factorial2000-catalog-sync' ),
		'analysisDone'         => __( 'Аналіз завершено! Знайдено груп:', 'factorial2000-catalog-sync' ),
		'analysisError'        => __( 'Помилка при аналізі XML.', 'factorial2000-catalog-sync' ),
		'groupId'              => __( 'Group ID:', 'factorial2000-catalog-sync' ),
		'variationsInXml'      => __( 'Варіацій в XML:', 'factorial2000-catalog-sync' ),
		'selectAttributes'     => __( 'Виберіть варіаційні атрибути:', 'factorial2000-catalog-sync' ),
		'attributesHint'       => __( 'Примітка: Можна вибрати тільки атрибути що варіюються (мають різні значення між варіаціями)', 'factorial2000-catalog-sync' ),
		'noAttributes'         => __( 'Немає атрибутів що відрізняються', 'factorial2000-catalog-sync' ),
		'variationsWillCreate' => __( 'Буде створено варіацій:', 'factorial2000-catalog-sync' ),
		'variationsWarning'    => __( 'Увага: вибрані атрибути не варіюються - буде створено 1 варіацію', 'factorial2000-catalog-sync' ),
		'enterSkuBeforeImport' => __( 'Будь ласка, введіть SKU Prefix перед початком імпорту.', 'factorial2000-catalog-sync' ),
		'importStopped'        => __( 'Імпорт зупинено.', 'factorial2000-catalog-sync' ),
		'productsImported'       => __( 'товарів імпортовано', 'factorial2000-catalog-sync' ),
		'importFinished'       => __( 'Імпорт завершено!', 'factorial2000-catalog-sync' ),
		'errorPrefix'          => __( 'Помилка:', 'factorial2000-catalog-sync' ),
		'importFailed'         => __( 'Сталася помилка під час імпорту.', 'factorial2000-catalog-sync' ),
		'selectAttributeGroup' => __( 'Будь ласка, виберіть хоча б один атрибут для кожної групи товарів.', 'factorial2000-catalog-sync' ),
		'importedLabel'        => __( 'Імпортовано:', 'factorial2000-catalog-sync' ),
		'importFinishedCount'  => __( 'Імпорт завершено! Імпортовано:', 'factorial2000-catalog-sync' ),
		'productsLabel'        => __( 'товарів', 'factorial2000-catalog-sync' ),
		'varyingYes'           => __( 'варіюється', 'factorial2000-catalog-sync' ),
		'varyingNo'            => __( 'не варіюється', 'factorial2000-catalog-sync' ),
	);
}

/**
 * Enqueue admin styles and scripts for plugin pages.
 */
function f2cs_enqueue_admin_assets( $_hook_suffix ) {
	if ( ! f2cs_is_plugin_admin_screen() ) {
		return;
	}

	wp_enqueue_style(
		'f2cs-admin-settings',
		F2CS_URL . 'assets/css/admin-settings.css',
		array(),
		F2CS_VERSION
	);

	wp_enqueue_style(
		'f2cs-admin-support',
		F2CS_URL . 'assets/css/admin-support.css',
		array(),
		F2CS_VERSION
	);

	wp_enqueue_script(
		'f2cs-admin-support',
		F2CS_URL . 'assets/js/admin-support.js',
		array(),
		F2CS_VERSION,
		true
	);

	wp_localize_script(
		'f2cs-admin-support',
		'f2csSupport',
		array(
			'cardNumber'  => '4874100038712884',
			'copiedLabel' => __( 'Скопійовано', 'factorial2000-catalog-sync' ),
		)
	);

	if ( f2cs_get_admin_page_slug() === 'f2cs-import' ) {
		wp_enqueue_script(
			'f2cs-admin-import',
			F2CS_URL . 'assets/js/admin-import.js',
			array( 'jquery' ),
			F2CS_VERSION,
			true
		);

		wp_localize_script(
			'f2cs-admin-import',
			'f2csImport',
			array(
				'i18n' => f2cs_get_import_i18n(),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'f2cs_enqueue_admin_assets' );
