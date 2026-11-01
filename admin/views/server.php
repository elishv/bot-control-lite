<?php
/**
 * Server Rules tab.
 *
 * @package BotControl
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_apache   = Bot_Control_Server_Rules::apache( $s );
$botctl_nginx    = Bot_Control_Server_Rules::nginx( $s );
$botctl_is_ap    = Bot_Control_Server_Rules::is_apache();
$botctl_has      = Bot_Control_Server_Rules::htaccess_has_rules();
$botctl_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'unknown', 'bot-control' );
$botctl_proxied  = Bot_Control_IP::client( $s )['source'] !== 'REMOTE_ADDR';
?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'Block at the web-server level', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'The fastest block is the one PHP never sees. These rules make Apache, LiteSpeed or nginx reject blocked bots before WordPress starts – ideal for scanners and bots you have fully blocked. Rate limits, DNS/IP verification and load shedding still need PHP, so the plugin keeps handling those.', 'bot-control' ); ?></p>
	<p>
		<?php
		/* translators: %s: server software */
		printf( esc_html__( 'Detected web server: %s', 'bot-control' ), '<code>' . esc_html( $botctl_software ) . '</code>' );
		?>
	</p>
	<?php if ( empty( $botctl_apache ) ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'Nothing to export: no bot or category currently has the "Block" policy (and the IP block list is not included).', 'bot-control' ); ?></p></div>
	<?php endif; ?>
</div>

<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'server' ) ); ?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'Options', 'bot-control' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Keep .htaccess in sync', 'bot-control' ); ?></th>
			<td>
				<label><input type="checkbox" name="botctl[server_rules_auto]" value="1" <?php checked( $s['server_rules_auto'] ); ?> <?php disabled( ! $botctl_is_ap && ! $s['server_rules_auto'] ); ?>> <?php esc_html_e( 'Rewrite the .htaccess rules automatically whenever the bot rules change (Apache / LiteSpeed only)', 'bot-control' ); ?></label>
				<p class="description"><?php esc_html_e( 'In Monitor mode, or when Bot Control is disabled or deactivated, the rules are removed automatically.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Include IP block list', 'bot-control' ); ?></th>
			<td>
				<label><input type="checkbox" name="botctl[server_rules_ips]" value="1" <?php checked( $s['server_rules_ips'] ); ?>> <?php esc_html_e( 'Also block the "Always block" IPs at server level', 'bot-control' ); ?></label>
				<?php if ( $botctl_proxied ) : ?>
					<p class="description botctl-bad"><?php esc_html_e( 'Your site is behind a proxy/CDN. The web server only sees the real visitor IP if it is configured for it (Apache mod_remoteip / nginx real_ip). Otherwise IP rules would match the proxy – leave this off.', 'bot-control' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</div>
<?php submit_button( __( 'Save server rule settings', 'bot-control' ) ); ?>
</form>

<div class="botctl-grid">
	<div class="botctl-panel">
		<h2><?php esc_html_e( 'Apache / LiteSpeed (.htaccess)', 'bot-control' ); ?></h2>
		<p>
			<?php esc_html_e( 'Status:', 'bot-control' ); ?>
			<?php if ( $botctl_has ) : ?>
				<strong class="botctl-good"><?php esc_html_e( 'Rules are in .htaccess', 'bot-control' ); ?></strong>
			<?php else : ?>
				<strong><?php esc_html_e( 'Not installed', 'bot-control' ); ?></strong>
			<?php endif; ?>
		</p>
		<?php if ( $botctl_apache ) : ?>
			<pre class="botctl-pre"><?php echo esc_html( "# BEGIN Bot Control\n" . implode( "\n", $botctl_apache ) . "\n# END Bot Control" ); ?></pre>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Bot Control places its block at the top of .htaccess, before the WordPress block – rules below WordPress\' rewrite would never run. A backup of the previous file is kept as .htaccess.botctl-backup.', 'bot-control' ); ?></p>
		<div class="botctl-buttons">
			<?php if ( $botctl_apache ) : ?>
				<?php Bot_Control_Admin::form_open( 'htaccess', array( 'op' => 'write' ), 'botctl-inline' ); ?>
					<button class="button button-primary botctl-confirm"><?php echo $botctl_has ? esc_html__( 'Update .htaccess', 'bot-control' ) : esc_html__( 'Write to .htaccess', 'bot-control' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( $botctl_has ) : ?>
				<?php Bot_Control_Admin::form_open( 'htaccess', array( 'op' => 'remove' ), 'botctl-inline' ); ?>
					<button class="button"><?php esc_html_e( 'Remove from .htaccess', 'bot-control' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php if ( ! $botctl_is_ap ) : ?>
			<p class="description"><?php esc_html_e( 'Your server does not look like Apache/LiteSpeed, so .htaccess is probably ignored. Use the nginx configuration instead.', 'bot-control' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="botctl-panel">
		<h2><?php esc_html_e( 'nginx', 'bot-control' ); ?></h2>
		<?php if ( '' === $botctl_nginx['http'] ) : ?>
			<p class="description"><?php esc_html_e( 'Nothing to export.', 'bot-control' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'nginx has no .htaccess – ask your host or sysadmin to add these two snippets, then reload nginx (nginx -t && systemctl reload nginx). Re-export after changing the rules.', 'bot-control' ); ?></p>
			<h3><?php esc_html_e( '1. http { } context', 'bot-control' ); ?></h3>
			<pre class="botctl-pre"><?php echo esc_html( $botctl_nginx['http'] ); ?></pre>
			<h3><?php esc_html_e( '2. server { } context', 'bot-control' ); ?></h3>
			<pre class="botctl-pre"><?php echo esc_html( $botctl_nginx['server'] ); ?></pre>
		<?php endif; ?>
	</div>
</div>
