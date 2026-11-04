<?php
/**
 * robots.txt management.
 *
 * - Adds "Disallow: /" groups for blocked bots (polite bots stop crawling entirely,
 *   saving the request instead of receiving a 403).
 * - Controls robots-only tokens (Google-Extended, Applebot-Extended).
 * - Optionally adds Crawl-delay for rate-limited bots (honoured by Bing, Yandex and
 *   most SEO tools; Google ignores it).
 *
 * IMPORTANT robots.txt rule: a crawler that finds a group naming it specifically
 * IGNORES the "User-agent: *" group. So every Crawl-delay group we add repeats the
 * rules of the "*" group – otherwise we would accidentally open /wp-admin/ etc.
 *
 * Only works with WordPress' virtual robots.txt (no physical robots.txt file).
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Robots.
 */
final class Bot_Control_Robots {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'filter' ), 999, 2 );
	}

	/**
	 * Is a physical robots.txt file shadowing the virtual one?
	 *
	 * @return bool
	 */
	public static function physical_file_exists() {
		return file_exists( ABSPATH . 'robots.txt' );
	}

	/**
	 * Append our rules.
	 *
	 * @param string $output Robots output.
	 * @param mixed  $public blog_public value.
	 * @return string
	 */
	public static function filter( $output, $public ) {
		$s = Bot_Control_Settings::get();
		if ( empty( $s['enabled'] ) || empty( $s['robots_enabled'] ) ) {
			return $output;
		}
		$rules = self::build( $s, (string) $output );
		if ( '' === $rules ) {
			return $output;
		}
		return rtrim( (string) $output ) . "\n\n" . $rules . "\n";
	}

	/**
	 * Build our robots block.
	 *
	 * @param array  $s        Settings.
	 * @param string $existing Current robots output (to copy the "*" group).
	 * @return string
	 */
	public static function build( array $s, $existing ) {
		$star   = self::star_rules( $existing );
		$groups = array();
		$seen   = array();

		foreach ( Bot_Control_Bot_Registry::all() as $id => $bot ) {
			if ( empty( $bot['robots'] ) ) {
				continue;
			}
			$rule = Bot_Control_Policy::for_bot( $id, $s );

			if ( 'block' === $rule['policy'] ) {
				$lines = array();
				foreach ( $bot['robots'] as $token ) {
					if ( isset( $seen[ strtolower( $token ) ] ) ) {
						continue;
					}
					$seen[ strtolower( $token ) ] = true;
					$lines[] = 'User-agent: ' . $token;
				}
				if ( $lines ) {
					$lines[]  = 'Disallow: /';
					$groups[] = implode( "\n", $lines );
				}
				continue;
			}

			// Google ignores Crawl-delay, so never create a Google-specific group for it.
			if ( 'limit' === $rule['policy'] && ! empty( $s['robots_crawl_delay'] ) && ! $bot['robots_only'] && 'Google' !== $bot['vendor'] ) {
				$delay = min( 60, (int) ceil( (int) $s['window'] / max( 1, (int) $rule['limit'] ) ) );
				// A 1-second delay constrains nothing and only bloats robots.txt.
				if ( $delay < 2 ) {
					continue;
				}
				$lines = array();
				foreach ( $bot['robots'] as $token ) {
					if ( isset( $seen[ strtolower( $token ) ] ) ) {
						continue;
					}
					$seen[ strtolower( $token ) ] = true;
					$lines[] = 'User-agent: ' . $token;
				}
				if ( $lines ) {
					$lines    = array_merge( $lines, $star );
					$lines[]  = 'Crawl-delay: ' . $delay;
					$groups[] = implode( "\n", $lines );
				}
			}
		}

		if ( empty( $groups ) ) {
			return '';
		}

		return "# BEGIN All-IN Crawler & AI Agent Manager\n" . implode( "\n\n", $groups ) . "\n# END All-IN Crawler & AI Agent Manager";
	}

	/**
	 * Extract Allow/Disallow lines of the "User-agent: *" group(s).
	 *
	 * @param string $robots Robots content.
	 * @return string[]
	 */
	public static function star_rules( $robots ) {
		$rules    = array();
		$in_star  = false;
		$prev_ua  = false;

		foreach ( preg_split( '/\r\n|\r|\n/', $robots ) as $line ) {
			$clean = trim( preg_replace( '/#.*$/', '', $line ) );
			if ( '' === $clean ) {
				continue;
			}
			if ( preg_match( '/^user-agent\s*:\s*(.+)$/i', $clean, $m ) ) {
				$is_star = '*' === trim( $m[1] );
				// Consecutive User-agent lines belong to the same group.
				$in_star = $prev_ua ? ( $in_star || $is_star ) : $is_star;
				$prev_ua = true;
				continue;
			}
			$prev_ua = false;
			if ( $in_star && preg_match( '/^(allow|disallow)\s*:/i', $clean ) ) {
				$rules[] = $clean;
			}
		}

		return array_values( array_unique( $rules ) );
	}
}
