<?php
/**
 * Minimal WordPress function stubs for offline unit tests.
 *
 * Every stub is guarded with function_exists() so this file can also be
 * loaded inside a real WordPress install without side effects.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Shared in-memory state for the stubs.
 */
final class F2000CS_Test_State {
	/**
	 * Options store.
	 *
	 * @var array<string, mixed>
	 */
	public static $options = array();

	/**
	 * User meta store: [ user_id => [ meta_key => value ] ].
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $user_meta = array();

	/**
	 * Current user ID for get_current_user_id().
	 *
	 * @var int
	 */
	public static $current_user_id = 0;

	/**
	 * Transients store.
	 *
	 * @var array<string, mixed>
	 */
	public static $transients = array();

	/**
	 * Cron event store: list of [ 'time', 'hook', 'args' ].
	 *
	 * @var array<int, array>
	 */
	public static $cron_events = array();

	/**
	 * Hook callbacks registered via add_action()/add_filter().
	 *
	 * @var array<string, array<int, array>>
	 */
	public static $hooks = array();

	/**
	 * Responses served by wp_remote_get() keyed by URL.
	 *
	 * @var array<string, array|WP_Error>
	 */
	public static $http_get_responses = array();

	/**
	 * Queue of responses consumed by wp_remote_post() (retry tests).
	 *
	 * @var array<int, array|WP_Error>
	 */
	public static $http_post_queue = array();

	/**
	 * Bodies posted via wp_remote_post() (captured for assertions).
	 *
	 * @var array<int, array>
	 */
	public static $http_posts = array();

	/**
	 * Return value for get_current_screen().
	 *
	 * @var object|null
	 */
	public static $current_screen;

	/**
	 * Admin state records (settings, menus, enqueues).
	 *
	 * @var array<string, array>
	 */
	public static $menu_pages    = array();
	public static $submenu_pages = array();
	public static $registered_settings = array();
	public static $settings_sections   = array();
	public static $settings_fields     = array();
	public static $enqueued_styles     = array();
	public static $enqueued_scripts    = array();
	public static $localized           = array();

	/**
	 * Optional factory callable for wp_get_image_editor( $path ).
	 *
	 * @var callable|null
	 */
	public static $image_editor_factory;

	/**
	 * Last args passed to wp_insert_term() (for slug assertions).
	 *
	 * @var array<string, mixed>
	 */
	public static $last_insert_term_args = array();

	/**
	 * Auto-incrementing fake term IDs.
	 *
	 * @var int
	 */
	public static $term_id_seq = 1;

	/**
	 * Controllable auth / capability / context flags for behavioral tests.
	 *
	 * @var bool
	 */
	public static $nonce_valid = true;

	/**
	 * @var bool
	 */
	public static $can_manage_options = true;

	/**
	 * @var bool
	 */
	public static $is_user_logged_in = false;

	/**
	 * @var bool
	 */
	public static $is_product = false;

	/**
	 * Queried object ID returned by get_queried_object_id().
	 *
	 * @var int
	 */
	public static $queried_object_id = 0;

	/**
	 * Fake WC products keyed by ID (returned from wc_get_product).
	 *
	 * @var array<int, object>
	 */
	public static $products = array();

	/**
	 * Post meta store keyed as "{post_id}:{meta_key}".
	 *
	 * @var array<string, mixed>
	 */
	public static $post_meta = array();

	/**
	 * Reset all state between tests.
	 *
	 * Hook registrations are kept: they are installed once at bootstrap
	 * and must remain visible to hook-registration assertions.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$options       = array();
		self::$user_meta     = array();
		self::$current_user_id = 0;
		self::$transients    = array();
		self::$cron_events   = array();
		self::$http_get_responses = array();
		self::$http_post_queue    = array();
		self::$http_posts         = array();
		self::$current_screen     = null;
		self::$image_editor_factory = null;
		self::$menu_pages         = array();
		self::$submenu_pages      = array();
		self::$registered_settings = array();
		self::$settings_sections   = array();
		self::$settings_fields     = array();
		self::$enqueued_styles     = array();
		self::$enqueued_scripts    = array();
		self::$localized           = array();
		self::$last_insert_term_args = array();
		self::$term_id_seq = 1;
		self::$nonce_valid         = true;
		self::$can_manage_options  = true;
		self::$is_user_logged_in   = false;
		self::$is_product          = false;
		self::$queried_object_id   = 0;
		self::$products            = array();
		self::$post_meta           = array();

		// Mirror a fresh WP request: no stale globals from previous tests.
		unset( $GLOBALS['product'] );

		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) ) {
			if ( property_exists( $GLOBALS['wpdb'], 'col_queue' ) ) {
				$GLOBALS['wpdb']->col_queue = array();
			}
			if ( property_exists( $GLOBALS['wpdb'], 'var_queue' ) ) {
				$GLOBALS['wpdb']->var_queue = array();
			}
			if ( property_exists( $GLOBALS['wpdb'], 'results_queue' ) ) {
				$GLOBALS['wpdb']->results_queue = array();
			}
			if ( property_exists( $GLOBALS['wpdb'], 'queries' ) ) {
				$GLOBALS['wpdb']->queries = array();
			}
			if ( property_exists( $GLOBALS['wpdb'], 'update_return' ) ) {
				$GLOBALS['wpdb']->update_return = 0;
			}
		}
	}
}

/**
 * Thrown by wp_send_json_* stubs so AJAX handlers can be asserted without dying.
 */
class F2000CS_JsonResponseException extends Exception {
	/**
	 * @var bool
	 */
	public $success;

