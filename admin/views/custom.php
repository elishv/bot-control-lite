<?php
/**
 * Custom bots tab.
 *
 * @package AllInCrawlerAIAgentManager
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_categories = Bot_Control_Bot_Registry::categories();
$botctl_cat_opts   = array();
foreach ( $botctl_categories as $botctl_id => $botctl_cat ) {
	$botctl_cat_opts[ $botctl_id ] = $botctl_cat['label'];
}

/**
 * Render one editable row.
 *
 * @param string $index    Row index.
 * @param array  $row      Row data.
 * @param array  $cat_opts Category options.
 * @return void
 */
$botctl_render_row = function ( $index, array $row, array $cat_opts ) {
	$base = 'botctl[custom_bots][' . $index . ']';
	?>
	<tr class="botctl-custom-row">
		<td>
			<input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $row['id'] ); ?>">
			<input type="text" class="regular-text" name="<?php echo esc_attr( $base ); ?>[name]" value="<?php echo esc_attr( $row['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Partner feed importer', 'all-in-crawler-ai-agent-manager' ); ?>">
		</td>
		<td>
			<input type="text" class="regular-text code" name="<?php echo esc_attr( $base ); ?>[pattern]" value="<?php echo esc_attr( $row['pattern'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. MyPartnerBot', 'all-in-crawler-ai-agent-manager' ); ?>">
		</td>
		<td>
			<?php
			Bot_Control_Admin::select(
				$base . '[match]',
				array(
					'contains' => __( 'Contains', 'all-in-crawler-ai-agent-manager' ),
					'regex'    => __( 'Regex', 'all-in-crawler-ai-agent-manager' ),
				),
				$row['match']
			);
			?>
		</td>
		<td><?php Bot_Control_Admin::select( $base . '[category]', $cat_opts, $row['category'] ); ?></td>
		<td><?php Bot_Control_Admin::select( $base . '[policy]', Bot_Control_Admin::policy_options( true ), $row['policy'] ); ?></td>
		<td><input type="number" min="0" max="100000" class="small-text" name="<?php echo esc_attr( $base ); ?>[limit]" value="<?php echo $row['limit'] ? esc_attr( $row['limit'] ) : ''; ?>"></td>
		<td><button type="button" class="button-link botctl-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'all-in-crawler-ai-agent-manager' ); ?>"><span class="dashicons dashicons-trash"></span></button></td>
	</tr>
	<?php
};

$botctl_empty = array(
	'id'       => '',
	'name'     => '',
	'pattern'  => '',
	'match'    => 'contains',
	'category' => 'unknown_bot',
	'policy'   => 'inherit',
	'limit'    => 0,
);
?>
<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'custom' ) ); ?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'Custom bots', 'all-in-crawler-ai-agent-manager' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Add bots that are not in the built-in list, or override how a specific User-Agent is treated. Custom rules are checked before the built-in list, so they always win.', 'all-in-crawler-ai-agent-manager' ); ?><br>
		<?php esc_html_e( '"Contains" is a case-insensitive text match. "Regex" is a PCRE pattern without delimiters (e.g. ^MyBot/\d+).', 'all-in-crawler-ai-agent-manager' ); ?>
	</p>

	<table class="widefat botctl-custom">
		<thead><tr>
			<th><?php esc_html_e( 'Name', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'User-Agent pattern', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Match', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Category', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Policy', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th><?php esc_html_e( 'Limit', 'all-in-crawler-ai-agent-manager' ); ?></th>
			<th></th>
		</tr></thead>
		<tbody id="botctl-custom-body">
		<?php
		foreach ( array_values( $s['custom_bots'] ) as $botctl_i => $botctl_row ) {
			$botctl_render_row( (string) $botctl_i, array_merge( $botctl_empty, $botctl_row ), $botctl_cat_opts );
		}
		?>
		</tbody>
	</table>

	<script type="text/template" id="botctl-custom-template">
		<?php $botctl_render_row( '__INDEX__', $botctl_empty, $botctl_cat_opts ); ?>
	</script>

	<p><button type="button" class="button" id="botctl-add-row"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add bot', 'all-in-crawler-ai-agent-manager' ); ?></button></p>
	<p class="description"><?php esc_html_e( 'Tip: use the User-Agent tester on the Tools tab to check which rule a User-Agent hits.', 'all-in-crawler-ai-agent-manager' ); ?></p>
</div>
<?php submit_button( __( 'Save custom bots', 'all-in-crawler-ai-agent-manager' ) ); ?>
</form>
