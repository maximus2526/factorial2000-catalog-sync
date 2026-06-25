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
		'selectFile'           => __( 'Будь ласка, виберіть XML файл.', 'xml-prom' ),
		'enterSkuPrefix'       => __( 'Будь ласка, введіть SKU Prefix.', 'xml-prom' ),
		'analyzing'            => __( 'Аналіз XML файлу...', 'xml-prom' ),
		'analysisDone'         => __( 'Аналіз завершено! Знайдено груп:', 'xml-prom' ),
		'analysisError'        => __( 'Помилка при аналізі XML.', 'xml-prom' ),
		'groupId'              => __( 'Group ID:', 'xml-prom' ),
		'variationsInXml'      => __( 'Варіацій в XML:', 'xml-prom' ),
		'selectAttributes'     => __( 'Виберіть варіаційні атрибути:', 'xml-prom' ),
		'attributesHint'       => __( 'Примітка: Можна вибрати тільки атрибути що варіюються (мають різні значення між варіаціями)', 'xml-prom' ),
		'noAttributes'         => __( 'Немає атрибутів що відрізняються', 'xml-prom' ),
		'variationsWillCreate' => __( 'Буде створено варіацій:', 'xml-prom' ),
		'variationsWarning'    => __( 'Увага: вибрані атрибути не варіюються - буде створено 1 варіацію', 'xml-prom' ),
		'enterSkuBeforeImport' => __( 'Будь ласка, введіть SKU Prefix перед початком імпорту.', 'xml-prom' ),
		'importStopped'        => __( 'Імпорт зупинено.', 'xml-prom' ),
		'productsImported'       => __( 'товарів імпортовано', 'xml-prom' ),
		'importFinished'       => __( 'Імпорт завершено!', 'xml-prom' ),
		'errorPrefix'          => __( 'Помилка:', 'xml-prom' ),
		'importFailed'         => __( 'Сталася помилка під час імпорту.', 'xml-prom' ),
		'selectAttributeGroup' => __( 'Будь ласка, виберіть хоча б один атрибут для кожної групи товарів.', 'xml-prom' ),
		'importedLabel'        => __( 'Імпортовано:', 'xml-prom' ),
		'importFinishedCount'  => __( 'Імпорт завершено! Імпортовано:', 'xml-prom' ),
		'productsLabel'        => __( 'товарів', 'xml-prom' ),
		'varyingYes'           => __( 'варіюється', 'xml-prom' ),
		'varyingNo'            => __( 'не варіюється', 'xml-prom' ),
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
			'copiedLabel' => __( 'Скопійовано', 'xml-prom' ),
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
