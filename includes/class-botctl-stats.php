<?php
/**
 * Daily aggregated statistics per bot (allowed / limited / blocked).
 *
 * One atomic upsert per bot request; human requests are only counted when denied.
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stats.
 */
final class Bot_Control_Stats {

	/**
	 * Count a request.
	 *
	 * @param array  $match   Detector result.
	 * @param string $outcome allowed|limited|blocked.
	 * @return void
	 */
	public static function record( array $match, $outcome ) {
		$s = Bot_Control_Settings::get();
		if ( empty( $s['stats_enabled'] ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'botctl_stats';

		$allowed = 'allowed' === $outcome ? 1 : 0;
		$limited = 'limited' === $outcome ? 1 : 0;
		$blocked = 'blocked' === $outcome ? 1 : 0;

		$suppress = $wpdb->suppress_errors( true );
		$result   = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (day, bot_id, label, category, allowed, limited, blocked) VALUES (%s, %s, %s, %s, %d, %d, %d)
				ON DUPLICATE KEY UPDATE allowed = allowed + %d, limited = limited + %d, blocked = blocked + %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'Y-m-d' ),
				substr( $match['bot_id'], 0, 64 ),
				substr( $match['label'], 0, 100 ),
				substr( $match['category'], 0, 32 ),
				$allowed,
				$limited,
				$blocked,
				$allowed,
				$limited,
				$blocked
			)
		);
		$wpdb->suppress_errors( $suppress );

		if ( false === $result ) {
			Bot_Control_Logger::error( 'Stats', 'Failed to record stats', array( 'db_error' => $wpdb->last_error ) );
		}
	}

	/**
	 * Totals by category since a date.
	 *
	 * @param int $days Days back.
	 * @return array
	 */
	public static function by_category( $days ) {
		global $wpdb;
		$table = $wpdb->prefix . 'botctl_stats';
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT category, SUM(allowed) AS allowed, SUM(limited) AS limited, SUM(blocked) AS blocked FROM {$table} WHERE day >= %s GROUP BY category ORDER BY (SUM(allowed)+SUM(limited)+SUM(blocked)) DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days )
			),
			ARRAY_A
		);
	}

	/**
	 * Top bots since a date.
	 *
	 * @param int $days  Days back.
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function top_bots( $days, $limit = 25 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'botctl_stats';
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_id, MAX(label) AS label, MAX(category) AS category, SUM(allowed) AS allowed, SUM(limited) AS limited, SUM(blocked) AS blocked, SUM(allowed+limited+blocked) AS total FROM {$table} WHERE day >= %s GROUP BY bot_id ORDER BY total DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days ),
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Grand totals since a date (used to gate the optional review notice on real value).
	 *
	 * @param int $days Days back.
	 * @return array{allowed:int,limited:int,blocked:int}
	 */
	public static function totals( $days ) {
		global $wpdb;
		$table = $wpdb->prefix . 'botctl_stats';
		$row   = (array) $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(allowed) AS allowed, SUM(limited) AS limited, SUM(blocked) AS blocked FROM {$table} WHERE day >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days )
			),
			ARRAY_A
		);
		return array(
			'allowed' => (int) ( $row['allowed'] ?? 0 ),
			'limited' => (int) ( $row['limited'] ?? 0 ),
			'blocked' => (int) ( $row['blocked'] ?? 0 ),
		);
	}

	/**
	 * Daily totals.
	 *
	 * @param int $days Days back.
	 * @return array
	 */
	public static function daily( $days ) {
		global $wpdb;
		$table = $wpdb->prefix . 'botctl_stats';
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, SUM(allowed) AS allowed, SUM(limited) AS limited, SUM(blocked) AS blocked FROM {$table} WHERE day >= %s GROUP BY day ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days )
			),
			ARRAY_A
		);
	}

	/**
	 * Start date (site timezone) N-1 days back, so "7 days" includes today.
	 *
	 * @param int $days Days.
	 * @return string Y-m-d.
	 */
	private static function since( $days ) {
		return wp_date( 'Y-m-d', time() - ( max( 1, (int) $days ) - 1 ) * DAY_IN_SECONDS );
	}

	/**
	 * Delete old rows (cron).
	 *
	 * @return void
	 */
	public static function cleanup() {
		global $wpdb;
		$s = Bot_Control_Settings::get();

		$suppress = $wpdb->suppress_errors( true );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}botctl_stats WHERE day < %s", self::since( (int) $s['stats_retention'] + 1 ) ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}botctl_events WHERE ts < %s", wp_date( 'Y-m-d H:i:s', time() - (int) $s['log_retention'] * DAY_IN_SECONDS ) ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}botctl_verify WHERE checked < %d", time() - 8 * DAY_IN_SECONDS ) );
		$wpdb->suppress_errors( $suppress );

		Bot_Control_Logger::debug( 'Stats', 'Cleanup finished' );
	}
}
