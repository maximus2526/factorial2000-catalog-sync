<?php

defined( 'ABSPATH' ) || exit;

/**
 * Check whether current admin screen belongs to this plugin.
 */
function f2000cs_is_plugin_admin_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	return $screen && strpos( $screen->id, 'f2000cs-' ) !== false;
}

/**
 * Get current plugin admin page slug.
 */
function f2000cs_get_admin_page_slug() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check used only to decide which assets to enqueue.
	return isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
}

/**
 * Translations for the import admin script.
 */
function f2000cs_get_import_i18n() {
	return array(
		'selectFile'           => __( 'Будь ласка, виберіть XML файл.', 'factorial2000-catalog-sync' ),
		'enterUrl'             => __( 'Будь ласка, вкажіть URL XML файлу.', 'factorial2000-catalog-sync' ),
		'enterSkuPrefix'       => __( 'Будь ласка, введіть SKU Prefix.', 'factorial2000-catalog-sync' ),
		'analyzing'            => __( 'Аналіз XML файлу...', 'factorial2000-catalog-sync' ),
		'analysisDone'         => __( 'Аналіз завершено! Знайдено груп:', 'factorial2000-catalog-sync' ),
		'analysisError'        => __( 'Помилка при аналізі XML.', 'factorial2000-catalog-sync' ),
		'groupId'              => __( 'ID групи:', 'factorial2000-catalog-sync' ),
		'variationsInXml'      => __( 'Варіацій в XML:', 'factorial2000-catalog-sync' ),
		'selectAttributes'     => __( 'Виберіть варіаційні атрибути:', 'factorial2000-catalog-sync' ),
		'attributesHint'       => __( 'Примітка: Можна вибрати тільки атрибути що варіюються (мають різні значення між варіаціями)', 'factorial2000-catalog-sync' ),
		'noAttributes'         => __( 'Немає атрибутів що відрізняються', 'factorial2000-catalog-sync' ),
		'variationsWillCreate' => __( 'Буде створено варіацій (по 1 на offer у XML):', 'factorial2000-catalog-sync' ),
		'variationsWarning'    => __( 'Увага: немає варіативних атрибутів у виборі — група може бути пропущена', 'factorial2000-catalog-sync' ),
		'enterSkuBeforeImport' => __( 'Будь ласка, введіть SKU Prefix перед початком імпорту.', 'factorial2000-catalog-sync' ),
		'importStopped'        => __( 'Імпорт зупинено.', 'factorial2000-catalog-sync' ),
		'productsImported'     => __( 'товарів імпортовано', 'factorial2000-catalog-sync' ),
		'importFinished'       => __( 'Імпорт завершено!', 'factorial2000-catalog-sync' ),
		'errorPrefix'          => __( 'Помилка:', 'factorial2000-catalog-sync' ),
		'importFailed'         => __( 'Сталася помилка під час імпорту.', 'factorial2000-catalog-sync' ),
		'selectAttributeGroup' => __( 'Будь ласка, виберіть хоча б один атрибут для кожної групи товарів.', 'factorial2000-catalog-sync' ),
		'importedLabel'        => __( 'Імпортовано:', 'factorial2000-catalog-sync' ),
		'importFinishedCount'  => __( 'Імпорт завершено! Імпортовано:', 'factorial2000-catalog-sync' ),
		'productsLabel'        => __( 'товарів', 'factorial2000-catalog-sync' ),
		'varyingYes'           => __( 'варіюється', 'factorial2000-catalog-sync' ),
		'varyingNo'            => __( 'не варіюється', 'factorial2000-catalog-sync' ),
		'enterSkuBeforeUpdate' => __( 'Будь ласка, введіть SKU Prefix перед оновленням полів.', 'factorial2000-catalog-sync' ),
		'selectUpdateField'    => __( 'Будь ласка, виберіть хоча б одне поле для оновлення.', 'factorial2000-catalog-sync' ),
		'fieldsProcessed'      => __( 'товарів перевірено', 'factorial2000-catalog-sync' ),
		'updateFieldsFinished' => __( 'Оновлення полів завершено!', 'factorial2000-catalog-sync' ),
		/* translators: 1: updated count, 2: not found count */
		'updateFieldsSummary'  => __( 'Оновлено: %1$d. Не знайдено за SKU: %2$d.', 'factorial2000-catalog-sync' ),
		'updateFieldsStopped'  => __( 'Оновлення полів зупинено.', 'factorial2000-catalog-sync' ),
	);
}

/**
 * Localized config for admin-settings.js (Pro locks + trial countdown).
 *
 * @param bool $is_pro Whether Pro is unlocked.
 * @return array
 */
