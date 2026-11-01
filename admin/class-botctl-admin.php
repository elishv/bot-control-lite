<?php
/**
 * Admin screens and form handlers.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
final class Bot_Control_Admin {

	const SLUG = 'bot-control';
	const CAP  = 'manage_options';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( 'Bot_Control_Install', 'maybe_upgrade' ) );
		add_action( 'admin_init', array( 'Bot_Control_Early_Loader', 'maybe_refresh' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BOTCTL_FILE ), array( __CLASS__, 'action_links' ) );

		$actions = array( 'save', 'preset', 'ip_action', 'clear_logs', 'clear_stats', 'clear_verify', 'export', 'import', 'reset', 'early_loader', 'refresh_lists', 'htaccess', 'send_report', 'preview_report' );
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
			'dashboard'  => __( 'Dashboard', 'bot-control' ),
			'rules'      => __( 'Bot Rules', 'bot-control' ),
			'custom'     => __( 'Custom Bots', 'bot-control' ),
			'protection' => __( 'Protection', 'bot-control' ),
			'robots'     => __( 'robots.txt', 'bot-control' ),
			'llms'       => __( 'llms.txt', 'bot-control' ),
			'lists'      => __( 'Bot Lists', 'bot-control' ),
			'server'     => __( 'Server Rules', 'bot-control' ),
			'reports'    => __( 'Reports', 'bot-control' ),
			'logs'       => __( 'Logs', 'bot-control' ),
			'tools'      => __( 'Tools', 'bot-control' ),
			'advanced'   => __( 'Advanced', 'bot-control' ),
		);
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		add_menu_page(
			__( 'Bot Control', 'bot-control' ),
			__( 'Bot Control', 'bot-control' ),
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
				'confirm' => __( 'Are you sure?', 'bot-control' ),
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
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'bot-control' ) . '</a>' );
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bot-control' ) );
		}
		$tab = self::current_tab();
		$s   = Bot_Control_Settings::get();

		echo '<div class="wrap botctl-wrap">';
		echo '<h1 class="botctl-title"><span class="dashicons dashicons-shield"></span> ' . esc_html__( 'Bot Control', 'bot-control' ) . ' <span class="botctl-version">v' . esc_html( BOTCTL_VERSION ) . '</span></h1>';

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
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Bot Control is disabled.', 'bot-control' ) . '</strong> ' . esc_html__( 'No request is being filtered. Enable it on the Protection tab.', 'bot-control' ) . '</p></div>';
		} elseif ( 'monitor' === $s['mode'] ) {
			echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Monitor mode:', 'bot-control' ) . '</strong> ' . esc_html__( 'decisions are logged but nothing is blocked. Switch to Enforce on the Protection tab when you are happy with the results.', 'bot-control' ) . '</p></div>';
		}
		if ( BOTCTL_DB_VERSION !== get_option( Bot_Control_Install::DB_OPTION ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Bot Control database tables are missing. Deactivate and re-activate the plugin.', 'bot-control' ) . '</p></div>';
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
			return __( 'Impostors (fake search engines)', 'bot-control' );
		}
		if ( 'human' === $cat ) {
			return __( 'Human visitors', 'bot-control' );
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
			$opts['inherit'] = __( '— Category default —', 'bot-control' );
		}
		$opts['allow'] = __( 'Allow', 'bot-control' );
		$opts['limit'] = __( 'Rate limit', 'bot-control' );
		$opts['block'] = __( 'Block', 'bot-control' );
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
			'blocked'    => __( 'Blocked (403)', 'bot-control' ),
			'limited'    => __( 'Rate limited (429)', 'bot-control' ),
			'impostor'   => __( 'Impostor (403)', 'bot-control' ),
			'shed'       => __( 'Load shed (503)', 'bot-control' ),
			'ip_blocked' => __( 'IP blocked (403)', 'bot-control' ),
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
			wp_die( esc_html__( 'You do not have permission to do this.', 'bot-control' ), 403 );
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
		$input = isset( $_POST['botctl'] ) && is_array( $_POST['botctl'] ) ? wp_unslash( $_POST['botctl'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- sanitized in sanitize_tab().

		if ( ! in_array( $tab, array( 'rules', 'custom', 'protection', 'robots', 'advanced', 'lists', 'server', 'reports', 'llms' ), true ) ) {
			self::done( 'dashboard', __( 'Unknown settings section.', 'bot-control' ), 'error' );
		}

		$new = Bot_Control_Settings::sanitize_tab( $tab, $input, Bot_Control_Settings::get() );
		Bot_Control_Settings::update( $new );
		Bot_Control_Logger::debug( 'Admin', 'Settings tab saved', array( 'tab' => $tab ) );

		self::done( $tab, __( 'Settings saved.', 'bot-control' ) );
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
			self::done( 'rules', __( 'Unknown preset.', 'bot-control' ), 'error' );
		}
		$s               = Bot_Control_Settings::get();
		$s['categories'] = $presets[ $preset ]['categories'];
		Bot_Control_Settings::update( $s );
		Bot_Control_Logger::debug( 'Admin', 'Preset applied', array( 'preset' => $preset ) );

		/* translators: %s: preset name */
		self::done( 'rules', sprintf( __( 'Preset "%s" applied. Per-bot overrides were kept and still take precedence.', 'bot-control' ), $presets[ $preset ]['label'] ) );
	}

	/**
	 * Add an IP to the allow or block list.
	 *
	 * @return void
	 */
	public static function handle_ip_action() {
		self::guard( 'ip_action' );
		$ip   = isset( $_POST['ip'] ) ? Bot_Control_IP::normalize( sanitize_text_field( wp_unslash( $_POST['ip'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		$list = isset( $_POST['list'] ) && 'allow' === $_POST['list'] ? 'ip_allowlist' : 'ip_blocklist'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		$back = isset( $_POST['back'] ) ? sanitize_key( wp_unslash( $_POST['back'] ) ) : 'logs'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().

		if ( ! Bot_Control_IP::is_valid_ip( $ip ) ) {
			self::done( $back, __( 'Invalid IP address.', 'bot-control' ), 'error' );
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
		self::done( $back, sprintf( __( '%1$s added to the %2$s.', 'bot-control' ), $ip, 'ip_allowlist' === $list ? __( 'allow list', 'bot-control' ) : __( 'block list', 'bot-control' ) ) );
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
		self::done( 'logs', __( 'Log cleared.', 'bot-control' ) );
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
		self::done( 'tools', __( 'Statistics cleared.', 'bot-control' ) );
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
		self::done( 'tools', __( 'Verification cache cleared.', 'bot-control' ) );
	}

	/**
	 * Export settings as JSON.
	 *
	 * @return void
	 */
	public static function handle_export() {
		self::guard( 'export' );
		$payload = array(
			'plugin'   => 'bot-control',
			'version'  => BOTCTL_VERSION,
			'exported' => gmdate( 'c' ),
			'settings' => Bot_Control_Settings::get(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bot-control-settings-' . gmdate( 'Y-m-d' ) . '.json' );
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
		$raw  = isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- decoded and fully sanitized below.
		$data = json_decode( (string) $raw, true );

		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) || ( isset( $data['plugin'] ) && 'bot-control' !== $data['plugin'] ) ) {
			Bot_Control_Logger::debug( 'Admin', 'Import rejected: invalid JSON', array( 'json_error' => json_last_error_msg() ) );
			self::done( 'tools', __( 'Import failed: this is not a valid Bot Control export.', 'bot-control' ), 'error' );
		}

		Bot_Control_Settings::update( Bot_Control_Settings::sanitize_all( $data['settings'] ) );
		self::done( 'tools', __( 'Settings imported.', 'bot-control' ) );
	}

	/**
	 * Reset to defaults.
	 *
	 * @return void
	 */
	public static function handle_reset() {
		self::guard( 'reset' );
		Bot_Control_Settings::reset();
		self::done( 'tools', __( 'All settings were reset to defaults.', 'bot-control' ) );
	}

	/**
	 * Install/remove early loader.
	 *
	 * @return void
	 */
	public static function handle_early_loader() {
		self::guard( 'early_loader' );
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		if ( 'remove' === $op ) {
			Bot_Control_Early_Loader::uninstall();
			self::done( 'tools', __( 'Early loader removed.', 'bot-control' ) );
		}
		$result = Bot_Control_Early_Loader::install();
		if ( is_wp_error( $result ) ) {
			self::done( 'tools', $result->get_error_message(), 'error' );
		}
		self::done( 'tools', __( 'Early loader installed. Bot Control now runs before all other plugins.', 'bot-control' ) );
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
			self::done( 'lists', sprintf( __( 'Bot feed updated: %d bots loaded.', 'bot-control' ), (int) $status['count'] ) );
		}

		$settings = Bot_Control_Settings::get();
		if ( empty( $settings['ip_lists_optin'] ) ) {
			self::done( 'lists', __( 'Official IP list downloads are off. Turn on "Download official crawler IP lists" and save first.', 'bot-control' ), 'warning' );
		}

		$urls   = Bot_Control_IP_Lists::refresh();
		$failed = count( array_filter( $urls, function ( $u ) {
			return empty( $u['ok'] );
		} ) );
		if ( $failed ) {
			/* translators: 1: failed lists, 2: total lists */
			self::done( 'lists', sprintf( __( '%1$d of %2$d IP lists could not be downloaded. Previous data (if any) is kept – see the table for details.', 'bot-control' ), $failed, count( $urls ) ), 'warning' );
		}
		self::done( 'lists', __( 'All official IP lists were updated.', 'bot-control' ) );
	}

	/**
	 * Write or remove the .htaccess rules.
	 *
	 * @return void
	 */
	public static function handle_htaccess() {
		self::guard( 'htaccess' );
		$remove = isset( $_POST['op'] ) && 'remove' === $_POST['op']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		$result = Bot_Control_Server_Rules::write_htaccess( null, $remove );
		if ( is_wp_error( $result ) ) {
			self::done( 'server', $result->get_error_message(), 'error' );
		}
		self::done( 'server', $remove ? __( 'Bot Control rules removed from .htaccess.', 'bot-control' ) : __( '.htaccess updated. A backup of the previous file was saved as .htaccess.botctl-backup.', 'bot-control' ) );
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
			self::done( 'reports', sprintf( __( 'Report sent to %s.', 'bot-control' ), $to ) );
		}
		self::done( 'reports', __( 'The report could not be sent. Check that your site can send email (an SMTP plugin is recommended).', 'bot-control' ), 'error' );
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
