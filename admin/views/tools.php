<?php
/**
 * Tools tab.
 *
 * @package AllInCrawlerAIAgentManager
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only tester.
$botctl_test_ua = isset( $_GET['test_ua'] ) ? sanitize_text_field( wp_unslash( $_GET['test_ua'] ) ) : '';
$botctl_test_ip = isset( $_GET['test_ip'] ) ? sanitize_text_field( wp_unslash( $_GET['test_ip'] ) ) : '';
$botctl_tested  = isset( $_GET['test_ua'] );
// phpcs:enable

$botctl_samples = array(
	'Googlebot'           => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
	'GPTBot'              => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)',
	'ChatGPT-User'        => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot',
	'python-requests'     => 'python-requests/2.32.3',
	'Chrome (human)'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
);
?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'User-Agent tester', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description"><?php esc_html_e( 'See how All-IN Crawler & AI Agent Manager would classify a request. Add an IP to also run the DNS verification (a real lookup).', 'all-in-crawler-ai-agent-manager' ); ?></p>
	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( Bot_Control_Admin::SLUG ); ?>">
		<input type="hidden" name="tab" value="tools">
		<p><input type="text" name="test_ua" class="large-text code" value="<?php echo esc_attr( $botctl_test_ua ); ?>" placeholder="<?php esc_attr_e( 'Paste a User-Agent (leave empty to test an empty User-Agent)', 'all-in-crawler-ai-agent-manager' ); ?>"></p>
		<p>
			<input type="text" name="test_ip" class="regular-text code" value="<?php echo esc_attr( $botctl_test_ip ); ?>" placeholder="<?php esc_attr_e( 'Optional IP, e.g. 66.249.66.1', 'all-in-crawler-ai-agent-manager' ); ?>">
			<button class="button button-primary"><?php esc_html_e( 'Test', 'all-in-crawler-ai-agent-manager' ); ?></button>
		</p>
		<p class="botctl-samples"><?php esc_html_e( 'Samples:', 'all-in-crawler-ai-agent-manager' ); ?>
			<?php foreach ( $botctl_samples as $botctl_label => $botctl_ua ) : ?>
				<a href="<?php echo esc_url( Bot_Control_Admin::url( 'tools', array( 'test_ua' => $botctl_ua ) ) ); ?>"><?php echo esc_html( $botctl_label ); ?></a>
			<?php endforeach; ?>
		</p>
	</form>

	<?php if ( $botctl_tested ) : ?>
		<?php
		$botctl_match = Bot_Control_Detector::detect( $botctl_test_ua, $s );
		$botctl_rule  = 'bot' === $botctl_match['type'] ? Bot_Control_Policy::resolve( $botctl_match, $s ) : null;
		$botctl_ver   = null;
		$botctl_needs = ! empty( $botctl_match['verify'] ) || ( 'registry' === $botctl_match['source'] && Bot_Control_IP_Lists::has( $botctl_match['bot_id'] ) );
		if ( $botctl_test_ip && Bot_Control_IP::is_valid_ip( $botctl_test_ip ) && $botctl_needs ) {
			$botctl_ver = Bot_Control_Verifier::verify( Bot_Control_IP::normalize( $botctl_test_ip ), $botctl_match['bot_id'], $botctl_match['verify'], false );
		}
		$botctl_policies = Bot_Control_Admin::policy_options();
		?>
		<table class="widefat botctl-result">
			<tr><th><?php esc_html_e( 'Classified as', 'all-in-crawler-ai-agent-manager' ); ?></th><td><strong><?php echo esc_html( $botctl_match['label'] ); ?></strong> <code><?php echo esc_html( $botctl_match['bot_id'] ); ?></code></td></tr>
			<tr><th><?php esc_html_e( 'Category', 'all-in-crawler-ai-agent-manager' ); ?></th><td><?php echo esc_html( Bot_Control_Admin::category_label( $botctl_match['category'] ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Matched by', 'all-in-crawler-ai-agent-manager' ); ?></th><td><?php echo esc_html( $botctl_match['source'] ); ?></td></tr>
			<?php if ( $botctl_rule ) : ?>
				<tr><th><?php esc_html_e( 'Policy', 'all-in-crawler-ai-agent-manager' ); ?></th><td>
					<span class="botctl-pill botctl-pill-<?php echo esc_attr( $botctl_rule['policy'] ); ?>"><?php echo esc_html( $botctl_policies[ $botctl_rule['policy'] ] ); ?></span>
					<?php if ( 'limit' === $botctl_rule['policy'] ) : ?>
						<?php
						/* translators: 1: limit, 2: seconds, 3: scope */
						printf( esc_html__( '%1$d requests / %2$d s, counted %3$s', 'all-in-crawler-ai-agent-manager' ), (int) $botctl_rule['limit'], (int) $s['window'], 'bot' === $botctl_rule['scope'] ? esc_html__( 'per bot (all IPs)', 'all-in-crawler-ai-agent-manager' ) : esc_html__( 'per IP', 'all-in-crawler-ai-agent-manager' ) );
						?>
					<?php endif; ?>
					<span class="description">(<?php echo esc_html( $botctl_rule['origin'] ); ?>)</span>
				</td></tr>
			<?php else : ?>
				<tr><th><?php esc_html_e( 'Policy', 'all-in-crawler-ai-agent-manager' ); ?></th><td>
					<?php
					echo $s['human_limit_enabled']
						/* translators: 1: limit, 2: seconds */
						? esc_html( sprintf( __( 'Human visitor – limited to %1$d requests / %2$d s per IP (logged-in users exempt).', 'all-in-crawler-ai-agent-manager' ), (int) $s['human_limit'], (int) $s['window'] ) )
						: esc_html__( 'Human visitor – not limited.', 'all-in-crawler-ai-agent-manager' );
					?>
				</td></tr>
			<?php endif; ?>
			<?php if ( $botctl_needs ) : ?>
				<tr><th><?php esc_html_e( 'Verification', 'all-in-crawler-ai-agent-manager' ); ?></th><td>
					<?php
					if ( Bot_Control_IP_Lists::has( $botctl_match['bot_id'] ) ) {
						esc_html_e( 'Official IP list (checked first).', 'all-in-crawler-ai-agent-manager' );
						echo ' ';
					}
					if ( ! empty( $botctl_match['verify'] ) ) {
						/* translators: %s: domains */
						printf( esc_html__( 'Reverse DNS must end in: %s', 'all-in-crawler-ai-agent-manager' ), '<code>' . esc_html( implode( ', ', $botctl_match['verify'] ) ) . '</code>' );
					}
					if ( $botctl_ver ) {
						echo '<br><strong>' . esc_html( Bot_Control_Verifier::label( $botctl_ver['status'] ) ) . '</strong>';
						if ( $botctl_ver['host'] ) {
							echo ' – <code>' . esc_html( $botctl_ver['host'] ) . '</code>';
						}
					}
					?>
				</td></tr>
			<?php endif; ?>
		</table>
	<?php endif; ?>
