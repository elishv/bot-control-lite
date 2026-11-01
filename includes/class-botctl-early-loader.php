<?php
/**
 * Optional must-use "early loader".
 *
 * WordPress loads must-use plugins before regular plugins. Installing a tiny
 * loader in wp-content/mu-plugins/ lets Bot Control reject bad requests before
 * any other plugin (WooCommerce, page builders…) spends CPU on them.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Early loader manager.
 */
final class Bot_Control_Early_Loader {

	const FILENAME = 'bot-control-early-loader.php';

	/**
	 * Loader path.
	 *
	 * @return string
	 */
	public static function path() {
		return trailingslashit( WPMU_PLUGIN_DIR ) . self::FILENAME;
	}

	/**
	 * Is it installed?
	 *
	 * @return bool
	 */
	public static function is_installed() {
		return file_exists( self::path() );
	}

	/**
	 * Did it actually load Bot Control on this request?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return defined( 'BOTCTL_EARLY_LOADED' ) && BOTCTL_EARLY_LOADED;
	}

	/**
	 * Write the loader.
	 *
	 * @return true|WP_Error
	 */
	public static function install() {
		$dir = WPMU_PLUGIN_DIR;
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			Bot_Control_Logger::error( 'EarlyLoader', 'Cannot create mu-plugins directory', array( 'dir' => $dir ) );
			return new WP_Error( 'botctl_mu_dir', __( 'Could not create the mu-plugins directory. Check file permissions.', 'bot-control' ) );
		}

		$basename = plugin_basename( BOTCTL_FILE );

		// Paths are resolved at runtime from WP_PLUGIN_DIR, so the loader survives site migrations.
		$code  = "<?php\n";
		$code .= "/**\n";
		$code .= " * Plugin Name: Bot Control – Early Loader\n";
		$code .= " * Description: Runs the Bot Control firewall before regular plugins load. Managed automatically by Bot Control – do not edit.\n";
		$code .= " * Author: Eli Shvarzman\n";
		$code .= " * Version: " . BOTCTL_VERSION . "\n";
		$code .= " */\n\n";
		$code .= "defined( 'ABSPATH' ) || exit;\n\n";
		$code .= '$botctl_basename = ' . var_export( $basename, true ) . ";\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$code .= '$botctl_file     = WP_PLUGIN_DIR . \'/\' . $botctl_basename;' . "\n";
		$code .= '$botctl_active   = in_array( $botctl_basename, (array) get_option( \'active_plugins\', array() ), true );' . "\n";
		$code .= 'if ( ! $botctl_active && is_multisite() ) {' . "\n";
		$code .= "\t" . '$botctl_active = array_key_exists( $botctl_basename, (array) get_site_option( \'active_sitewide_plugins\', array() ) );' . "\n";
		$code .= "}\n";
		$code .= 'if ( $botctl_active && is_readable( dirname( $botctl_file ) . \'/includes/bootstrap.php\' ) ) {' . "\n";
		$code .= "\tdefine( 'BOTCTL_EARLY_LOADED', true );\n";
		$code .= "\tdefine( 'BOTCTL_PLUGIN_FILE', \$botctl_file );\n";
		$code .= "\t" . 'require_once dirname( $botctl_file ) . \'/includes/bootstrap.php\';' . "\n";
		$code .= "}\n";
		$code .= 'unset( $botctl_basename, $botctl_file, $botctl_active );' . "\n";

		$written = @file_put_contents( self::path(), $code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written ) {
			Bot_Control_Logger::error( 'EarlyLoader', 'Cannot write loader file', array( 'path' => self::path() ) );
			return new WP_Error( 'botctl_mu_write', __( 'Could not write the early loader file. Check that wp-content/mu-plugins is writable.', 'bot-control' ) );
		}

		Bot_Control_Logger::debug( 'EarlyLoader', 'Early loader installed', array( 'path' => self::path() ) );
		return true;
	}

	/**
	 * Remove the loader.
	 *
	 * @return void
	 */
	public static function uninstall() {
		if ( self::is_installed() ) {
			wp_delete_file( self::path() );
			Bot_Control_Logger::debug( 'EarlyLoader', 'Early loader removed' );
		}
	}

	/**
	 * Keep the loader's paths current after the plugin folder moved or was updated.
	 *
	 * @return void
	 */
	public static function maybe_refresh() {
		if ( ! self::is_installed() ) {
			return;
		}
		$contents = (string) @file_get_contents( self::path() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === strpos( $contents, 'Version: ' . BOTCTL_VERSION ) || false === strpos( $contents, var_export( plugin_basename( BOTCTL_FILE ), true ) ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			self::install();
		}
	}
}
