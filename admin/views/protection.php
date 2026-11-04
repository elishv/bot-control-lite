<?php
/**
 * Protection tab.
 *
 * @package AllInCrawlerAIAgentManager
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_load = Bot_Control_Load_Monitor::state( $s );
?>
<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'protection' ) ); ?>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'General', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable All-IN Crawler & AI Agent Manager', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td><label><input type="checkbox" name="botctl[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Filter incoming requests', 'all-in-crawler-ai-agent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Mode', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<label><input type="radio" name="botctl[mode]" value="enforce" <?php checked( $s['mode'], 'enforce' ); ?>> <strong><?php esc_html_e( 'Enforce', 'all-in-crawler-ai-agent-manager' ); ?></strong> – <?php esc_html_e( 'deny requests according to the rules.', 'all-in-crawler-ai-agent-manager' ); ?></label><br>
				<label><input type="radio" name="botctl[mode]" value="monitor" <?php checked( $s['mode'], 'monitor' ); ?>> <strong><?php esc_html_e( 'Monitor', 'all-in-crawler-ai-agent-manager' ); ?></strong> – <?php esc_html_e( 'only log what would be denied. Use it for a few days after first setup to validate your rules safely.', 'all-in-crawler-ai-agent-manager' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-window"><?php esc_html_e( 'Rate-limit window', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<input id="botctl-window" type="number" min="10" max="3600" class="small-text" name="botctl[window]" value="<?php echo esc_attr( $s['window'] ); ?>"> <?php esc_html_e( 'seconds', 'all-in-crawler-ai-agent-manager' ); ?>
				<p class="description"><?php esc_html_e( 'All limits are "requests per window". 60 seconds is a good default.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Fake search-engine protection', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Scrapers often pretend to be Googlebot because most sites let Google in. All-IN Crawler & AI Agent Manager checks the claim with a reverse + forward DNS lookup (the method Google, Bing, Apple and Yandex officially document) and caches the result per IP.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Verify search engines', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td><label><input type="checkbox" name="botctl[verify_bots]" value="1" <?php checked( $s['verify_bots'] ); ?>> <?php esc_html_e( 'Verify bots marked "DNS verified" on the Bot Rules tab', 'all-in-crawler-ai-agent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Impostors', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<?php
				Bot_Control_Admin::select(
					'botctl[impostor_policy]',
					array(
						'block' => __( 'Block (recommended)', 'all-in-crawler-ai-agent-manager' ),
						'limit' => __( 'Rate limit like unidentified bots', 'all-in-crawler-ai-agent-manager' ),
					),
					$s['impostor_policy']
				);
				?>
				<p class="description"><?php esc_html_e( 'Requests whose reverse DNS points somewhere else (e.g. "Googlebot" from a cloud server). Requests with no reverse DNS at all are treated as unidentified bots, not impostors.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Server load protection', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description"><?php esc_html_e( 'When the server is under pressure, non-essential bots get "503 – come back later" until it recovers. Search engines treat a short 503 as temporary – no ranking impact.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Load shedding', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td><label><input type="checkbox" name="botctl[load_shedding]" value="1" <?php checked( $s['load_shedding'] ); ?>> <?php esc_html_e( 'Shed bot traffic when the server is overloaded', 'all-in-crawler-ai-agent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-threshold"><?php esc_html_e( 'Threshold', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<input id="botctl-threshold" type="number" step="0.1" min="0.1" max="50" class="small-text" name="botctl[load_threshold]" value="<?php echo esc_attr( $s['load_threshold'] ); ?>"> <?php esc_html_e( 'load average per CPU core', 'all-in-crawler-ai-agent-manager' ); ?>
				<p class="description">
					<?php esc_html_e( '1.0 = all cores fully busy. 2.0 is a safe default; lower it on VPS/dedicated servers, raise it on shared hosting where the load of other sites is included.', 'all-in-crawler-ai-agent-manager' ); ?>
					<?php if ( $botctl_load['supported'] ) : ?>
						<br><?php /* translators: 1: per-core load, 2: cores */ printf( esc_html__( 'Right now: %1$s per core on %2$d cores.', 'all-in-crawler-ai-agent-manager' ), esc_html( number_format_i18n( $botctl_load['per_core'], 2 ) ), (int) $botctl_load['cores'] ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-cores"><?php esc_html_e( 'CPU cores', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<input id="botctl-cores" type="number" min="0" max="1024" class="small-text" name="botctl[cpu_cores]" value="<?php echo esc_attr( $s['cpu_cores'] ); ?>">
				<p class="description"><?php esc_html_e( '0 = detect automatically.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Never shed', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<?php foreach ( Bot_Control_Bot_Registry::categories() as $botctl_id => $botctl_cat ) : ?>
					<label class="botctl-check"><input type="checkbox" name="botctl[shed_exempt][]" value="<?php echo esc_attr( $botctl_id ); ?>" <?php checked( in_array( $botctl_id, (array) $s['shed_exempt'], true ) ); ?>> <?php echo esc_html( $botctl_cat['label'] ); ?></label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Only verified search engines count as "Search engines" – impostors are never exempt.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Human visitors', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Protects against scrapers that disguise themselves as normal browsers. Logged-in users are never limited. Static files (images, CSS, JS) are normally served without PHP and are not counted.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Rate-limit humans', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="botctl[human_limit_enabled]" value="1" <?php checked( $s['human_limit_enabled'] ); ?>> <?php esc_html_e( 'Limit page requests per IP', 'all-in-crawler-ai-agent-manager' ); ?></label>
				<p class="description botctl-bad"><?php esc_html_e( 'Before enabling: check on the Dashboard that "Your IP" shows your real public IP. Behind a proxy/CDN that is not configured, every visitor would share one counter.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-human-limit"><?php esc_html_e( 'Limit', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<input id="botctl-human-limit" type="number" min="10" max="100000" class="small-text" name="botctl[human_limit]" value="<?php echo esc_attr( $s['human_limit'] ); ?>"> <?php /* translators: %d: seconds */ printf( esc_html__( 'requests per IP per %d seconds', 'all-in-crawler-ai-agent-manager' ), (int) $s['window'] ); ?>
				<p class="description"><?php esc_html_e( 'Keep it generous: offices, schools and mobile carriers put many people behind one IP. 120/min is a safe starting point.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'IP lists & exclusions', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="botctl-allow"><?php esc_html_e( 'Always allow', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<textarea id="botctl-allow" name="botctl[ip_allowlist]" rows="5" class="large-text code" placeholder="203.0.113.10&#10;198.51.100.0/24 # office"><?php echo esc_textarea( $s['ip_allowlist'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One IP or CIDR range per line, # for comments. Bypasses every rule – use for your office, agency, payment gateways or integrations.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-block"><?php esc_html_e( 'Always block', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<textarea id="botctl-block" name="botctl[ip_blocklist]" rows="5" class="large-text code"><?php echo esc_textarea( $s['ip_blocklist'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Same format. These IPs receive 403 on every request.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-paths"><?php esc_html_e( 'Excluded paths', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<textarea id="botctl-paths" name="botctl[excluded_paths]" rows="4" class="large-text code"><?php echo esc_textarea( $s['excluded_paths'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Requests starting with these paths are never filtered (one per line). Lines containing "?" match anywhere in the URL, e.g. "?wc-api=". Add payment-gateway callbacks and webhooks here.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<?php submit_button( __( 'Save protection settings', 'all-in-crawler-ai-agent-manager' ) ); ?>
</form>
