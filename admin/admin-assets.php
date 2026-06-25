<?php

defined( 'ABSPATH' ) || exit;

/**
 * Check whether current admin screen belongs to this plugin.
 */
function prom_xml_importer_is_plugin_admin_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	return $screen && strpos( $screen->id, 'prom-xml-importer' ) !== false;
}

/**
 * Get current plugin admin page slug.
 */
function prom_xml_importer_get_admin_page_slug() {
	return isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
}

/**
 * Translations for the import admin script.
 */
function prom_xml_importer_get_import_i18n() {
	return array(
		'selectFile'           => __( 'Будь ласка, виберіть XML файл.', 'prom-xml-importer' ),
		'enterSkuPrefix'       => __( 'Будь ласка, введіть SKU Prefix.', 'prom-xml-importer' ),
		'analyzing'            => __( 'Аналіз XML файлу...', 'prom-xml-importer' ),
		'analysisDone'         => __( 'Аналіз завершено! Знайдено груп:', 'prom-xml-importer' ),
		'analysisError'        => __( 'Помилка при аналізі XML.', 'prom-xml-importer' ),
		'groupId'              => __( 'Group ID:', 'prom-xml-importer' ),
		'variationsInXml'      => __( 'Варіацій в XML:', 'prom-xml-importer' ),
		'selectAttributes'     => __( 'Виберіть варіаційні атрибути:', 'prom-xml-importer' ),
		'attributesHint'       => __( 'Примітка: Можна вибрати тільки атрибути що варіюються (мають різні значення між варіаціями)', 'prom-xml-importer' ),
		'noAttributes'         => __( 'Немає атрибутів що відрізняються', 'prom-xml-importer' ),
		'variationsWillCreate' => __( 'Буде створено варіацій:', 'prom-xml-importer' ),
		'variationsWarning'    => __( 'Увага: вибрані атрибути не варіюються - буде створено 1 варіацію', 'prom-xml-importer' ),
		'enterSkuBeforeImport' => __( 'Будь ласка, введіть SKU Prefix перед початком імпорту.', 'prom-xml-importer' ),
		'importStopped'        => __( 'Імпорт зупинено.', 'prom-xml-importer' ),
		'productsImported'       => __( 'товарів імпортовано', 'prom-xml-importer' ),
		'importFinished'       => __( 'Імпорт завершено!', 'prom-xml-importer' ),
		'errorPrefix'          => __( 'Помилка:', 'prom-xml-importer' ),
		'importFailed'         => __( 'Сталася помилка під час імпорту.', 'prom-xml-importer' ),
		'selectAttributeGroup' => __( 'Будь ласка, виберіть хоча б один атрибут для кожної групи товарів.', 'prom-xml-importer' ),
		'importedLabel'        => __( 'Імпортовано:', 'prom-xml-importer' ),
		'importFinishedCount'  => __( 'Імпорт завершено! Імпортовано:', 'prom-xml-importer' ),
		'productsLabel'        => __( 'товарів', 'prom-xml-importer' ),
		'varyingYes'           => __( 'варіюється', 'prom-xml-importer' ),
		'varyingNo'            => __( 'не варіюється', 'prom-xml-importer' ),
	);
}

/**
 * Enqueue admin styles and scripts for plugin pages.
 */
function prom_xml_importer_enqueue_admin_assets( $_hook_suffix ) {
	if ( ! prom_xml_importer_is_plugin_admin_screen() ) {
		return;
	}

	wp_enqueue_style(
		'prom-xml-admin-settings',
		PROM_XML_IMPORTER_URL . 'assets/css/admin-settings.css',
		array(),
		PROM_XML_IMPORTER_VERSION
	);

	wp_enqueue_style(
		'prom-xml-admin-support',
		PROM_XML_IMPORTER_URL . 'assets/css/admin-support.css',
		array(),
		PROM_XML_IMPORTER_VERSION
	);

	wp_enqueue_script(
		'prom-xml-admin-support',
		PROM_XML_IMPORTER_URL . 'assets/js/admin-support.js',
		array(),
		PROM_XML_IMPORTER_VERSION,
		true
	);

	wp_localize_script(
		'prom-xml-admin-support',
		'promXmlSupport',
		array(
			'cardNumber'  => '4874100038712884',
			'copiedLabel' => __( 'Скопійовано', 'prom-xml-importer' ),
		)
	);

	if ( prom_xml_importer_get_admin_page_slug() === 'prom-xml-importer-import' ) {
		wp_enqueue_script(
			'prom-xml-admin-import',
			PROM_XML_IMPORTER_URL . 'assets/js/admin-import.js',
			array( 'jquery' ),
			PROM_XML_IMPORTER_VERSION,
			true
		);

		wp_localize_script(
			'prom-xml-admin-import',
			'promXmlImport',
			array(
				'i18n' => prom_xml_importer_get_import_i18n(),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'prom_xml_importer_enqueue_admin_assets' );
