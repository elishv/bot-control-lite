<?php
/**
 * Reports tab.
 *
 * @package BotControl
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_next    = wp_next_scheduled( Bot_Control_Reports::CRON );
$botctl_runtime = get_option( Bot_Control_Load_Monitor::RUNTIME_OPTION, array() );
$botctl_samples = is_array( $botctl_runtime ) && isset( $botctl_runtime['page_samples'] ) ? (int) $botctl_runtime['page_samples'] : 0;
?>
<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'reports' ) ); ?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'Email reports', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'A clear summary by email: bot traffic compared with the previous period, how often AI assistants fetched your pages, fake bots caught, top bots and IPs, and the server time saved. Agencies: set your own brand name to send it to clients as your report.', 'bot-control' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Send reports', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[report_enabled]" value="1" <?php checked( $s['report_enabled'] ); ?>> <?php esc_html_e( 'Email a report automatically', 'bot-control' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Frequency', 'bot-control' ); ?></th>
			<td>
				<?php
				Bot_Control_Admin::select(
					'botctl[report_frequency]',
					array(
						'weekly'  => __( 'Weekly (Monday 08:00, last 7 days)', 'bot-control' ),
						'monthly' => __( 'Monthly (1st of the month 08:00, last 30 days)', 'bot-control' ),
					),
					$s['report_frequency']
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-rcpt"><?php esc_html_e( 'Recipients', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-rcpt" type="text" class="large-text" name="botctl[report_recipients]" value="<?php echo esc_attr( $s['report_recipients'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
				<p class="description"><?php esc_html_e( 'Comma-separated. Empty = the site admin email.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-brand"><?php esc_html_e( 'Brand name', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-brand" type="text" class="regular-text" name="botctl[report_brand]" value="<?php echo esc_attr( $s['report_brand'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<p class="description"><?php esc_html_e( 'Shown in the subject, header and footer (white-label). Empty = site name.', 'bot-control' ); ?></p>
			</td>
		</tr>
	</table>
	<p class="description">
		<?php
		if ( $botctl_next ) {
			/* translators: %s: date */
			printf( esc_html__( 'Next report: %s.', 'bot-control' ), esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $botctl_next ) ) );
		}
		echo ' ';
		/* translators: %d: samples */
		printf( esc_html__( 'Page-time samples for the "time saved" estimate: %d (shown in reports from 20 samples).', 'bot-control' ), (int) $botctl_samples );
		?>
	</p>
</div>
<?php submit_button( __( 'Save report settings', 'bot-control' ) ); ?>
</form>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Try it', 'bot-control' ); ?></h2>
	<div class="botctl-buttons">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank" class="botctl-inline">
			<input type="hidden" name="action" value="botctl_preview_report">
			<?php wp_nonce_field( 'botctl_preview_report' ); ?>
			<button class="button"><?php esc_html_e( 'Preview report', 'bot-control' ); ?> ↗</button>
		</form>
		<?php Bot_Control_Admin::form_open( 'send_report', array(), 'botctl-inline' ); ?>
			<button class="button button-primary"><?php esc_html_e( 'Send report now', 'bot-control' ); ?></button>
		</form>
	</div>
	<p class="description"><?php esc_html_e( 'Tip: WordPress\' default mail is often filtered as spam. Use an SMTP plugin for reliable delivery.', 'bot-control' ); ?></p>
</div>
