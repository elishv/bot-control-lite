<?php
/**
 * Admin screens and form handlers.
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
final class Bot_Control_Admin {

	const SLUG = 'all-in-crawler-ai-agent-manager';
	const CAP  = 'manage_options';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( 'Bot_Control_Install', 'maybe_upgrade' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BOTCTL_FILE ), array( __CLASS__, 'action_links' ) );

		$actions = array( 'save', 'preset', 'ip_action', 'clear_logs', 'clear_stats', 'clear_verify', 'export', 'import', 'reset', 'refresh_lists', 'htaccess', 'send_report', 'preview_report' );
		foreach ( $actions as $action ) {
			add_action( 'admin_post_botctl_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'dashboard'  => __( 'Dashboard', 'all-in-crawler-ai-agent-manager' ),
			'rules'      => __( 'Bot Rules', 'all-in-crawler-ai-agent-manager' ),
			'custom'     => __( 'Custom Bots', 'all-in-crawler-ai-agent-manager' ),
			'protection' => __( 'Protection', 'all-in-crawler-ai-agent-manager' ),
			'robots'     => __( 'robots.txt', 'all-in-crawler-ai-agent-manager' ),
			'llms'       => __( 'llms.txt', 'all-in-crawler-ai-agent-manager' ),
			'lists'      => __( 'Bot Lists', 'all-in-crawler-ai-agent-manager' ),
			'server'     => __( 'Server Rules', 'all-in-crawler-ai-agent-manager' ),
			'reports'    => __( 'Reports', 'all-in-crawler-ai-agent-manager' ),
			'logs'       => __( 'Logs', 'all-in-crawler-ai-agent-manager' ),
			'tools'      => __( 'Tools', 'all-in-crawler-ai-agent-manager' ),
			'advanced'   => __( 'Advanced', 'all-in-crawler-ai-agent-manager' ),
		);
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		add_menu_page(
			__( 'All-IN Crawler & AI Agent Manager', 'all-in-crawler-ai-agent-manager' ),
			__( 'All-IN Crawler', 'all-in-crawler-ai-agent-manager' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-shield',
			80
		);
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		$url = plugin_dir_url( BOTCTL_FILE );
		wp_enqueue_style( 'botctl-admin', $url . 'admin/assets/admin.css', array(), BOTCTL_VERSION );
		wp_enqueue_script( 'botctl-admin', $url . 'admin/assets/admin.js', array(), BOTCTL_VERSION, true );
		wp_localize_script(
			'botctl-admin',
			'botctlAdmin',
			array(
				'confirm' => __( 'Are you sure?', 'all-in-crawler-ai-agent-manager' ),
			)
		);
	}

	/**
	 * Plugins list links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'all-in-crawler-ai-agent-manager' ) . '</a>' );
		return $links;
	}

	/**
	 * Admin URL.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function url( $tab = 'dashboard', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'dashboard';
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'all-in-crawler-ai-agent-manager' ) );
		}
		$tab = self::current_tab();
		$s   = Bot_Control_Settings::get();

		echo '<div class="wrap botctl-wrap">';
		echo '<h1 class="botctl-title"><span class="dashicons dashicons-shield"></span> ' . esc_html__( 'All-IN Crawler & AI Agent Manager', 'all-in-crawler-ai-agent-manager' ) . ' <span class="botctl-version">v' . esc_html( BOTCTL_VERSION ) . '</span></h1>';

		self::global_notices( $s );
		settings_errors( 'botctl' );

		echo '<nav class="nav-tab-wrapper botctl-tabs">';
		foreach ( self::tabs() as $id => $label ) {
			printf(
				'<a href="%s" class="nav-tab %s">%s</a>',
				esc_url( self::url( $id ) ),
				$id === $tab ? 'nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		echo '<div class="botctl-tab-content">';
		include BOTCTL_DIR . 'admin/views/' . $tab . '.php';
		echo '</div></div>';
	}

	/**
	 * Warnings shown on every tab.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function global_notices( array $s ) {
		if ( empty( $s['enabled'] ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'All-IN Crawler & AI Agent Manager is disabled.', 'all-in-crawler-ai-agent-manager' ) . '</strong> ' . esc_html__( 'No request is being filtered. Enable it on the Protection tab.', 'all-in-crawler-ai-agent-manager' ) . '</p></div>';
		} elseif ( 'monitor' === $s['mode'] ) {
			echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Monitor mode:', 'all-in-crawler-ai-agent-manager' ) . '</strong> ' . esc_html__( 'decisions are logged but nothing is blocked. Switch to Enforce on the Protection tab when you are happy with the results.', 'all-in-crawler-ai-agent-manager' ) . '</p></div>';
		}
		if ( BOTCTL_DB_VERSION !== get_option( Bot_Control_Install::DB_OPTION ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'All-IN Crawler & AI Agent Manager database tables are missing. Deactivate and re-activate the plugin.', 'all-in-crawler-ai-agent-manager' ) . '</p></div>';
		}
	}

	/**
	 * Category label (including pseudo categories).
	 *
	 * @param string $cat Category.
	 * @return string
	 */
	public static function category_label( $cat ) {
		$cats = Bot_Control_Bot_Registry::categories();
		if ( isset( $cats[ $cat ] ) ) {
			return $cats[ $cat ]['label'];
		}
		if ( 'impostor' === $cat ) {
			return __( 'Impostors (fake search engines)', 'all-in-crawler-ai-agent-manager' );
		}
		if ( 'human' === $cat ) {
			return __( 'Human visitors', 'all-in-crawler-ai-agent-manager' );
		}
		return $cat;
	}

	/**
	 * Policy labels.
	 *
	 * @param bool $inherit Include inherit.
	 * @return array
	 */
	public static function policy_options( $inherit = false ) {
		$opts = array();
		if ( $inherit ) {
			$opts['inherit'] = __( '— Category default —', 'all-in-crawler-ai-agent-manager' );
		}
		$opts['allow'] = __( 'Allow', 'all-in-crawler-ai-agent-manager' );
		$opts['limit'] = __( 'Rate limit', 'all-in-crawler-ai-agent-manager' );
		$opts['block'] = __( 'Block', 'all-in-crawler-ai-agent-manager' );
		return $opts;
	}

	/**
	 * Action labels.
	 *
	 * @param string $action Action.
	 * @return string
	 */
	public static function action_label( $action ) {
		$labels = array(
			'blocked'    => __( 'Blocked (403)', 'all-in-crawler-ai-agent-manager' ),
			'limited'    => __( 'Rate limited (429)', 'all-in-crawler-ai-agent-manager' ),
			'impostor'   => __( 'Impostor (403)', 'all-in-crawler-ai-agent-manager' ),
			'shed'       => __( 'Load shed (503)', 'all-in-crawler-ai-agent-manager' ),
			'ip_blocked' => __( 'IP blocked (403)', 'all-in-crawler-ai-agent-manager' ),
		);
		return isset( $labels[ $action ] ) ? $labels[ $action ] : $action;
	}

	/**
	 * Render a <select>.
	 *
	 * @param string $name    Field name.
	 * @param array  $options value => label.
	 * @param string $current Current value.
	 * @param array  $attrs   Extra attributes.
	 * @return void
	 */
	public static function select( $name, array $options, $current, array $attrs = array() ) {
		$extra = '';
		foreach ( $attrs as $k => $v ) {
			$extra .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		echo '<select name="' . esc_attr( $name ) . '"' . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Form opening for admin-post.
	 *
	 * @param string $action Action suffix.
	 * @param array  $hidden Hidden fields.
	 * @param string $class  Form class.
	 * @return void
	 */
	public static function form_open( $action, array $hidden = array(), $class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="' . esc_attr( $class ) . '">';
		echo '<input type="hidden" name="action" value="botctl_' . esc_attr( $action ) . '">';
		foreach ( $hidden as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		wp_nonce_field( 'botctl_' . $action );
	}

	/**
	 * Verify capability + nonce.
	 *
	 * @param string $action Action suffix.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'all-in-crawler-ai-agent-manager' ), 403 );
		}
		check_admin_referer( 'botctl_' . $action );
	}

	/**
	 * Redirect back with messages.
	 *
	 * @param string $tab     Tab.
	 * @param string $message Success message.
	 * @param string $type    success|error|warning|info.
	 * @return void
	 */
	private static function done( $tab, $message, $type = 'success' ) {
		add_settings_error( 'botctl', 'botctl_msg', $message, $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( self::url( $tab, array( 'settings-updated' => 'true' ) ) );
		exit;
	}

	/* --------------------------------------------------------------------- Handlers */

	/**
	 * Save a settings tab.
	 *
	 * @return void
	 */
	public static function handle_save() {
		self::guard( 'save' );
		$tab   = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		// Whitelisted, type-cast and sanitized key by key in clean_input(), then validated per field in sanitize_tab().
		$input = isset( $_POST['botctl'] ) && is_array( $_POST['botctl'] ) ? Bot_Control_Settings::clean_input( wp_unslash( $_POST['botctl'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Sanitized in clean_input(); nonce verified in self::guard().

		if ( ! in_array( $tab, array( 'rules', 'custom', 'protection', 'robots', 'advanced', 'lists', 'server', 'reports', 'llms' ), true ) ) {
			self::done( 'dashboard', __( 'Unknown settings section.', 'all-in-crawler-ai-agent-manager' ), 'error' );
		}

		$new = Bot_Control_Settings::sanitize_tab( $tab, $input, Bot_Control_Settings::get() );
		Bot_Control_Settings::update( $new );
		Bot_Control_Logger::debug( 'Admin', 'Settings tab saved', array( 'tab' => $tab ) );

		self::done( $tab, __( 'Settings saved.', 'all-in-crawler-ai-agent-manager' ) );
	}

	/**
	 * Apply a preset.
	 *
	 * @return void
	 */
	public static function handle_preset() {
		self::guard( 'preset' );
		$preset  = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		$presets = Bot_Control_Settings::presets();
		if ( ! isset( $presets[ $preset ] ) ) {
			self::done( 'rules', __( 'Unknown preset.', 'all-in-crawler-ai-agent-manager' ), 'error' );
		}
		$s               = Bot_Control_Settings::get();
		$s['categories'] = $presets[ $preset ]['categories'];
		Bot_Control_Settings::update( $s );
		Bot_Control_Logger::debug( 'Admin', 'Preset applied', array( 'preset' => $preset ) );

		/* translators: %s: preset name */
		self::done( 'rules', sprintf( __( 'Preset "%s" applied. Per-bot overrides were kept and still take precedence.', 'all-in-crawler-ai-agent-manager' ), $presets[ $preset ]['label'] ) );
	}

	/**
	 * Add an IP to the allow or block list.
	 *
	 * @return void
	 */
	public static function handle_ip_action() {
		self::guard( 'ip_action' );
		$ip   = isset( $_POST['ip'] ) ? Bot_Control_IP::normalize( sanitize_text_field( wp_unslash( $_POST['ip'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		$list = isset( $_POST['list'] ) && 'allow' === sanitize_key( wp_unslash( $_POST['list'] ) ) ? 'ip_allowlist' : 'ip_blocklist'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		$back = isset( $_POST['back'] ) ? sanitize_key( wp_unslash( $_POST['back'] ) ) : 'logs'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().

		if ( ! Bot_Control_IP::is_valid_ip( $ip ) ) {
			self::done( $back, __( 'Invalid IP address.', 'all-in-crawler-ai-agent-manager' ), 'error' );
		}

		$s       = Bot_Control_Settings::get();
		$entries = Bot_Control_IP::parse_list( $s[ $list ] );
		if ( ! in_array( $ip, $entries, true ) ) {
			$s[ $list ] = trim( $s[ $list ] . "\n" . $ip . ' # ' . wp_date( 'Y-m-d' ) );
			// An IP can't be on both lists.
			$other = 'ip_allowlist' === $list ? 'ip_blocklist' : 'ip_allowlist';
			$lines = array_filter(
				preg_split( '/\r\n|\r|\n/', $s[ $other ] ),
				function ( $line ) use ( $ip ) {
					return trim( preg_replace( '/#.*$/', '', $line ) ) !== $ip;
				}
			);
			$s[ $other ] = implode( "\n", $lines );
			Bot_Control_Settings::update( $s );
		}

		Bot_Control_Logger::debug( 'Admin', 'IP added to list', array( 'ip' => $ip, 'list' => $list ) );
		/* translators: 1: IP, 2: list name */
		self::done( $back, sprintf( __( '%1$s added to the %2$s.', 'all-in-crawler-ai-agent-manager' ), $ip, 'ip_allowlist' === $list ? __( 'allow list', 'all-in-crawler-ai-agent-manager' ) : __( 'block list', 'all-in-crawler-ai-agent-manager' ) ) );
	}

	/**
	 * Clear event log.
	 *
	 * @return void
	 */
	public static function handle_clear_logs() {
		self::guard( 'clear_logs' );
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}botctl_events" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::done( 'logs', __( 'Log cleared.', 'all-in-crawler-ai-agent-manager' ) );
	}

	/**
	 * Clear statistics.
	 *
	 * @return void
	 */
	public static function handle_clear_stats() {
		self::guard( 'clear_stats' );
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}botctl_stats" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::done( 'tools', __( 'Statistics cleared.', 'all-in-crawler-ai-agent-manager' ) );
	}

	/**
	 * Clear DNS verification cache.
	 *
	 * @return void
	 */
	public static function handle_clear_verify() {
		self::guard( 'clear_verify' );
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}botctl_verify" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'botctl_verify' );
		}
		self::done( 'tools', __( 'Verification cache cleared.', 'all-in-crawler-ai-agent-manager' ) );
	}

	/**
	 * Export settings as JSON.
	 *
	 * @return void
	 */
	public static function handle_export() {
		self::guard( 'export' );
		$payload = array(
			'plugin'   => 'all-in-crawler-ai-agent-manager',
			'version'  => BOTCTL_VERSION,
			'exported' => gmdate( 'c' ),
			'settings' => Bot_Control_Settings::get(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=all-in-crawler-ai-agent-manager-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Import settings.
	 *
	 * @return void
	 */
	public static function handle_import() {
		self::guard( 'import' );
		// The JSON document itself cannot be run through a text sanitizer without corrupting it, so it is
		// size-checked, decoded, and then every field of the decoded data is whitelisted, type-cast and
		// sanitized in Bot_Control_Settings::sanitize_import() before anything is stored.
		$raw = isset( $_POST['json'] ) && is_string( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Decoded data is sanitized field by field below; nonce verified in self::guard().
		$settings = null;
		if ( '' !== $raw && strlen( $raw ) <= 1048576 ) {
			$settings = Bot_Control_Settings::sanitize_import( json_decode( $raw, true, 16 ) );
		}

		if ( null === $settings ) {
			Bot_Control_Logger::debug( 'Admin', 'Import rejected: invalid JSON', array( 'json_error' => json_last_error_msg() ) );
			self::done( 'tools', __( 'Import failed: this is not a valid All-IN Crawler & AI Agent Manager export.', 'all-in-crawler-ai-agent-manager' ), 'error' );
		}

		Bot_Control_Settings::update( $settings );
		self::done( 'tools', __( 'Settings imported.', 'all-in-crawler-ai-agent-manager' ) );
	}

	/**
	 * Reset to defaults.
	 *
	 * @return void
	 */
	public static function handle_reset() {
		self::guard( 'reset' );
		Bot_Control_Settings::reset();
		self::done( 'tools', __( 'All settings were reset to defaults.', 'all-in-crawler-ai-agent-manager' ) );
	}

	/**
	 * Refresh official IP lists or the remote bot feed now.
	 *
	 * @return void
	 */
	public static function handle_refresh_lists() {
		self::guard( 'refresh_lists' );
		$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : 'ip'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().

		if ( 'feed' === $what ) {
			$result = Bot_Control_Remote_List::refresh();
			Bot_Control_Bot_Registry::reset();
			if ( is_wp_error( $result ) ) {
				self::done( 'lists', $result->get_error_message(), 'error' );
			}
			Bot_Control_Server_Rules::maybe_sync( Bot_Control_Settings::get() );
			$status = Bot_Control_Remote_List::status();
			/* translators: %d: number of bots */
			self::done( 'lists', sprintf( __( 'Bot feed updated: %d bots loaded.', 'all-in-crawler-ai-agent-manager' ), (int) $status['count'] ) );
		}

		$settings = Bot_Control_Settings::get();
		if ( empty( $settings['ip_lists_optin'] ) ) {
			self::done( 'lists', __( 'Official IP list downloads are off. Turn on "Download official crawler IP lists" and save first.', 'all-in-crawler-ai-agent-manager' ), 'warning' );
		}

		$urls   = Bot_Control_IP_Lists::refresh();
		$failed = count( array_filter( $urls, function ( $u ) {
			return empty( $u['ok'] );
		} ) );
		if ( $failed ) {
			/* translators: 1: failed lists, 2: total lists */
			self::done( 'lists', sprintf( __( '%1$d of %2$d IP lists could not be downloaded. Previous data (if any) is kept – see the table for details.', 'all-in-crawler-ai-agent-manager' ), $failed, count( $urls ) ), 'warning' );
		}
		self::done( 'lists', __( 'All official IP lists were updated.', 'all-in-crawler-ai-agent-manager' ) );
	}

	/**
	 * Write or remove the .htaccess rules.
	 *
	 * @return void
	 */
	public static function handle_htaccess() {
		self::guard( 'htaccess' );
		$remove = isset( $_POST['op'] ) && 'remove' === sanitize_key( wp_unslash( $_POST['op'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		$result = Bot_Control_Server_Rules::write_htaccess( null, $remove );
		if ( is_wp_error( $result ) ) {
			self::done( 'server', $result->get_error_message(), 'error' );
		}
		self::done( 'server', $remove ? __( 'All-IN Crawler & AI Agent Manager rules removed from .htaccess.', 'all-in-crawler-ai-agent-manager' ) : __( '.htaccess updated. A backup of the previous file was saved as .htaccess.botctl-backup.', 'all-in-crawler-ai-agent-manager' ) );
	}

	/**
	 * Send the report now.
	 *
	 * @return void
	 */
	public static function handle_send_report() {
		self::guard( 'send_report' );
		$to = implode( ', ', Bot_Control_Reports::recipients( Bot_Control_Settings::get() ) );
		if ( Bot_Control_Reports::send() ) {
			/* translators: %s: recipients */
			self::done( 'reports', sprintf( __( 'Report sent to %s.', 'all-in-crawler-ai-agent-manager' ), $to ) );
		}
		self::done( 'reports', __( 'The report could not be sent. Check that your site can send email (an SMTP plugin is recommended).', 'all-in-crawler-ai-agent-manager' ), 'error' );
	}

	/**
	 * Show the report in the browser.
	 *
	 * @return void
	 */
	public static function handle_preview_report() {
		self::guard( 'preview_report' );
		$s    = Bot_Control_Settings::get();
		$days = 'monthly' === $s['report_frequency'] ? 30 : 7;
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo Bot_Control_Reports::render( Bot_Control_Reports::collect( $days ), $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
		exit;
	}
}