function f2000cs_get_admin_script_config( $is_pro ) {
	$locked_ids = array();

	if ( ! $is_pro ) {
		// Price adjust lives inside supplier cards (CSS/Pro badge). Lock only settings rows.
		$locked_ids = array(
			'f2000cs_hide_variable_low_instock',
			'f2000cs_variable_low_instock_max',
		);
	}

	return array(
		'isPro'     => (bool) $is_pro,
		'canAdd'    => function_exists( 'f2000cs_can_add_supplier_slot' ) ? (bool) f2000cs_can_add_supplier_slot() : (bool) $is_pro,
		'trialEnds' => function_exists( 'f2000cs_get_trial_ends_at' ) ? (int) f2000cs_get_trial_ends_at() : 0,
		'proTip'    => __( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ),
		'exportTip' => __( 'В Pro', 'factorial2000-catalog-sync' ),
		'lockedIds' => $locked_ids,
		'i18n'      => array(
			/* translators: 1: days, 2: hours, 3: minutes */
			'trialLeft'  => __( 'залишилось %1$d дн. %2$d год. %3$d хв.', 'factorial2000-catalog-sync' ),
			'trialEnded' => __( 'Тріал закінчився. Уже збережені постачальники лишаються; додати нові — у Pro (тріал = повний Pro).', 'factorial2000-catalog-sync' ),
		),
	);
}

/**
 * Enqueue admin styles and scripts for plugin pages.
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Hook callback signature requires the argument.
function f2000cs_enqueue_admin_assets( $_hook_suffix ) {
	$is_plugin_screen = f2000cs_is_plugin_admin_screen();
	$is_pro           = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	$page             = f2000cs_get_admin_page_slug();

	// Export menu soft-lock needs assets on all admin screens for free users.
	$need_settings_assets = $is_plugin_screen || ( ! $is_pro && current_user_can( 'manage_options' ) );

	if ( ! $need_settings_assets ) {
		return;
	}

	wp_enqueue_style(
		'f2000cs-admin-settings',
		F2000CS_URL . 'assets/css/admin-settings.css',
		array(),
		F2000CS_VERSION
	);

	wp_enqueue_script(
		'f2000cs-admin-settings',
		F2000CS_URL . 'assets/js/admin-settings.js',
		array(),
		F2000CS_VERSION,
		true
	);

	wp_localize_script(
		'f2000cs-admin-settings',
		'f2000csAdmin',
		f2000cs_get_admin_script_config( $is_pro )
	);

	if ( ! $is_plugin_screen ) {
		return;
	}

	wp_enqueue_style(
		'f2000cs-admin-support',
		F2000CS_URL . 'assets/css/admin-support.css',
		array(),
		F2000CS_VERSION
	);

	wp_enqueue_script(
		'f2000cs-admin-support',
		F2000CS_URL . 'assets/js/admin-support.js',
		array(),
		F2000CS_VERSION,
		true
	);

	wp_localize_script(
		'f2000cs-admin-support',
		'f2000csSupport',
		array(
			'cardNumber'  => '4874100038712884',
			'copiedLabel' => __( 'Скопійовано', 'factorial2000-catalog-sync' ),
		)
	);

	if ( 'f2000cs-import' === $page ) {
		wp_enqueue_script(
			'f2000cs-admin-import',
			F2000CS_URL . 'assets/js/admin-import.js',
			array( 'jquery' ),
			F2000CS_VERSION,
			true
		);

		wp_localize_script(
			'f2000cs-admin-import',
			'f2000csImport',
			array(
				'i18n' => f2000cs_get_import_i18n(),
			)
		);
	}

	if ( 'f2000cs-export' === $page ) {
		wp_enqueue_script(
			'f2000cs-admin-xml-editor',
			F2000CS_URL . 'assets/js/admin-xml-editor.js',
			array( 'jquery' ),
			F2000CS_VERSION,
			true
		);

		wp_localize_script(
			'f2000cs-admin-xml-editor',
			'f2000csXmlEditor',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'f2000cs_xml_editor' ),
				'i18n'    => array(
					'enterUrl'        => __( 'Вкажіть URL XML файлу або оберіть файл.', 'factorial2000-catalog-sync' ),
					'errorGeneric'    => __( 'Сталася помилка. Спробуйте ще раз.', 'factorial2000-catalog-sync' ),
					'loading'         => __( 'Завантаження…', 'factorial2000-catalog-sync' ),
					'loaded'          => __( 'XML завантажено:', 'factorial2000-catalog-sync' ),
					'products'        => __( 'товарів', 'factorial2000-catalog-sync' ),
					'selectCategory'  => __( 'Оберіть категорії зліва, щоб побачити товари.', 'factorial2000-catalog-sync' ),
					'noProducts'      => __( 'У вибраних категоріях немає товарів за поточними умовами. Спробуйте зняти умови «Лише в наявності» або ціновий фільтр.', 'factorial2000-catalog-sync' ),
					'inStock'         => __( 'В наявності', 'factorial2000-catalog-sync' ),
					'outOfStock'      => __( 'Немає', 'factorial2000-catalog-sync' ),
					'vendor'          => __( 'Виробник', 'factorial2000-catalog-sync' ),
					'sku'             => __( 'SKU', 'factorial2000-catalog-sync' ),
					'selectSomething' => __( 'Оберіть хоча б одну категорію або товар.', 'factorial2000-catalog-sync' ),
					'ready'           => __( 'Готово! Відфільтровано', 'factorial2000-catalog-sync' ),
					'download'        => __( 'Завантажити', 'factorial2000-catalog-sync' ),
					'done'            => __( 'Відфільтрований XML створено.', 'factorial2000-catalog-sync' ),
				),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'f2000cs_enqueue_admin_assets' );
