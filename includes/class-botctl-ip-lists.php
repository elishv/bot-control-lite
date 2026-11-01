<?php
/**
 * Official crawler IP lists (Google, Bing, Apple, OpenAI, Perplexity).
 *
 * Vendors publish their crawler IP ranges as JSON in a common format:
 *   { "creationTime": "...", "prefixes": [ { "ipv4Prefix": "1.2.3.0/24" }, { "ipv6Prefix": "2001:db8::/32" } ] }
 *
 * The lists are refreshed daily. They let Bot Control verify bots that have no
 * reverse DNS (OpenAI, Perplexity) and skip the DNS lookup for listed IPs.
 *
 * Safety: a bot's lists are only used while ALL of its lists are fresh (fetched
 * within 7 days). Stale lists never cause a genuine bot to be flagged as an impostor.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * IP lists.
 */
final class Bot_Control_IP_Lists {

	const OPTION  = 'botctl_ip_ranges';
	const MAX_AGE = 7 * DAY_IN_SECONDS;

	/**
	 * Loaded data (per request).
	 *
	 * @var array|null
	 */
	private static $data = null;

	/**
	 * Bot id => list URLs.
	 *
	 * @return array
	 */
	public static function sources() {
		$g = 'https://developers.google.com/static/search/apis/ipranges/';
		$sources = array(
			'googlebot'       => array( $g . 'googlebot.json', $g . 'user-triggered-fetchers.json', $g . 'user-triggered-fetchers-google.json' ),
			'googleother'     => array( $g . 'googlebot.json' ),
			'google_ads'      => array( $g . 'special-crawlers.json' ),
			'bingbot'         => array( 'https://www.bing.com/toolbox/bingbot.json' ),
			'applebot'        => array( 'https://search.developer.apple.com/applebot.json' ),
			'gptbot'          => array( 'https://openai.com/gptbot.json' ),
			'oai_searchbot'   => array( 'https://openai.com/searchbot.json' ),
			'chatgpt_user'    => array( 'https://openai.com/chatgpt-user.json' ),
			'perplexitybot'   => array( 'https://www.perplexity.com/perplexitybot.json' ),
			'perplexity_user' => array( 'https://www.perplexity.com/perplexity-user.json' ),
		);

		/**
		 * Filter the official IP list sources.
		 *
		 * @param array $sources bot_id => string[] URLs.
		 */
		return (array) apply_filters( 'botctl_ip_list_sources', $sources );
	}

	/**
	 * Stored data.
	 *
	 * @return array { urls: array<url, array{cidrs: string[], fetched: int, ok: bool, error: string, checked: int}> }
	 */
	public static function data() {
		if ( null === self::$data ) {
			$data       = get_option( self::OPTION, array() );
			self::$data = is_array( $data ) && isset( $data['urls'] ) && is_array( $data['urls'] ) ? $data : array( 'urls' => array() );
		}
		return self::$data;
	}

	/**
	 * Does this bot have fresh official lists available?
	 *
	 * @param string $bot_id Bot id.
	 * @return bool
	 */
	public static function has( $bot_id ) {
		$settings = Bot_Control_Settings::get();
		if ( empty( $settings['ip_lists_optin'] ) ) {
			return false;
		}
		$sources = self::sources();
		if ( empty( $sources[ $bot_id ] ) ) {
			return false;
		}
		$data = self::data();
		foreach ( $sources[ $bot_id ] as $url ) {
			if ( empty( $data['urls'][ $url ]['fetched'] ) || $data['urls'][ $url ]['fetched'] < time() - self::MAX_AGE || empty( $data['urls'][ $url ]['cidrs'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Is the IP in the bot's official ranges?
	 *
	 * @param string $ip     IP.
	 * @param string $bot_id Bot id.
	 * @return bool
	 */
	public static function contains( $ip, $bot_id ) {
		$sources = self::sources();
		if ( empty( $sources[ $bot_id ] ) ) {
			return false;
		}
		$data = self::data();
		foreach ( $sources[ $bot_id ] as $url ) {
			if ( ! empty( $data['urls'][ $url ]['cidrs'] ) && Bot_Control_IP::in_list( $ip, $data['urls'][ $url ]['cidrs'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fetch all lists (daily cron or "Refresh now").
	 *
	 * @return array Per-URL status.
	 */
	public static function refresh() {
		$data = self::data();
		$urls = array();
		foreach ( self::sources() as $list ) {
			foreach ( $list as $url ) {
				$urls[ $url ] = true;
			}
		}

		foreach ( array_keys( $urls ) as $url ) {
			$entry = isset( $data['urls'][ $url ] ) ? $data['urls'][ $url ] : array( 'cidrs' => array(), 'fetched' => 0 );
			$res   = self::fetch( $url );

			$entry['checked'] = time();
			if ( is_wp_error( $res ) ) {
				$entry['ok']    = false;
				$entry['error'] = $res->get_error_message();
				Bot_Control_Logger::error( 'IPLists', 'List download failed – keeping previous data', array( 'url' => $url, 'error' => $entry['error'] ) );
			} else {
				$entry['ok']      = true;
				$entry['error']   = '';
				$entry['cidrs']   = $res;
				$entry['fetched'] = time();
				Bot_Control_Logger::debug( 'IPLists', 'List updated', array( 'url' => $url, 'prefixes' => count( $res ) ) );
			}
			$data['urls'][ $url ] = $entry;
		}

		// Drop URLs that are no longer used.
		$data['urls'] = array_intersect_key( $data['urls'], $urls );

		update_option( self::OPTION, $data, false );
		self::$data = $data;

		// Re-check IPs that previously failed or had no DNS: they may be listed now.
		global $wpdb;
		$ids = array_keys( self::sources() );
		if ( $ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
			$suppress     = $wpdb->suppress_errors( true );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}botctl_verify WHERE status <> 1 AND bot_id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->suppress_errors( $suppress );
		}

		return $data['urls'];
	}

	/**
	 * Download and parse one list.
	 *
	 * @param string $url URL.
	 * @return string[]|WP_Error CIDRs.
	 */
	public static function fetch( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'BotControl/' . BOTCTL_VERSION, // No site URL: the readme promises no site data is sent to the list publishers.
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'botctl_http', sprintf( 'HTTP %d', $code ) );
		}
		$cidrs = self::parse( wp_remote_retrieve_body( $response ) );
		if ( empty( $cidrs ) ) {
			return new WP_Error( 'botctl_parse', 'No valid prefixes in response' );
		}
		return $cidrs;
	}

	/**
	 * Parse the common prefixes JSON format.
	 *
	 * @param string $json JSON.
	 * @return string[]
	 */
	public static function parse( $json ) {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) || empty( $data['prefixes'] ) || ! is_array( $data['prefixes'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $data['prefixes'] as $prefix ) {
			if ( ! is_array( $prefix ) ) {
				continue;
			}
			foreach ( array( 'ipv4Prefix', 'ipv6Prefix' ) as $key ) {
				if ( ! empty( $prefix[ $key ] ) && is_string( $prefix[ $key ] ) ) {
					$cidr = trim( $prefix[ $key ] );
					if ( Bot_Control_IP::is_valid_entry( $cidr ) ) {
						$out[] = $cidr;
					}
				}
			}
			if ( count( $out ) >= 20000 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Remove stored data.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION );
		self::$data = null;
	}
}
