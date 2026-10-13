<?php
/**
 * WP-CLI: wp f2000cs marketplace build <feed>
 *
 * Handy for a server cron that regenerates large feeds without WP-Cron limits.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {

	/**
	 * Marketplace feed commands.
	 */
	class Marketplace_CLI {

		/**
		 * Build a marketplace feed from the WooCommerce catalog.
		 *
		 * ## OPTIONS
		 *
		 * <feed>
		 * : Feed id, e.g. "prom".
		 *
		 * ## EXAMPLES
		 *
		 *     wp f2000cs marketplace build prom
		 *
		 * @param array $args Positional arguments.
		 * @return void
		 */
		public function build( $args ) {
			$id = (string) ( $args[0] ?? '' );

			if ( ! function_exists( 'f2000cs_is_pro' ) || ! f2000cs_is_pro() ) {
				\WP_CLI::error( 'Marketplace feeds need an active Pro license or trial.' );
			}

			if ( ! Marketplace_Registry::get( $id ) ) {
				\WP_CLI::error( 'Unknown feed: ' . $id );
			}

			$result = Marketplace_Job::run( $id, 3600, null, true );

			if ( 'done' !== $result['status'] ) {
				\WP_CLI::error( (string) ( $result['message'] ?? 'Build failed.' ) );
			}

			\WP_CLI::success(
				sprintf( '%d offers, %d categories, %d skipped → %s', $result['exported'], $result['categories'], $result['skipped'], $result['url'] )
			);
		}
	}

	\WP_CLI::add_command( 'f2000cs marketplace', Marketplace_CLI::class );
}
