<?php
/**
 * Server load protection ("load shedding").
 *
 * When the 1-minute load average per CPU core crosses the threshold, non-exempt
 * bots receive "503 Service Unavailable" + Retry-After. Search engines treat a
 * short-lived 503 as "come back later" – no ranking or indexing impact.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load monitor.
 */
final class Bot_Control_Load_Monitor {

	const RUNTIME_OPTION = 'botctl_runtime';

	/**
	 * Per-request memo.
	 *
	 * @var array|null
	 */
	private static $state = null;

	/**
	 * Current load state.
	 *
	 * @param array $s Settings.
	 * @return array { supported: bool, load: float, cores: int, per_core: float, overloaded: bool }
	 */
	public static function state( array $s ) {
		if ( null !== self::$state ) {
			return self::$state;
		}

		$state = array(
			'supported'  => false,
			'load'       => 0.0,
			'cores'      => 0,
			'per_core'   => 0.0,
			'overloaded' => false,
		);

		if ( function_exists( 'sys_getloadavg' ) ) {
			$avg   = @sys_getloadavg(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$cores = (int) $s['cpu_cores'] > 0 ? (int) $s['cpu_cores'] : self::detect_cores();
			if ( is_array( $avg ) && $cores > 0 ) {
				$state['supported']  = true;
				$state['load']       = (float) $avg[0];
				$state['cores']      = $cores;
				$state['per_core']   = round( $avg[0] / $cores, 2 );
				$state['overloaded'] = ! empty( $s['load_shedding'] ) && $state['per_core'] >= (float) $s['load_threshold'];
			}
		}

		self::$state = $state;
		return $state;
	}

	/**
	 * Detect CPU cores (cached for a day in an autoloaded option).
	 *
	 * @return int 0 if unknown.
	 */
	public static function detect_cores() {
		$runtime = get_option( self::RUNTIME_OPTION, array() );
		if ( is_array( $runtime ) && isset( $runtime['cores'], $runtime['detected'] ) && $runtime['detected'] > time() - DAY_IN_SECONDS ) {
			return (int) $runtime['cores'];
		}

		$cores = 0;
		if ( @is_readable( '/proc/cpuinfo' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$info = @file_get_contents( '/proc/cpuinfo' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $info ) {
				$cores = (int) preg_match_all( '/^processor\s*:/m', $info );
			}
		}
		if ( $cores < 1 ) {
			$env = getenv( 'NUMBER_OF_PROCESSORS' );
			if ( $env ) {
				$cores = (int) $env;
			}
		}

		$runtime = is_array( $runtime ) ? $runtime : array();
		$runtime['cores']    = $cores;
		$runtime['detected'] = time();
		update_option( self::RUNTIME_OPTION, $runtime, true );

		Bot_Control_Logger::debug( 'LoadMonitor', 'CPU cores detected', array( 'cores' => $cores ) );

		return $cores;
	}
}
