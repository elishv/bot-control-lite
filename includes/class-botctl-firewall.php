<?php
/**
 * The decision engine. Runs once per request, as early as possible.
 *
 * Order of checks:
 *  1. Skip CLI / cron / excluded paths / the server itself / allow-listed IPs.
 *  2. Block-listed IPs → 403.
 *  3. Classify the User-Agent (custom rules → registry → generic bot signals → human).
 *  4. Bots claiming to be a search engine are DNS-verified; impostors are blocked.
 *  5. Server overloaded? Non-exempt bots get 503 + Retry-After.
 *  6. Apply the policy: allow, limit (429 + Retry-After) or block (403).
 *  7. Humans: optional per-IP rate limit (logged-in users are exempt).
 *
 * Anything unexpected fails OPEN (request allowed) and is written to the error log.
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Firewall.
 */
final class Bot_Control_Firewall {

	/**
	 * Already ran this request?
	 *
	 * @var bool
	 */
	private static $ran = false;

	/**
	 * Last decision (for debugging / tests).
	 *
	 * @var array
	 */
	private static $decision = array();

	/**
	 * Pending deferred human check.
	 *
	 * @var array|null
	 */
	private static $deferred = null;

	/**
	 * Entry point.
	 *
	 * @return void
	 */
	public static function run() {
		if ( self::$ran ) {
			return;
		}
		self::$ran = true;

		try {
			self::evaluate();
		} catch ( \Throwable $e ) {
			Bot_Control_Logger::error(
				'Firewall',
				'Unexpected error – request allowed (fail open)',
				array(
					'error' => $e->getMessage(),
					'file'  => $e->getFile(),
					'line'  => $e->getLine(),
				)
			);
		}
	}

	/**
	 * Last decision.
	 *
	 * @return array
	 */
	public static function decision() {
		return self::$decision;
	}

