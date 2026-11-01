<?php
/**
 * Remote bot-list feed.
 *
 * New bots appear every month. Instead of waiting for a plugin release, Bot Control
 * ships extra bot definitions in data/bots.json (bundled, always used, no network
 * request) and can optionally download more daily from a JSON feed URL that the
 * site owner enters (an agency feed, or a commercial feed that needs an access key).
 * There is no default remote feed: with the feed enabled and the URL left empty,
 * the bundled data/bots.json is used and nothing is downloaded.
 *
 * Feed format:
 * {
 *   "format": 1,
 *   "updated": "2026-09-24",
 *   "bots": {
 *     "newbot": { "name": "NewBot", "vendor": "Acme", "category": "ai_training",
 *                 "patterns": ["NewBot"], "robots": ["NewBot"], "verify": [], "note": "" }
 *   }
 * }
 *
 * Safety rules (a broken or malicious feed must never hurt the site):
 *  - HTTPS only; at most 500 bots.
 *  - Feed bots can only ADD new ids – built-in bots can't be changed or weakened.
 *  - A pattern is rejected if it is shorter than 3 characters or matches any
 *    common real browser User-Agent (so a feed can't turn visitors into "bots").
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remote list.
 */
final class Bot_Control_Remote_List {

	const OPTION = 'botctl_remote_bots';
	const STATUS = 'botctl_remote_status';

	/**
	 * Default feed URL. Empty on purpose: no remote request unless the site owner
	 * enters a feed URL (or a developer sets one with the botctl_default_feed_url filter).
	 */
	const DEFAULT_URL = '';

	/**
	 * Bundled bots cache.
	 *
	 * @var array|null
	 */
	private static $bundled = null;

	/**
	 * Default feed URL (filterable). Empty = use the bundled data/bots.json only.
	 *
	 * @return string
	 */
	public static function default_url() {
		return (string) apply_filters( 'botctl_default_feed_url', self::DEFAULT_URL );
	}