</div>

<div class="botctl-grid">
	<div class="botctl-panel">
		<h2><?php esc_html_e( 'Export / import settings', 'all-in-crawler-ai-agent-manager' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Move your configuration between sites (e.g. staging → production, or across client sites).', 'all-in-crawler-ai-agent-manager' ); ?></p>
		<?php Bot_Control_Admin::form_open( 'export' ); ?>
			<p><button class="button"><?php esc_html_e( 'Download settings (JSON)', 'all-in-crawler-ai-agent-manager' ); ?></button></p>
		</form>
		<?php Bot_Control_Admin::form_open( 'import' ); ?>
			<p><textarea name="json" rows="5" class="large-text code" placeholder="<?php esc_attr_e( 'Paste exported JSON here', 'all-in-crawler-ai-agent-manager' ); ?>"></textarea></p>
			<p><button class="button botctl-confirm"><?php esc_html_e( 'Import settings', 'all-in-crawler-ai-agent-manager' ); ?></button></p>
		</form>
	</div>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Maintenance', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<div class="botctl-buttons">
		<?php Bot_Control_Admin::form_open( 'clear_stats', array(), 'botctl-inline' ); ?>
			<button class="button botctl-confirm"><?php esc_html_e( 'Clear statistics', 'all-in-crawler-ai-agent-manager' ); ?></button>
		</form>
		<?php Bot_Control_Admin::form_open( 'clear_verify', array(), 'botctl-inline' ); ?>
			<button class="button"><?php esc_html_e( 'Clear DNS verification cache', 'all-in-crawler-ai-agent-manager' ); ?></button>
		</form>
		<?php Bot_Control_Admin::form_open( 'reset', array(), 'botctl-inline' ); ?>
			<button class="button button-link-delete botctl-confirm"><?php esc_html_e( 'Reset all settings to defaults', 'all-in-crawler-ai-agent-manager' ); ?></button>
		</form>
	</div>
</div>
