<?php
/**
 * robots.txt tab.
 *
 * @package AllInCrawlerAIAgentManager
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_physical = Bot_Control_Robots::physical_file_exists();

// Rebuild WordPress' own robots output (as do_robots() does, without our block) so the preview copies the "*" group accurately.
remove_filter( 'robots_txt', array( 'Bot_Control_Robots', 'filter' ), 999 );
$botctl_public = get_option( 'blog_public' );
$botctl_core   = "User-agent: *\n";
if ( '0' === (string) $botctl_public ) {
	$botctl_core .= "Disallow: /\n";
} else {
	$botctl_site_url = wp_parse_url( site_url() );
	$botctl_path     = ( ! empty( $botctl_site_url['path'] ) ) ? $botctl_site_url['path'] : '';
	$botctl_core    .= "Disallow: $botctl_path/wp-admin/\n";
	$botctl_core    .= "Allow: $botctl_path/wp-admin/admin-ajax.php\n";
}
$botctl_base    = (string) apply_filters( 'robots_txt', $botctl_core, $botctl_public ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
add_filter( 'robots_txt', array( 'Bot_Control_Robots', 'filter' ), 999, 2 );
$botctl_ours = Bot_Control_Robots::build( $s, $botctl_base );
?>
<?php if ( $botctl_physical ) : ?>
	<div class="notice notice-error inline"><p>
		<strong><?php esc_html_e( 'A physical robots.txt file exists in your site root.', 'all-in-crawler-ai-agent-manager' ); ?></strong>
		<?php esc_html_e( 'The web server serves that file directly, so WordPress (and All-IN Crawler & AI Agent Manager) cannot add rules to it. Copy the rules below into the file manually, or delete/rename it to let All-IN Crawler & AI Agent Manager manage robots.txt.', 'all-in-crawler-ai-agent-manager' ); ?>
	</p></div>
<?php endif; ?>

<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'robots' ) ); ?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'robots.txt rules', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Well-behaved bots (all major search engines and AI companies) read robots.txt before crawling. Telling them "Disallow" means they never send the requests at all – cheaper than blocking them one by one. Bad bots ignore robots.txt, which is why the firewall rules exist too.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Manage robots.txt', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="botctl[robots_enabled]" value="1" <?php checked( $s['robots_enabled'] ); ?>> <?php esc_html_e( 'Add "Disallow: /" for every bot whose policy is Block (including Google-Extended and Applebot-Extended)', 'all-in-crawler-ai-agent-manager' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Crawl-delay', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td>
				<label><input type="checkbox" name="botctl[robots_crawl_delay]" value="1" <?php checked( $s['robots_crawl_delay'] ); ?>> <?php esc_html_e( 'Add Crawl-delay for rate-limited bots', 'all-in-crawler-ai-agent-manager' ); ?></label>
				<p class="description"><?php esc_html_e( 'Asks polite bots (Bing, Yandex, most SEO tools) to slow down to your limit instead of hitting it and receiving 429s. Google ignores Crawl-delay, so no Google group is ever added. Each group repeats your "User-agent: *" rules, because a bot with its own group ignores the "*" group.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
	</table>
</div>
<?php submit_button( __( 'Save robots.txt settings', 'all-in-crawler-ai-agent-manager' ) ); ?>
</form>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Preview of the lines All-IN Crawler & AI Agent Manager adds', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<?php if ( '' === $botctl_ours ) : ?>
		<p class="description"><?php esc_html_e( 'Nothing to add with the current rules (no blocked bots with a robots.txt token).', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<?php else : ?>
		<pre class="botctl-pre"><?php echo esc_html( $botctl_ours ); ?></pre>
	<?php endif; ?>
	<p><a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your live robots.txt', 'all-in-crawler-ai-agent-manager' ); ?> ↗</a></p>
</div>
