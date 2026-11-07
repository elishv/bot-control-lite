<?php
/**
 * Logs tab.
 *
 * @package AllInCrawlerAIAgentManager
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$botctl_per_page = 50;
$botctl_paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$botctl_action   = isset( $_GET['log_action'] ) ? sanitize_key( wp_unslash( $_GET['log_action'] ) ) : '';
$botctl_search   = isset( $_GET['log_ip'] ) ? sanitize_text_field( wp_unslash( $_GET['log_ip'] ) ) : '';
// phpcs:enable

$botctl_where = array( '1=1' );
$botctl_args  = array();
if ( '' !== $botctl_action ) {
	$botctl_where[] = 'action = %s';
	$botctl_args[]  = $botctl_action;
}
if ( '' !== $botctl_search ) {
	$botctl_where[] = '(ip LIKE %s OR label LIKE %s OR ua LIKE %s)';
	$botctl_like    = '%' . $wpdb->esc_like( $botctl_search ) . '%';
	$botctl_args[]  = $botctl_like;
	$botctl_args[]  = $botctl_like;
	$botctl_args[]  = $botctl_like;
}
$botctl_where_sql = implode( ' AND ', $botctl_where );
$botctl_table     = $wpdb->prefix . 'botctl_events';

$botctl_count_sql = "SELECT COUNT(*) FROM {$botctl_table} WHERE {$botctl_where_sql}";
$botctl_total     = (int) ( $botctl_args ? $wpdb->get_var( $wpdb->prepare( $botctl_count_sql, $botctl_args ) ) : $wpdb->get_var( $botctl_count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

$botctl_rows_sql = "SELECT * FROM {$botctl_table} WHERE {$botctl_where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
$botctl_rows     = (array) $wpdb->get_results( $wpdb->prepare( $botctl_rows_sql, array_merge( $botctl_args, array( $botctl_per_page, ( $botctl_paged - 1 ) * $botctl_per_page ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

$botctl_pages = max( 1, (int) ceil( $botctl_total / $botctl_per_page ) );
$botctl_allow = Bot_Control_IP::parse_list( $s['ip_allowlist'] );
$botctl_block = Bot_Control_IP::parse_list( $s['ip_blocklist'] );
?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'Denied requests', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Every denied request is counted in the statistics, but the log keeps at most 3 rows per IP and action per window, so a bot hammering the site cannot flood your database.', 'all-in-crawler-ai-agent-manager' ); ?>
		<?php if ( empty( $s['log_enabled'] ) ) : ?>
			<strong class="botctl-bad"><?php esc_html_e( 'Logging is currently disabled (Advanced tab).', 'all-in-crawler-ai-agent-manager' ); ?></strong>
		<?php endif; ?>
	</p>

	<form method="get" class="botctl-log-filter">
		<input type="hidden" name="page" value="<?php echo esc_attr( Bot_Control_Admin::SLUG ); ?>">
		<input type="hidden" name="tab" value="logs">
		<select name="log_action">
			<option value=""><?php esc_html_e( 'All actions', 'all-in-crawler-ai-agent-manager' ); ?></option>
			<?php foreach ( array( 'blocked', 'limited', 'impostor', 'shed', 'ip_blocked' ) as $botctl_a ) : ?>
				<option value="<?php echo esc_attr( $botctl_a ); ?>" <?php selected( $botctl_action, $botctl_a ); ?>><?php echo esc_html( Bot_Control_Admin::action_label( $botctl_a ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="log_ip" value="<?php echo esc_attr( $botctl_search ); ?>" placeholder="<?php esc_attr_e( 'IP, bot or User-Agent…', 'all-in-crawler-ai-agent-manager' ); ?>">
		<button class="button"><?php esc_html_e( 'Filter', 'all-in-crawler-ai-agent-manager' ); ?></button>
		<span class="botctl-count">
		<?php
		/* translators: %s: number of rows */
		printf( esc_html__( '%s entries', 'all-in-crawler-ai-agent-manager' ), esc_html( number_format_i18n( $botctl_total ) ) );
		?>
		</span>
	</form>

	<table class="widefat striped botctl-log">
		<thead><tr>
			<th><?php esc_html_e( 'Time', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'IP', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Bot', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Action', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Request', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'User-Agent', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th></th>
		</tr></thead>
		<tbody>
		<?php if ( empty( $botctl_rows ) ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No entries.', 'all-in-crawler-ai-agent-manager' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $botctl_rows as $botctl_row ) : ?>
			<tr>
				<td class="botctl-nowrap"><?php echo esc_html( $botctl_row['ts'] ); ?></td>
				<td><code><?php echo esc_html( $botctl_row['ip'] ); ?></code></td>
				<td>
					<strong><?php echo esc_html( $botctl_row['label'] ); ?></strong><br>
					<span class="description"><?php echo esc_html( Bot_Control_Admin::category_label( $botctl_row['category'] ) ); ?></span>
				</td>
				<td>
					<span class="botctl-pill botctl-pill-<?php echo esc_attr( 'limited' === $botctl_row['action'] || 'shed' === $botctl_row['action'] ? 'limit' : 'block' ); ?>"><?php echo esc_html( Bot_Control_Admin::action_label( $botctl_row['action'] ) ); ?></span>
					<?php if ( ! (int) $botctl_row['enforced'] ) : ?>
						<br><em class="description"><?php esc_html_e( 'watch-only', 'all-in-crawler-ai-agent-manager' ); ?></em>
					<?php endif; ?>
				</td>
				<td class="botctl-uri"><code><?php echo esc_html( $botctl_row['method'] . ' ' . $botctl_row['uri'] ); ?></code></td>
				<td class="botctl-ua"><?php echo esc_html( $botctl_row['ua'] ); ?></td>
				<td class="botctl-actions">
					<?php if ( ! in_array( $botctl_row['ip'], $botctl_block, true ) ) : ?>
						<?php Bot_Control_Admin::form_open( 'ip_action', array( 'ip' => $botctl_row['ip'], 'list' => 'block', 'back' => 'logs' ), 'botctl-inline' ); ?>
						<button class="button button-small botctl-confirm"><?php esc_html_e( 'Block IP', 'all-in-crawler-ai-agent-manager' ); ?></button></form>
					<?php endif; ?>
					<?php if ( ! in_array( $botctl_row['ip'], $botctl_allow, true ) ) : ?>
						<?php Bot_Control_Admin::form_open( 'ip_action', array( 'ip' => $botctl_row['ip'], 'list' => 'allow', 'back' => 'logs' ), 'botctl-inline' ); ?>
						<button class="button button-small botctl-confirm"><?php esc_html_e( 'Allow IP', 'all-in-crawler-ai-agent-manager' ); ?></button></form>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $botctl_pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $botctl_paged,
						'total'   => $botctl_pages,
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>

	<?php Bot_Control_Admin::form_open( 'clear_logs' ); ?>
		<p><button class="button botctl-confirm"><?php esc_html_e( 'Clear log', 'all-in-crawler-ai-agent-manager' ); ?></button></p>
	</form>
</div>
