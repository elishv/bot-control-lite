<?php
/**
 * Minimal WordPress stand-in so the plugin classes can run outside WordPress.
 *
 * @package AllInCrawlerAIAgentManager
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'BOTCTL_TEST_REQUESTS', true );

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * In-memory options and a rate-limit table.
 */
final class Botctl_Test_State {
	public static $options = array();
	public static $cron    = array();
}

/**
 * wpdb stand-in. The rate table is counted in memory so limits can be exceeded.
 */
class Botctl_Test_Wpdb {
	public $prefix         = 'wp_';
	public $last_error     = '';
	public $insert_id      = 0;
	public $rows_affected  = 0;
	public $last_args      = array();
	public $rate           = array();

	public function get_charset_collate() {
		return 'utf8mb4_unicode_ci';
	}

	public function suppress_errors( $suppress ) {
		unset( $suppress );
		return false;
	}

	public function prepare( $query, ...$args ) {
		$this->last_args = $args;
		return $query;
	}

	public function query( $query ) {
		$query = (string) $query;
		if ( false !== strpos( $query, 'botctl_rate' ) && isset( $this->last_args[0] ) ) {
			$hash = (string) $this->last_args[0];
			if ( ! isset( $this->rate[ $hash ] ) ) {
				$this->rate[ $hash ]  = 1;
				$this->rows_affected  = 1;
				$this->insert_id      = 1;
			} else {
				$this->rate[ $hash ]++;
				$this->rows_affected = 2;
				$this->insert_id     = $this->rate[ $hash ];
			}
			return 1;
		}
		$this->rows_affected = 1;
		return 1;
	}

	public function insert( $table, $data, $format = null ) {
		unset( $table, $data, $format );
		return 1;
	}

	public function get_results( $query, $output = OBJECT ) {
		unset( $query, $output );
		return array();
	}
}

$GLOBALS['wpdb'] = new Botctl_Test_Wpdb();

function botctl_test_reset_options() {
	Botctl_Test_State::$options = array(
		'home'    => 'https://example.com',
		'siteurl' => 'https://example.com',
	);
	$GLOBALS['wpdb']->rate          = array();
	$GLOBALS['wpdb']->last_error    = '';
	$GLOBALS['botctl_test_dbdelta'] = array();
}

function get_option( $option, $default = false ) {
	return array_key_exists( $option, Botctl_Test_State::$options ) ? Botctl_Test_State::$options[ $option ] : $default;
}

function add_option( $option, $value = '', $deprecated = '', $autoload = null ) {
	unset( $deprecated, $autoload );
	if ( array_key_exists( $option, Botctl_Test_State::$options ) ) {
		return false;
	}
	Botctl_Test_State::$options[ $option ] = $value;
	return true;
}

function update_option( $option, $value, $autoload = null ) {
	unset( $autoload );
	Botctl_Test_State::$options[ $option ] = $value;
	return true;
}

function delete_option( $option ) {
	unset( Botctl_Test_State::$options[ $option ] );
	return true;
}

function wp_next_scheduled( $hook ) {
	return isset( Botctl_Test_State::$cron[ $hook ] ) ? Botctl_Test_State::$cron[ $hook ] : false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	unset( $recurrence );
	Botctl_Test_State::$cron[ $hook ] = $timestamp;
	return true;
}

function wp_schedule_single_event( $timestamp, $hook ) {
	Botctl_Test_State::$cron[ $hook ] = $timestamp;
	return true;
}

function wp_clear_scheduled_hook( $hook ) {
	unset( Botctl_Test_State::$cron[ $hook ] );
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function sanitize_text_field( $str ) {
	$str = wp_strip_all_tags( (string) $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( $str );
}

function sanitize_textarea_field( $str ) {
	return trim( wp_strip_all_tags( (string) $str ) );
}

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function sanitize_email( $email ) {
	$email = trim( (string) $email );
	return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
}

function is_email( $email ) {
	return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
}

function esc_url_raw( $url, $protocols = null ) {
	unset( $protocols );
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( '/' === $url[0] ) {
		return $url;
	}
	$clean = filter_var( $url, FILTER_SANITIZE_URL );
	return is_string( $clean ) ? $clean : '';
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function wp_parse_url( $url, $component = -1 ) {
	$parsed = parse_url( (string) $url );
	if ( ! is_array( $parsed ) ) {
		return -1 === $component ? array() : null;
	}
	if ( -1 === $component ) {
		return $parsed;
	}
	$map = array(
		PHP_URL_SCHEME   => 'scheme',
		PHP_URL_HOST     => 'host',
		PHP_URL_PORT     => 'port',
		PHP_URL_USER     => 'user',
		PHP_URL_PASS     => 'pass',
		PHP_URL_PATH     => 'path',
		PHP_URL_QUERY    => 'query',
		PHP_URL_FRAGMENT => 'fragment',
	);
	$key = isset( $map[ $component ] ) ? $map[ $component ] : '';
	return ( '' !== $key && isset( $parsed[ $key ] ) ) ? $parsed[ $key ] : null;
}

function current_time( $type, $gmt = 0 ) {
	unset( $gmt );
	if ( 'mysql' === $type ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
	if ( 'Y-m-d' === $type ) {
		return gmdate( 'Y-m-d' );
	}
	return time();
}

function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}

function do_action( $hook, ...$args ) {
	unset( $hook, $args );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}

function remove_filter( $hook, $callback, $priority = 10 ) {
	unset( $hook, $callback, $priority );
	return true;
}

function register_activation_hook( $file, $callback ) {
	unset( $file, $callback );
}

function register_deactivation_hook( $file, $callback ) {
	unset( $file, $callback );
}

function is_admin() {
	return false;
}

function wp_using_ext_object_cache() {
	return false;
}

function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	unset( $setting, $code, $message, $type );
}

function post_type_exists( $post_type ) {
	return in_array( $post_type, array( 'post', 'page' ), true );
}

function status_header( $code ) {
	$GLOBALS['botctl_test_status'] = (int) $code;
}

function wp_timezone() {
	return new DateTimeZone( 'UTC' );
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

class WP_Error {
	public $code    = '';
	public $message = '';

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

botctl_test_reset_options();

// No HTTP method yet, so the firewall's first run (at the end of bootstrap) skips the request.
unset( $_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'] );

require dirname( __DIR__ ) . '/includes/bootstrap.php';
