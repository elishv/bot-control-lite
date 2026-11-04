<?php
/**
 * Client IP detection (proxy / CDN aware) and CIDR matching for IPv4 and IPv6.
 *
 * Security: forwarded headers (X-Forwarded-For, CF-Connecting-IP…) are only trusted
 * when the direct peer (REMOTE_ADDR) is a trusted proxy. Otherwise anyone could
 * send a fake header and bypass rate limits.
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * IP utilities.
 */
final class Bot_Control_IP {

	/**
	 * Cloudflare published ranges (https://www.cloudflare.com/ips/).
	 *
	 * @var string[]
	 */
	const CLOUDFLARE = array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
		'108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
		'162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
		'2a06:98c0::/29', '2c0f:f248::/32',
	);

	/**
	 * Private, loopback, link-local and CGNAT ranges.
	 *
	 * @var string[]
	 */
	const PRIVATE_RANGES = array(
		'10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8', '100.64.0.0/10', '169.254.0.0/16',
		'::1/128', 'fc00::/7', 'fe80::/10',
	);

	/**
	 * Resolved client, cached per request.
	 *
	 * @var array|null
	 */
	private static $client = null;

	/**
	 * Parsed list cache.
	 *
	 * @var array
	 */
	private static $lists = array();

	/**
	 * Get the real client IP.
	 *
	 * @param array $s Settings.
	 * @return array { ip: string, source: string }
	 */
	public static function client( array $s ) {
		if ( null !== self::$client ) {
			return self::$client;
		}

		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? self::normalize( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
		$result = array(
			'ip'     => $remote,
			'source' => 'REMOTE_ADDR',
		);

		$mode         = isset( $s['ip_source'] ) ? $s['ip_source'] : 'auto';
		$trusted_peer = '' !== $remote && self::is_trusted_proxy( $remote, $s );
		$cf_peer      = '' !== $remote && self::in_list( $remote, self::CLOUDFLARE );

		$cf_ip  = self::header_ip( 'HTTP_CF_CONNECTING_IP' );
		$real   = self::header_ip( 'HTTP_X_REAL_IP' );
		$xff_ip = self::xff_ip( $s );

		switch ( $mode ) {
			case 'remote_addr':
				break;

			case 'cloudflare':
				if ( $cf_ip && ( $cf_peer || $trusted_peer ) ) {
					$result = array( 'ip' => $cf_ip, 'source' => 'CF-Connecting-IP' );
				}
				break;

			case 'x_forwarded_for':
				if ( $xff_ip && ( $trusted_peer || $cf_peer ) ) {
					$result = array( 'ip' => $xff_ip, 'source' => 'X-Forwarded-For' );
				}
				break;

			case 'x_real_ip':
				if ( $real && ( $trusted_peer || $cf_peer ) ) {
					$result = array( 'ip' => $real, 'source' => 'X-Real-IP' );
				}
				break;

			default: // auto.
				if ( $cf_ip && $cf_peer ) {
					$result = array( 'ip' => $cf_ip, 'source' => 'CF-Connecting-IP (auto)' );
				} elseif ( $trusted_peer && $cf_ip ) {
					// Cloudflare -> local reverse proxy (nginx/Docker) -> PHP.
					$result = array( 'ip' => $cf_ip, 'source' => 'CF-Connecting-IP via proxy (auto)' );
				} elseif ( $trusted_peer && $xff_ip ) {
					$result = array( 'ip' => $xff_ip, 'source' => 'X-Forwarded-For (auto)' );
				} elseif ( $trusted_peer && $real ) {
					$result = array( 'ip' => $real, 'source' => 'X-Real-IP (auto)' );
				}
				break;
		}

		/**
		 * Filter the detected client IP.
		 *
		 * @param array $result { ip, source }.
		 * @param array $s      Settings.
		 */
		self::$client = (array) apply_filters( 'botctl_client_ip', $result, $s );

		return self::$client;
	}

	/**
	 * Reset the per-request cache (used by tests / admin tools).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$client = null;
	}

	/**
	 * Read a single-IP header.
	 *
	 * @param string $key $_SERVER key.
	 * @return string Empty when missing/invalid.
	 */
	private static function header_ip( $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			return '';
		}
		$ip = self::normalize( trim( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) );
		return self::is_valid_ip( $ip ) ? $ip : '';
	}

	/**
	 * Right-most untrusted IP of the X-Forwarded-For chain (the left side can be forged by the client).
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private static function xff_ip( array $s ) {
		if ( empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return '';
		}
		$chain = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) );
		$valid = array();
		foreach ( $chain as $ip ) {
			$ip = self::normalize( $ip );
			if ( self::is_valid_ip( $ip ) ) {
				$valid[] = $ip;
			}
		}
		if ( empty( $valid ) ) {
			return '';
		}
		for ( $i = count( $valid ) - 1; $i >= 0; $i-- ) {
			if ( ! self::is_trusted_proxy( $valid[ $i ], $s ) && ! self::in_list( $valid[ $i ], self::CLOUDFLARE ) ) {
				return $valid[ $i ];
			}
		}
		return $valid[0];
	}

	/**
	 * Is the IP a proxy we trust to set forwarded headers?
	 *
	 * @param string $ip IP.
	 * @param array  $s  Settings.
	 * @return bool
	 */
	public static function is_trusted_proxy( $ip, array $s ) {
		if ( self::is_private( $ip ) ) {
			return true;
		}
		$list = self::parse_list( isset( $s['trusted_proxies'] ) ? $s['trusted_proxies'] : '' );
		return ! empty( $list ) && self::in_list( $ip, $list );
	}

	/**
	 * Private / loopback / CGNAT?
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_private( $ip ) {
		return self::in_list( $ip, self::PRIVATE_RANGES );
	}

	/**
	 * Is this request coming from the server itself (loopback, WP-Cron spawn, Site Health)?
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_self( $ip ) {
		if ( self::in_list( $ip, array( '127.0.0.0/8', '::1/128' ) ) ) {
			return true;
		}
		$server = isset( $_SERVER['SERVER_ADDR'] ) ? self::normalize( sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) ) : '';
		return '' !== $server && $server === $ip;
	}

	/**
	 * Normalize an IP (compress IPv6, strip IPv4-mapped prefix).
	 *
	 * @param string $ip Raw IP.
	 * @return string
	 */
	public static function normalize( $ip ) {
		$ip = trim( (string) $ip );
		if ( '' === $ip ) {
			return '';
		}
		// Strip port from "1.2.3.4:5678" and brackets from "[::1]:80".
		if ( preg_match( '/^\[([0-9a-f:.]+)\](?::\d+)?$/i', $ip, $m ) ) {
			$ip = $m[1];
		} elseif ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $m ) ) {
			$ip = $m[1];
		}
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $packed ) {
			return $ip;
		}
		$ip = inet_ntop( $packed );
		if ( 0 === stripos( $ip, '::ffff:' ) && false !== strpos( $ip, '.' ) ) {
			$ip = substr( $ip, 7 );
		}
		return $ip;
	}

	/**
	 * Valid IP address?
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_valid_ip( $ip ) {
		return false !== filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Valid IP or CIDR entry?
	 *
	 * @param string $entry Entry.
	 * @return bool
	 */
	public static function is_valid_entry( $entry ) {
		if ( false === strpos( $entry, '/' ) ) {
			return self::is_valid_ip( $entry );
		}
		list( $net, $bits ) = explode( '/', $entry, 2 );
		if ( ! self::is_valid_ip( $net ) || ! ctype_digit( $bits ) ) {
			return false;
		}
		$max = false !== strpos( $net, ':' ) ? 128 : 32;
		return (int) $bits >= 0 && (int) $bits <= $max;
	}

	/**
	 * Parse a textarea list into IP/CIDR entries (supports # comments).
	 *
	 * @param string $text List.
	 * @return string[]
	 */
	public static function parse_list( $text ) {
		$text = (string) $text;
		$key  = md5( $text );
		if ( isset( self::$lists[ $key ] ) ) {
			return self::$lists[ $key ];
		}
		$out = array();
		foreach ( preg_split( '/[\r\n,]+/', $text ) as $line ) {
			$line = trim( preg_replace( '/#.*$/', '', $line ) );
			if ( '' !== $line && self::is_valid_entry( $line ) ) {
				$out[] = $line;
			}
		}
		self::$lists[ $key ] = $out;
		return $out;
	}

	/**
	 * Does the IP match any entry of the list?
	 *
	 * @param string   $ip   IP.
	 * @param string[] $list IP/CIDR entries.
	 * @return bool
	 */
	public static function in_list( $ip, array $list ) {
		foreach ( $list as $entry ) {
			if ( self::matches( $ip, $entry ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Match an IP against a single IP or CIDR (IPv4 + IPv6).
	 *
	 * @param string $ip    IP.
	 * @param string $entry IP or CIDR.
	 * @return bool
	 */
	public static function matches( $ip, $entry ) {
		$ip_bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $ip_bin ) {
			return false;
		}
		if ( false === strpos( $entry, '/' ) ) {
			$entry_bin = @inet_pton( $entry ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false !== $entry_bin && $entry_bin === $ip_bin;
		}
		list( $net, $bits ) = explode( '/', $entry, 2 );
		$net_bin = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $net_bin || strlen( $net_bin ) !== strlen( $ip_bin ) ) {
			return false;
		}
		$bits  = (int) $bits;
		$bytes = intdiv( $bits, 8 );
		$rem   = $bits % 8;
		if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $net_bin, 0, $bytes ) ) {
			return false;
		}
		if ( 0 === $rem ) {
			return true;
		}
		$mask = chr( ( 0xFF << ( 8 - $rem ) ) & 0xFF );
		return ( $ip_bin[ $bytes ] & $mask ) === ( $net_bin[ $bytes ] & $mask );
	}
}
