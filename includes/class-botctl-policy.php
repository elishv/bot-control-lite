<?php
/**
 * Resolves the effective rule (allow / limit / block + limit + scope) for a detected client.
 *
 * Precedence: custom rule > per-bot override > category default.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Policy.
 */
final class Bot_Control_Policy {

	/**
	 * Resolve.
	 *
	 * @param array $match Detector result.
	 * @param array $s     Settings.
	 * @return array { policy, limit, scope, origin }
	 */
	public static function resolve( array $match, array $s ) {
		$cat  = $match['category'];
		$rule = isset( $s['categories'][ $cat ] ) ? $s['categories'][ $cat ] : array( 'policy' => 'limit', 'limit' => 20, 'scope' => 'ip' );

		$resolved = array(
			'policy' => $rule['policy'],
			'limit'  => (int) $rule['limit'],
			'scope'  => $rule['scope'],
			'origin' => 'category',
		);

		if ( 'custom' === $match['source'] && ! empty( $match['custom'] ) ) {
			$row = $match['custom'];
			if ( 'inherit' !== $row['policy'] ) {
				$resolved['policy'] = $row['policy'];
				$resolved['origin'] = 'custom';
			}
			if ( (int) $row['limit'] > 0 ) {
				$resolved['limit']  = (int) $row['limit'];
				$resolved['origin'] = 'custom';
			}
		} elseif ( 'registry' === $match['source'] && isset( $s['bots'][ $match['bot_id'] ] ) ) {
			$over = $s['bots'][ $match['bot_id'] ];
			if ( isset( $over['policy'] ) && 'inherit' !== $over['policy'] ) {
				$resolved['policy'] = $over['policy'];
				$resolved['origin'] = 'bot';
			}
			if ( ! empty( $over['limit'] ) ) {
				$resolved['limit']  = (int) $over['limit'];
				$resolved['origin'] = 'bot';
			}
		}

		$resolved['limit'] = max( 1, $resolved['limit'] );

		/**
		 * Filter the resolved policy for a request.
		 *
		 * @param array $resolved { policy, limit, scope, origin }.
		 * @param array $match    Detector result.
		 * @param array $s        Settings.
		 */
		return (array) apply_filters( 'botctl_policy', $resolved, $match, $s );
	}

	/**
	 * Effective policy for a registry bot (admin display & robots.txt).
	 *
	 * @param string $bot_id Bot id.
	 * @param array  $s      Settings.
	 * @return array
	 */
	public static function for_bot( $bot_id, array $s ) {
		$bot = Bot_Control_Bot_Registry::get( $bot_id );
		if ( ! $bot ) {
			return array( 'policy' => 'allow', 'limit' => 0, 'scope' => 'ip', 'origin' => 'none' );
		}
		return self::resolve(
			array(
				'type'     => 'bot',
				'bot_id'   => $bot_id,
				'label'    => $bot['name'],
				'category' => $bot['category'],
				'source'   => 'registry',
				'verify'   => $bot['verify'],
			),
			$s
		);
	}
}
