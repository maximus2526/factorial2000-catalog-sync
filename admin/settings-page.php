<?php
/**
 * Admin settings bootstrap: loads split admin page modules.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/admin-menu.php';
require_once __DIR__ . '/settings-fields.php';
require_once __DIR__ . '/page-update.php';
require_once __DIR__ . '/page-import.php';
require_once __DIR__ . '/page-export.php';
require_once __DIR__ . '/page-docs.php';
