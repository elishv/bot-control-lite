<?php
/**
 * Bot Lists tab: official IP lists + remote bot feed.
 *
 * @package BotControl
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
	<h2><?php esc_html_e( 'Official crawler IP lists', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Google, Bing, Apple, OpenAI and Perplexity publish the IP addresses their crawlers use. Bot Control downloads them daily and uses them to prove a bot is genuine – this also covers OpenAI and Perplexity bots, which have no reverse DNS. A request claiming to be GPTBot from any other IP is treated as an impostor.', 'bot-control' ); ?></p>
	<p class="description"><?php esc_html_e( 'Safety: a list is only used while it is fresh (downloaded within 7 days). If downloads keep failing, Bot Control falls back to DNS checks or treats the bot as unverified – it never blocks a genuine bot because of an outdated list.', 'bot-control' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Use official IP lists', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[ip_lists_enabled]" value="1" <?php checked( $s['ip_lists_enabled'] ); ?>> <?php esc_html_e( 'Download daily and verify bots against them', 'bot-control' ); ?></label></td>
		</tr>
	</table>

	<table class="widefat striped">
		<thead><tr>
			<th><?php esc_html_e( 'Bot', 'bot-control' ); ?></th>
			<th><?php esc_html_e( 'Source', 'bot-control' ); ?></th>
			<th class="num"><?php esc_html_e( 'IP ranges', 'bot-control' ); ?></th>
			<th><?php esc_html_e( 'Last update', 'bot-control' ); ?></th>
			<th><?php esc_html_e( 'Status', 'bot-control' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $botctl_sources as $botctl_bot => $botctl_urls ) : ?>
			<?php foreach ( $botctl_urls as $botctl_i => $botctl_url ) : ?>
				<?php $botctl_entry = isset( $botctl_data['urls'][ $botctl_url ] ) ? $botctl_data['urls'][ $botctl_url ] : array(); ?>
				<tr>
					<td><?php echo 0 === $botctl_i ? '<strong>' . esc_html( isset( $botctl_registry[ $botctl_bot ] ) ? $botctl_registry[ $botctl_bot ]['name'] : $botctl_bot ) . '</strong>' : ''; ?></td>
					<td><code class="botctl-patterns"><?php echo esc_html( $botctl_url ); ?></code></td>
					<td class="num"><?php echo esc_html( number_format_i18n( isset( $botctl_entry['cidrs'] ) ? count( $botctl_entry['cidrs'] ) : 0 ) ); ?></td>
					<td class="botctl-nowrap"><?php echo ! empty( $botctl_entry['fetched'] ) ? esc_html( sprintf( /* translators: %s: time */ __( '%s ago', 'bot-control' ), human_time_diff( $botctl_entry['fetched'] ) ) ) : '—'; ?></td>
					<td>
						<?php if ( empty( $botctl_entry ) ) : ?>
							<span class="botctl-pill botctl-pill-limit"><?php esc_html_e( 'Not downloaded yet', 'bot-control' ); ?></span>
						<?php elseif ( ! empty( $botctl_entry['ok'] ) ) : ?>
							<span class="botctl-pill botctl-pill-allow"><?php esc_html_e( 'OK', 'bot-control' ); ?></span>
						<?php else : ?>
							<span class="botctl-pill botctl-pill-block"><?php esc_html_e( 'Failed', 'bot-control' ); ?></span>
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
			? esc_html( sprintf( __( 'Next automatic update in %s.', 'bot-control' ), human_time_diff( $botctl_next ) ) )
			: esc_html__( 'Automatic updates are not scheduled – deactivate and re-activate the plugin.', 'bot-control' );
		?>
	</p>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Bot list updates (feed)', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'New AI crawlers appear every month. The feed adds new bot definitions daily without waiting for a plugin update. Feed bots can only be added – they can never change or weaken built-in bots – and any pattern that would match a real browser is rejected automatically.', 'bot-control' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable feed', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[remote_list_enabled]" value="1" <?php checked( $s['remote_list_enabled'] ); ?>> <?php esc_html_e( 'Download bot definitions daily', 'bot-control' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-feed-url"><?php esc_html_e( 'Feed URL', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-feed-url" type="url" class="large-text code" name="botctl[remote_list_url]" value="<?php echo esc_attr( $s['remote_list_url'] ); ?>" placeholder="https://">
				<p class="description"><?php esc_html_e( 'Leave empty to use only the bot list bundled with the plugin (nothing is downloaded). Agencies can host their own feed (same JSON format – see docs/DEVELOPER.md) and use it across all client sites. When a URL is set, your site requests it once a day.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-feed-key"><?php esc_html_e( 'Access key', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-feed-key" type="password" autocomplete="off" class="regular-text code" name="botctl[remote_list_key]" value="<?php echo esc_attr( $s['remote_list_key'] ); ?>">
				<p class="description"><?php esc_html_e( 'Optional. Sent as a Bearer token for private or commercial feeds.', 'bot-control' ); ?></p>
			</td>
		</tr>
	</table>

	<?php if ( ! empty( $botctl_feed['checked'] ) ) : ?>
		<p>
			<?php esc_html_e( 'Last check:', 'bot-control' ); ?>
			<?php echo esc_html( sprintf( /* translators: %s: time */ __( '%s ago', 'bot-control' ), human_time_diff( $botctl_feed['checked'] ) ) ); ?> –
			<?php if ( ! empty( $botctl_feed['ok'] ) ) : ?>
				<span class="botctl-pill botctl-pill-allow"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d bot loaded', '%d bots loaded', (int) $botctl_feed['count'], 'bot-control' ), (int) $botctl_feed['count'] ) ); ?></span>
			<?php else : ?>
				<span class="botctl-pill botctl-pill-block"><?php esc_html_e( 'Failed', 'bot-control' ); ?></span> <?php echo esc_html( $botctl_feed['error'] ); ?>
			<?php endif; ?>
		</p>
		<?php if ( ! empty( $botctl_feed['rejected'] ) ) : ?>
			<details><summary><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d entries rejected by the safety checks', 'bot-control' ), count( $botctl_feed['rejected'] ) ) ); ?></summary>
				<ul class="botctl-list"><?php foreach ( $botctl_feed['rejected'] as $botctl_r ) : ?><li><code><?php echo esc_html( $botctl_r ); ?></code></li><?php endforeach; ?></ul>
			</details>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $botctl_feedbots ) : ?>
		<details class="botctl-details">
			<summary><?php esc_html_e( 'Bots from the feed', 'bot-control' ); ?> <span class="count">(<?php echo count( $botctl_feedbots ); ?>)</span></summary>
			<table class="widefat striped">
				<?php foreach ( $botctl_feedbots as $botctl_bot ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $botctl_bot['name'] ); ?></strong> <span class="botctl-vendor"><?php echo esc_html( $botctl_bot['vendor'] ); ?></span></td>
						<td><?php echo esc_html( Bot_Control_Admin::category_label( $botctl_bot['category'] ) ); ?></td>
						<td><code class="botctl-patterns"><?php echo esc_html( implode( ', ', $botctl_bot['patterns'] ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="description"><?php esc_html_e( 'Feed bots appear on the Bot Rules tab (marked "feed") where you can override them like any other bot.', 'bot-control' ); ?></p>
		</details>
	<?php endif; ?>
</div>

<?php submit_button( __( 'Save bot list settings', 'bot-control' ) ); ?>
</form>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Update now', 'bot-control' ); ?></h2>
	<div class="botctl-buttons">
		<?php Bot_Control_Admin::form_open( 'refresh_lists', array( 'what' => 'ip' ), 'botctl-inline' ); ?>
			<button class="button"><?php esc_html_e( 'Download official IP lists', 'bot-control' ); ?></button>
		</form>
		<?php Bot_Control_Admin::form_open( 'refresh_lists', array( 'what' => 'feed' ), 'botctl-inline' ); ?>
			<button class="button"><?php esc_html_e( 'Download bot feed', 'bot-control' ); ?></button>
		</form>
	</div>
	<p class="description"><?php esc_html_e( 'Save your settings first – downloads use the saved feed URL and key.', 'bot-control' ); ?></p>
</div>