	/**
	 * Bot definitions bundled with the plugin (data/bots.json), validated with the
	 * same rules as a remote feed. No network request.
	 *
	 * @return array
	 */
	public static function bundled() {
		if ( null !== self::$bundled ) {
			return self::$bundled;
		}
		self::$bundled = array();
		$file          = dirname( __DIR__ ) . '/data/bots.json';
		if ( is_readable( $file ) ) {
			$result = self::validate( json_decode( (string) file_get_contents( $file ), true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled file.
			if ( ! is_wp_error( $result ) ) {
				foreach ( $result['bots'] as $id => $bot ) {
					unset( $bot['remote'] );
					$bot['bundled']       = true;
					self::$bundled[ $id ] = $bot;
				}
			}
		}
		return self::$bundled;
	}

	/**
	 * Real browser User-Agents that no feed pattern may match.
	 *
	 * @var string[]
	 */
	const BROWSER_SAMPLES = array(
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15',
		'Mozilla/5.0 (X11; Linux x86_64; rv:142.0) Gecko/20100101 Firefox/142.0',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1',
		'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36',
		'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36',
		'Mozilla/5.0 (iPad; CPU OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/140.0.0.0 Mobile/15E148 Safari/604.1',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 OPR/125.0.0.0',
	);

	/**
	 * Feed bots, already validated (read by the registry on every request).
	 *
	 * @return array
	 */
	public static function bots() {
		$bots = get_option( self::OPTION, array() );
		return is_array( $bots ) ? $bots : array();
	}

	/**
	 * Last update status.
	 *
	 * @return array
	 */
	public static function status() {
		$status = get_option( self::STATUS, array() );
		return is_array( $status ) ? $status : array();
	}

	/**
	 * Download, validate and store the feed.
	 *
	 * @return true|WP_Error
	 */
	public static function refresh() {
		$s   = Bot_Control_Settings::get();
		$url = trim( (string) $s['remote_list_url'] );
		if ( '' === $url ) {
			$url = trim( self::default_url() );
		}

		$status = self::status();
		if ( '' === $url ) {
			// No feed URL: the bundled list is all there is. Nothing is downloaded.
			delete_option( self::OPTION );
			$status['checked']  = time();
			$status['url']      = '';
			$status['ok']       = true;
			$status['error']    = '';
			$status['updated']  = time();
			$status['count']    = 0;
			$status['rejected'] = array();
			update_option( self::STATUS, $status, false );
			return true;
		}

		$result = self::download( $url, (string) $s['remote_list_key'] );
		$status['checked'] = time();
		$status['url']     = $url;

		if ( is_wp_error( $result ) ) {
			$status['ok']    = false;
			$status['error'] = $result->get_error_message();
			update_option( self::STATUS, $status, false );
			Bot_Control_Logger::error( 'RemoteList', 'Feed update failed – keeping previous list', array( 'url' => $url, 'error' => $status['error'] ) );
			return $result;
		}

		update_option( self::OPTION, $result['bots'], true );
		$status['ok']       = true;
		$status['error']    = '';
		$status['updated']  = time();
		$status['count']    = count( $result['bots'] );
		$status['rejected'] = $result['rejected'];
		update_option( self::STATUS, $status, false );

		Bot_Control_Logger::debug( 'RemoteList', 'Feed updated', array( 'url' => $url, 'bots' => $status['count'], 'rejected' => count( $result['rejected'] ) ) );
		return true;
	}

	/**
	 * Download and validate.
	 *
	 * @param string $url URL.
	 * @param string $key Optional access key (sent as Bearer token).
	 * @return array|WP_Error { bots: array, rejected: string[] }
	 */
	public static function download( $url, $key = '' ) {
		if ( 0 !== strpos( $url, 'https://' ) || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'botctl_feed_url', __( 'The feed URL must be a valid https:// address.', 'bot-control' ) );
		}
		$headers = array( 'Accept' => 'application/json' );
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'headers'    => $headers,
				'user-agent' => 'BotControl/' . BOTCTL_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'botctl_feed_http', sprintf( __( 'The feed returned HTTP %d.', 'bot-control' ), $code ) );
		}
		return self::validate( json_decode( wp_remote_retrieve_body( $response ), true ) );
	}

	/**
	 * Validate feed data.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array|WP_Error
	 */
	public static function validate( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['bots'] ) || ! is_array( $data['bots'] ) ) {
			return new WP_Error( 'botctl_feed_format', __( 'The feed is not valid Bot Control JSON.', 'bot-control' ) );
		}

		$builtin    = Bot_Control_Bot_Registry::builtin();
		$categories = Bot_Control_Bot_Registry::category_ids();
		$bots       = array();
		$rejected   = array();

		foreach ( $data['bots'] as $id => $bot ) {
			if ( count( $bots ) >= 500 ) {
				break;
			}
			$id = sanitize_key( (string) $id );
			if ( '' === $id || ! is_array( $bot ) ) {
				continue;
			}
			if ( isset( $builtin[ $id ] ) ) {
				$rejected[] = $id . ' (built-in id)';
				continue;
			}
			$category = isset( $bot['category'] ) ? sanitize_key( $bot['category'] ) : '';
			if ( ! in_array( $category, $categories, true ) ) {
				$rejected[] = $id . ' (unknown category)';
				continue;
			}

			$patterns = array();
			foreach ( isset( $bot['patterns'] ) ? (array) $bot['patterns'] : array() as $pattern ) {
				$pattern = is_string( $pattern ) ? trim( wp_strip_all_tags( $pattern ) ) : '';
				if ( self::safe_pattern( $pattern ) ) {
					$patterns[] = substr( $pattern, 0, 100 );
				} elseif ( '' !== $pattern ) {
					$rejected[] = $id . ' pattern "' . $pattern . '" (unsafe)';
				}
			}
			if ( empty( $patterns ) ) {
				$rejected[] = $id . ' (no safe patterns)';
				continue;
			}

			$robots = array();
			foreach ( isset( $bot['robots'] ) ? (array) $bot['robots'] : array() as $token ) {
				if ( is_string( $token ) && preg_match( '/^[A-Za-z0-9._\- ]{2,60}$/', $token ) ) {
					$robots[] = $token;
				}
			}

			$verify = array();
			foreach ( isset( $bot['verify'] ) ? (array) $bot['verify'] : array() as $domain ) {
				$domain = is_string( $domain ) ? strtolower( trim( $domain ) ) : '';
				if ( preg_match( '/^([a-z0-9-]+\.)+[a-z]{2,}$/', $domain ) && count( $verify ) < 5 ) {
					$verify[] = $domain;
				}
			}

			$bots[ $id ] = array(
				'name'     => isset( $bot['name'] ) ? substr( sanitize_text_field( $bot['name'] ), 0, 60 ) : $id,
				'vendor'   => isset( $bot['vendor'] ) ? substr( sanitize_text_field( $bot['vendor'] ), 0, 60 ) : '',
				'category' => $category,
				'patterns' => array_slice( $patterns, 0, 10 ),
				'robots'   => array_slice( $robots, 0, 10 ),
				'verify'   => $verify,
				'note'     => isset( $bot['note'] ) ? substr( sanitize_text_field( $bot['note'] ), 0, 200 ) : '',
				'remote'   => true,
			);
		}

		return array(
			'bots'     => $bots,
			'rejected' => $rejected,
		);
	}

	/**
	 * A pattern is safe when it is long enough and matches none of the real browser samples.
	 *
	 * @param string $pattern Pattern.
	 * @return bool
	 */
	public static function safe_pattern( $pattern ) {
		if ( strlen( $pattern ) < 3 ) {
			return false;
		}
		foreach ( self::BROWSER_SAMPLES as $ua ) {
			if ( false !== stripos( $ua, $pattern ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Remove stored feed data.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION );
		delete_option( self::STATUS );
	}
}
