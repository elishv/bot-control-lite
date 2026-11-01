<?php
/**
 * User-Agent classification.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detector.
 */
final class Bot_Control_Detector {

	/**
	 * Generic "this looks like a bot" signals for bots missing from the registry.
	 */
	const GENERIC = '~(?:bot\b|bot[/_;-]|crawl|spider|scraper|slurp|archiver|fetcher|headless|\+https?://)~i';

	/**
	 * Known human-browser false positives for the generic pattern (e.g. "CUBOT" phones).
	 */
	const GENERIC_EXCLUDE = '~(?:cubot)~i';

	/**
	 * Classify a User-Agent.
	 *
	 * @param string $ua User-Agent.
	 * @param array  $s  Settings.
	 * @return array {
	 *     @type string $type     bot|human.
	 *     @type string $bot_id   Registry id, custom_<id>, unknown:<token>, no_ua or human.
	 *     @type string $label    Display name.
	 *     @type string $category Category id (human for humans).
	 *     @type string $source   registry|custom|generic|empty|human.
	 *     @type array  $verify   Reverse-DNS domains when verification applies.
	 * }
	 */
	public static function detect( $ua, array $s ) {
		$ua = trim( (string) $ua );

		if ( '' === $ua ) {
			return self::result( 'bot', 'no_ua', 'Empty User-Agent', 'no_ua', 'empty' );
		}

		// 1. Custom rules first – the site owner always has the last word.
		foreach ( $s['custom_bots'] as $row ) {
			if ( empty( $row['pattern'] ) ) {
				continue;
			}
			$hit = ( 'regex' === $row['match'] )
				? 1 === @preg_match( Bot_Control_Settings::regex( $row['pattern'] ), $ua ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				: false !== stripos( $ua, $row['pattern'] );
			if ( $hit ) {
				$res           = self::result( 'bot', 'custom_' . $row['id'], $row['name'], $row['category'], 'custom' );
				$res['custom'] = $row;
				return $res;
			}
		}

		// 2. Built-in registry.
		foreach ( Bot_Control_Bot_Registry::all() as $id => $bot ) {
			if ( $bot['robots_only'] ) {
				continue;
			}
			foreach ( $bot['patterns'] as $pattern ) {
				if ( false !== stripos( $ua, $pattern ) ) {
					$res           = self::result( 'bot', $id, $bot['name'], $bot['category'], 'registry' );
					$res['verify'] = $bot['verify'];
					return $res;
				}
			}
		}

		// 3. Generic bot signals.
		if ( preg_match( self::GENERIC, $ua ) && ! preg_match( self::GENERIC_EXCLUDE, $ua ) ) {
			$token = self::bot_token( $ua );
			return self::result( 'bot', 'unknown:' . strtolower( $token ), $token, 'unknown_bot', 'generic' );
		}

		return self::result( 'human', 'human', 'Human visitor', 'human', 'human' );
	}

	/**
	 * Extract a product token (e.g. "NewAIBot") from an unknown bot UA, for statistics.
	 *
	 * @param string $ua User-Agent.
	 * @return string
	 */
	public static function bot_token( $ua ) {
		if ( preg_match( '~([A-Za-z0-9._-]*(?:bot|crawler|spider|scraper|fetcher)[A-Za-z0-9._-]*)~i', $ua, $m ) ) {
			return substr( $m[1], 0, 40 );
		}
		if ( preg_match( '~^([A-Za-z0-9._-]{2,40})~', $ua, $m ) ) {
			return $m[1];
		}
		return 'unknown';
	}

	/**
	 * Build a result array.
	 *
	 * @param string $type     Type.
	 * @param string $bot_id   Bot id.
	 * @param string $label    Label.
	 * @param string $category Category.
	 * @param string $source   Source.
	 * @return array
	 */
	private static function result( $type, $bot_id, $label, $category, $source ) {
		return array(
			'type'     => $type,
			'bot_id'   => substr( $bot_id, 0, 64 ),
			'label'    => $label,
			'category' => $category,
			'source'   => $source,
			'verify'   => array(),
		);
	}
}