	/**
	 * Requests we never touch.
	 *
	 * @return bool
	 */
	private static function should_skip() {
		// WP-CLI and cron are not visitor traffic. The test suite sets
		// BOTCTL_TEST_REQUESTS so it can exercise a normal HTTP decision from PHP CLI.
		$cli = 'cli' === PHP_SAPI || ( defined( 'WP_CLI' ) && WP_CLI );
		if ( $cli && ! ( defined( 'BOTCTL_TEST_REQUESTS' ) && BOTCTL_TEST_REQUESTS ) ) {
			return true;
		}
		if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
			return true;
		}
		return '' === Bot_Control_Request::method();
	}

	/**
	 * Is the current URL excluded?
	 *
	 * Each line is matched as a prefix of the request path (relative to the site's
	 * sub-directory, if any). Lines containing "?" are matched anywhere in the URL.
	 *
	 * @param array $s Settings.
	 * @return bool
	 */
	private static function is_excluded_path( array $s ) {
		if ( '' === trim( (string) $s['excluded_paths'] ) ) {
			return false;
		}
		$uri  = Bot_Control_Request::uri( '/' );
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		$home_path = (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH );
		$home_path = rtrim( $home_path, '/' );
		$relative  = ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) ? substr( $path, strlen( $home_path ) ) : $path;

		foreach ( preg_split( '/\r\n|\r|\n/', $s['excluded_paths'] ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( false !== strpos( $line, '?' ) ) {
				if ( false !== stripos( $uri, $line ) ) {
					return true;
				}
				continue;
			}
			if ( 0 === strpos( $path, $line ) || 0 === strpos( $relative, $line ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Evaluate the request.
	 *
	 * @return void
	 */
	private static function evaluate() {
		$s = Bot_Control_Settings::get();

		if ( empty( $s['enabled'] ) || self::should_skip() ) {
			return;
		}

		if ( self::is_excluded_path( $s ) ) {
			self::$decision = array( 'action' => 'skip', 'reason' => 'excluded_path' );
			return;
		}

		$client = Bot_Control_IP::client( $s );
		$ip     = $client['ip'];

		if ( '' === $ip || Bot_Control_IP::is_self( $ip ) ) {
			self::$decision = array( 'action' => 'skip', 'reason' => 'self_or_unknown_ip' );
			return;
		}

		$allow = Bot_Control_IP::parse_list( $s['ip_allowlist'] );
		if ( $allow && Bot_Control_IP::in_list( $ip, $allow ) ) {
			self::$decision = array( 'action' => 'allow', 'reason' => 'ip_allowlist', 'ip' => $ip );
			return;
		}

		$ua    = Bot_Control_Request::user_agent();
		$match = Bot_Control_Detector::detect( $ua, $s );

		$block = Bot_Control_IP::parse_list( $s['ip_blocklist'] );
		if ( $block && Bot_Control_IP::in_list( $ip, $block ) ) {
			self::deny( 403, 'ip_blocked', $match, $ip, $s, 0 );
			return;
		}

		if ( 'human' === $match['type'] ) {
			self::human( $match, $ip, $s );
			return;
		}

		self::bot( $match, $ip, $s );
	}

	/**
	 * Handle a bot request.
	 *
	 * @param array  $match Detector result.
	 * @param string $ip    IP.
	 * @param array  $s     Settings.
	 * @return void
	 */
	private static function bot( array $match, $ip, array $s ) {
		$impostor = false;

		// Anti-spoofing: is "Googlebot" really Google?
		if ( ! empty( $s['verify_bots'] ) && ( ! empty( $match['verify'] ) || ( 'registry' === $match['source'] && Bot_Control_IP_Lists::has( $match['bot_id'] ) ) ) ) {
			$v = Bot_Control_Verifier::verify( $ip, $match['bot_id'], $match['verify'] );

			if ( Bot_Control_Verifier::FAILED === $v['status'] ) {
				$impostor          = true;
				$match['bot_id']   = substr( 'impostor:' . $match['bot_id'], 0, 64 );
				$match['label']    = $match['label'] . ' (impostor)';
				$match['category'] = 'impostor';
			} elseif ( Bot_Control_Verifier::UNKNOWN === $v['status'] ) {
				// No reverse DNS: treat as an ordinary unidentified bot.
				$match['bot_id']   = substr( 'unverified:' . $match['bot_id'], 0, 64 );
				$match['label']    = $match['label'] . ' (unverified)';
				$match['category'] = 'unknown_bot';
				$match['source']   = 'generic';
			}
		}

		if ( $impostor ) {
			if ( 'block' === $s['impostor_policy'] ) {
				self::deny( 403, 'impostor', $match, $ip, $s, 0 );
				return;
			}
			$rule = $s['categories']['unknown_bot'];
			$rule['policy'] = 'limit';
		} else {
			$rule = Bot_Control_Policy::resolve( $match, $s );
		}

		// Server under pressure: shed non-essential bot traffic first.
		if ( 'block' !== $rule['policy'] && ! in_array( $match['category'], (array) $s['shed_exempt'], true ) ) {
			$load = Bot_Control_Load_Monitor::state( $s );
			if ( $load['overloaded'] ) {
				Bot_Control_Logger::debug( 'Firewall', 'Load shedding', array( 'bot' => $match['bot_id'], 'per_core' => $load['per_core'] ) );
				self::deny( 503, 'shed', $match, $ip, $s, 120 );
				return;
			}
		}

		if ( 'block' === $rule['policy'] ) {
			self::deny( 403, 'blocked', $match, $ip, $s, 0 );
			return;
		}

		if ( 'limit' === $rule['policy'] ) {
			$key     = 'bot' === $rule['scope'] ? 'bot|' . $match['bot_id'] : 'bot|' . $match['bot_id'] . '|' . $ip;
			$counter = Bot_Control_Rate_Limiter::hit( $key, (int) $s['window'] );
			if ( $counter['hits'] > (int) $rule['limit'] ) {
				Bot_Control_Logger::debug(
					'Firewall',
					'Rate limit exceeded',
					array(
						'bot'   => $match['bot_id'],
						'ip'    => $ip,
						'hits'  => $counter['hits'],
						'limit' => $rule['limit'],
						'scope' => $rule['scope'],
					)
				);
				self::deny( 429, 'limited', $match, $ip, $s, max( 1, $counter['reset'] - time() ) );
				return;
			}
		}

		self::$decision = array(
			'action'   => 'allow',
			'bot_id'   => $match['bot_id'],
			'category' => $match['category'],
			'policy'   => $rule['policy'],
			'ip'       => $ip,
		);
		Bot_Control_Stats::record( $match, 'allowed' );
		self::debug_header( 'allow', $match );
	}

	/**
	 * Handle a human request.
	 *
	 * @param array  $match Detector result.
	 * @param string $ip    IP.
	 * @param array  $s     Settings.
	 * @return void
	 */
	private static function human( array $match, $ip, array $s ) {
		self::$decision = array( 'action' => 'allow', 'bot_id' => 'human', 'ip' => $ip );

		if ( empty( $s['human_limit_enabled'] ) ) {
			return;
		}

		// A logged-in cookie can't be validated this early; re-check on init (and don't trust a fake cookie).
		if ( self::has_login_cookie() ) {
			self::$deferred = array( 'match' => $match, 'ip' => $ip );
			add_action( 'init', array( __CLASS__, 'deferred_human_check' ), 0 );
			return;
		}

		self::apply_human_limit( $match, $ip, $s );
	}

	/**
	 * Deferred human check for requests carrying a login cookie.
	 *
	 * @return void
	 */
	public static function deferred_human_check() {
		if ( null === self::$deferred || is_user_logged_in() ) {
			return;
		}
		try {
			self::apply_human_limit( self::$deferred['match'], self::$deferred['ip'], Bot_Control_Settings::get() );
		} catch ( \Throwable $e ) {
			Bot_Control_Logger::error( 'Firewall', 'Deferred human check failed – allowed', array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Per-IP human rate limit.
	 *
	 * @param array  $match Detector result.
	 * @param string $ip    IP.
	 * @param array  $s     Settings.
	 * @return void
	 */
	private static function apply_human_limit( array $match, $ip, array $s ) {
		$counter = Bot_Control_Rate_Limiter::hit( 'human|' . $ip, (int) $s['window'] );
		if ( $counter['hits'] > (int) $s['human_limit'] ) {
			Bot_Control_Logger::debug( 'Firewall', 'Human rate limit exceeded', array( 'ip' => $ip, 'hits' => $counter['hits'] ) );
			self::deny( 429, 'limited', $match, $ip, $s, max( 1, $counter['reset'] - time() ) );
		}
	}

	/**
	 * Does the request carry a WordPress login cookie?
	 *
	 * @return bool
	 */
	private static function has_login_cookie() {
		return Bot_Control_Request::has_cookie_prefix( 'wordpress_logged_in_' );
	}

	/**
	 * Deny (or, in monitor mode, only record) a request.
	 *
	 * @param int    $code   HTTP status: 403, 429 or 503.
	 * @param string $action blocked|limited|impostor|shed|ip_blocked.
	 * @param array  $match  Detector result.
	 * @param string $ip     IP.
	 * @param array  $s      Settings.
	 * @param int    $retry  Retry-After seconds (0 = none).
	 * @return void
	 */
	private static function deny( $code, $action, array $match, $ip, array $s, $retry ) {
		$enforced = 'monitor' !== $s['mode'];

		self::$decision = array(
			'action'   => $action,
			'code'     => $code,
			'enforced' => $enforced,
			'bot_id'   => $match['bot_id'],
			'category' => $match['category'],
			'ip'       => $ip,
		);

		Bot_Control_Stats::record( $match, 429 === $code || 503 === $code ? 'limited' : 'blocked' );
		Bot_Control_Logger::event(
			array(
				'ip'       => $ip,
				'bot_id'   => $match['bot_id'],
				'label'    => $match['label'],
				'category' => $match['category'],
				'action'   => $action,
				'enforced' => $enforced ? 1 : 0,
			)
		);
		Bot_Control_Logger::debug( 'Firewall', $enforced ? 'Request denied' : 'Request would be denied (monitor mode)', self::$decision );

		if ( ! $enforced ) {
			self::debug_header( 'monitor-' . $action, $match );
			return;
		}

		/**
		 * Last chance to let a denied request through (return false to allow).
		 *
		 * @param bool  $deny     Whether to deny.
		 * @param array $decision Decision details.
		 */
		if ( ! apply_filters( 'botctl_should_deny', true, self::$decision ) ) {
			return;
		}

		self::send( $code, $action, $retry );
	}

	/**
	 * Send the denial response and stop.
	 *
	 * @param int    $code   Status code.
	 * @param string $action Action.
	 * @param int    $retry  Retry-After.
	 * @return void
	 */
	private static function send( $code, $action, $retry ) {
		$messages = array(
			403 => 'Access denied.',
			429 => 'Too many requests. Please slow down and retry later.',
			503 => 'Service temporarily unavailable. Please retry later.',
		);

		/**
		 * Filter the plain-text body of denial responses.
		 *
		 * @param string $message Message.
		 * @param int    $code    Status code.
		 * @param string $action  Action.
		 */
		$message = (string) apply_filters( 'botctl_denied_message', isset( $messages[ $code ] ) ? $messages[ $code ] : 'Denied.', $code, $action );

		if ( ! headers_sent() ) {
			status_header( $code );
			header( 'Content-Type: text/plain; charset=utf-8' );
			// Never let a page cache or CDN store a denial.
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'X-AllIN-Crawler: ' . $action );
			if ( $retry > 0 ) {
				header( 'Retry-After: ' . (int) $retry );
			}
		}

		echo esc_html( $message );
		exit;
	}

	/**
	 * Add a diagnostic response header when debug mode is on.
	 *
	 * @param string $action Action.
	 * @param array  $match  Detector result.
	 * @return void
	 */
	private static function debug_header( $action, array $match ) {
		if ( ! Bot_Control_Logger::debug_enabled() || headers_sent() ) {
			return;
		}
		header( 'X-AllIN-Crawler: ' . $action . '; bot=' . preg_replace( '/[^a-z0-9_:\-]/i', '', $match['bot_id'] ) . '; category=' . $match['category'] );
	}
}
