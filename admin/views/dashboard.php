<?php
/**
 * Dashboard tab.
 *
 * @package AllInCrawlerAIAgentManager
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$botctl_client  = Bot_Control_IP::client( $s );
$botctl_load    = Bot_Control_Load_Monitor::state( $s );
$botctl_backend = Bot_Control_Rate_Limiter::backend();
$botctl_cats    = Bot_Control_Stats::by_category( 7 );
$botctl_top     = Bot_Control_Stats::top_bots( 7, 20 );
$botctl_daily   = Bot_Control_Stats::daily( 14 );

$botctl_since_24h = wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
$botctl_offenders = (array) $wpdb->get_results(
	$wpdb->prepare(
		"SELECT ip, COUNT(*) AS events, MAX(label) AS label, MAX(ts) AS last_seen FROM {$wpdb->prefix}botctl_events WHERE ts >= %s GROUP BY ip ORDER BY events DESC LIMIT 10",
		$botctl_since_24h
	),
	ARRAY_A
);

$botctl_backend_labels = array(
	'apcu'         => __( 'APCu (shared memory) – fastest', 'all-in-crawler-ai-agent-manager' ),
	'object_cache' => __( 'Persistent object cache (Redis/Memcached) – fast', 'all-in-crawler-ai-agent-manager' ),
	'database'     => __( 'Database – works everywhere, slowest', 'all-in-crawler-ai-agent-manager' ),
);
?>
<div class="botctl-cards">
	<div class="botctl-card">
		<h3><?php esc_html_e( 'Protection', 'all-in-crawler-ai-agent-manager' ); ?></h3>
		<?php if ( empty( $s['enabled'] ) ) : ?>
			<p class="botctl-big botctl-bad"><?php esc_html_e( 'Disabled', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php elseif ( 'monitor' === $s['mode'] ) : ?>
			<p class="botctl-big botctl-warn"><?php esc_html_e( 'Watch-only', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php else : ?>
			<p class="botctl-big botctl-good"><?php esc_html_e( 'Enforcing', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php endif; ?>
		<p class="description">
			<?php
			if ( empty( $s['enabled'] ) ) {
				esc_html_e( 'Turn it on from the Protection tab when you want decisions to be logged.', 'all-in-crawler-ai-agent-manager' );
			} elseif ( 'monitor' === $s['mode'] ) {
				esc_html_e( 'Logging only. Nothing is blocked, rate-limited or load-shed, and robots.txt is unchanged until you switch to Enforce.', 'all-in-crawler-ai-agent-manager' );
			} else {
				esc_html_e( 'Rules are applied while WordPress loads plugins, before the theme and most of the site start.', 'all-in-crawler-ai-agent-manager' );
			}
			?>
		</p>
	</div>

	<div class="botctl-card">
		<h3><?php esc_html_e( 'Rate-limit storage', 'all-in-crawler-ai-agent-manager' ); ?></h3>
		<p class="botctl-big <?php echo 'database' === $botctl_backend ? 'botctl-warn' : 'botctl-good'; ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $botctl_backend ) ) ); ?></p>
		<p class="description"><?php echo esc_html( $botctl_backend_labels[ $botctl_backend ] ); ?>
		<?php if ( 'database' === $botctl_backend ) : ?>
			<?php esc_html_e( 'Ask your host to enable APCu or Redis for the best protection under heavy load.', 'all-in-crawler-ai-agent-manager' ); ?>
		<?php endif; ?>
		</p>
	</div>

	<div class="botctl-card">
		<h3><?php esc_html_e( 'Your IP as seen by All-IN Crawler & AI Agent Manager', 'all-in-crawler-ai-agent-manager' ); ?></h3>
		<p class="botctl-big"><code><?php echo esc_html( $botctl_client['ip'] ); ?></code></p>
		<p class="description">
			<?php
			/* translators: %s: header name */
			printf( esc_html__( 'Source: %s.', 'all-in-crawler-ai-agent-manager' ), esc_html( $botctl_client['source'] ) );
			if ( Bot_Control_IP::is_private( $botctl_client['ip'] ) ) {
				echo ' <strong class="botctl-bad">' . esc_html__( 'This is a private/proxy address – configure the IP source on the Advanced tab before enabling human rate limiting, or all visitors will share one counter.', 'all-in-crawler-ai-agent-manager' ) . '</strong>';
			}
			?>
		</p>
	</div>

	<div class="botctl-card">
		<h3><?php esc_html_e( 'Server load', 'all-in-crawler-ai-agent-manager' ); ?></h3>
		<?php if ( $botctl_load['supported'] ) : ?>
			<p class="botctl-big <?php echo $botctl_load['overloaded'] ? 'botctl-bad' : 'botctl-good'; ?>">
				<?php echo esc_html( number_format_i18n( $botctl_load['per_core'], 2 ) ); ?>
				<small>/ <?php echo esc_html( number_format_i18n( (float) $s['load_threshold'], 2 ) ); ?> <?php esc_html_e( 'per core', 'all-in-crawler-ai-agent-manager' ); ?></small>
			</p>
			<p class="description">
				<?php
				/* translators: 1: load, 2: cores */
				printf( esc_html__( 'Load average %1$s on %2$d CPU cores.', 'all-in-crawler-ai-agent-manager' ), esc_html( number_format_i18n( $botctl_load['load'], 2 ) ), (int) $botctl_load['cores'] );
				echo ' ';
				echo $s['load_shedding'] ? esc_html__( 'Load shedding is on.', 'all-in-crawler-ai-agent-manager' ) : esc_html__( 'Load shedding is off.', 'all-in-crawler-ai-agent-manager' );
				?>
			</p>
		<?php else : ?>
			<p class="botctl-big botctl-warn"><?php esc_html_e( 'Unavailable', 'all-in-crawler-ai-agent-manager' ); ?></p>
			<p class="description"><?php esc_html_e( 'The server does not expose its load average or CPU count. Set the CPU core count manually on the Protection tab to enable load shedding.', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<div class="botctl-grid">
	<div class="botctl-panel">
		<h2><?php esc_html_e( 'Last 7 days by category', 'all-in-crawler-ai-agent-manager' ); ?></h2>
		<?php if ( empty( $botctl_cats ) ) : ?>
			<p class="description"><?php esc_html_e( 'No bot traffic recorded yet. Statistics appear as soon as bots visit your site.', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php else : ?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Category', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th class="num"><?php esc_html_e( 'Allowed', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th class="num"><?php esc_html_e( 'Limited', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th class="num"><?php esc_html_e( 'Blocked', 'all-in-crawler-ai-agent-manager' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $botctl_cats as $botctl_row ) : ?>
				<tr>
					<td><?php echo esc_html( Bot_Control_Admin::category_label( $botctl_row['category'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( (int) $botctl_row['allowed'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( (int) $botctl_row['limited'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( (int) $botctl_row['blocked'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<div class="botctl-panel">
		<h2><?php esc_html_e( 'Last 14 days', 'all-in-crawler-ai-agent-manager' ); ?></h2>
		<?php
		$botctl_max = 1;
		foreach ( $botctl_daily as $botctl_row ) {
			$botctl_max = max( $botctl_max, (int) $botctl_row['allowed'] + (int) $botctl_row['limited'] + (int) $botctl_row['blocked'] );
		}
		?>
		<?php if ( empty( $botctl_daily ) ) : ?>
			<p class="description"><?php esc_html_e( 'No data yet.', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php else : ?>
			<div class="botctl-legend">
				<span class="botctl-sw botctl-sw-allowed"></span><?php esc_html_e( 'Allowed', 'all-in-crawler-ai-agent-manager' ); ?>
				<span class="botctl-sw botctl-sw-limited"></span><?php esc_html_e( 'Limited', 'all-in-crawler-ai-agent-manager' ); ?>
				<span class="botctl-sw botctl-sw-blocked"></span><?php esc_html_e( 'Blocked', 'all-in-crawler-ai-agent-manager' ); ?>
			</div>
			<div class="botctl-bars">
			<?php foreach ( $botctl_daily as $botctl_row ) : ?>
				<?php
				$botctl_total = (int) $botctl_row['allowed'] + (int) $botctl_row['limited'] + (int) $botctl_row['blocked'];
				$botctl_tip   = sprintf(
					/* translators: 1: date, 2: allowed, 3: limited, 4: blocked */
					__( '%1$s — allowed %2$s, limited %3$s, blocked %4$s', 'all-in-crawler-ai-agent-manager' ),
					$botctl_row['day'],
					number_format_i18n( (int) $botctl_row['allowed'] ),
					number_format_i18n( (int) $botctl_row['limited'] ),
					number_format_i18n( (int) $botctl_row['blocked'] )
				);
				?>
				<div class="botctl-bar-row" title="<?php echo esc_attr( $botctl_tip ); ?>">
					<span class="botctl-bar-day"><?php echo esc_html( wp_date( 'M j', strtotime( $botctl_row['day'] . ' 12:00:00' ) ) ); ?></span>
					<span class="botctl-bar">
						<span class="botctl-seg botctl-sw-allowed" style="width:<?php echo esc_attr( round( 100 * (int) $botctl_row['allowed'] / $botctl_max, 2 ) ); ?>%"></span>
						<span class="botctl-seg botctl-sw-limited" style="width:<?php echo esc_attr( round( 100 * (int) $botctl_row['limited'] / $botctl_max, 2 ) ); ?>%"></span>
						<span class="botctl-seg botctl-sw-blocked" style="width:<?php echo esc_attr( round( 100 * (int) $botctl_row['blocked'] / $botctl_max, 2 ) ); ?>%"></span>
					</span>
					<span class="botctl-bar-total"><?php echo esc_html( number_format_i18n( $botctl_total ) ); ?></span>
				</div>
			<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="botctl-grid">
	<div class="botctl-panel">
		<h2><?php esc_html_e( 'Top bots – last 7 days', 'all-in-crawler-ai-agent-manager' ); ?></h2>
		<?php if ( empty( $botctl_top ) ) : ?>
			<p class="description"><?php esc_html_e( 'No data yet.', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php else : ?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Bot', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th><?php esc_html_e( 'Category', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th class="num"><?php esc_html_e( 'Allowed', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th class="num"><?php esc_html_e( 'Limited', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th class="num"><?php esc_html_e( 'Blocked', 'all-in-crawler-ai-agent-manager' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $botctl_top as $botctl_row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $botctl_row['label'] ); ?></strong></td>
					<td><?php echo esc_html( Bot_Control_Admin::category_label( $botctl_row['category'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( (int) $botctl_row['allowed'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( (int) $botctl_row['limited'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( (int) $botctl_row['blocked'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Tip: an unidentified bot with lots of traffic? Add it on the Custom Bots tab to give it its own rule.', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="botctl-panel">
		<h2><?php esc_html_e( 'Most denied IPs – last 24 hours', 'all-in-crawler-ai-agent-manager' ); ?></h2>
		<?php if ( empty( $botctl_offenders ) ) : ?>
			<p class="description"><?php esc_html_e( 'Nothing denied in the last 24 hours.', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php else : ?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'IP', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th><?php esc_html_e( 'Identified as', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th class="num"><?php esc_html_e( 'Logged events', 'all-in-crawler-ai-agent-manager' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $botctl_offenders as $botctl_row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $botctl_row['ip'] ); ?></code></td>
					<td><?php echo esc_html( $botctl_row['label'] ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( (int) $botctl_row['events'] ) ); ?></td>
					<td class="botctl-actions">
						<?php Bot_Control_Admin::form_open( 'ip_action', array( 'ip' => $botctl_row['ip'], 'list' => 'block', 'back' => 'dashboard' ), 'botctl-inline' ); ?>
						<button class="button button-small botctl-confirm"><?php esc_html_e( 'Block IP', 'all-in-crawler-ai-agent-manager' ); ?></button></form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Event logging is throttled (max 3 rows per IP per window), so counts here show activity, not total requests.', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php endif; ?>
	</div>
</div>
