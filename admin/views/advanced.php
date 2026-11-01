<?php
/**
 * Advanced tab.
 *
 * @package BotControl
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
	<h2><?php esc_html_e( 'Client IP detection', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Behind Cloudflare, a load balancer or a reverse proxy, the real visitor IP is in a header. Headers are only trusted when the request arrives from a trusted proxy, so they cannot be forged to dodge limits.', 'bot-control' ); ?></p>
	<table class="widefat botctl-result">
		<tr><th>REMOTE_ADDR</th><td><code><?php echo esc_html( $botctl_remote ); ?></code></td></tr>
		<?php foreach ( $botctl_hdrs as $botctl_name => $botctl_value ) : ?>
			<tr><th><?php echo esc_html( $botctl_name ); ?></th><td><code><?php echo esc_html( $botctl_value ); ?></code></td></tr>
		<?php endforeach; ?>
		<tr><th><?php esc_html_e( 'Detected IP', 'bot-control' ); ?></th><td><strong><code><?php echo esc_html( $botctl_client['ip'] ); ?></code></strong> (<?php echo esc_html( $botctl_client['source'] ); ?>)</td></tr>
	</table>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'IP source', 'bot-control' ); ?></th>
			<td>
				<?php
				Bot_Control_Admin::select(
					'botctl[ip_source]',
					array(
						'auto'            => __( 'Automatic (recommended)', 'bot-control' ),
						'remote_addr'     => __( 'REMOTE_ADDR only (no proxy)', 'bot-control' ),
						'cloudflare'      => __( 'Cloudflare (CF-Connecting-IP)', 'bot-control' ),
						'x_forwarded_for' => __( 'X-Forwarded-For', 'bot-control' ),
						'x_real_ip'       => __( 'X-Real-IP', 'bot-control' ),
					),
					$s['ip_source']
				);
				?>
				<p class="description"><?php esc_html_e( 'Automatic trusts Cloudflare\'s published IP ranges and private-network proxies (10.x, 172.16-31.x, 192.168.x, 127.x).', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-proxies"><?php esc_html_e( 'Additional trusted proxies', 'bot-control' ); ?></label></th>
			<td>
				<textarea id="botctl-proxies" name="botctl[trusted_proxies]" rows="4" class="large-text code"><?php echo esc_textarea( $s['trusted_proxies'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Public IPs/CIDRs of your own load balancer or CDN (Sucuri, Fastly, Bunny, AWS ALB…). One per line.', 'bot-control' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Storage', 'bot-control' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Rate-limit counters', 'bot-control' ); ?></th>
			<td>
				<?php
				Bot_Control_Admin::select(
					'botctl[storage]',
					array(
						'auto'         => __( 'Automatic (APCu → object cache → database)', 'bot-control' ),
						'apcu'         => __( 'APCu', 'bot-control' ),
						'object_cache' => __( 'Persistent object cache (Redis / Memcached)', 'bot-control' ),
						'database'     => __( 'Database', 'bot-control' ),
					),
					$s['storage']
				);
				?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: APCu status, 2: object cache status, 3: active backend */
						esc_html__( 'APCu: %1$s · Persistent object cache: %2$s · In use: %3$s', 'bot-control' ),
						Bot_Control_Rate_Limiter::apcu_available() ? esc_html__( 'available', 'bot-control' ) : esc_html__( 'not available', 'bot-control' ),
						wp_using_ext_object_cache() ? esc_html__( 'available', 'bot-control' ) : esc_html__( 'not available', 'bot-control' ),
						'<strong>' . esc_html( Bot_Control_Rate_Limiter::backend() ) . '</strong>'
					);
					?>
					<br><?php esc_html_e( 'APCu counters are per server. On multi-server setups use the object cache (Redis) so all servers share the counters.', 'bot-control' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Logging & statistics', 'bot-control' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Statistics', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[stats_enabled]" value="1" <?php checked( $s['stats_enabled'] ); ?>> <?php esc_html_e( 'Count bot requests per day (one small database write per bot request)', 'bot-control' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Event log', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[log_enabled]" value="1" <?php checked( $s['log_enabled'] ); ?>> <?php esc_html_e( 'Log denied requests', 'bot-control' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Retention', 'bot-control' ); ?></th>
			<td>
				<?php esc_html_e( 'Keep log entries for', 'bot-control' ); ?>
				<input type="number" min="1" max="365" class="small-text" name="botctl[log_retention]" value="<?php echo esc_attr( $s['log_retention'] ); ?>"> <?php esc_html_e( 'days', 'bot-control' ); ?>,
				<?php esc_html_e( 'statistics for', 'bot-control' ); ?>
				<input type="number" min="7" max="730" class="small-text" name="botctl[stats_retention]" value="<?php echo esc_attr( $s['stats_retention'] ); ?>"> <?php esc_html_e( 'days', 'bot-control' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Debug mode', 'bot-control' ); ?></th>
			<td>
				<label><input type="checkbox" name="botctl[debug]" value="1" <?php checked( $s['debug'] ); ?>> <?php esc_html_e( 'Write detailed decisions to the PHP error log and add an X-Bot-Control response header', 'bot-control' ); ?></label>
				<p class="description"><?php esc_html_e( 'Search the log for "[BotControl]". Turn off when done – it is verbose. You can also define BOTCTL_DEBUG in wp-config.php.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Uninstall', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[keep_data]" value="1" <?php checked( $s['keep_data'] ); ?>> <?php esc_html_e( 'Keep settings and data when the plugin is deleted', 'bot-control' ); ?></label></td>
		</tr>
	</table>
</div>

<?php submit_button( __( 'Save advanced settings', 'bot-control' ) ); ?>
</form>
