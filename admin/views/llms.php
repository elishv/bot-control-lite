<?php
/**
 * llms.txt tab.
 *
 * @package BotControl
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;

$botctl_types = get_post_types( array( 'public' => true ), 'objects' );
unset( $botctl_types['attachment'] );
?>
<?php if ( Bot_Control_Llms::physical_file_exists() ) : ?>
	<div class="notice notice-error inline"><p><?php esc_html_e( 'A physical llms.txt file exists in your site root. The web server serves it directly – delete or rename it to use the generated version.', 'bot-control' ); ?></p></div>
<?php endif; ?>

<?php Bot_Control_Admin::form_open( 'save', array( 'tab' => 'llms' ) ); ?>
<div class="botctl-panel">
	<h2><?php esc_html_e( 'llms.txt', 'bot-control' ); ?></h2>
	<p class="description"><?php esc_html_e( 'llms.txt is a proposed standard (llmstxt.org): a short Markdown file at /llms.txt that tells AI assistants what your site is about and which pages matter most. Support is still emerging, so treat it as a low-cost bet on AI visibility, not a ranking factor. Pages set to "noindex" in Yoast SEO, Rank Math or SEOPress are left out automatically.', 'bot-control' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Serve /llms.txt', 'bot-control' ); ?></th>
			<td><label><input type="checkbox" name="botctl[llms_enabled]" value="1" <?php checked( $s['llms_enabled'] ); ?>> <?php esc_html_e( 'Generate and serve llms.txt', 'bot-control' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-llms-summary"><?php esc_html_e( 'Summary', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-llms-summary" type="text" class="large-text" name="botctl[llms_summary]" value="<?php echo esc_attr( $s['llms_summary'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>">
				<p class="description"><?php esc_html_e( 'One sentence: who you are and what you offer. Empty = the site tagline. This is the most important line – write it for an AI that has never heard of you.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-llms-details"><?php esc_html_e( 'Details', 'bot-control' ); ?></label></th>
			<td>
				<textarea id="botctl-llms-details" name="botctl[llms_details]" rows="5" class="large-text"><?php echo esc_textarea( $s['llms_details'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Optional Markdown paragraphs: service area, key facts, pricing model, how to contact you.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Content types', 'bot-control' ); ?></th>
			<td>
				<?php foreach ( $botctl_types as $botctl_type ) : ?>
					<label class="botctl-check"><input type="checkbox" name="botctl[llms_post_types][]" value="<?php echo esc_attr( $botctl_type->name ); ?>" <?php checked( in_array( $botctl_type->name, (array) $s['llms_post_types'], true ) ); ?>> <?php echo esc_html( $botctl_type->labels->name ); ?></label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-llms-limit"><?php esc_html_e( 'Items per type', 'bot-control' ); ?></label></th>
			<td>
				<input id="botctl-llms-limit" type="number" min="1" max="500" class="small-text" name="botctl[llms_limit]" value="<?php echo esc_attr( $s['llms_limit'] ); ?>">
				<p class="description"><?php esc_html_e( 'Pages are listed in menu order, other types newest first. Keep it focused – llms.txt is a curated overview, not a sitemap.', 'bot-control' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="botctl-llms-extra"><?php esc_html_e( 'Extra links', 'bot-control' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" name="botctl[llms_extra_title]" value="<?php echo esc_attr( $s['llms_extra_title'] ); ?>" placeholder="<?php esc_attr_e( 'Section title (default: Resources)', 'bot-control' ); ?>">
				<textarea id="botctl-llms-extra" name="botctl[llms_extra]" rows="4" class="large-text code" placeholder="Price list | https://example.com/prices | Current prices in ILS"><?php echo esc_textarea( $s['llms_extra'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One per line: Title | URL | optional description.', 'bot-control' ); ?></p>
			</td>
		</tr>
	</table>
</div>
<?php submit_button( __( 'Save llms.txt settings', 'bot-control' ) ); ?>
</form>

<div class="botctl-panel">
	<h2><?php esc_html_e( 'Preview', 'bot-control' ); ?></h2>
	<pre class="botctl-pre"><?php echo esc_html( Bot_Control_Llms::build( $s ) ); ?></pre>
	<?php if ( $s['llms_enabled'] ) : ?>
		<p><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your live llms.txt', 'bot-control' ); ?> ↗</a></p>
	<?php endif; ?>
</div>
