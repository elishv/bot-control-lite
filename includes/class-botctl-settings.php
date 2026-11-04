<?php
/**
 * Settings storage, defaults, presets and sanitization.
 *
 * All settings live in one autoloaded option so reading them on every request
 * costs no extra database query.
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
final class Bot_Control_Settings {

	const OPTION = 'botctl_settings';

	/**
	 * Request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default category rules.
	 *
	 * policy: allow | limit | block. limit: requests per window. scope: ip (per IP) | bot (all IPs of that bot combined).
	 *
	 * @return array
	 */
	public static function default_categories() {
		return array(
			'search_engine' => array( 'policy' => 'allow', 'limit' => 300, 'scope' => 'ip' ),
			'ai_search'     => array( 'policy' => 'limit', 'limit' => 120, 'scope' => 'ip' ),
			'ai_training'   => array( 'policy' => 'limit', 'limit' => 20, 'scope' => 'bot' ),
			'seo_tool'      => array( 'policy' => 'limit', 'limit' => 20, 'scope' => 'bot' ),
			'social'        => array( 'policy' => 'allow', 'limit' => 120, 'scope' => 'ip' ),
			'monitoring'    => array( 'policy' => 'allow', 'limit' => 60, 'scope' => 'ip' ),
			'feed_reader'   => array( 'policy' => 'limit', 'limit' => 30, 'scope' => 'ip' ),
			'archiver'      => array( 'policy' => 'limit', 'limit' => 10, 'scope' => 'bot' ),
			'scripts'       => array( 'policy' => 'limit', 'limit' => 30, 'scope' => 'ip' ),
			'bad_bot'       => array( 'policy' => 'block', 'limit' => 1, 'scope' => 'ip' ),
			'unknown_bot'   => array( 'policy' => 'limit', 'limit' => 20, 'scope' => 'ip' ),
			'no_ua'         => array( 'policy' => 'limit', 'limit' => 10, 'scope' => 'ip' ),
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// General.
			'enabled'               => 1,
			'mode'                  => 'enforce', // enforce | monitor.
			'window'                => 60,        // Rate-limit window in seconds.

			// Bot rules.
			'categories'            => self::default_categories(),
			'bots'                  => array(),   // bot_id => array( policy => inherit|allow|limit|block, limit => int ).
			'custom_bots'           => array(),   // list of array( id, name, pattern, match, category, policy, limit ).

			// Verification (anti-spoofing).
			'verify_bots'           => 1,
			'impostor_policy'       => 'block',   // block | limit.

			// Human visitors.
			'human_limit_enabled'   => 0,
			'human_limit'           => 120,

			// Server load protection.
			'load_shedding'         => 1,
			'load_threshold'        => 2.0,       // 1-minute load average per CPU core.
			'cpu_cores'             => 0,         // 0 = auto-detect.
			'shed_exempt'           => array( 'search_engine', 'social', 'monitoring' ),

			// Lists.
			'ip_allowlist'          => '',
			'ip_blocklist'          => '',
			'excluded_paths'        => "/wp-cron.php\n/wc-api/",

			// robots.txt.
			'robots_enabled'        => 1,
			'robots_crawl_delay'    => 0,

			// Advanced.
			'ip_source'             => 'auto',    // auto | remote_addr | cloudflare | x_forwarded_for | x_real_ip.
			'trusted_proxies'       => '',
			'storage'               => 'auto',    // auto | apcu | object_cache | database.
			'log_enabled'           => 1,
			'stats_enabled'         => 1,
			'log_retention'         => 14,        // Days.
			'stats_retention'       => 90,        // Days.
			'debug'                 => 0,
			'keep_data'             => 0,         // Keep data when the plugin is uninstalled.

			// Official IP lists & remote bot feed.
			// Off by default: nothing is downloaded until the site owner opts in on the Bot Lists tab.
			// A new key on purpose, so a value stored by an older build (where this was on by default)
			// never switches the downloads on by itself.
			'ip_lists_optin'        => 0,
			'remote_list_enabled'   => 0,
			'remote_list_url'       => '',        // Empty = bundled data/bots.json only (no download).
			'remote_list_key'       => '',        // Optional access key (Bearer token) for private/commercial feeds.

			// Web-server rules.
			'server_rules_auto'     => 0,         // Keep .htaccess in sync automatically.
			'server_rules_ips'      => 0,         // Include the IP block list in server rules.

			// Email reports.
			'report_enabled'        => 0,
			'report_frequency'      => 'weekly',  // weekly | monthly.
			'report_recipients'     => '',        // Empty = site admin email.
			'report_brand'          => '',        // White-label name (agency). Empty = site name.

			// llms.txt.
			'llms_enabled'          => 0,
			'llms_summary'          => '',
			'llms_details'          => '',
			'llms_post_types'       => array( 'page', 'post' ),
			'llms_limit'            => 25,
			'llms_extra_title'      => '',
			'llms_extra'            => '',
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored   = get_option( self::OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = self::defaults();
		$settings = array_merge( $defaults, $stored );

		// Categories: merge each category so new categories/keys get defaults.
		$categories = array();
		foreach ( $defaults['categories'] as $cat => $rule ) {
			$saved              = ( isset( $stored['categories'][ $cat ] ) && is_array( $stored['categories'][ $cat ] ) ) ? $stored['categories'][ $cat ] : array();
			$categories[ $cat ] = array_merge( $rule, $saved );
		}
		$settings['categories'] = $categories;

		foreach ( array( 'bots', 'custom_bots', 'shed_exempt', 'llms_post_types' ) as $key ) {
			if ( ! is_array( $settings[ $key ] ) ) {
				$settings[ $key ] = $defaults[ $key ];
			}
		}

		self::$cache = $settings;
		return self::$cache;
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Full settings array.
	 * @return void
	 */
	public static function update( array $settings ) {
		$old = self::get();
		update_option( self::OPTION, $settings, true );
		self::$cache = null;
		if ( class_exists( 'Bot_Control_Bot_Registry' ) ) {
			Bot_Control_Bot_Registry::reset();
		}
		Bot_Control_Logger::debug( 'Settings', 'Settings saved' );

		/**
		 * Fires after the settings were saved (server rules sync, report schedule, llms.txt cache…).
		 *
		 * @param array $settings New settings.
		 * @param array $old      Previous settings.
		 */
		do_action( 'botctl_settings_updated', self::get(), $old );
	}

	/**
	 * Restore defaults.
	 *
	 * @return void
	 */
	public static function reset() {
		self::update( self::defaults() );
	}

	/**
	 * Presets: one-click category policies.
	 *
	 * @return array
	 */
	public static function presets() {
		$base = self::default_categories();

		$ai_visibility                = $base;
		$ai_visibility['ai_search']   = array( 'policy' => 'allow', 'limit' => 120, 'scope' => 'ip' );
		$ai_visibility['ai_training'] = array( 'policy' => 'limit', 'limit' => 60, 'scope' => 'bot' );

		$protect                = $base;
		$protect['ai_search']   = array( 'policy' => 'limit', 'limit' => 120, 'scope' => 'ip' );
		$protect['ai_training'] = array( 'policy' => 'block', 'limit' => 1, 'scope' => 'bot' );
		$protect['seo_tool']    = array( 'policy' => 'limit', 'limit' => 10, 'scope' => 'bot' );
		$protect['scripts']     = array( 'policy' => 'limit', 'limit' => 20, 'scope' => 'ip' );

		$lockdown                = $base;
		$lockdown['ai_search']   = array( 'policy' => 'limit', 'limit' => 30, 'scope' => 'ip' );
		$lockdown['ai_training'] = array( 'policy' => 'block', 'limit' => 1, 'scope' => 'bot' );
		$lockdown['seo_tool']    = array( 'policy' => 'block', 'limit' => 1, 'scope' => 'bot' );
		$lockdown['feed_reader'] = array( 'policy' => 'limit', 'limit' => 10, 'scope' => 'ip' );
		$lockdown['archiver']    = array( 'policy' => 'block', 'limit' => 1, 'scope' => 'bot' );
		$lockdown['scripts']     = array( 'policy' => 'block', 'limit' => 1, 'scope' => 'ip' );
		$lockdown['unknown_bot'] = array( 'policy' => 'block', 'limit' => 1, 'scope' => 'ip' );
		$lockdown['no_ua']       = array( 'policy' => 'block', 'limit' => 1, 'scope' => 'ip' );

		return array(
			'balanced'      => array(
				'label'       => __( 'Balanced (recommended)', 'all-in-crawler-ai-agent-manager' ),
				'description' => __( 'Search engines and social previews allowed, AI assistants lightly limited, training crawlers, SEO tools and scripts throttled, scanners blocked.', 'all-in-crawler-ai-agent-manager' ),
				'categories'  => $base,
			),
			'ai_visibility' => array(
				'label'       => __( 'Maximum AI visibility', 'all-in-crawler-ai-agent-manager' ),
				'description' => __( 'For publishers who want to be cited by ChatGPT, Claude, Perplexity & co. AI assistants fully allowed, training crawlers only gently limited.', 'all-in-crawler-ai-agent-manager' ),
				'categories'  => $ai_visibility,
			),
			'protect'       => array(
				'label'       => __( 'Protect content from AI training', 'all-in-crawler-ai-agent-manager' ),
				'description' => __( 'Blocks AI training crawlers while staying visible in AI search answers and classic search.', 'all-in-crawler-ai-agent-manager' ),
				'categories'  => $protect,
			),
			'lockdown'      => array(
				'label'       => __( 'Lockdown (under attack)', 'all-in-crawler-ai-agent-manager' ),
				'description' => __( 'Only verified search engines, social previews and monitoring get through freely. Use temporarily while the site is being hammered.', 'all-in-crawler-ai-agent-manager' ),
				'categories'  => $lockdown,
			),
		);
	}

	/**
	 * Sanitize a policy value.
	 *
	 * @param mixed $value   Raw value.
	 * @param bool  $inherit Whether "inherit" is allowed.
	 * @return string
	 */
	private static function policy( $value, $inherit = false ) {
		$allowed = array( 'allow', 'limit', 'block' );
		if ( $inherit ) {
			$allowed[] = 'inherit';
		}
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : ( $inherit ? 'inherit' : 'limit' );
	}

	/**
	 * Clamp an integer.
	 *
	 * @param mixed $value Raw.
	 * @param int   $min   Min.
	 * @param int   $max   Max.
	 * @return int
	 */
	private static function int_range( $value, $min, $max ) {
		$value = (int) $value;
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Sanitize a multi-line text list.
	 *
	 * @param mixed $value Raw textarea.
	 * @return string
	 */
	private static function lines( $value ) {
		$value = sanitize_textarea_field( (string) $value );
		$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ), 'strlen' );
		return implode( "\n", $lines );
	}

	/**
	 * Sanitize the submitted fields of one admin tab and merge them into the current settings.
	 *
	 * @param string $tab     Tab id: rules | custom | protection | robots | advanced.
	 * @param array  $input   Raw (unslashed) input.
	 * @param array  $current Current settings.
	 * @return array New settings.
	 */
	public static function sanitize_tab( $tab, array $input, array $current ) {
		$s = $current;

		switch ( $tab ) {
			case 'rules':
				foreach ( Bot_Control_Bot_Registry::category_ids() as $cat ) {
					if ( ! isset( $input['categories'][ $cat ] ) || ! is_array( $input['categories'][ $cat ] ) ) {
						continue;
					}
					$raw                     = $input['categories'][ $cat ];
					$s['categories'][ $cat ] = array(
						'policy' => self::policy( isset( $raw['policy'] ) ? $raw['policy'] : 'limit' ),
						'limit'  => self::int_range( isset( $raw['limit'] ) ? $raw['limit'] : 20, 1, 100000 ),
						'scope'  => ( isset( $raw['scope'] ) && 'bot' === $raw['scope'] ) ? 'bot' : 'ip',
					);
				}

				$bots = array();
				$registry = Bot_Control_Bot_Registry::all();
				if ( isset( $input['bots'] ) && is_array( $input['bots'] ) ) {
					foreach ( $input['bots'] as $bot_id => $raw ) {
						$bot_id = sanitize_key( $bot_id );
						if ( ! isset( $registry[ $bot_id ] ) || ! is_array( $raw ) ) {
							continue;
						}
						$policy = self::policy( isset( $raw['policy'] ) ? $raw['policy'] : 'inherit', true );
						if ( $registry[ $bot_id ]['robots_only'] && 'limit' === $policy ) {
							$policy = 'inherit';
						}
						$limit = isset( $raw['limit'] ) && '' !== $raw['limit'] ? self::int_range( $raw['limit'], 0, 100000 ) : 0;
						// Only store real overrides to keep the option small.
						if ( 'inherit' !== $policy || $limit > 0 ) {
							$bots[ $bot_id ] = array(
								'policy' => $policy,
								'limit'  => $limit,
							);
						}
					}
				}
				$s['bots'] = $bots;
				break;

			case 'custom':
				$rows = array();
				if ( isset( $input['custom_bots'] ) && is_array( $input['custom_bots'] ) ) {
					foreach ( $input['custom_bots'] as $raw ) {
						if ( ! is_array( $raw ) ) {
							continue;
						}
						$pattern = isset( $raw['pattern'] ) ? trim( wp_strip_all_tags( (string) $raw['pattern'] ) ) : '';
						if ( '' === $pattern ) {
							continue;
						}
						$match = ( isset( $raw['match'] ) && 'regex' === $raw['match'] ) ? 'regex' : 'contains';
						if ( 'regex' === $match && false === @preg_match( self::regex( $pattern ), '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
							add_settings_error( 'botctl', 'bad_regex', sprintf( /* translators: %s: pattern */ __( 'Invalid regular expression skipped: %s', 'all-in-crawler-ai-agent-manager' ), $pattern ) );
							continue;
						}
						$category = isset( $raw['category'] ) ? sanitize_key( $raw['category'] ) : 'unknown_bot';
						if ( ! in_array( $category, Bot_Control_Bot_Registry::category_ids(), true ) ) {
							$category = 'unknown_bot';
						}
						$id = isset( $raw['id'] ) ? sanitize_key( $raw['id'] ) : '';
						if ( '' === $id ) {
							$id = substr( md5( $pattern . wp_rand() ), 0, 10 );
						}
						$name   = isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '';
						$rows[] = array(
							'id'       => $id,
							'name'     => '' !== $name ? $name : $pattern,
							'pattern'  => substr( $pattern, 0, 200 ),
							'match'    => $match,
							'category' => $category,
							'policy'   => self::policy( isset( $raw['policy'] ) ? $raw['policy'] : 'inherit', true ),
							'limit'    => isset( $raw['limit'] ) && '' !== $raw['limit'] ? self::int_range( $raw['limit'], 0, 100000 ) : 0,
						);
					}
				}
				$s['custom_bots'] = $rows;
				break;

			case 'protection':
				$s['enabled']             = empty( $input['enabled'] ) ? 0 : 1;
				$s['mode']                = ( isset( $input['mode'] ) && 'monitor' === $input['mode'] ) ? 'monitor' : 'enforce';
				$s['window']              = self::int_range( isset( $input['window'] ) ? $input['window'] : 60, 10, 3600 );
				$s['verify_bots']         = empty( $input['verify_bots'] ) ? 0 : 1;
				$s['impostor_policy']     = ( isset( $input['impostor_policy'] ) && 'limit' === $input['impostor_policy'] ) ? 'limit' : 'block';
				$s['human_limit_enabled'] = empty( $input['human_limit_enabled'] ) ? 0 : 1;
				$s['human_limit']         = self::int_range( isset( $input['human_limit'] ) ? $input['human_limit'] : 120, 10, 100000 );
				$s['load_shedding']       = empty( $input['load_shedding'] ) ? 0 : 1;
				$s['load_threshold']      = max( 0.1, min( 50.0, round( (float) ( isset( $input['load_threshold'] ) ? $input['load_threshold'] : 2 ), 2 ) ) );
				$s['cpu_cores']           = self::int_range( isset( $input['cpu_cores'] ) ? $input['cpu_cores'] : 0, 0, 1024 );
				$exempt                   = isset( $input['shed_exempt'] ) && is_array( $input['shed_exempt'] ) ? array_map( 'sanitize_key', $input['shed_exempt'] ) : array();
				$s['shed_exempt']         = array_values( array_intersect( $exempt, Bot_Control_Bot_Registry::category_ids() ) );
				$s['ip_allowlist']        = self::ip_list( isset( $input['ip_allowlist'] ) ? $input['ip_allowlist'] : '' );
				$s['ip_blocklist']        = self::ip_list( isset( $input['ip_blocklist'] ) ? $input['ip_blocklist'] : '' );
				$s['excluded_paths']      = self::lines( isset( $input['excluded_paths'] ) ? $input['excluded_paths'] : '' );
				break;

			case 'robots':
				$s['robots_enabled']     = empty( $input['robots_enabled'] ) ? 0 : 1;
				$s['robots_crawl_delay'] = empty( $input['robots_crawl_delay'] ) ? 0 : 1;
				break;

			case 'advanced':
				$sources              = array( 'auto', 'remote_addr', 'cloudflare', 'x_forwarded_for', 'x_real_ip' );
				$s['ip_source']       = ( isset( $input['ip_source'] ) && in_array( $input['ip_source'], $sources, true ) ) ? $input['ip_source'] : 'auto';
				$s['trusted_proxies'] = self::ip_list( isset( $input['trusted_proxies'] ) ? $input['trusted_proxies'] : '' );
				$storages             = array( 'auto', 'apcu', 'object_cache', 'database' );
				$s['storage']         = ( isset( $input['storage'] ) && in_array( $input['storage'], $storages, true ) ) ? $input['storage'] : 'auto';
				$s['log_enabled']     = empty( $input['log_enabled'] ) ? 0 : 1;
				$s['stats_enabled']   = empty( $input['stats_enabled'] ) ? 0 : 1;
				$s['log_retention']   = self::int_range( isset( $input['log_retention'] ) ? $input['log_retention'] : 14, 1, 365 );
				$s['stats_retention'] = self::int_range( isset( $input['stats_retention'] ) ? $input['stats_retention'] : 90, 7, 730 );
				$s['debug']           = empty( $input['debug'] ) ? 0 : 1;
				$s['keep_data']       = empty( $input['keep_data'] ) ? 0 : 1;
				break;

			case 'lists':
				$s['ip_lists_optin']      = empty( $input['ip_lists_optin'] ) ? 0 : 1;
				unset( $s['ip_lists_enabled'] ); // Legacy key from older builds; never used as consent.
				$s['remote_list_enabled'] = empty( $input['remote_list_enabled'] ) ? 0 : 1;
				$raw_url                  = isset( $input['remote_list_url'] ) ? trim( (string) $input['remote_list_url'] ) : '';
				$url                      = esc_url_raw( $raw_url, array( 'https' ) );
				if ( '' !== $raw_url && '' === $url ) {
					add_settings_error( 'botctl', 'bad_feed_url', __( 'The feed URL must start with https:// – only the bundled bot list is used.', 'all-in-crawler-ai-agent-manager' ) );
				}
				$s['remote_list_url']     = $url;
				$s['remote_list_key']     = isset( $input['remote_list_key'] ) ? substr( sanitize_text_field( $input['remote_list_key'] ), 0, 200 ) : '';
				break;

			case 'server':
				$s['server_rules_auto'] = empty( $input['server_rules_auto'] ) ? 0 : 1;
				$s['server_rules_ips']  = empty( $input['server_rules_ips'] ) ? 0 : 1;
				break;

			case 'reports':
				$s['report_enabled']    = empty( $input['report_enabled'] ) ? 0 : 1;
				$s['report_frequency']  = ( isset( $input['report_frequency'] ) && 'monthly' === $input['report_frequency'] ) ? 'monthly' : 'weekly';
				$emails                 = array();
				foreach ( preg_split( '/[\s,;]+/', isset( $input['report_recipients'] ) ? (string) $input['report_recipients'] : '' ) as $email ) {
					$email = sanitize_email( $email );
					if ( is_email( $email ) ) {
						$emails[] = $email;
					} elseif ( '' !== trim( $email ) ) {
						add_settings_error( 'botctl', 'bad_email', __( 'An invalid email address was skipped.', 'all-in-crawler-ai-agent-manager' ) );
					}
				}
				$s['report_recipients'] = implode( ', ', array_unique( $emails ) );
				$s['report_brand']      = isset( $input['report_brand'] ) ? substr( sanitize_text_field( $input['report_brand'] ), 0, 80 ) : '';
				break;

			case 'llms':
				$s['llms_enabled']     = empty( $input['llms_enabled'] ) ? 0 : 1;
				$s['llms_summary']     = isset( $input['llms_summary'] ) ? substr( sanitize_text_field( $input['llms_summary'] ), 0, 500 ) : '';
				$s['llms_details']     = isset( $input['llms_details'] ) ? substr( sanitize_textarea_field( $input['llms_details'] ), 0, 3000 ) : '';
				$types                 = isset( $input['llms_post_types'] ) && is_array( $input['llms_post_types'] ) ? array_map( 'sanitize_key', $input['llms_post_types'] ) : array();
				$s['llms_post_types']  = array_values( array_filter( $types, 'post_type_exists' ) );
				$s['llms_limit']       = self::int_range( isset( $input['llms_limit'] ) ? $input['llms_limit'] : 25, 1, 500 );
				$s['llms_extra_title'] = isset( $input['llms_extra_title'] ) ? substr( sanitize_text_field( $input['llms_extra_title'] ), 0, 80 ) : '';
				$s['llms_extra']       = isset( $input['llms_extra'] ) ? substr( sanitize_textarea_field( $input['llms_extra'] ), 0, 5000 ) : '';
				break;
		}

		return $s;
	}

	/**
	 * Sanitize a full settings array (used by import).
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize_all( array $input ) {
		$s = self::defaults();
		// Checkbox-style fields default to 0 when absent in sanitize_tab, so feed defaults first.
		$input = array_merge( $s, $input );
		foreach ( array( 'rules', 'custom', 'protection', 'robots', 'advanced', 'lists', 'server', 'reports', 'llms' ) as $tab ) {
			$s = self::sanitize_tab( $tab, $input, $s );
		}
		return $s;
	}

	/**
	 * First-pass clean-up of raw settings input (admin form or imported JSON).
	 *
	 * Only known setting keys are kept. Each value is type-cast to the type of its
	 * default and sanitized (keys with sanitize_key(), text with sanitize_text_field()
	 * / sanitize_textarea_field(), the feed URL with esc_url_raw()). sanitize_tab()
	 * then applies the strict per-field rules (allowed values, ranges, IP/e-mail checks).
	 *
	 * @param mixed $input Raw, unslashed input.
	 * @return array
	 */
	public static function clean_input( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$clean = array();
		foreach ( self::defaults() as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = $input[ $key ];
			if ( is_array( $default ) ) {
				$clean[ $key ] = is_array( $value ) ? self::clean_deep( $value, 3 ) : array();
			} elseif ( is_array( $value ) || is_object( $value ) ) {
				continue; // A scalar setting never accepts an array.
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $value;
			} elseif ( is_float( $default ) ) {
				$clean[ $key ] = (float) $value;
			} elseif ( 'remote_list_url' === $key ) {
				$clean[ $key ] = esc_url_raw( trim( (string) $value ) ); // https-only is enforced in sanitize_tab().
			} else {
				$clean[ $key ] = sanitize_textarea_field( (string) $value );
			}
		}
		return $clean;
	}

	/**
	 * Recursively sanitize a nested settings array (keys and scalar leaves).
	 *
	 * @param array $value Raw array.
	 * @param int   $depth Levels still allowed.
	 * @return array
	 */
	private static function clean_deep( array $value, $depth ) {
		$out = array();
		foreach ( $value as $k => $v ) {
			$k = is_int( $k ) ? $k : sanitize_key( (string) $k );
			if ( '' === $k ) {
				continue;
			}
			if ( is_array( $v ) ) {
				if ( $depth > 1 ) {
					$out[ $k ] = self::clean_deep( $v, $depth - 1 );
				}
			} elseif ( is_bool( $v ) || is_int( $v ) || is_float( $v ) ) {
				$out[ $k ] = $v;
			} elseif ( is_string( $v ) ) {
				$out[ $k ] = sanitize_text_field( $v );
			}
		}
		return $out;
	}

	/**
	 * Validate and sanitize a decoded settings export (json_decode() output), field by field.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array|null Sanitized settings, or null when it is not a valid export.
	 */
	public static function sanitize_import( $data ) {
		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return null;
		}
		// Envelope: only these keys are read; everything else is ignored.
		$plugin = isset( $data['plugin'] ) && is_string( $data['plugin'] ) ? sanitize_key( $data['plugin'] ) : '';
		// Exports made before the rename carry the former slug 'bot-control'; both are accepted.
		if ( '' !== $plugin && ! in_array( $plugin, array( 'all-in-crawler-ai-agent-manager', 'bot-control' ), true ) ) {
			return null;
		}
		return self::sanitize_all( self::clean_input( $data['settings'] ) );
	}

	/**
	 * Validate an IP / CIDR list, dropping invalid entries (comments with # are kept).
	 *
	 * @param mixed $value Raw textarea.
	 * @return string
	 */
	private static function ip_list( $value ) {
		$out = array();
		foreach ( preg_split( '/[\r\n,]+/', sanitize_textarea_field( (string) $value ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( 0 === strpos( $line, '#' ) ) {
				$out[] = $line;
				continue;
			}
			$entry = trim( preg_replace( '/\s*#.*$/', '', $line ) );
			if ( Bot_Control_IP::is_valid_entry( $entry ) ) {
				$out[] = $line;
			} else {
				add_settings_error( 'botctl', 'bad_ip', sprintf( /* translators: %s: entry */ __( 'Invalid IP/CIDR skipped: %s', 'all-in-crawler-ai-agent-manager' ), $entry ) );
			}
		}
		return implode( "\n", $out );
	}

	/**
	 * Build a safe PCRE from a user pattern (without delimiters).
	 *
	 * @param string $pattern User pattern.
	 * @return string
	 */
	public static function regex( $pattern ) {
		return '~' . str_replace( '~', '\~', $pattern ) . '~i';
	}
}
