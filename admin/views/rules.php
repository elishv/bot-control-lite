<?php
/**
 * Bot rules tab: presets, category defaults, per-bot overrides.
 *
 * @package BotControl
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_categories = Bot_Control_Bot_Registry::categories();
$botctl_registry   = Bot_Control_Bot_Registry::all();
$botctl_window     = (int) $s['window'];
$botctl_ipsrc      = Bot_Control_IP_Lists::sources();
?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'Quick presets', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'A preset replaces the category defaults below. Your per-bot overrides are kept.', 'bot-control' ); ?></p>
	<div class="botctl-presets">
		<?php foreach ( Bot_Control_Settings::presets() as $botctl_id => $botctl_preset ) : ?>
			<div class="botctl-preset">
				<h3><?php echo esc_html( $botctl_preset['label'] ); ?></h3>
				<p><?php echo esc_html( $botctl_preset['description'] ); ?></p>
				<?php Bot_Control_Admin::form_open( 'preset', array( 'preset' => $botctl_id ) ); ?>
					<button class="button botctl-confirm"><?php esc_html_e( 'Apply', 'bot-control' ); ?></button>
				</form>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'rules' ) ); ?>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Category defaults', 'bot-control' ); ?></h2>
	<p class="description">
		<?php
		/* translators: %d: seconds */
		printf( esc_html__( 'Limits are requests per %d seconds (change the window on the Protection tab). "Per IP" counts each IP separately; "Per bot" counts all IPs of the same bot together – best against crawlers that spread over hundreds of IPs.', 'bot-control' ), (int) $botctl_window );
		?>
	</p>
	<table class="widefat striped botctl-rules">
		<thead><tr>
			<th><?php esc_html_e( 'Category', 'bot-control' ); ?></th>
			<th><?php esc_html_e( 'Policy', 'bot-control' ); ?></th>
			<th><?php esc_html_e( 'Limit', 'bot-control' ); ?></th>
			<th><?php esc_html_e( 'Count', 'bot-control' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( Bot_Control_Bot_Registry::category_ids() as $botctl_cat ) : ?>
			<?php $botctl_rule = $s['categories'][ $botctl_cat ]; ?>
			<tr>
				<td>
					<strong><?php echo esc_html( $botctl_categories[ $botctl_cat ]['label'] ); ?></strong>
					<p class="description"><?php echo esc_html( $botctl_categories[ $botctl_cat ]['description'] ); ?></p>
				</td>
				<td><?php Bot_Control_Admin::select( 'botctl[categories][' . $botctl_cat . '][policy]', Bot_Control_Admin::policy_options(), $botctl_rule['policy'], array( 'class' => 'botctl-policy' ) ); ?></td>
				<td><input type="number" min="1" max="100000" class="small-text" name="botctl[categories][<?php echo esc_attr( $botctl_cat ); ?>][limit]" value="<?php echo esc_attr( $botctl_rule['limit'] ); ?>"> / <?php echo (int) $botctl_window; ?>s</td>
				<td>
					<?php
					Bot_Control_Admin::select(
						'botctl[categories][' . $botctl_cat . '][scope]',
						array(
							'ip'  => __( 'Per IP', 'bot-control' ),
							'bot' => __( 'Per bot', 'bot-control' ),
						),
						$botctl_rule['scope']
					);
					?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Individual bots', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Override the category default for a specific bot. Leave the limit empty to use the category limit.', 'bot-control' ); ?></p>
	<p><input type="search" class="regular-text botctl-filter" placeholder="<?php esc_attr_e( 'Filter bots…', 'bot-control' ); ?>"></p>

	<?php foreach ( Bot_Control_Bot_Registry::category_ids() as $botctl_cat ) : ?>
		<?php
		$botctl_in_cat = array_filter(
			$botctl_registry,
			function ( $bot ) use ( $botctl_cat ) {
				return $bot['category'] === $botctl_cat;
			}
		);
		if ( empty( $botctl_in_cat ) ) {
			continue;
		}
		?>
		<details class="botctl-details" <?php echo in_array( $botctl_cat, array( 'ai_search', 'ai_training' ), true ) ? 'open' : ''; ?>>
			<summary><?php echo esc_html( $botctl_categories[ $botctl_cat ]['label'] ); ?> <span class="count">(<?php echo count( $botctl_in_cat ); ?>)</span></summary>
			<table class="widefat striped botctl-bots">
				<thead><tr>
					<th><?php esc_html_e( 'Bot', 'bot-control' ); ?></th>
					<th><?php esc_html_e( 'Matches User-Agent', 'bot-control' ); ?></th>
					<th><?php esc_html_e( 'Policy', 'bot-control' ); ?></th>
					<th><?php esc_html_e( 'Limit override', 'bot-control' ); ?></th>
					<th><?php esc_html_e( 'Effective', 'bot-control' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $botctl_in_cat as $botctl_id => $botctl_bot ) : ?>
					<?php
					$botctl_over      = isset( $s['bots'][ $botctl_id ] ) ? $s['bots'][ $botctl_id ] : array( 'policy' => 'inherit', 'limit' => 0 );
					$botctl_effective = Bot_Control_Policy::for_bot( $botctl_id, $s );
					$botctl_opts      = Bot_Control_Admin::policy_options( true );
					if ( $botctl_bot['robots_only'] ) {
						unset( $botctl_opts['limit'] );
					}
					?>
					<tr class="botctl-bot-row" data-search="<?php echo esc_attr( strtolower( $botctl_bot['name'] . ' ' . $botctl_bot['vendor'] . ' ' . implode( ' ', $botctl_bot['patterns'] ) ) ); ?>">
						<td>
							<strong><?php echo esc_html( $botctl_bot['name'] ); ?></strong>
							<span class="botctl-vendor"><?php echo esc_html( $botctl_bot['vendor'] ); ?></span>
							<?php if ( ! empty( $botctl_bot['verify'] ) ) : ?>
								<span class="botctl-badge" title="<?php esc_attr_e( 'Genuine requests are verified by reverse DNS; fakes are treated as impostors.', 'bot-control' ); ?>"><?php esc_html_e( 'DNS verified', 'bot-control' ); ?></span>
							<?php endif; ?>
							<?php if ( isset( $botctl_ipsrc[ $botctl_id ] ) ) : ?>
								<span class="botctl-badge" title="<?php esc_attr_e( 'Verified against the IP list the vendor publishes (Bot Lists tab).', 'bot-control' ); ?>"><?php esc_html_e( 'Official IP list', 'bot-control' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $botctl_bot['remote'] ) ) : ?>
								<span class="botctl-badge botctl-badge-alt"><?php esc_html_e( 'feed', 'bot-control' ); ?></span>
							<?php endif; ?>
							<?php if ( $botctl_bot['robots_only'] ) : ?>
								<span class="botctl-badge botctl-badge-alt"><?php esc_html_e( 'robots.txt only', 'bot-control' ); ?></span>
							<?php endif; ?>
							<?php if ( $botctl_bot['note'] ) : ?>
								<p class="description"><?php echo esc_html( $botctl_bot['note'] ); ?></p>
							<?php endif; ?>
						</td>
						<td><code class="botctl-patterns"><?php echo esc_html( $botctl_bot['robots_only'] ? implode( ', ', $botctl_bot['robots'] ) : implode( ', ', $botctl_bot['patterns'] ) ); ?></code></td>
						<td><?php Bot_Control_Admin::select( 'botctl[bots][' . $botctl_id . '][policy]', $botctl_opts, $botctl_over['policy'] ); ?></td>
						<td>
							<?php if ( ! $botctl_bot['robots_only'] ) : ?>
								<input type="number" min="0" max="100000" class="small-text" placeholder="<?php echo esc_attr( $s['categories'][ $botctl_cat ]['limit'] ); ?>" name="botctl[bots][<?php echo esc_attr( $botctl_id ); ?>][limit]" value="<?php echo $botctl_over['limit'] ? esc_attr( $botctl_over['limit'] ) : ''; ?>">
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $botctl_bot['robots_only'] ) : ?>
								<span class="botctl-pill botctl-pill-<?php echo 'block' === $botctl_effective['policy'] ? 'block' : 'allow'; ?>"><?php echo 'block' === $botctl_effective['policy'] ? esc_html__( 'Disallowed in robots.txt', 'bot-control' ) : esc_html__( 'Allowed', 'bot-control' ); ?></span>
							<?php else : ?>
								<span class="botctl-pill botctl-pill-<?php echo esc_attr( $botctl_effective['policy'] ); ?>">
									<?php
									$botctl_labels = Bot_Control_Admin::policy_options();
									echo esc_html( $botctl_labels[ $botctl_effective['policy'] ] );
									if ( 'limit' === $botctl_effective['policy'] ) {
										echo ' ' . esc_html( $botctl_effective['limit'] . '/' . $botctl_window . 's' );
									}
									?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</details>
	<?php endforeach; ?>
</div>

<?php submit_button( __( 'Save bot rules', 'bot-control' ) ); ?>
</form>
