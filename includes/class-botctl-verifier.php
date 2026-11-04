<?php
/**
 * Verifies that a request claiming to be e.g. Googlebot really comes from Google,
 * using forward-confirmed reverse DNS (the method Google, Bing, Apple and Yandex document).
 *
 * Results are cached (object cache + database) so DNS is only queried once per IP:
 * verified/failed for 7 days, "unknown" (no PTR record) for 1 hour.
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verifier.
 */
final class Bot_Control_Verifier {

	const VERIFIED = 1;
	const FAILED   = 0;
	const UNKNOWN  = 2;

	/**
	 * Verify an IP for a bot.
	 *
	 * @param string   $ip      Client IP.
	 * @param string   $bot_id  Bot id.
	 * @param string[] $domains Allowed reverse-DNS domains.
	 * @param bool     $use_cache Use cached result.
	 * @return array { status: int, host: string, cached: bool }
	 */
	public static function verify( $ip, $bot_id, array $domains, $use_cache = true ) {
		global $wpdb;

		$cache_key = md5( $ip . '|' . $bot_id );
		$table     = $wpdb->prefix . 'botctl_verify';

		if ( $use_cache ) {
			$cached = wp_cache_get( $cache_key, 'botctl_verify' );
			if ( is_array( $cached ) ) {
				$cached['cached'] = true;
				return $cached;
			}

			$suppress = $wpdb->suppress_errors( true );
			$row      = $wpdb->get_row( $wpdb->prepare( "SELECT status, host, checked FROM {$table} WHERE ip = %s AND bot_id = %s", $ip, $bot_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->suppress_errors( $suppress );

			if ( $row ) {
				$ttl = self::ttl( (int) $row['status'] );
				if ( (int) $row['checked'] + $ttl > time() ) {
					$result = array( 'status' => (int) $row['status'], 'host' => (string) $row['host'] );
					wp_cache_set( $cache_key, $result, 'botctl_verify', 3600 );
					$result['cached'] = true;
					return $result;
				}
			}
		}

		$result = self::check( $ip, $bot_id, $domains );

		Bot_Control_Logger::debug(
			'Verifier',
			'DNS verification performed',
			array(
				'ip'     => $ip,
				'bot'    => $bot_id,
				'status' => $result['status'],
				'host'   => $result['host'],
			)
		);

		$suppress = $wpdb->suppress_errors( true );
		$ok       = $wpdb->replace(
			$table,
			array(
				'ip'      => $ip,
				'bot_id'  => $bot_id,
				'status'  => $result['status'],
				'host'    => substr( $result['host'], 0, 255 ),
				'checked' => time(),
			),
			array( '%s', '%s', '%d', '%s', '%d' )
		);
		$wpdb->suppress_errors( $suppress );
		if ( false === $ok ) {
			Bot_Control_Logger::error( 'Verifier', 'Could not store verification result', array( 'db_error' => $wpdb->last_error ) );
		}

		wp_cache_set( $cache_key, $result, 'botctl_verify', 3600 );
		$result['cached'] = false;
		return $result;
	}

	/**
	 * Cache lifetime per status.
	 *
	 * @param int $status Status.
	 * @return int Seconds.
	 */
	private static function ttl( $status ) {
		return self::UNKNOWN === $status ? HOUR_IN_SECONDS : 7 * DAY_IN_SECONDS;
	}

	/**
	 * Verify without cache: official IP list first, then reverse DNS.
	 *
	 * @param string   $ip      IP.
	 * @param string   $bot_id  Bot id.
	 * @param string[] $domains Reverse-DNS domains (may be empty).
	 * @return array { status, host }
	 */
	public static function check( $ip, $bot_id, array $domains ) {
		if ( Bot_Control_IP_Lists::has( $bot_id ) ) {
			if ( Bot_Control_IP_Lists::contains( $ip, $bot_id ) ) {
				return array( 'status' => self::VERIFIED, 'host' => '(official IP list)' );
			}
			if ( empty( $domains ) ) {
				// Vendor publishes its IPs and has no reverse DNS: not listed = not genuine.
				return array( 'status' => self::FAILED, 'host' => '(not in official IP list)' );
			}
			// Listed vendor with reverse DNS too: the list may lag behind – fall back to DNS.
		}
		if ( empty( $domains ) ) {
			return array( 'status' => self::UNKNOWN, 'host' => '' );
		}
		return self::lookup( $ip, $domains );
	}

	/**
	 * Forward-confirmed reverse DNS lookup.
	 *
	 * @param string   $ip      IP.
	 * @param string[] $domains Allowed domains.
	 * @return array { status, host }
	 */
	public static function lookup( $ip, array $domains ) {
		$host = @gethostbyaddr( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $host || $host === $ip ) {
			// No PTR record (or resolver problem). Don't call it an impostor – treat as unverified.
			return array( 'status' => self::UNKNOWN, 'host' => '' );
		}

		$host = strtolower( rtrim( $host, '.' ) );

		if ( ! self::host_matches( $host, $domains ) ) {
			return array( 'status' => self::FAILED, 'host' => $host );
		}

		// Forward-confirm: the hostname must resolve back to the same IP.
		$ips = array();
		if ( false !== strpos( $ip, ':' ) ) {
			$records = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $records ) ) {
				foreach ( $records as $r ) {
					if ( ! empty( $r['ipv6'] ) ) {
						$ips[] = Bot_Control_IP::normalize( $r['ipv6'] );
					}
				}
			}
		} else {
			$v4 = @gethostbynamel( $host ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $v4 ) ) {
				$ips = $v4;
			}
		}

		if ( empty( $ips ) ) {
			return array( 'status' => self::UNKNOWN, 'host' => $host );
		}

		return array(
			'status' => in_array( Bot_Control_IP::normalize( $ip ), $ips, true ) ? self::VERIFIED : self::FAILED,
			'host'   => $host,
		);
	}

	/**
	 * Does the host belong to one of the domains?
	 *
	 * @param string   $host    Hostname.
	 * @param string[] $domains Domains.
	 * @return bool
	 */
	public static function host_matches( $host, array $domains ) {
		foreach ( $domains as $domain ) {
			$domain = strtolower( $domain );
			if ( $host === $domain ) {
				return true;
			}
			$suffix = '.' . $domain;
			if ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Status label.
	 *
	 * @param int $status Status.
	 * @return string
	 */
	public static function label( $status ) {
		switch ( (int) $status ) {
			case self::VERIFIED:
				return __( 'Verified', 'all-in-crawler-ai-agent-manager' );
			case self::FAILED:
				return __( 'Impostor (not genuine)', 'all-in-crawler-ai-agent-manager' );
			default:
				return __( 'Unverified (no reverse DNS)', 'all-in-crawler-ai-agent-manager' );
		}
	}
}
