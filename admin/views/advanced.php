<?php
/**
 * Advanced tab.
 *
 * @package AllInCrawlerAIAgentManager
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_client = Bot_Control_IP::client( $s );
$botctl_remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
$botctl_hdrs   = array();
foreach ( array( 'HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP', 'HTTP_X_FORWARDED_FOR' => 'X-Forwarded-For', 'HTTP_X_REAL_IP' => 'X-Real-IP' ) as $botctl_key => $botctl_name ) {
	if ( ! empty( $_SERVER[ $botctl_key ] ) ) {
		$botctl_hdrs[ $botctl_name ] = sanitize_text_field( wp_unslash( $_SERVER[ $botctl_key ] ) );
	}
}
?>
<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'advanced' ) ); ?>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Client IP detection', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Behind Cloudflare, a load balancer or a reverse proxy, the real visitor IP is in a header. Headers are only trusted when the request arrives from a trusted proxy, so they cannot be forged to dodge limits.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<table class="widefat botctl-result">
		<tr><th>REMOTE_ADDR</th><td><code><?php echo esc_html( $botctl_remote ); ?></code></td></tr>
		<?php foreach ( $botctl_hdrs as $botctl_name => $botctl_value ) : ?>
			<tr><th><?php echo esc_html( $botctl_name ); ?></th><td><code><?php echo esc_html( $botctl_value ); ?></code></td></tr>
		<?php endforeach; ?>
		<tr><th><?php esc_html_e( 'Detected IP', 'all-in-crawler-ai-agent-manager' ); ?></th><td><strong><code><?php echo esc_html( $botctl_client['ip'] ); ?></code></strong> (<?php echo esc_html( $botctl_client['source'] ); ?>)</td></tr>
	</table>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'IP source', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<?php
				Bot_Control_Admin::select(
					'botctl[ip_source]',
					array(
						'auto'            => __( 'Automatic (recommended)', 'all-in-crawler-ai-agent-manager' ),
						'remote_addr'     => __( 'REMOTE_ADDR only (no proxy)', 'all-in-crawler-ai-agent-manager' ),
						'cloudflare'      => __( 'Cloudflare (CF-Connecting-IP)', 'all-in-crawler-ai-agent-manager' ),
						'x_forwarded_for' => __( 'X-Forwarded-For', 'all-in-crawler-ai-agent-manager' ),
						'x_real_ip'       => __( 'X-Real-IP', 'all-in-crawler-ai-agent-manager' ),
					),
					$s['ip_source']
				);
				?>
				<p class="description"><?php esc_html_e( 'Automatic trusts Cloudflare\'s published IP ranges and private-network proxies (10.x, 172.16-31.x, 192.168.x, 127.x).', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-proxies"><?php esc_html_e( 'Additional trusted proxies', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<textarea id="botctl-proxies" name="botctl[trusted_proxies]" rows="4" class="large-text code"><?php echo esc_textarea( $s['trusted_proxies'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Public IPs/CIDRs of your own load balancer or CDN (Sucuri, Fastly, Bunny, AWS ALB…). One per line.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Storage', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Rate-limit counters', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<?php
				Bot_Control_Admin::select(
					'botctl[storage]',
					array(
						'auto'         => __( 'Automatic (APCu → object cache → database)', 'all-in-crawler-ai-agent-manager' ),
						'apcu'         => __( 'APCu', 'all-in-crawler-ai-agent-manager' ),
						'object_cache' => __( 'Persistent object cache (Redis / Memcached)', 'all-in-crawler-ai-agent-manager' ),
						'database'     => __( 'Database', 'all-in-crawler-ai-agent-manager' ),
					),
					$s['storage']
				);
				?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: APCu status, 2: object cache status, 3: active backend */
						esc_html__( 'APCu: %1$s · Persistent object cache: %2$s · In use: %3$s', 'all-in-crawler-ai-agent-manager' ),
						Bot_Control_Rate_Limiter::apcu_available() ? esc_html__( 'available', 'all-in-crawler-ai-agent-manager' ) : esc_html__( 'not available', 'all-in-crawler-ai-agent-manager' ),
						wp_using_ext_object_cache() ? esc_html__( 'available', 'all-in-crawler-ai-agent-manager' ) : esc_html__( 'not available', 'all-in-crawler-ai-agent-manager' ),
						'<strong>' . esc_html( Bot_Control_Rate_Limiter::backend() ) . '</strong>'
					);
					?>
					<br><?php esc_html_e( 'APCu counters are per server. On multi-server setups use the object cache (Redis) so all servers share the counters.', 'all-in-crawler-ai-agent-manager' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Logging & statistics', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Statistics', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td><label><input type="checkbox" name="botctl[stats_enabled]" value="1" <?php checked( $s['stats_enabled'] ); ?>> <?php esc_html_e( 'Count bot requests per day (one small database write per bot request)', 'all-in-crawler-ai-agent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Event log', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td><label><input type="checkbox" name="botctl[log_enabled]" value="1" <?php checked( $s['log_enabled'] ); ?>> <?php esc_html_e( 'Log denied requests', 'all-in-crawler-ai-agent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Retention', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<?php esc_html_e( 'Keep log entries for', 'all-in-crawler-ai-agent-manager' ); ?>
				<input type="number" min="1" max="365" class="small-text" name="botctl[log_retention]" value="<?php echo esc_attr( $s['log_retention'] ); ?>"> <?php esc_html_e( 'days', 'all-in-crawler-ai-agent-manager' ); ?>,
				<?php esc_html_e( 'statistics for', 'all-in-crawler-ai-agent-manager' ); ?>
				<input type="number" min="7" max="730" class="small-text" name="botctl[stats_retention]" value="<?php echo esc_attr( $s['stats_retention'] ); ?>"> <?php esc_html_e( 'days', 'all-in-crawler-ai-agent-manager' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Debug mode', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="botctl[debug]" value="1" <?php checked( $s['debug'] ); ?>> <?php esc_html_e( 'Write detailed decisions to the PHP error log and add an X-AllIN-Crawler response header', 'all-in-crawler-ai-agent-manager' ); ?></label>
				<p class="description"><?php esc_html_e( 'Search the log for "[AllIN-Crawler]". Turn off when done – it is verbose. You can also define BOTCTL_DEBUG in wp-config.php.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Uninstall', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td><label><input type="checkbox" name="botctl[keep_data]" value="1" <?php checked( $s['keep_data'] ); ?>> <?php esc_html_e( 'Keep settings and data when the plugin is deleted', 'all-in-crawler-ai-agent-manager' ); ?></label></td>
		</tr>
	</table>
</div>

<?php submit_button( __( 'Save advanced settings', 'all-in-crawler-ai-agent-manager' ) ); ?>
</form>