	/**
	 * @var mixed
	 */
	public $data;

	/**
	 * @param bool  $success Success flag.
	 * @param mixed $data    Response payload.
	 */
	public function __construct( $success, $data = null ) {
		parent::__construct( $success ? 'wp_send_json_success' : 'wp_send_json_error' );
		$this->success = (bool) $success;
		$this->data    = $data;
	}
}

/**
 * Simple WP_Error stand-in.
 */
class WP_Error {
	/**
	 * Error code.
	 *
	 * @var string
	 */
	public $code = '';

	/**
	 * Error message.
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Constructor.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}

if ( ! function_exists( '__return_true' ) ) {
	/**
	 * @return true
	 */
	function __return_true() {
		return true;
	}
}

if ( ! function_exists( '__return_false' ) ) {
	/**
	 * @return false
	 */
	function __return_false() {
		return false;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Arg count.
	 * @return void
	 */
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		add_filter( $hook, $callback, $priority, $args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Arg count.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		F2000CS_Test_State::$hooks[ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		);

		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback (null removes the last added).
	 * @param int      $priority Priority.
	 * @return bool
	 */
	function remove_filter( $hook, $callback = null, $priority = 10 ) {
		if ( empty( F2000CS_Test_State::$hooks[ $hook ] ) ) {
			return false;
		}

		if ( null === $callback ) {
			$entry = array_pop( F2000CS_Test_State::$hooks[ $hook ] );

			return null !== $entry;
		}

		foreach ( F2000CS_Test_State::$hooks[ $hook ] as $key => $entry ) {
			if ( $entry['callback'] === $callback && $entry['priority'] === $priority ) {
				unset( F2000CS_Test_State::$hooks[ $hook ][ $key ] );

				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a filter hook has a specific callback registered.
	 *
	 * @param string $hook     Hook name.
	 * @param string $callback Callback name.
	 * @return bool|int
	 */
	function has_filter( $hook, $callback = false ) {
		if ( empty( F2000CS_Test_State::$hooks[ $hook ] ) ) {
			return false;
		}
		if ( false === $callback ) {
			return count( F2000CS_Test_State::$hooks[ $hook ] ) > 0;
		}
		foreach ( F2000CS_Test_State::$hooks[ $hook ] as $entry ) {
			if ( $entry['callback'] === $callback ) {
				return $entry['priority'];
			}
		}
		return false;
	}
}

if ( ! function_exists( 'do_action' ) ) {	/**
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Arguments.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
		if ( ! empty( F2000CS_Test_State::$hooks[ $hook ] ) ) {
			foreach ( F2000CS_Test_State::$hooks[ $hook ] as $entry ) {
				call_user_func_array( $entry['callback'], array_slice( $args, 0, $entry['args'] ) );
			}
		}
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( ! empty( F2000CS_Test_State::$hooks[ $hook ] ) ) {
			foreach ( F2000CS_Test_State::$hooks[ $hook ] as $entry ) {
				$value = call_user_func_array( $entry['callback'], array_merge( array( $value ), array_slice( $args, 0, max( 0, $entry['args'] - 1 ) ) ) );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $option    Option name.
	 * @param mixed  $default   Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, F2000CS_Test_State::$options ) ? F2000CS_Test_State::$options[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @param bool   $autoload Autoload flag (ignored).
	 * @return bool
	 */
	function update_option( $option, $value, $autoload = null ) {
		F2000CS_Test_State::$options[ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $option Option name.
	 * @return bool
	 */
	function delete_option( $option ) {
		if ( array_key_exists( $option, F2000CS_Test_State::$options ) ) {
			unset( F2000CS_Test_State::$options[ $option ] );

			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * @return int
	 */
	function get_current_user_id() {
		return (int) F2000CS_Test_State::$current_user_id;
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	/**
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed
	 */
	function get_user_meta( $user_id, $key = '', $single = false ) {
		$user_id = (int) $user_id;
		$store   = isset( F2000CS_Test_State::$user_meta[ $user_id ] ) ? F2000CS_Test_State::$user_meta[ $user_id ] : array();

		if ( '' === $key ) {
			return $store;
		}

		if ( ! array_key_exists( $key, $store ) ) {
			return $single ? '' : array();
		}

		return $single ? $store[ $key ] : array( $store[ $key ] );
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	/**
	 * @param int    $user_id    User ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return int|bool
	 */
	function update_user_meta( $user_id, $meta_key, $meta_value, $prev_value = '' ) {
		$user_id = (int) $user_id;
		if ( ! isset( F2000CS_Test_State::$user_meta[ $user_id ] ) ) {
			F2000CS_Test_State::$user_meta[ $user_id ] = array();
		}
		F2000CS_Test_State::$user_meta[ $user_id ][ $meta_key ] = $meta_value;

		return true;
	}
}

if ( ! function_exists( 'delete_user_meta' ) ) {
	/**
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	function delete_user_meta( $user_id, $meta_key, $meta_value = '' ) {
		$user_id = (int) $user_id;
		if ( isset( F2000CS_Test_State::$user_meta[ $user_id ][ $meta_key ] ) ) {
			unset( F2000CS_Test_State::$user_meta[ $user_id ][ $meta_key ] );

			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'delete_metadata' ) ) {
	/**
	 * @param string $meta_type  Meta type (user, post, …).
	 * @param int    $object_id  Object ID (0 + delete_all = all objects).
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Unused.
	 * @param bool   $delete_all Delete for all objects.
	 * @return bool
	 */
	function delete_metadata( $meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false ) {
		if ( 'user' !== $meta_type ) {
			return false;
		}

		if ( $delete_all ) {
			foreach ( F2000CS_Test_State::$user_meta as $uid => $meta ) {
				unset( F2000CS_Test_State::$user_meta[ $uid ][ $meta_key ] );
			}

			return true;
		}

		return delete_user_meta( (int) $object_id, $meta_key );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @param string $key Transient key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		$entry = isset( F2000CS_Test_State::$transients[ $key ] ) ? F2000CS_Test_State::$transients[ $key ] : null;

		if ( null === $entry ) {
			return false;
		}

		if ( $entry['expires'] > 0 && time() >= $entry['expires'] ) {
			unset( F2000CS_Test_State::$transients[ $key ] );

			return false;
		}

		return $entry['value'];
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param string $key      Transient key.
	 * @param mixed  $value    Value.
	 * @param int    $expiration Expiration in seconds.
	 * @return bool
	 */
	function set_transient( $key, $value, $expiration = 0 ) {
		F2000CS_Test_State::$transients[ $key ] = array(
			'value'   => $value,
			'expires' => $expiration > 0 ? time() + $expiration : 0,
		);

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * @param string $key Transient key.
	 * @return bool
	 */
	function delete_transient( $key ) {
		if ( isset( F2000CS_Test_State::$transients[ $key ] ) ) {
			unset( F2000CS_Test_State::$transients[ $key ] );

			return true;
		}

		return false;
	}
}

if ( ! function_exists( '_get_cron_array' ) ) {
	/**
	 * @return array
	 */
	function _get_cron_array() {
		$crons = array();

		foreach ( F2000CS_Test_State::$cron_events as $event ) {
			$crons[ $event['time'] ][ $event['hook'] ][] = array(
				'args' => $event['args'],
			);
		}

		return $crons;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param array  $args Args.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook, $args = array() ) {
		foreach ( F2000CS_Test_State::$cron_events as $event ) {
			if ( $event['hook'] !== $hook ) {
				continue;
			}

			if ( empty( $args ) && ! empty( $event['args'] ) ) {
				continue;
			}

			if ( $args !== $event['args'] ) {
				continue;
			}

			return $event['time'];
		}

		return false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * @param int    $timestamp Timestamp.
	 * @param string $recurrence Recurrence (ignored).
	 * @param string $hook      Hook name.
	 * @param array  $args      Args.
	 * @return bool
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		F2000CS_Test_State::$cron_events[] = array(
			'time' => $timestamp,
			'hook' => $hook,
			'args' => $args,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/**
	 * @param int    $timestamp Timestamp.
	 * @param string $hook      Hook name.
	 * @param array  $args      Args.
	 * @return bool
	 */
	function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
		return wp_schedule_event( $timestamp, 'single', $hook, $args );
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param array  $args Args.
	 * @return int Number of cleared events.
	 */
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		$cleared = 0;

		foreach ( F2000CS_Test_State::$cron_events as $key => $event ) {
			if ( $event['hook'] !== $hook ) {
				continue;
			}

			if ( ! empty( $args ) && $args !== $event['args'] ) {
				continue;
			}

			unset( F2000CS_Test_State::$cron_events[ $key ] );
			++$cleared;
		}

		F2000CS_Test_State::$cron_events = array_values( F2000CS_Test_State::$cron_events );

		return $cleared;
	}
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
	/**
	 * @param int    $timestamp Timestamp.
	 * @param string $hook      Hook name.
	 * @param array  $args      Args.
	 * @return bool
	 */
	function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
		foreach ( F2000CS_Test_State::$cron_events as $key => $event ) {
			if ( $event['hook'] === $hook && $event['time'] === $timestamp && $event['args'] === $args ) {
				unset( F2000CS_Test_State::$cron_events[ $key ] );
				F2000CS_Test_State::$cron_events = array_values( F2000CS_Test_State::$cron_events );

				return true;
			}
		}

		return false;
	}
}

	if ( ! function_exists( 'absint' ) ) {
		/**
		 * @param mixed $number Number.
		 * @return int
		 */
		function absint( $number ) {
			return abs( (int) $number );
		}
	}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param array|object|string $args    Args.
	 * @param array               $defaults Defaults.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( is_string( $args ) ) {
			parse_str( $args, $args );
		}

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * @param string $url  URL.
	 * @param array  $args Args.
	 * @return array|WP_Error
	 */
	function wp_remote_get( $url, $args = array() ) {
		if ( isset( F2000CS_Test_State::$http_get_responses[ $url ] ) ) {
			return F2000CS_Test_State::$http_get_responses[ $url ];
		}

		return new WP_Error( 'http_request_failed', 'No stub response for ' . $url );
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * @param string $url  URL.
	 * @param array  $args Args.
	 * @return array|WP_Error
	 */
	function wp_remote_post( $url, $args = array() ) {
		F2000CS_Test_State::$http_posts[] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( ! empty( F2000CS_Test_State::$http_post_queue ) ) {
			return array_shift( F2000CS_Test_State::$http_post_queue );
		}

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '',
		);
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param array|WP_Error $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( $response instanceof WP_Error ) {
			return 0;
		}

		return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param array|WP_Error $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		if ( $response instanceof WP_Error ) {
			return '';
		}

		return isset( $response['body'] ) ? $response['body'] : '';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Thing.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
	/**
	 * @return bool
	 */
	function wp_cache_flush() {
		return true;
	}
}

if ( ! function_exists( 'wp_tempnam' ) ) {
	/**
	 * @param string $filename Filename.
	 * @return string
	 */
	function wp_tempnam( $filename = '' ) {
		$dir = sys_get_temp_dir();

		return tempnam( $dir, 'f2000cs_' );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * @param string $file File path.
	 * @return bool
	 */
	function wp_delete_file( $file ) {
		if ( file_exists( $file ) ) {
			return unlink( $file );
		}

		return false;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * @param string $single Single.
	 * @param string $plural Plural.
	 * @param int    $count  Count.
	 * @param string $domain Domain.
	 * @return string
	 */
	function _n( $single, $plural, $count, $domain = 'default' ) {
		return 1 === absint( $count ) ? $single : $plural;
	}
}

if ( ! function_exists( '_e' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Domain.
	 * @return void
	 */
	function _e( $text, $domain = 'default' ) {
		unset( $domain );
		echo $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Domain.
	 * @return void
	 */
	function esc_attr_e( $text, $domain = 'default' ) {
		unset( $domain );
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		$url = trim( (string) $url );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		return $url;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'http://example.org/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	/**
	 * @param string $url    URL.
	 * @param string $action Action.
	 * @param string $name   Query arg.
	 * @return string
	 */
	function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
		$sep = ( false === strpos( $url, '?' ) ) ? '?' : '&';

		return $url . $sep . rawurlencode( $name ) . '=test-nonce';
	}
}

if ( ! function_exists( 'wp_hash' ) ) {
	/**
	 * Deterministic stand-in for WP's salted hash.
	 *
	 * @param string $data   Data to hash.
	 * @param string $scheme Hash scheme.
	 * @return string
	 */
	function wp_hash( $data, $scheme = 'auth' ) {
		return hash( 'md5', $data . '|' . $scheme );
	}
}

if ( ! function_exists( 'wp_get_session_token' ) ) {
	/**
	 * @return string
	 */
	function wp_get_session_token() {
		return 'test-session-token';
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * Deterministic stand-in for random_int().
	 *
	 * @param int $min Minimum.
	 * @param int $max Maximum.
	 * @return int
	 */
	function wp_rand( $min = 0, $max = 0 ) {
		return 424242;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * @param string $action Action.
	 * @param string $query_arg Query arg.
	 * @return bool
	 */
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		if ( ! F2000CS_Test_State::$nonce_valid ) {
			throw new F2000CS_JsonResponseException(
				false,
				array( 'message' => 'Invalid nonce' )
			);
		}

		return true;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * @param string|WP_Error $message Message.
	 * @param string          $title   Title.
	 * @param array|int       $args    Args.
	 * @return never
	 */
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' );
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	/**
	 * @return object|null
	 */
	function get_current_screen() {
		return F2000CS_Test_State::$current_screen;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * @param string $format Format.
	 * @param int    $timestamp Timestamp.
	 * @return string
	 */
	function wp_date( $format, $timestamp = null ) {
		return gmdate( $format, null === $timestamp ? time() : $timestamp );
	}
}

if ( ! function_exists( 'wc_clean' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wc_clean( $value ) {
		return is_array( $value ) ? array_map( 'wc_clean', $value ) : sanitize_text_field( (string) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function sanitize_text_field( $text ) {
		return trim( (string) $text );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Lowercases and keeps only a-z0-9, dashes and underscores (WP behavior).
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'wc_delete_product_transients' ) ) {
	/**
	 * @param int $product_id Product ID.
	 * @return void
	 */
	function wc_delete_product_transients( $product_id ) {
		// No-op in tests.
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	/**
	 * @param int $product_id Product ID.
	 * @return object|null
	 */
	function wc_get_product( $product_id ) {
		$id = (int) $product_id;

		return F2000CS_Test_State::$products[ $id ] ?? null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$store_key = (int) $post_id . ':' . (string) $key;
		if ( ! array_key_exists( $store_key, F2000CS_Test_State::$post_meta ) ) {
			return $single ? '' : array();
		}

		$value = F2000CS_Test_State::$post_meta[ $store_key ];

		return $single ? $value : array( $value );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $meta_key, $meta_value ) {
		$store_key = (int) $post_id . ':' . (string) $meta_key;
		F2000CS_Test_State::$post_meta[ $store_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	function delete_post_meta( $post_id, $meta_key ) {
		$store_key = (int) $post_id . ':' . (string) $meta_key;
		unset( F2000CS_Test_State::$post_meta[ $store_key ] );
		return true;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	/**
	 * @return int
	 */
	function get_queried_object_id() {
		return (int) F2000CS_Test_State::$queried_object_id;
	}
}

if ( ! function_exists( 'wp_insert_term' ) ) {
	/**
	 * @param string $term     Term name.
	 * @param string $taxonomy Taxonomy.
	 * @param array  $args     Args.
	 * @return array
	 */
	function wp_insert_term( $term, $taxonomy, $args = array() ) {
		F2000CS_Test_State::$last_insert_term_args = is_array( $args ) ? $args : array();
		$id = F2000CS_Test_State::$term_id_seq++;

		return array(
			'term_id'          => $id,
			'term_taxonomy_id' => $id,
		);
	}
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
	/**
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	function taxonomy_exists( $taxonomy ) {
		return false;
	}
}

if ( ! function_exists( 'register_taxonomy' ) ) {
	/**
	 * @param string       $taxonomy Taxonomy.
	 * @param array|string $object_type Object types.
	 * @param array        $args Args.
	 * @return void
	 */
	function register_taxonomy( $taxonomy, $object_type, $args = array() ) {
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	/**
	 * @param string     $field Field.
	 * @param string|int $value Value.
	 * @param string     $taxonomy Taxonomy.
	 * @return object|false
	 */
	function get_term_by( $field, $value, $taxonomy = '' ) {
		return false;
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	/**
	 * @param int          $object_id Object ID.
	 * @param array|string $terms Terms.
	 * @param string       $taxonomy Taxonomy.
	 * @param bool         $append Append.
	 * @return array
	 */
	function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
		return (array) $terms;
	}
}

/**
 * Fake $wpdb object exposing the query surface used by the plugin.
 */
class F2000CS_Fake_WPDB {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * WooCommerce lookup table name.
	 *
	 * @var string
	 */
	public $wc_product_meta_lookup = 'wp_wc_product_meta_lookup';

	/**
	 * Core table names used by the plugin queries.
	 *
	 * @var string
	 */
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public $options  = 'wp_options';

	/**
	 * Queued get_col() responses, consumed in order.
	 *
	 * @var array<int, array>
	 */
	public $col_queue = array();

	/**
	 * Queued get_results() responses, consumed in order.
	 *
	 * @var array<int, array>
	 */
	public $results_queue = array();

	/**
	 * Recorded SQL passed to query().
	 *
	 * @var array<int, string>
	 */
	public $queries = array();

	/**
	 * Return value for update() calls (0 = no rows, 1 = success).
	 *
	 * @var int
	 */
	public $update_return = 0;

	/**
	 * Last data array passed to update().
	 *
	 * @var array
	 */
	public $last_update_data = array();

	/**
	 * Last where array passed to update().
	 *
	 * @var array
	 */
	public $last_update_where = array();

	/**
	 * Placeholder replacement used by prepare().
	 *
	 * @var array<string, array<int, string|int>>
	 */
	private $placeholder_state = array();

	/**
	 * @param string $query Query.
	 * @param mixed  ...$args Arguments.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$args = array_values( $args );

		$result = '';
		$len    = strlen( $query );
		$pos    = 0;
		$arg    = 0;

		while ( $pos < $len ) {
			$char = $query[ $pos ];

			if ( '%' === $char ) {
				$next = $pos + 1 < $len ? $query[ $pos + 1 ] : '';

				if ( 's' === $next || 'd' === $next || 'f' === $next ) {
					$value = isset( $args[ $arg ] ) ? $args[ $arg ] : '';
					$arg++;

					if ( 'd' === $next ) {
						$value = (int) $value;
					} elseif ( 'f' === $next ) {
						$value = (float) $value;
					} else {
						$value = addslashes( (string) $value );
					}

					$result .= $value;
					$pos    += 2;
					continue;
				}

				if ( 'i' === $next || 'd' === $next || '%' === $next ) {
					$pos += 2;
					continue;
				}
			}

			$result .= $char;
			$pos++;
		}

		return $result;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public function esc_like( $text ) {
		return str_replace( array( '%', '_' ), array( '\\%', '\\_' ), (string) $text );
	}

	/**
	 * Queued get_var() responses, consumed in order.
	 *
	 * @var array<int, mixed>
	 */
	public $var_queue = array();

	/**
	 * @param string $query Query.
	 * @return string|null
	 */
	public function get_var( $query = null ) {
		if ( ! empty( $this->var_queue ) ) {
			return array_shift( $this->var_queue );
		}

		return null;
	}

	/**
	 * @param string $query Query.
	 * @return array
	 */
	public function get_col( $query = null ) {
		if ( ! empty( $this->col_queue ) ) {
			return (array) array_shift( $this->col_queue );
		}

		return array();
	}

	/**
	 * @param string $query Query.
	 * @return array
	 */
	public function get_results( $query = null ) {
		if ( ! empty( $this->results_queue ) ) {
			return (array) array_shift( $this->results_queue );
		}

		return array();
	}

	/**
	 * @param string $query Query.
	 * @return int|bool
	 */
	public function query( $query ) {
		$this->queries[] = (string) $query;

		return 0;
	}

	/**
	 * @param string $table Table.
	 * @param array  $data  Data.
	 * @return int
	 */
	public function insert( $table, $data ) {
		$this->queries[] = 'INSERT INTO ' . $table;
		return 1;
	}

	/**
	 * @param string $table Table.
	 * @param array  $data  Data.
	 * @param array  $where Where.
	 * @return int|false
	 */
	public function update( $table, $data, $where ) {
		$this->last_update_data  = $data;
		$this->last_update_where = $where;
		$this->queries[] = 'UPDATE ' . $table;
		return $this->update_return;
	}
}

if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new F2000CS_Fake_WPDB();
}

/**
 * Minimal WP_Image_Editor stand-in for offline image-processor tests.
 */
class F2000CS_Fake_Image_Editor {
	/**
	 * @var string
	 */
	public $path;

	/**
	 * @var int
	 */
	public $width = 100;

	/**
	 * @var int
	 */
	public $height = 100;

	/**
	 * @var int
	 */
	public $quality = 90;

	/**
	 * @var string|null
	 */
	public $last_mime;

	/**
	 * @param string $path Path.
	 * @param int    $width Width.
	 * @param int    $height Height.
	 */
	public function __construct( $path, $width = 100, $height = 100 ) {
		$this->path   = $path;
		$this->width  = (int) $width;
		$this->height = (int) $height;
	}

	/**
	 * @return array{width:int,height:int}
	 */
	public function get_size() {
		return array(
			'width'  => $this->width,
			'height' => $this->height,
		);
	}

	/**
	 * @param int  $max_w Max width.
	 * @param int  $max_h Max height.
	 * @param bool $crop  Crop.
	 * @return true
	 */
	public function resize( $max_w, $max_h, $crop = false ) {
		$this->width  = (int) $max_w;
		$this->height = (int) $max_h;

		return true;
	}

	/**
	 * @param int $quality Quality.
	 * @return bool
	 */
	public function set_quality( $quality ) {
		$this->quality = (int) $quality;

		return true;
	}

	/**
	 * @param string      $dest Dest path.
	 * @param string|null $mime Mime.
	 * @return array{path:string,file:string,width:int,height:int,mime-type:string}
	 */
	public function save( $dest = null, $mime = null ) {
		$dest = $dest ? $dest : $this->path;
		$this->last_mime = $mime;

		if ( $dest !== $this->path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			copy( $this->path, $dest );
		} else {
			// Touch file to simulate rewrite.
			touch( $this->path );
		}

		$this->path = $dest;

		return array(
			'path'      => $dest,
			'file'      => basename( $dest ),
			'width'     => $this->width,
			'height'    => $this->height,
			'mime-type' => $mime ? $mime : 'image/jpeg',
		);
	}
}

if ( ! function_exists( 'wp_get_image_editor' ) ) {
	/**
	 * @param string $path Path.
	 * @return F2000CS_Fake_Image_Editor|WP_Error
	 */
	function wp_get_image_editor( $path ) {
		if ( is_callable( F2000CS_Test_State::$image_editor_factory ) ) {
			return call_user_func( F2000CS_Test_State::$image_editor_factory, $path );
		}

		if ( ! file_exists( $path ) ) {
			return new WP_Error( 'missing', 'File missing' );
		}

		return new F2000CS_Fake_Image_Editor( $path, 100, 100 );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	/**
	 * @param string $filename Filename.
	 * @return string
	 */
	function sanitize_file_name( $filename ) {
		$filename = preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $filename );

		return is_string( $filename ) && '' !== $filename ? $filename : 'image';
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url       URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

// -----------------------------------------------------------------------
// Admin-page stubs (lightweight, only enough to let render functions run)
// -----------------------------------------------------------------------

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return void
	 */
	function esc_html_e( $text, $domain = 'default' ) {
		unset( $domain );
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_attr__( $text, $domain = 'default' ) {
		unset( $domain );
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * @param string $action Action.
	 * @param string $name   Name.
	 * @param bool   $referer Referer.
	 * @return void
	 */
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="stub_nonce">';
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * @param string $action Action.
	 * @return string
	 */
	function wp_create_nonce( $action = -1 ) {
		return 'stub_nonce_' . md5( (string) $action );
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * @param string $nonce  Nonce.
	 * @param string $action Action.
	 * @return int|false
	 */
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return F2000CS_Test_State::$nonce_valid ? 1 : false;
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	/**
	 * @param string $action Action.
	 * @param string $query_arg Query arg.
	 * @param bool   $stop Stop on failure.
	 * @return int|false
	 */
	function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) {
		if ( F2000CS_Test_State::$nonce_valid ) {
			return 1;
		}

		if ( $stop ) {
			throw new F2000CS_JsonResponseException( false, array( 'message' => 'invalid_nonce' ) );
		}

		return false;
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	/**
	 * @param mixed $data   Data.
	 * @param int   $status Status.
	 * @return never
	 */
	function wp_send_json_error( $data = null, $status = null ) {
		throw new F2000CS_JsonResponseException( false, $data );
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	/**
	 * @param mixed $data   Data.
	 * @param int   $status Status.
	 * @return never
	 */
	function wp_send_json_success( $data = null, $status = null ) {
		throw new F2000CS_JsonResponseException( true, $data );
	}
}

if ( ! function_exists( 'wp_send_json' ) ) {
	/**
	 * @param mixed $response Response.
	 * @param int   $status   Status.
	 * @return never
	 */
	function wp_send_json( $response = null, $status = null ) {
		$success = is_array( $response ) ? ! empty( $response['success'] ) : (bool) $response;
		throw new F2000CS_JsonResponseException( $success, $response );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * @param string $data Data.
	 * @return string
	 */
	function wp_kses_post( $data ) {
		return (string) $data;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Strips all HTML tags (mirrors WP core).
	 *
	 * @param string $text          Text to strip.
	 * @param bool   $remove_breaks Whether to collapse line breaks into spaces.
	 * @return string
	 */
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );

		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}

		return trim( $text );
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $title, $menu_title, $cap, $slug, $callback, $icon = '', $pos = null ) {
		F2000CS_Test_State::$menu_pages[ $slug ] = array(
			'title'      => $title,
			'menu_title' => $menu_title,
			'cap'        => $cap,
		);
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent, $title, $menu_title, $cap, $slug, $callback ) {
		F2000CS_Test_State::$submenu_pages[ $parent ][ $slug ] = array(
			'title'      => $title,
			'menu_title' => $menu_title,
			'cap'        => $cap,
		);
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( $group, $name, $args = array() ) {
		F2000CS_Test_State::$registered_settings[ $group ][] = $name;
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( $id, $title, $callback, $page ) {
		F2000CS_Test_State::$settings_sections[ $page ][ $id ] = array(
			'title' => $title,
		);
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( $id, $title, $callback, $page, $section ) {
		F2000CS_Test_State::$settings_fields[ $page ][ $section ][] = array(
			'id'    => $id,
			'title' => $title,
		);
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true ) {
		echo '<button type="submit" name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( $group ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '">';
	}
}

if ( ! function_exists( 'settings_errors' ) ) {
	/**
	 * @param string $setting Setting group.
	 * @return void
	 */
	function settings_errors( $setting = '' ) {
		// No-op in tests.
	}
}

if ( ! function_exists( 'disabled' ) ) {
	function disabled( $disabled ) {
		echo $disabled ? ' disabled="disabled"' : '';
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked ) {
		echo $checked ? ' checked="checked"' : '';
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected ) {
		echo $selected ? ' selected="selected"' : '';
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		F2000CS_Test_State::$enqueued_styles[ $handle ] = compact( 'src', 'deps', 'ver', 'media' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		F2000CS_Test_State::$enqueued_scripts[ $handle ] = compact( 'src', 'deps', 'ver', 'in_footer' );
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		F2000CS_Test_State::$localized[ $handle ][ $name ] = $data;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * @param string $string Path.
	 * @return string
	 */
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		if ( 'manage_options' === $cap ) {
			return (bool) F2000CS_Test_State::$can_manage_options;
		}

		return false;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * @return bool
	 */
	function is_user_logged_in() {
		return (bool) F2000CS_Test_State::$is_user_logged_in;
	}
}

if ( ! function_exists( 'is_product' ) ) {
	/**
	 * @return bool
	 */
	function is_product() {
		return (bool) F2000CS_Test_State::$is_product;
	}
}

/**
 * Minimal WC_Product stand-in for stock / frontend display tests.
 */
if ( ! class_exists( 'WC_Product', false ) ) {
	class WC_Product {
		/**
		 * @var int
		 */
		public $id;

		/**
		 * @var string
		 */
		public $type = 'simple';

		/**
		 * @var array<int, int>
		 */
		public $children = array();

		/**
		 * @var string
		 */
		public $stock_status = 'outofstock';

		/**
		 * @param int    $id   Product ID.
		 * @param string $type Product type.
		 */
		public function __construct( $id = 0, $type = 'simple' ) {
			$this->id   = (int) $id;
			$this->type = (string) $type;
		}

		/**
		 * @return int
		 */
		public function get_id() {
			return (int) $this->id;
		}

		/**
		 * @param string $type Type.
		 * @return bool
		 */
		public function is_type( $type ) {
			return $this->type === $type;
		}

		/**
		 * @return string
		 */
		public function get_type() {
			return $this->type;
		}

		/**
		 * @return array<int, int>
		 */
		public function get_children() {
			return $this->children;
		}

		/**
		 * @return string
		 */
		public function get_stock_status() {
			return $this->stock_status;
		}

		/**
		 * @param string $status Status.
		 * @return void
		 */
		public function set_stock_status( $status ) {
			$this->stock_status = (string) $status;
		}

		/**
		 * @return void
		 */
		public function save() {
			// No-op in tests.
		}
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, $decimals );
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, $length );
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'basedir' => sys_get_temp_dir() . '/wp-uploads',
			'baseurl' => 'http://example.org/wp-content/uploads',
		);
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return mkdir( $dir, 0777, true );
		}

		return true;
	}
}

if ( ! function_exists( 'wp_check_filetype' ) ) {
	function wp_check_filetype( $filename, $mimes = null ) {
		$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );

		return array( 'ext' => $ext, 'type' => '' );
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return isset( $_POST['action'] );
	}
}

if ( ! function_exists( 'map_deep' ) ) {
	function map_deep( $value, $callback ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				$value[ $index ] = map_deep( $item, $callback );
			}
		} elseif ( is_object( $value ) ) {
			$object_vars = get_object_vars( $value );
			foreach ( $object_vars as $property_name => $property_value ) {
				$value->$property_name = map_deep( $property_value, $callback );
			}
		} else {
			$value = call_user_func( $callback, $value );
		}

		return $value;
	}
}

if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
	function get_woocommerce_currency_symbol( $currency = '' ) {
		return '';
	}
}

if ( ! function_exists( 'wp_filesystem' ) ) {
	function wp_filesystem() {
		return true;
	}
}

if ( ! function_exists( 'download_url' ) ) {
	/**
	 * @param string $url     URL.
	 * @param int    $timeout Timeout.
	 * @return string|WP_Error
	 */
	function download_url( $url, $timeout = 300 ) {
		// In tests, treat a registered HTTP response with code 200 as success.
		$resp = F2000CS_Test_State::$http_get_responses[ $url ] ?? null;
		if ( $resp && isset( $resp['response']['code'] ) && 200 === $resp['response']['code'] ) {
			$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_dl_' );
			file_put_contents( $tmp, $resp['body'] ?? '' );
			return $tmp;
		}
		return new WP_Error( 'download_failed', 'Download failed' );
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	/**
	 * @return string
	 */
	function get_woocommerce_currency() {
		return 'UAH';
	}
}
