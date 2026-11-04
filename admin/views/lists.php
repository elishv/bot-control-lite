<?php
/**
 * Bot Lists tab: official IP lists + remote bot feed.
 *
 * @package AllInCrawlerAIAgentManager
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_data     = Bot_Control_IP_Lists::data();
$botctl_sources  = Bot_Control_IP_Lists::sources();
$botctl_registry = Bot_Control_Bot_Registry::all();
$botctl_feed     = Bot_Control_Remote_List::status();
$botctl_feedbots = Bot_Control_Remote_List::bots();
$botctl_next     = wp_next_scheduled( Bot_Control_Install::DAILY_HOOK );
?>
<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'lists' ) ); ?>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Official crawler IP lists', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Google, Bing, Apple, OpenAI and Perplexity publish the IP addresses their crawlers use. If you turn this on, All-IN Crawler & AI Agent Manager downloads them daily and uses them to prove a bot is genuine – this also covers OpenAI and Perplexity bots, which have no reverse DNS. A request claiming to be GPTBot from any other IP is then treated as an impostor.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<p class="description"><?php esc_html_e( 'Off by default. While it is off, nothing is downloaded and bots are verified with DNS lookups only (OpenAI and Perplexity bots stay unverified). When on, your server sends a plain HTTPS request once a day to each publisher listed in the table below (Google, Microsoft/Bing, Apple, OpenAI, Perplexity). No data about your site or visitors is sent; the publishers see your server\'s IP address, as with any download.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<p class="description"><?php esc_html_e( 'Safety: a list is only used while it is fresh (downloaded within 7 days). If downloads keep failing, All-IN Crawler & AI Agent Manager falls back to DNS checks or treats the bot as unverified – it never blocks a genuine bot because of an outdated list.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Download official crawler IP lists', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td><label><input type="checkbox" name="botctl[ip_lists_optin]" value="1" <?php checked( ! empty( $s['ip_lists_optin'] ) ); ?>> <?php esc_html_e( 'Yes, download the lists daily from the crawler publishers and verify bots against them', 'all-in-crawler-ai-agent-manager' ); ?></label></td>
		</tr>
	</table>

	<table class="widefat striped">
		<thead><tr>
			<th><?php esc_html_e( 'Bot', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Source', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th class="num"><?php esc_html_e( 'IP ranges', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Last update', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Status', 'all-in-crawler-ai-agent-manager' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $botctl_sources as $botctl_bot => $botctl_urls ) : ?>
			<?php foreach ( $botctl_urls as $botctl_i => $botctl_url ) : ?>
				<?php $botctl_entry = isset( $botctl_data['urls'][ $botctl_url ] ) ? $botctl_data['urls'][ $botctl_url ] : array(); ?>
				<tr>
					<td><?php echo 0 === $botctl_i ? '<strong>' . esc_html( isset( $botctl_registry[ $botctl_bot ] ) ? $botctl_registry[ $botctl_bot ]['name'] : $botctl_bot ) . '</strong>' : ''; ?></td>
					<td><code class="botctl-patterns"><?php echo esc_html( $botctl_url ); ?></code></td>
					<td class="num"><?php echo esc_html( number_format_i18n( isset( $botctl_entry['cidrs'] ) ? count( $botctl_entry['cidrs'] ) : 0 ) ); ?></td>
					<td class="botctl-nowrap"><?php echo ! empty( $botctl_entry['fetched'] ) ? esc_html( sprintf( /* translators: %s: time */ __( '%s ago', 'all-in-crawler-ai-agent-manager' ), human_time_diff( $botctl_entry['fetched'] ) ) ) : '—'; ?></td>
					<td>
						<?php if ( empty( $botctl_entry ) ) : ?>
							<span class="botctl-pill botctl-pill-limit"><?php esc_html_e( 'Not downloaded yet', 'all-in-crawler-ai-agent-manager' ); ?></span>
						<?php elseif ( ! empty( $botctl_entry['ok'] ) ) : ?>
							<span class="botctl-pill botctl-pill-allow"><?php esc_html_e( 'OK', 'all-in-crawler-ai-agent-manager' ); ?></span>
						<?php else : ?>
							<span class="botctl-pill botctl-pill-block"><?php esc_html_e( 'Failed', 'all-in-crawler-ai-agent-manager' ); ?></span>
							<br><span class="description"><?php echo esc_html( $botctl_entry['error'] ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">
		<?php
		echo $botctl_next
			/* translators: %s: time */
			? esc_html( sprintf( __( 'Next automatic update in %s.', 'all-in-crawler-ai-agent-manager' ), human_time_diff( $botctl_next ) ) )
			: esc_html__( 'Automatic updates are not scheduled – deactivate and re-activate the plugin.', 'all-in-crawler-ai-agent-manager' );
		?>
	</p>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Bot list updates (feed)', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description"><?php esc_html_e( 'New AI crawlers appear every month. The feed adds new bot definitions daily without waiting for a plugin update. Feed bots can only be added – they can never change or weaken built-in bots – and any pattern that would match a real browser is rejected automatically.', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable feed', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<td><label><input type="checkbox" name="botctl[remote_list_enabled]" value="1" <?php checked( $s['remote_list_enabled'] ); ?>> <?php esc_html_e( 'Download bot definitions daily', 'all-in-crawler-ai-agent-manager' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-feed-url"><?php esc_html_e( 'Feed URL', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<input id="botctl-feed-url" type="url" class="large-text code" name="botctl[remote_list_url]" value="<?php echo esc_attr( $s['remote_list_url'] ); ?>" placeholder="https://">
				<p class="description"><?php esc_html_e( 'Leave empty to use only the bot list bundled with the plugin (nothing is downloaded). Agencies can host their own feed (same JSON format – see docs/DEVELOPER.md) and use it across all client sites. When a URL is set, your site requests it once a day.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-feed-key"><?php esc_html_e( 'Access key', 'all-in-crawler-ai-agent-manager' ); ?></label></th>
			<td>
				<input id="botctl-feed-key" type="password" autocomplete="off" class="regular-text code" name="botctl[remote_list_key]" value="<?php echo esc_attr( $s['remote_list_key'] ); ?>">
				<p class="description"><?php esc_html_e( 'Optional. Sent as a Bearer token for private or commercial feeds.', 'all-in-crawler-ai-agent-manager' ); ?></p>
			</td>
		</tr>
	</table>

	<?php if ( ! empty( $botctl_feed['checked'] ) ) : ?>
		<p>
			<?php esc_html_e( 'Last check:', 'all-in-crawler-ai-agent-manager' ); ?>
			<?php echo esc_html( sprintf( /* translators: %s: time */ __( '%s ago', 'all-in-crawler-ai-agent-manager' ), human_time_diff( $botctl_feed['checked'] ) ) ); ?> –
			<?php if ( ! empty( $botctl_feed['ok'] ) ) : ?>
				<span class="botctl-pill botctl-pill-allow"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d bot loaded', '%d bots loaded', (int) $botctl_feed['count'], 'all-in-crawler-ai-agent-manager' ), (int) $botctl_feed['count'] ) ); ?></span>
			<?php else : ?>
				<span class="botctl-pill botctl-pill-block"><?php esc_html_e( 'Failed', 'all-in-crawler-ai-agent-manager' ); ?></span> <?php echo esc_html( $botctl_feed['error'] ); ?>
			<?php endif; ?>
		</p>
		<?php if ( ! empty( $botctl_feed['rejected'] ) ) : ?>
			<details><summary><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d entries rejected by the safety checks', 'all-in-crawler-ai-agent-manager' ), count( $botctl_feed['rejected'] ) ) ); ?></summary>
				<ul class="botctl-list"><?php foreach ( $botctl_feed['rejected'] as $botctl_r ) : ?><li><code><?php echo esc_html( $botctl_r ); ?></code></li><?php endforeach; ?></ul>
			</details>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $botctl_feedbots ) : ?>
		<details class="botctl-details">
			<summary><?php esc_html_e( 'Bots from the feed', 'all-in-crawler-ai-agent-manager' ); ?> <span class="count">(<?php echo count( $botctl_feedbots ); ?>)</span></summary>
			<table class="widefat striped">
				<?php foreach ( $botctl_feedbots as $botctl_bot ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $botctl_bot['name'] ); ?></strong> <span class="botctl-vendor"><?php echo esc_html( $botctl_bot['vendor'] ); ?></span></td>
						<td><?php echo esc_html( Bot_Control_Admin::category_label( $botctl_bot['category'] ) ); ?></td>
						<td><code class="botctl-patterns"><?php echo esc_html( implode( ', ', $botctl_bot['patterns'] ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="description"><?php esc_html_e( 'Feed bots appear on the Bot Rules tab (marked "feed") where you can override them like any other bot.', 'all-in-crawler-ai-agent-manager' ); ?></p>
		</details>
	<?php endif; ?>
</div>

<?php submit_button( __( 'Save bot list settings', 'all-in-crawler-ai-agent-manager' ) ); ?>
</form>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Update now', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<div class="botctl-buttons">
		<?php Bot_Control_Admin::form_open( 'refresh_lists', array( 'what' => 'ip' ), 'botctl-inline' ); ?>
			<button class="button" <?php disabled( empty( $s['ip_lists_optin'] ) ); ?>><?php esc_html_e( 'Download official IP lists', 'all-in-crawler-ai-agent-manager' ); ?></button>
		</form>
		<?php Bot_Control_Admin::form_open( 'refresh_lists', array( 'what' => 'feed' ), 'botctl-inline' ); ?>
			<button class="button"><?php esc_html_e( 'Download bot feed', 'all-in-crawler-ai-agent-manager' ); ?></button>
		</form>
	</div>
	<p class="description"><?php esc_html_e( 'Save your settings first – downloads use the saved feed URL and key.', 'all-in-crawler-ai-agent-manager' ); ?></p>
</div>
