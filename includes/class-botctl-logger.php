<?php
/**
 * Debug logging (error_log) and the "denied requests" event log.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logger.
 */
final class Bot_Control_Logger {

	/**
	 * Is debug logging enabled? (setting or BOTCTL_DEBUG constant).
	 *
	 * @return bool
	 */
	public static function debug_enabled() {
		if ( defined( 'BOTCTL_DEBUG' ) && BOTCTL_DEBUG ) {
			return true;
		}
		if ( ! class_exists( 'Bot_Control_Settings' ) ) {
			return false;
		}
		$settings = Bot_Control_Settings::get();
		return ! empty( $settings['debug'] );
	}

	/**
	 * Write a debug line to the PHP error log with a clear, greppable identifier.
	 *
	 * Format: [BotControl][component] message | {"context":"json"}
	 *
	 * @param string $component Component identifier, e.g. "Firewall", "Verifier".
	 * @param string $message   Message.
	 * @param array  $context   Extra context (will be JSON encoded).
	 * @return void
	 */
	public static function debug( $component, $message, array $context = array() ) {
		if ( ! self::debug_enabled() ) {
			return;
		}
		$line = '[BotControl][' . $component . '] ' . $message;
		if ( ! empty( $context ) ) {
			$line .= ' | ' . wp_json_encode( $context );
		}
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Always-on error logging for real failures (DB errors, unexpected exceptions).
	 *
	 * @param string $component Component identifier.
	 * @param string $message   Message.
	 * @param array  $context   Extra context.
	 * @return void
	 */
	public static function error( $component, $message, array $context = array() ) {
		$line = '[BotControl][' . $component . '][ERROR] ' . $message;
		if ( ! empty( $context ) ) {
			$line .= ' | ' . wp_json_encode( $context );
		}
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Record a denied (or would-be denied, in monitor mode) request.
	 *
	 * Logging is throttled per IP + action so a bot hammering the site cannot
	 * flood the log table: at most 3 rows per IP/action per rate-limit window.
	 *
	 * @param array $event {
	 *     @type string $ip       Client IP.
	 *     @type string $bot_id   Bot identifier.
	 *     @type string $label    Human readable bot label.
	 *     @type string $category Category id.
	 *     @type string $action   blocked|limited|impostor|shed|ip_blocked.
	 *     @type int    $enforced 1 if the request was actually denied, 0 in monitor mode.
	 * }
	 * @return void
	 */
	public static function event( array $event ) {
		$settings = Bot_Control_Settings::get();
		if ( empty( $settings['log_enabled'] ) ) {
			return;
		}

		$throttle = Bot_Control_Rate_Limiter::hit( 'log|' . $event['ip'] . '|' . $event['action'], (int) $settings['window'] );
		if ( $throttle['hits'] > 3 ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'botctl_events';

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( substr( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ), 0, 8 ) ) : 'GET';

		$suppress = $wpdb->suppress_errors( true );
		$result   = $wpdb->insert(
			$table,
			array(
				'ts'       => current_time( 'mysql' ),
				'ip'       => substr( (string) $event['ip'], 0, 45 ),
				'bot_id'   => substr( (string) $event['bot_id'], 0, 64 ),
				'label'    => substr( (string) $event['label'], 0, 100 ),
				'category' => substr( (string) $event['category'], 0, 32 ),
				'action'   => substr( (string) $event['action'], 0, 16 ),
				'enforced' => (int) $event['enforced'],
				'method'   => $method,
				'uri'      => substr( (string) $uri, 0, 255 ),
				'ua'       => substr( (string) $ua, 0, 255 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		$wpdb->suppress_errors( $suppress );

		if ( false === $result ) {
			self::error( 'Logger', 'Failed to insert event row', array( 'db_error' => $wpdb->last_error ) );
		}
	}
}
