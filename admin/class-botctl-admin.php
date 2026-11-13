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

	const REVIEW_URL = 'https://wordpress.org/support/plugin/all-in-crawler-ai-agent-manager/reviews/#new-post';

	/**
	 * Minimum bot requests handled in the last 14 days before the review notice may show.
	 */
	const REVIEW_MIN_REQUESTS = 500;

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( 'Bot_Control_Install', 'maybe_upgrade' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss_review' ) );
		add_action( 'admin_notices', array( __CLASS__, 'watch_notice' ) );
		add_action( 'wp_ajax_botctl_dismiss_watch', array( __CLASS__, 'ajax_dismiss_watch' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BOTCTL_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );

		$actions = array( 'save', 'preset', 'ip_action', 'clear_logs', 'clear_stats', 'clear_verify', 'export', 'import', 'reset', 'refresh_lists', 'htaccess', 'send_report', 'preview_report', 'mode', 'dismiss_watch' );
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
		$url = plugin_dir_url( BOTCTL_FILE );
		if ( get_option( Bot_Control_Install::WATCH_NOTICE ) && current_user_can( self::CAP ) ) {
			wp_enqueue_script( 'botctl-notice', $url . 'admin/assets/notice.js', array(), BOTCTL_VERSION, true );
			wp_localize_script(
				'botctl-notice',
				'botctlNotice',
				array(
					'ajax'  => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'botctl_dismiss_watch' ),
				)
			);
		}
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
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
	 * Support and review links in the plugin's row on the Plugins screen (not a notice).
	 *
	 * @param array  $links Row meta links.
	 * @param string $file  Plugin basename.
	 * @return array
	 */
	public static function row_meta( $links, $file ) {
		if ( plugin_basename( BOTCTL_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( 'https://wordpress.org/support/plugin/all-in-crawler-ai-agent-manager/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support forum', 'all-in-crawler-ai-agent-manager' ) . '</a>';
		$links[] = '<a href="' . esc_url( self::REVIEW_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Leave a review', 'all-in-crawler-ai-agent-manager' ) . '</a>';
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
		echo '<h1 class="botctl-title"><span class="dashicons dashicons-shield"></span> ' . esc_html__( 'All-IN Crawler & AI Agent Manager', 'all-in-crawler-ai-agent-manager' ) . ' <span class="botctl-version">v' . esc_html( BOTCTL_VERSION ) . '</span> ';
		self::mode_badge( $s );
		echo '</h1>';

		self::global_notices( $s );
		self::review_notice();
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
		} else {
			self::mode_banner( $s );
		}
		if ( BOTCTL_DB_VERSION !== get_option( Bot_Control_Install::DB_OPTION ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'All-IN Crawler & AI Agent Manager database tables are missing. Deactivate and re-activate the plugin.', 'all-in-crawler-ai-agent-manager' ) . '</p></div>';
		}
	}

	/**
	 * Badge next to the page title.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function mode_badge( array $s ) {
		$watch = 'monitor' === $s['mode'];
		echo '<span class="botctl-mode ' . esc_attr( $watch ? 'botctl-mode-watch' : 'botctl-mode-enforce' ) . '">';
		echo esc_html( $watch ? __( 'Watch-only', 'all-in-crawler-ai-agent-manager' ) : __( 'Enforce', 'all-in-crawler-ai-agent-manager' ) );
		echo '</span>';
	}

	/**
	 * Persistent explanation of the current mode, with a one-click switch.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function mode_banner( array $s ) {
		$watch = 'monitor' === $s['mode'];
		echo '<div class="notice ' . esc_attr( $watch ? 'notice-info' : 'notice-warning' ) . ' botctl-mode-banner">';
		echo '<p>';
		if ( $watch ) {
			echo '<strong>' . esc_html__( 'Watch-only.', 'all-in-crawler-ai-agent-manager' ) . '</strong> ';
			echo esc_html__( 'All-IN Crawler & AI Agent Manager is only logging and reporting. It is not blocking requests, applying rate limits, shedding load, or changing robots.txt. Switch to Enforce when the log looks right.', 'all-in-crawler-ai-agent-manager' );
		} else {
			echo '<strong>' . esc_html__( 'Enforce.', 'all-in-crawler-ai-agent-manager' ) . '</strong> ';
			echo esc_html__( 'Rules are live. Matching bots can be blocked or rate-limited, an overloaded server can answer bots with 503, and robots.txt is updated when that option is enabled.', 'all-in-crawler-ai-agent-manager' );
		}
		echo '</p><div class="botctl-mode-actions">';
		self::mode_switch_form( $watch ? 'enforce' : 'monitor', self::current_tab() );
		echo '<a class="button button-link" href="' . esc_url( self::url( 'protection' ) ) . '">' . esc_html__( 'Mode settings', 'all-in-crawler-ai-agent-manager' ) . '</a>';
		echo '</div></div>';
	}

	/**
	 * One-click mode switch (admin-post).
	 *
	 * @param string $target monitor|enforce.
	 * @param string $tab    Tab to return to.
	 * @return void
	 */
	private static function mode_switch_form( $target, $tab ) {
		$enforce = 'enforce' === $target;
		$label   = $enforce
			? __( 'Switch to Enforce', 'all-in-crawler-ai-agent-manager' )
			: __( 'Switch to Watch-only', 'all-in-crawler-ai-agent-manager' );
		$confirm = $enforce
			? __( 'Switch to Enforce? Matching bots can be blocked or rate-limited, load shedding can send 503, and robots.txt can be changed.', 'all-in-crawler-ai-agent-manager' )
			: '';
		self::form_open( 'mode', array( 'mode' => $target, 'tab' => $tab ), 'botctl-inline' );
		echo '<button type="submit" class="' . esc_attr( 'button ' . ( $enforce ? 'button-primary botctl-confirm' : '' ) ) . '"';
		if ( '' !== $confirm ) {
			echo ' data-confirm="' . esc_attr( $confirm ) . '"';
		}
		echo '>' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Dismissible notice shown once after a fresh activation, on every admin screen.
	 *
	 * @return void
	 */
	public static function watch_notice() {
		if ( ! current_user_can( self::CAP ) || ! get_option( Bot_Control_Install::WATCH_NOTICE ) ) {
			return;
		}
		$s = Bot_Control_Settings::get();
		if ( 'monitor' !== $s['mode'] ) {
			delete_option( Bot_Control_Install::WATCH_NOTICE );
			return;
		}
		$dismiss = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'botctl_dismiss_watch',
				),
				admin_url( 'admin-post.php' )
			),
			'botctl_dismiss_watch'
		);
		echo '<div id="botctl-watch-notice" class="notice notice-info is-dismissible">';
		echo '<p><strong>' . esc_html__( 'All-IN Crawler & AI Agent Manager is in Watch-only mode.', 'all-in-crawler-ai-agent-manager' ) . '</strong> ';
		echo esc_html__( 'A new install only logs and reports. It does not block crawlers, apply rate limits, shed load, or change robots.txt until you switch to Enforce.', 'all-in-crawler-ai-agent-manager' ) . '</p>';
		echo '<div class="botctl-mode-actions">';
		self::mode_switch_form( 'enforce', 'protection' );
		echo '<a class="button button-link" href="' . esc_url( self::url( 'dashboard' ) ) . '">' . esc_html__( 'Open the dashboard', 'all-in-crawler-ai-agent-manager' ) . '</a>';
		echo '<a class="button button-link" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'all-in-crawler-ai-agent-manager' ) . '</a>';
		echo '</div></div>';
	}

	/**
	 * Optional review request, shown only after the plugin has demonstrably done
	 * something for this site: 14+ days since activation AND at least
	 * REVIEW_MIN_REQUESTS bot requests handled in the last 14 days. Only on this
	 * plugin's Dashboard and Reports tabs, only to admins, dismissible, snoozable,
	 * no reward, no star wording, no effect on features (guidelines #9 and #11).
	 *
	 * @return void
	 */
	private static function review_notice() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		if ( ! in_array( self::current_tab(), array( 'dashboard', 'reports' ), true ) ) {
			return;
		}
		$activated = (int) get_option( Bot_Control_Install::ACTIVATED_AT, 0 );
		if ( $activated < 1 || ( time() - $activated ) < 14 * DAY_IN_SECONDS ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, Bot_Control_Install::REVIEW_META, true ) ) {
			return;
		}
		if ( (int) get_user_meta( $user_id, Bot_Control_Install::REVIEW_SNOOZE_META, true ) > time() ) {
			return;
		}
		$t       = Bot_Control_Stats::totals( 14 );
		$handled = $t['allowed'] + $t['limited'] + $t['blocked'];
		if ( $handled < self::REVIEW_MIN_REQUESTS ) {
			return;
		}

		$url = function ( $action ) {
			return wp_nonce_url( add_query_arg( 'botctl_review', $action ), 'botctl_review' );
		};

		echo '<div id="botctl-review-notice" class="notice notice-info is-dismissible"><p><strong>';
		printf(
			/* translators: 1: bot requests handled, 2: requests limited or blocked */
			esc_html__( 'In the last 14 days All-IN Crawler & AI Agent Manager handled %1$s bot requests on this site and limited or blocked %2$s of them.', 'all-in-crawler-ai-agent-manager' ),
			esc_html( number_format_i18n( $handled ) ),
			esc_html( number_format_i18n( $t['limited'] + $t['blocked'] ) )
		);
		echo '</strong> ';
		echo esc_html__( 'If it has been useful, an honest review on WordPress.org helps other site owners decide whether to try it. It is optional and does not unlock or change anything.', 'all-in-crawler-ai-agent-manager' ) . '</p>';
		echo '<p><a class="button button-primary botctl-review-go" href="' . esc_url( self::REVIEW_URL ) . '" data-done="' . esc_url( $url( 'done' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Leave a review', 'all-in-crawler-ai-agent-manager' ) . '</a> ';
		echo '<a class="button button-secondary botctl-review-later" href="' . esc_url( $url( 'later' ) ) . '">' . esc_html__( 'Maybe later', 'all-in-crawler-ai-agent-manager' ) . '</a> ';
		echo '<a class="button button-link botctl-review-dismiss" href="' . esc_url( $url( 'done' ) ) . '">' . esc_html__( 'I already did / don\'t ask again', 'all-in-crawler-ai-agent-manager' ) . '</a> ';
		echo '<a class="button button-link" href="' . esc_url( 'https://wordpress.org/support/plugin/all-in-crawler-ai-agent-manager/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Something not working? Ask in the support forum', 'all-in-crawler-ai-agent-manager' ) . '</a></p></div>';
	}

	/**
	 * Handle the review notice buttons: "done" hides it for good, "later" snoozes 30 days.
	 * Also accepts the 1.2.1 link (?botctl_dismiss_review=1) so old cached pages keep working.
	 *
	 * @return void
	 */
	public static function maybe_dismiss_review() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified below before any change.
		if ( isset( $_GET['botctl_dismiss_review'] ) ) {
			$action = 'done';
			$nonce  = 'botctl_dismiss_review';
		} elseif ( isset( $_GET['botctl_review'] ) ) {
			$action = sanitize_key( wp_unslash( $_GET['botctl_review'] ) );
			$nonce  = 'botctl_review';
		} else {
			return;
		}
		// phpcs:enable
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		check_admin_referer( $nonce );
		if ( 'later' === $action ) {
			update_user_meta( get_current_user_id(), Bot_Control_Install::REVIEW_SNOOZE_META, time() + 30 * DAY_IN_SECONDS );
		} else {
			update_user_meta( get_current_user_id(), Bot_Control_Install::REVIEW_META, '1' );
		}
		wp_safe_redirect( remove_query_arg( array( 'botctl_review', 'botctl_dismiss_review', '_wpnonce' ) ) );
		exit;
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
	 * Switch between watch-only (monitor) and enforce without editing other settings.
	 *
	 * @return void
	 */
	public static function handle_mode() {
		self::guard( 'mode' );
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		if ( ! in_array( $mode, array( 'monitor', 'enforce' ), true ) ) {
			self::done( 'protection', __( 'Unknown mode.', 'all-in-crawler-ai-agent-manager' ), 'error' );
		}
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'protection'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard().
		if ( ! array_key_exists( $tab, self::tabs() ) ) {
			$tab = 'protection';
		}

		$s         = Bot_Control_Settings::get();
		$s['mode'] = $mode;
		Bot_Control_Settings::update( $s );
		if ( 'enforce' === $mode ) {
			delete_option( Bot_Control_Install::WATCH_NOTICE );
		}
		Bot_Control_Logger::debug( 'Admin', 'Mode switched', array( 'mode' => $mode ) );

		$message = 'enforce' === $mode
			? __( 'Enforce is on. Blocking, rate limits, load shedding and robots.txt changes now apply.', 'all-in-crawler-ai-agent-manager' )
			: __( 'Watch-only is on. Requests are logged and reported, and nothing is blocked, rate-limited or load-shed.', 'all-in-crawler-ai-agent-manager' );
		self::done( $tab, $message );
	}

	/**
	 * Dismiss the fresh-install watch-only notice (no-JavaScript path).
	 *
	 * @return void
	 */
	public static function handle_dismiss_watch() {
		self::guard( 'dismiss_watch' );
		delete_option( Bot_Control_Install::WATCH_NOTICE );
		self::done( 'dashboard', __( 'Notice dismissed. Watch-only mode is unchanged.', 'all-in-crawler-ai-agent-manager' ), 'info' );
	}

	/**
	 * Dismiss the fresh-install notice from the notice "X" button.
	 *
	 * @return void
	 */
	public static function ajax_dismiss_watch() {
		check_ajax_referer( 'botctl_dismiss_watch' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'all-in-crawler-ai-agent-manager' ), 403 );
		}
		delete_option( Bot_Control_Install::WATCH_NOTICE );
		wp_send_json_success();
	}

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
