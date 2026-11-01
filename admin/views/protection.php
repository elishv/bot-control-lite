<?php
/**
 * Protection tab.
 *
 * @package BotControl
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_load = Bot_Control_Load_Monitor::state( $s );
?>
<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'protection' ) ); ?>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'General', 'bot-control' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Bot Control', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Filter incoming requests', 'bot-control' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Mode', 'bot-control' ); ?></th>
			<td>
				<label><input type="radio" name="botctl[mode]" value="enforce" <?php checked( $s['mode'], 'enforce' ); ?>> <strong><?php esc_html_e( 'Enforce', 'bot-control' ); ?></strong> – <?php esc_html_e( 'deny requests according to the rules.', 'bot-control' ); ?></label><br>
				<label><input type="radio" name="botctl[mode]" value="monitor" <?php checked( $s['mode'], 'monitor' ); ?>> <strong><?php esc_html_e( 'Monitor', 'bot-control' ); ?></strong> – <?php esc_html_e( 'only log what would be denied. Use it for a few days after first setup to validate your rules safely.', 'bot-control' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-window"><?php esc_html_e( 'Rate-limit window', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-window" type="number" min="10" max="3600" class="small-text" name="botctl[window]" value="<?php echo esc_attr( $s['window'] ); ?>"> <?php esc_html_e( 'seconds', 'bot-control' ); ?>
				<p class="description"><?php esc_html_e( 'All limits are "requests per window". 60 seconds is a good default.', 'bot-control' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Fake search-engine protection', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Scrapers often pretend to be Googlebot because most sites let Google in. Bot Control checks the claim with a reverse + forward DNS lookup (the method Google, Bing, Apple and Yandex officially document) and caches the result per IP.', 'bot-control' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Verify search engines', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[verify_bots]" value="1" <?php checked( $s['verify_bots'] ); ?>> <?php esc_html_e( 'Verify bots marked "DNS verified" on the Bot Rules tab', 'bot-control' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Impostors', 'bot-control' ); ?></th>
			<td>
				<?php
				Bot_Control_Admin::select(
					'botctl[impostor_policy]',
					array(
						'block' => __( 'Block (recommended)', 'bot-control' ),
						'limit' => __( 'Rate limit like unidentified bots', 'bot-control' ),
					),
					$s['impostor_policy']
				);
				?>
				<p class="description"><?php esc_html_e( 'Requests whose reverse DNS points somewhere else (e.g. "Googlebot" from a cloud server). Requests with no reverse DNS at all are treated as unidentified bots, not impostors.', 'bot-control' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Server load protection', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'When the server is under pressure, non-essential bots get "503 – come back later" until it recovers. Search engines treat a short 503 as temporary – no ranking impact.', 'bot-control' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Load shedding', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[load_shedding]" value="1" <?php checked( $s['load_shedding'] ); ?>> <?php esc_html_e( 'Shed bot traffic when the server is overloaded', 'bot-control' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-threshold"><?php esc_html_e( 'Threshold', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-threshold" type="number" step="0.1" min="0.1" max="50" class="small-text" name="botctl[load_threshold]" value="<?php echo esc_attr( $s['load_threshold'] ); ?>"> <?php esc_html_e( 'load average per CPU core', 'bot-control' ); ?>
				<p class="description">
					<?php esc_html_e( '1.0 = all cores fully busy. 2.0 is a safe default; lower it on VPS/dedicated servers, raise it on shared hosting where the load of other sites is included.', 'bot-control' ); ?>
					<?php if ( $botctl_load['supported'] ) : ?>
						<br><?php /* translators: 1: per-core load, 2: cores */ printf( esc_html__( 'Right now: %1$s per core on %2$d cores.', 'bot-control' ), esc_html( number_format_i18n( $botctl_load['per_core'], 2 ) ), (int) $botctl_load['cores'] ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-cores"><?php esc_html_e( 'CPU cores', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-cores" type="number" min="0" max="1024" class="small-text" name="botctl[cpu_cores]" value="<?php echo esc_attr( $s['cpu_cores'] ); ?>">
				<p class="description"><?php esc_html_e( '0 = detect automatically.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Never shed', 'bot-control' ); ?></th>
			<td>
				<?php foreach ( Bot_Control_Bot_Registry::categories() as $botctl_id => $botctl_cat ) : ?>
					<label class="botctl-check"><input type="checkbox" name="botctl[shed_exempt][]" value="<?php echo esc_attr( $botctl_id ); ?>" <?php checked( in_array( $botctl_id, (array) $s['shed_exempt'], true ) ); ?>> <?php echo esc_html( $botctl_cat['label'] ); ?></label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Only verified search engines count as "Search engines" – impostors are never exempt.', 'bot-control' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Human visitors', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Protects against scrapers that disguise themselves as normal browsers. Logged-in users are never limited. Static files (images, CSS, JS) are normally served without PHP and are not counted.', 'bot-control' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Rate-limit humans', 'bot-control' ); ?></th>
			<td>
				<label><input type="checkbox" name="botctl[human_limit_enabled]" value="1" <?php checked( $s['human_limit_enabled'] ); ?>> <?php esc_html_e( 'Limit page requests per IP', 'bot-control' ); ?></label>
				<p class="description botctl-bad"><?php esc_html_e( 'Before enabling: check on the Dashboard that "Your IP" shows your real public IP. Behind a proxy/CDN that is not configured, every visitor would share one counter.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-human-limit"><?php esc_html_e( 'Limit', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-human-limit" type="number" min="10" max="100000" class="small-text" name="botctl[human_limit]" value="<?php echo esc_attr( $s['human_limit'] ); ?>"> <?php /* translators: %d: seconds */ printf( esc_html__( 'requests per IP per %d seconds', 'bot-control' ), (int) $s['window'] ); ?>
				<p class="description"><?php esc_html_e( 'Keep it generous: offices, schools and mobile carriers put many people behind one IP. 120/min is a safe starting point.', 'bot-control' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'IP lists & exclusions', 'bot-control' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="botctl-allow"><?php esc_html_e( 'Always allow', 'bot-control' ); ?></label></th>
			<td>
				<textarea id="botctl-allow" name="botctl[ip_allowlist]" rows="5" class="large-text code" placeholder="203.0.113.10&#10;198.51.100.0/24 # office"><?php echo esc_textarea( $s['ip_allowlist'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One IP or CIDR range per line, # for comments. Bypasses every rule – use for your office, agency, payment gateways or integrations.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-block"><?php esc_html_e( 'Always block', 'bot-control' ); ?></label></th>
			<td>
				<textarea id="botctl-block" name="botctl[ip_blocklist]" rows="5" class="large-text code"><?php echo esc_textarea( $s['ip_blocklist'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Same format. These IPs receive 403 on every request.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-paths"><?php esc_html_e( 'Excluded paths', 'bot-control' ); ?></label></th>
			<td>
				<textarea id="botctl-paths" name="botctl[excluded_paths]" rows="4" class="large-text code"><?php echo esc_textarea( $s['excluded_paths'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Requests starting with these paths are never filtered (one per line). Lines containing "?" match anywhere in the URL, e.g. "?wc-api=". Add payment-gateway callbacks and webhooks here.', 'bot-control' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<?php submit_button( __( 'Save protection settings', 'bot-control' ) ); ?>
</form>
