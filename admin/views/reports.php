<?php
/**
 * Reports tab.
 *
 * @package AllInCrawlerAIAgentManager
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_next    = wp_next_scheduled( Bot_Control_Reports::CRON );
$botctl_runtime = get_option( Bot_Control_Load_Monitor::RUNTIME_OPTION, array() );
$botctl_samples = is_array( $botctl_runtime ) && isset( $botctl_runtime['page_samples'] ) ? (int) $botctl_runtime['page_samples'] : 0;
?>
<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'reports' ) ); ?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'Email reports', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description"><?php esc_html_e( 'A clear summary by email: bot traffic compared with the previous period, how often AI assistants fetched your pages, fake bots caught, top bots and IPs, and the server time saved. Agencies: set your own brand name to send it to clients as your report.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Send reports', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td><label><input type="checkbox" name="botctl[report_enabled]" value="1" <?php checked( $s['report_enabled'] ); ?>> <?php esc_html_e( 'Email a report automatically', 'all-in-crawler-ai-agent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Frequency', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<?php
				Bot_Control_Admin::select(
					'botctl[report_frequency]',
					array(
						'weekly'  => __( 'Weekly (Monday 08:00, last 7 days)', 'all-in-crawler-ai-agent-manager' ),
						'monthly' => __( 'Monthly (1st of the month 08:00, last 30 days)', 'all-in-crawler-ai-agent-manager' ),
					),
					$s['report_frequency']
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-rcpt"><?php esc_html_e( 'Recipients', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<input id="botctl-rcpt" type="text" class="large-text" name="botctl[report_recipients]" value="<?php echo esc_attr( $s['report_recipients'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
				<p class="description"><?php esc_html_e( 'Comma-separated. Empty = the site admin email.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-brand"><?php esc_html_e( 'Brand name', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<input id="botctl-brand" type="text" class="regular-text" name="botctl[report_brand]" value="<?php echo esc_attr( $s['report_brand'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<p class="description"><?php esc_html_e( 'Shown in the subject, header and footer (white-label). Empty = site name.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
	</table>
	<p class="description">
		<?php
		if ( $botctl_next ) {
			/* translators: %s: date */
			printf( esc_html__( 'Next report: %s.', 'all-in-crawler-ai-agent-manager' ), esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $botctl_next ) ) );
		}
		echo ' ';
		/* translators: %d: samples */
		printf( esc_html__( 'Page-time samples for the "time saved" estimate: %d (shown in reports from 20 samples).', 'all-in-crawler-ai-agent-manager' ), (int) $botctl_samples );
		?>
	</p>
</div>
<?php submit_button( __( 'Save report settings', 'all-in-crawler-ai-agent-manager' ) ); ?>
</form>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Try it', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<div class="botctl-buttons">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank" class="botctl-inline">
			<input type="hidden" name="action" value="botctl_preview_report">
			<?php wp_nonce_field( 'botctl_preview_report' ); ?>
			<button class="button"><?php esc_html_e( 'Preview report', 'all-in-crawler-ai-agent-manager' ); ?> ↗</button>
		</form>
		<?php Bot_Control_Admin::form_open( 'send_report', array(), 'botctl-inline' ); ?>
			<button class="button button-primary"><?php esc_html_e( 'Send report now', 'all-in-crawler-ai-agent-manager' ); ?></button>
		</form>
	</div>
	<p class="description"><?php esc_html_e( 'Tip: WordPress\' default mail is often filtered as spam. Use an SMTP plugin for reliable delivery.', 'all-in-crawler-ai-agent-manager' ); ?></p>
</div>
