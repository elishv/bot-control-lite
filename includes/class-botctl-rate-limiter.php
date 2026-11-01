<?php
/**
 * Fixed-window rate limiter with three storage backends, fastest first:
 *
 *  1. APCu          – shared memory, no network or DB round-trip.
 *  2. Object cache  – Redis / Memcached through a persistent object-cache drop-in.
 *  3. Database      – one atomic INSERT … ON DUPLICATE KEY UPDATE per request.
 *
 * The limiter FAILS OPEN: if storage is unavailable the request is allowed,
 * so a storage problem can never take the site down.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rate limiter.
 */
final class Bot_Control_Rate_Limiter {

	/**
	 * Resolved backend for this request.
	 *
	 * @var string|null
	 */
	private static $backend = null;

	/**
	 * Which backend will be used.
	 *
	 * @param array|null $s Settings (null = current).
	 * @return string apcu|object_cache|database
	 */
	public static function backend( $s = null ) {
		if ( null === $s && null !== self::$backend ) {
			return self::$backend;
		}
		$settings = null === $s ? Bot_Control_Settings::get() : $s;
		$wanted   = isset( $settings['storage'] ) ? $settings['storage'] : 'auto';

		$apcu = self::apcu_available();
		$oc   = function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();

		if ( 'apcu' === $wanted && $apcu ) {
			$backend = 'apcu';
		} elseif ( 'object_cache' === $wanted && $oc ) {
			$backend = 'object_cache';
		} elseif ( 'database' === $wanted ) {
			$backend = 'database';
		} elseif ( $apcu ) {
			$backend = 'apcu';
		} elseif ( $oc ) {
			$backend = 'object_cache';
		} else {
			$backend = 'database';
		}

		if ( null === $s ) {
			self::$backend = $backend;
		}
		return $backend;
	}

	/**
	 * Is APCu usable in this SAPI?
	 *
	 * @return bool
	 */
	public static function apcu_available() {
		return function_exists( 'apcu_enabled' ) && apcu_enabled();
	}

	/**
	 * Register a hit and return the counter for the current window.
	 *
	 * @param string $key    Counter key (e.g. "bot|gptbot" or "ip|1.2.3.4").
	 * @param int    $window Window length in seconds.
	 * @return array { hits: int, reset: int (unix time the window ends), backend: string }
	 */
	public static function hit( $key, $window ) {
		$window = max( 1, (int) $window );
		$now    = time();
		$start  = (int) ( floor( $now / $window ) * $window );
		$reset  = $start + $window;
		$hash   = md5( $key );

		$backend = self::backend();
		$hits    = 0;

		try {
			switch ( $backend ) {
				case 'apcu':
					$hits = self::hit_apcu( $hash, $start, $window );
					break;
				case 'object_cache':
					$hits = self::hit_object_cache( $hash, $start, $window );
					break;
				default:
					$hits = self::hit_database( $hash, $start );
					break;
			}
		} catch ( \Throwable $e ) {
			Bot_Control_Logger::error( 'RateLimiter', 'Backend failure – failing open', array( 'backend' => $backend, 'error' => $e->getMessage() ) );
			$hits = 0;
		}

		return array(
			'hits'    => (int) $hits,
			'reset'   => $reset,
			'backend' => $backend,
		);
	}

	/**
	 * APCu counter.
	 *
	 * @param string $hash   Key hash.
	 * @param int    $start  Window start.
	 * @param int    $window Window.
	 * @return int
	 */
	private static function hit_apcu( $hash, $start, $window ) {
		$k = 'botctl_rl_' . $hash . '_' . $start;
		apcu_add( $k, 0, $window + 5 );
		$hits = apcu_inc( $k );
		if ( false === $hits ) {
			apcu_store( $k, 1, $window + 5 );
			$hits = 1;
		}
		return (int) $hits;
	}

	/**
	 * Persistent object cache counter.
	 *
	 * @param string $hash   Key hash.
	 * @param int    $start  Window start.
	 * @param int    $window Window.
	 * @return int
	 */
	private static function hit_object_cache( $hash, $start, $window ) {
		$k = $hash . '_' . $start;
		wp_cache_add( $k, 0, 'botctl_rl', $window + 5 );
		$hits = wp_cache_incr( $k, 1, 'botctl_rl' );
		if ( false === $hits ) {
			wp_cache_set( $k, 1, 'botctl_rl', $window + 5 );
			$hits = 1;
		}
		return (int) $hits;
	}

	/**
	 * Database counter: one atomic upsert.
	 *
	 * LAST_INSERT_ID(expr) makes MySQL hand the new counter value back through
	 * insert_id, so no second SELECT is needed.
	 *
	 * @param string $hash  Key hash.
	 * @param int    $start Window start.
	 * @return int
	 */
	private static function hit_database( $hash, $start ) {
		global $wpdb;
		$table = $wpdb->prefix . 'botctl_rate';

		$suppress = $wpdb->suppress_errors( true );
		$result   = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (rkey, win, hits) VALUES (%s, %d, 1) ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(IF(win = %d, hits + 1, 1)), win = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hash,
				$start,
				$start,
				$start
			)
		);
		$wpdb->suppress_errors( $suppress );

		if ( false === $result ) {
			Bot_Control_Logger::error( 'RateLimiter', 'Database counter failed – failing open', array( 'db_error' => $wpdb->last_error ) );
			return 0;
		}

		// 1 = new row inserted, 2 = existing row updated.
		if ( 1 === (int) $wpdb->rows_affected ) {
			return 1;
		}
		return max( 1, (int) $wpdb->insert_id );
	}

	/**
	 * Delete expired DB counters (cron).
	 *
	 * @return void
	 */
	public static function cleanup() {
		global $wpdb;
		$table    = $wpdb->prefix . 'botctl_rate';
		$suppress = $wpdb->suppress_errors( true );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE win < %d", time() - HOUR_IN_SECONDS * 2 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->suppress_errors( $suppress );
	}
}
