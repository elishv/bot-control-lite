<?php
/**
 * Scheduled email reports (weekly or monthly), white-label ready for agencies.
 *
 * Contents: bot traffic totals vs the previous period, AI-assistant visits
 * (your AI visibility), impostors caught, top bots, top denied IPs and an
 * estimate of the server time saved by denied requests.
 *
 * The "time saved" estimate uses a measured average page generation time:
 * when reports are enabled, ~2% of normal front-end page views record how long
 * WordPress took (rolling average). No guessing, no hard-coded numbers.
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reports.
 */
final class Bot_Control_Reports {

	const CRON = 'botctl_report';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'send_scheduled' ) );
		add_action( 'botctl_settings_updated', array( __CLASS__, 'schedule' ) );
		add_action( 'shutdown', array( __CLASS__, 'sample_page_time' ), 999 );
	}

	/**
	 * (Re)schedule according to settings.
	 *
	 * @param array|null $s Settings.
	 * @return void
	 */
	public static function schedule( $s = null ) {
		$s = is_array( $s ) ? $s : Bot_Control_Settings::get();
		wp_clear_scheduled_hook( self::CRON );
		if ( empty( $s['report_enabled'] ) ) {
			return;
		}
		// Next Monday 08:00 site time (weekly) or the 1st of next month (monthly).
		$tz   = wp_timezone();
		$next = 'monthly' === $s['report_frequency']
			? new DateTime( 'first day of next month 08:00', $tz )
			: new DateTime( 'next monday 08:00', $tz );
		// WordPress has no built-in monthly recurrence; the monthly report re-schedules itself after sending.
		wp_schedule_single_event( $next->getTimestamp(), self::CRON );
		Bot_Control_Logger::debug( 'Reports', 'Report scheduled', array( 'at' => $next->format( 'c' ), 'frequency' => $s['report_frequency'] ) );
	}

	/**
	 * Cron callback.
	 *
	 * @return void
	 */
	public static function send_scheduled() {
		self::send();
		self::schedule();
	}

	/**
	 * Recipients.
	 *
	 * @param array $s Settings.
	 * @return string[]
	 */
	public static function recipients( array $s ) {
		$list = array();
		foreach ( preg_split( '/[\s,;]+/', (string) $s['report_recipients'] ) as $email ) {
			$email = sanitize_email( $email );
			if ( is_email( $email ) ) {
				$list[] = $email;
			}
		}
		return $list ? $list : array( get_option( 'admin_email' ) );
	}

	/**
	 * Build and send the report.
	 *
	 * @return bool
	 */
	public static function send() {
		$s    = Bot_Control_Settings::get();
		$days = 'monthly' === $s['report_frequency'] ? 30 : 7;
		$data = self::collect( $days );
		$html = self::render( $data, $s );

		$brand = '' !== trim( (string) $s['report_brand'] ) ? $s['report_brand'] : get_bloginfo( 'name' );
		/* translators: 1: brand, 2: site name, 3: number of days */
		$subject = sprintf( __( '[%1$s] Bot report for %2$s – last %3$d days', 'all-in-crawler-ai-agent-manager' ), $brand, get_bloginfo( 'name' ), $days );

		$sent = wp_mail( self::recipients( $s ), $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );

		if ( ! $sent ) {
			Bot_Control_Logger::error( 'Reports', 'wp_mail() failed to send the report', array( 'to' => self::recipients( $s ) ) );
		} else {
			Bot_Control_Logger::debug( 'Reports', 'Report sent', array( 'to' => self::recipients( $s ), 'days' => $days ) );
		}
		return $sent;
	}

	/**
	 * Gather report data.
	 *
	 * @param int $days Period length.
	 * @return array
	 */
	public static function collect( $days ) {
		global $wpdb;
		$t = $wpdb->prefix . 'botctl_stats';

		$today      = current_time( 'Y-m-d' );
		$end        = wp_date( 'Y-m-d', strtotime( $today . ' 12:00:00' ) - DAY_IN_SECONDS ); // Up to yesterday: full days only.
		$start      = wp_date( 'Y-m-d', strtotime( $end . ' 12:00:00' ) - ( $days - 1 ) * DAY_IN_SECONDS );
		$prev_end   = wp_date( 'Y-m-d', strtotime( $start . ' 12:00:00' ) - DAY_IN_SECONDS );
		$prev_start = wp_date( 'Y-m-d', strtotime( $prev_end . ' 12:00:00' ) - ( $days - 1 ) * DAY_IN_SECONDS );

		$totals = function ( $from, $to ) use ( $wpdb, $t ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(allowed),0) a, COALESCE(SUM(limited),0) l, COALESCE(SUM(blocked),0) b FROM {$t} WHERE day BETWEEN %s AND %s", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return array(
				'allowed' => (int) $row['a'],
				'limited' => (int) $row['l'],
				'blocked' => (int) $row['b'],
			);
		};

		$current  = $totals( $start, $end );
		$previous = $totals( $prev_start, $prev_end );

		$categories = (array) $wpdb->get_results( $wpdb->prepare( "SELECT category, SUM(allowed) allowed, SUM(limited) limited, SUM(blocked) blocked, SUM(allowed+limited+blocked) total FROM {$t} WHERE day BETWEEN %s AND %s GROUP BY category ORDER BY total DESC", $start, $end ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$top        = (array) $wpdb->get_results( $wpdb->prepare( "SELECT MAX(label) label, MAX(category) category, SUM(allowed) allowed, SUM(limited) limited, SUM(blocked) blocked, SUM(allowed+limited+blocked) total FROM {$t} WHERE day BETWEEN %s AND %s GROUP BY bot_id ORDER BY total DESC LIMIT 10", $start, $end ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ai         = (array) $wpdb->get_results( $wpdb->prepare( "SELECT MAX(label) label, SUM(allowed+limited+blocked) total FROM {$t} WHERE day BETWEEN %s AND %s AND category = 'ai_search' GROUP BY bot_id ORDER BY total DESC LIMIT 5", $start, $end ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$impostors  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(blocked+limited),0) FROM {$t} WHERE day BETWEEN %s AND %s AND category = 'impostor'", $start, $end ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ips        = (array) $wpdb->get_results( $wpdb->prepare( "SELECT ip, MAX(label) label, COUNT(*) events FROM {$wpdb->prefix}botctl_events WHERE ts BETWEEN %s AND %s GROUP BY ip ORDER BY events DESC LIMIT 5", $start . ' 00:00:00', $end . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$runtime = get_option( Bot_Control_Load_Monitor::RUNTIME_OPTION, array() );
		$avg_ms  = ( is_array( $runtime ) && ! empty( $runtime['page_samples'] ) && $runtime['page_samples'] >= 20 ) ? (float) $runtime['avg_page_ms'] : 0.0;
		$denied  = $current['limited'] + $current['blocked'];

		return array(
			'days'       => $days,
			'start'      => $start,
			'end'        => $end,
			'current'    => $current,
			'previous'   => $previous,
			'categories' => $categories,
			'top'        => $top,
			'ai'         => $ai,
			'ai_total'   => array_sum( wp_list_pluck( $ai, 'total' ) ),
			'impostors'  => $impostors,
			'ips'        => $ips,
			'avg_ms'     => $avg_ms,
			'saved_s'    => $avg_ms > 0 ? (int) round( $denied * $avg_ms / 1000 ) : 0,
		);
	}

	/**
	 * Percentage change label.
	 *
	 * @param int $now  Current.
	 * @param int $prev Previous.
	 * @return string
	 */
	private static function change( $now, $prev ) {
		if ( $prev <= 0 ) {
			return $now > 0 ? __( 'new', 'all-in-crawler-ai-agent-manager' ) : '–';
		}
		$pct = round( ( $now - $prev ) / $prev * 100 );
		// Left-to-right marks keep "+12%" readable inside right-to-left (Hebrew) text.
		return "\u{200E}" . ( $pct > 0 ? '+' : '' ) . $pct . "%\u{200E}";
	}

	/**
	 * Human duration.
	 *
	 * @param int $seconds Seconds.
	 * @return string
	 */
	private static function duration( $seconds ) {
		if ( $seconds >= 3600 ) {
			/* translators: %s: hours */
			return sprintf( __( '%s hours', 'all-in-crawler-ai-agent-manager' ), number_format_i18n( $seconds / 3600, 1 ) );
		}
		if ( $seconds >= 60 ) {
			/* translators: %s: minutes */
			return sprintf( __( '%s minutes', 'all-in-crawler-ai-agent-manager' ), number_format_i18n( $seconds / 60 ) );
		}
		/* translators: %s: seconds */
		return sprintf( __( '%s seconds', 'all-in-crawler-ai-agent-manager' ), number_format_i18n( $seconds ) );
	}

	/**
	 * Render the HTML email (inline styles for email clients; RTL aware).
	 *
	 * @param array $d Data.
	 * @param array $s Settings.
	 * @return string
	 */
	public static function render( array $d, array $s ) {
		$brand = '' !== trim( (string) $s['report_brand'] ) ? $s['report_brand'] : get_bloginfo( 'name' );
		$dir   = is_rtl() ? 'rtl' : 'ltr';
		$align = is_rtl() ? 'right' : 'left';
		$c     = $d['current'];
		$p     = $d['previous'];
		$total = $c['allowed'] + $c['limited'] + $c['blocked'];
		$ptot  = $p['allowed'] + $p['limited'] + $p['blocked'];

		$td  = 'style="padding:8px 10px;border-bottom:1px solid #eee;text-align:' . $align . '"';
		$tdn = 'style="padding:8px 10px;border-bottom:1px solid #eee;text-align:' . ( is_rtl() ? 'left' : 'right' ) . ';white-space:nowrap"';

		$kpi = function ( $label, $value, $sub ) {
			return '<td style="padding:14px;background:#f6f7f9;border-radius:8px;width:25%;vertical-align:top">'
				. '<div style="font-size:12px;color:#646970;text-transform:uppercase;letter-spacing:.03em">' . esc_html( $label ) . '</div>'
				. '<div style="font-size:24px;font-weight:700;color:#1d2327;margin:4px 0">' . esc_html( $value ) . '</div>'
				. '<div style="font-size:12px;color:#646970">' . esc_html( $sub ) . '</div></td>';
		};

		ob_start();
		?>
<!DOCTYPE html>
<html dir="<?php echo esc_attr( $dir ); ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;background:#eef0f3;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1d2327" dir="<?php echo esc_attr( $dir ); ?>">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f3;padding:24px 0"><tr><td align="center">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#fff;border-radius:10px;overflow:hidden">
	<tr><td style="background:#1d2327;color:#fff;padding:20px 24px;text-align:<?php echo esc_attr( $align ); ?>">
		<div style="font-size:13px;opacity:.8"><?php echo esc_html( $brand ); ?></div>
		<div style="font-size:20px;font-weight:700;margin-top:4px"><?php esc_html_e( 'Bot traffic report', 'all-in-crawler-ai-agent-manager' ); ?> – <?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
		<div style="font-size:13px;opacity:.8;margin-top:4px"><?php echo esc_html( $d['start'] . ' → ' . $d['end'] ); ?></div>
	</td></tr>
	<tr><td style="padding:20px 24px">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="6"><tr>
			<?php
			/* translators: %s: change vs previous period */
			echo $kpi( __( 'Bot requests', 'all-in-crawler-ai-agent-manager' ), number_format_i18n( $total ), sprintf( __( '%s vs previous period', 'all-in-crawler-ai-agent-manager' ), self::change( $total, $ptot ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $kpi( __( 'Stopped', 'all-in-crawler-ai-agent-manager' ), number_format_i18n( $c['limited'] + $c['blocked'] ), __( 'rate-limited or blocked', 'all-in-crawler-ai-agent-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $kpi( __( 'AI assistant visits', 'all-in-crawler-ai-agent-manager' ), number_format_i18n( $d['ai_total'] ), __( 'ChatGPT, Claude, Perplexity…', 'all-in-crawler-ai-agent-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $kpi( __( 'Fake bots caught', 'all-in-crawler-ai-agent-manager' ), number_format_i18n( $d['impostors'] ), __( 'pretending to be Google/Bing', 'all-in-crawler-ai-agent-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</tr></table>

		<?php if ( $d['saved_s'] > 0 ) : ?>
		<p style="background:#e7f5ea;color:#00661a;padding:12px 14px;border-radius:8px;margin:16px 0">
			<?php
			/* translators: 1: duration, 2: milliseconds */
			printf( esc_html__( 'Estimated server time saved: %1$s (based on a measured average page time of %2$s ms).', 'all-in-crawler-ai-agent-manager' ), esc_html( self::duration( $d['saved_s'] ) ), esc_html( number_format_i18n( $d['avg_ms'] ) ) );
			?>
		</p>
		<?php endif; ?>

		<h3 style="margin:22px 0 8px;font-size:16px"><?php esc_html_e( 'By category', 'all-in-crawler-ai-agent-manager' ); ?></h3>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse">
			<tr style="background:#f6f7f9"><th <?php echo $td; // phpcs:ignore ?>><?php esc_html_e( 'Category', 'all-in-crawler-ai-agent-manager' ); ?></th><th <?php echo $tdn; // phpcs:ignore ?>><?php esc_html_e( 'Allowed', 'all-in-crawler-ai-agent-manager' ); ?></th><th <?php echo $tdn; // phpcs:ignore ?>><?php esc_html_e( 'Limited', 'all-in-crawler-ai-agent-manager' ); ?></th><th <?php echo $tdn; // phpcs:ignore ?>><?php esc_html_e( 'Blocked', 'all-in-crawler-ai-agent-manager' ); ?></th></tr>
			<?php foreach ( $d['categories'] as $row ) : ?>
			<tr><td <?php echo $td; // phpcs:ignore ?>><?php echo esc_html( self::category_label( $row['category'] ) ); ?></td><td <?php echo $tdn; // phpcs:ignore ?>><?php echo esc_html( number_format_i18n( (int) $row['allowed'] ) ); ?></td><td <?php echo $tdn; // phpcs:ignore ?>><?php echo esc_html( number_format_i18n( (int) $row['limited'] ) ); ?></td><td <?php echo $tdn; // phpcs:ignore ?>><?php echo esc_html( number_format_i18n( (int) $row['blocked'] ) ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( empty( $d['categories'] ) ) : ?>
			<tr><td colspan="4" <?php echo $td; // phpcs:ignore ?>><?php esc_html_e( 'No bot traffic recorded in this period.', 'all-in-crawler-ai-agent-manager' ); ?></td></tr>
			<?php endif; ?>
		</table>

		<?php if ( $d['top'] ) : ?>
		<h3 style="margin:22px 0 8px;font-size:16px"><?php esc_html_e( 'Top bots', 'all-in-crawler-ai-agent-manager' ); ?></h3>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse">
			<?php foreach ( $d['top'] as $row ) : ?>
			<tr><td <?php echo $td; // phpcs:ignore ?>><strong><?php echo esc_html( $row['label'] ); ?></strong><br><span style="color:#646970"><?php echo esc_html( self::category_label( $row['category'] ) ); ?></span></td><td <?php echo $tdn; // phpcs:ignore ?>><?php echo esc_html( number_format_i18n( (int) $row['total'] ) ); ?></td></tr>
			<?php endforeach; ?>
		</table>
		<?php endif; ?>

		<?php if ( $d['ai'] ) : ?>
		<h3 style="margin:22px 0 8px;font-size:16px"><?php esc_html_e( 'AI visibility – assistants that fetched your pages', 'all-in-crawler-ai-agent-manager' ); ?></h3>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse">
			<?php foreach ( $d['ai'] as $row ) : ?>
			<tr><td <?php echo $td; // phpcs:ignore ?>><?php echo esc_html( $row['label'] ); ?></td><td <?php echo $tdn; // phpcs:ignore ?>><?php echo esc_html( number_format_i18n( (int) $row['total'] ) ); ?></td></tr>
			<?php endforeach; ?>
		</table>
		<?php endif; ?>

		<?php if ( $d['ips'] ) : ?>
		<h3 style="margin:22px 0 8px;font-size:16px"><?php esc_html_e( 'Most denied IPs', 'all-in-crawler-ai-agent-manager' ); ?></h3>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse">
			<?php foreach ( $d['ips'] as $row ) : ?>
			<tr><td <?php echo $td; // phpcs:ignore ?>><code><?php echo esc_html( $row['ip'] ); ?></code></td><td <?php echo $td; // phpcs:ignore ?>><?php echo esc_html( $row['label'] ); ?></td></tr>
			<?php endforeach; ?>
		</table>
		<?php endif; ?>

		<p style="margin:24px 0 0"><a href="<?php echo esc_url( admin_url( 'admin.php?page=all-in-crawler-ai-agent-manager' ) ); ?>" style="display:inline-block;background:#2271b1;color:#fff;text-decoration:none;padding:10px 16px;border-radius:6px"><?php esc_html_e( 'Open the All-IN Crawler & AI Agent Manager dashboard', 'all-in-crawler-ai-agent-manager' ); ?></a></p>
	</td></tr>
	<tr><td style="padding:14px 24px;background:#f6f7f9;color:#646970;font-size:12px;text-align:<?php echo esc_attr( $align ); ?>">
		<?php
		/* translators: %s: brand */
		printf( esc_html__( 'Sent by %s using All-IN Crawler & AI Agent Manager. Change or stop these emails under All-IN Crawler → Reports.', 'all-in-crawler-ai-agent-manager' ), esc_html( $brand ) );

		echo '<br>';
		printf(
			/* translators: 1: support forum URL, 2: review URL */
			wp_kses( __( 'Questions? <a href="%1$s">Support forum</a>. Finding it useful? <a href="%2$s">An honest review</a> helps others find it.', 'all-in-crawler-ai-agent-manager' ), array( 'a' => array( 'href' => array() ) ) ),
			esc_url( 'https://wordpress.org/support/plugin/all-in-crawler-ai-agent-manager/' ),
			esc_url( Bot_Control_Admin::REVIEW_URL )
		);
		?>
	</td></tr>
</table>
</td></tr></table>
</body></html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Category label without loading the admin class.
	 *
	 * @param string $cat Category.
	 * @return string
	 */
	private static function category_label( $cat ) {
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
	 * Measure page generation time on ~2% of normal front-end page views.
	 *
	 * @return void
	 */
	public static function sample_page_time() {
		$s = Bot_Control_Settings::get();
		if ( empty( $s['report_enabled'] ) || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$decision = Bot_Control_Firewall::decision();
		if ( isset( $decision['action'] ) && ! in_array( $decision['action'], array( 'allow', 'skip' ), true ) ) {
			return; // Denied requests are not page views.
		}
		if ( 200 !== http_response_code() || 1 !== wp_rand( 1, 50 ) ) {
			return;
		}

		$ms      = timer_float() * 1000;
		$runtime = get_option( Bot_Control_Load_Monitor::RUNTIME_OPTION, array() );
		$runtime = is_array( $runtime ) ? $runtime : array();
		$avg     = isset( $runtime['avg_page_ms'] ) ? (float) $runtime['avg_page_ms'] : 0.0;
		$n       = isset( $runtime['page_samples'] ) ? (int) $runtime['page_samples'] : 0;

		// Rolling average: exact mean for the first 100 samples, then exponential.
		$runtime['avg_page_ms']  = round( $n < 100 ? ( $avg * $n + $ms ) / ( $n + 1 ) : $avg * 0.99 + $ms * 0.01, 1 );
		$runtime['page_samples'] = $n + 1;
		update_option( Bot_Control_Load_Monitor::RUNTIME_OPTION, $runtime, true );
	}
}
