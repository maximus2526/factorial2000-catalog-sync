<?php
/**
 * Admin menu registration for Catalog Sync pages.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

function f2000cs_add_admin_menu() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_menu_page(
		__( 'Оновлення XML', 'factorial2000-catalog-sync' ),
		__( 'Синхронізація каталогу', 'factorial2000-catalog-sync' ),
		'manage_options',
		'f2000cs-update',
		'f2000cs_update_page',
		'dashicons-update',
		60
	);

	add_submenu_page(
		'f2000cs-update',
		'Оновлення XML',
		'Оновлення XML',
		'manage_options',
		'f2000cs-update',
		'f2000cs_update_page'
	);

	add_submenu_page(
		'f2000cs-update',
		'Імпорт XML',
		'Імпорт XML',
		'manage_options',
		'f2000cs-import',
		'f2000cs_import_page'
	);

	$export_title = __( 'Налаштування вигрузки', 'factorial2000-catalog-sync' );
	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		$export_title .= ' <span class="f2000cs-pro-badge">Pro</span>';
	}

	add_submenu_page(
		'f2000cs-update',
		__( 'Налаштування вигрузки', 'factorial2000-catalog-sync' ),
		$export_title,
		'manage_options',
		'f2000cs-export',
		'f2000cs_export_page'
	);

	add_submenu_page(
		'f2000cs-update',
		__( 'Документація', 'factorial2000-catalog-sync' ),
		__( 'Документація', 'factorial2000-catalog-sync' ),
		'manage_options',
		'f2000cs-docs',
		'f2000cs_docs_page'
	);
}
add_action( 'admin_menu', 'f2000cs_add_admin_menu' );
