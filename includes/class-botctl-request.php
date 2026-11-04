<?php
/**
 * Sanitized access to request data ($_SERVER / $_COOKIE).
 *
 * Every value is unslashed and sanitized the moment it is read, so no other
 * class touches the superglobals directly for these fields.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Request.
 */
final class Bot_Control_Request {

	/**
	 * Requested URI (path + query), sanitized as a URL.
	 *
	 * @param string $fallback Value when the server did not provide one.
	 * @return string
	 */
	public static function uri( $fallback = '' ) {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $fallback;
		}
		$uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		return '' === $uri ? $fallback : substr( $uri, 0, 2048 );
	}

	/**
	 * Path part of the requested URI.
	 *
	 * @param string $fallback Value when the server did not provide one.
	 * @return string
	 */
	public static function path( $fallback = '' ) {
		return (string) wp_parse_url( self::uri( $fallback ), PHP_URL_PATH );
	}

	/**
	 * User-Agent header, sanitized as plain text.
	 *
	 * @return string
	 */
	public static function user_agent() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}
		return substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 );
	}

	/**
	 * HTTP method (upper case, letters only), '' when not an HTTP request.
	 *
	 * @return string
	 */
	public static function method() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
			return '';
		}
		return strtoupper( substr( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ), 0, 8 ) );
	}

	/**
	 * Does the request carry a cookie whose (sanitized) name starts with $prefix?
	 *
	 * Only cookie names are read; values are never used.
	 *
	 * @param string $prefix Lower-case cookie name prefix.
	 * @return bool
	 */
	public static function has_cookie_prefix( $prefix ) {
		foreach ( array_keys( $_COOKIE ) as $name ) {
			if ( 0 === strpos( sanitize_key( (string) $name ), $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}
