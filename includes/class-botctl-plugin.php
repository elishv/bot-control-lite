<?php
/**
 * Wires everything together.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin.
 */
final class Bot_Control_Plugin {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		register_activation_hook( BOTCTL_FILE, array( 'Bot_Control_Install', 'activate' ) );
		register_deactivation_hook( BOTCTL_FILE, array( 'Bot_Control_Install', 'deactivate' ) );

		add_action( Bot_Control_Install::CRON_HOOK, array( 'Bot_Control_Install', 'cleanup' ) );
		add_action( Bot_Control_Install::DAILY_HOOK, array( 'Bot_Control_Install', 'daily' ) );

		Bot_Control_Robots::init();
		Bot_Control_Llms::init();
		Bot_Control_Reports::init();
		Bot_Control_Server_Rules::init();

		if ( is_admin() ) {
			require_once BOTCTL_DIR . 'admin/class-botctl-admin.php';
			Bot_Control_Admin::init();
		}
	}
}
