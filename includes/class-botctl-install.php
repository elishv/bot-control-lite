<?php
/**
 * Activation, database tables, upgrades and scheduled cleanup.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Install.
 */
final class Bot_Control_Install {

	const DB_OPTION      = 'botctl_db_version';
	const VERSION_OPTION = 'botctl_version';
	const CRON_HOOK  = 'botctl_cleanup';
	const DAILY_HOOK = 'botctl_daily';

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		if ( false === get_option( Bot_Control_Settings::OPTION, false ) ) {
			add_option( Bot_Control_Settings::OPTION, Bot_Control_Settings::defaults(), '', true );
		}
		self::schedule();
		Bot_Control_Reports::schedule();
		Bot_Control_Logger::debug( 'Install', 'Plugin activated', array( 'version' => BOTCTL_VERSION ) );
	}

	/**
	 * Deactivation: stop cron, remove server rules and any mu-plugin loader left by an older build.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		wp_clear_scheduled_hook( Bot_Control_Reports::CRON );
		Bot_Control_Legacy::remove_early_loader();
		// Server rules must not keep blocking once the plugin is off.
		if ( Bot_Control_Server_Rules::htaccess_has_rules() ) {
			Bot_Control_Server_Rules::write_htaccess( null, true );
		}
		Bot_Control_Logger::debug( 'Install', 'Plugin deactivated' );
	}

	/**
	 * Run DB upgrades when the schema version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( BOTCTL_DB_VERSION !== get_option( self::DB_OPTION ) ) {
			self::create_tables();
		}
		if ( BOTCTL_VERSION !== get_option( self::VERSION_OPTION ) ) {
			// Once per version change: drop the mu-plugin loader an older build may have created.
			Bot_Control_Legacy::remove_early_loader();
			update_option( self::VERSION_OPTION, BOTCTL_VERSION, false );
		}
		self::schedule();
	}

	/**
	 * Make sure recurring events exist.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			// Daily housekeeping. It only downloads anything if the owner opted in (see daily()).
			wp_schedule_event( time() + 60, 'daily', self::DAILY_HOOK );
		}
	}

	/**
	 * Daily: refresh official IP lists and the remote bot feed.
	 *
	 * @return void
	 */
	public static function daily() {
		$s = Bot_Control_Settings::get();
		if ( ! empty( $s['ip_lists_optin'] ) ) {
			Bot_Control_IP_Lists::refresh();
		}
		if ( ! empty( $s['remote_list_enabled'] ) ) {
			Bot_Control_Remote_List::refresh();
			Bot_Control_Bot_Registry::reset();
			// New feed bots may change which User-Agents are blocked at server level.
			Bot_Control_Server_Rules::maybe_sync( $s );
		}
	}

	/**
	 * Create / update tables with dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql = array(
			"CREATE TABLE {$p}botctl_rate (
				rkey char(32) NOT NULL,
				win int(10) unsigned NOT NULL DEFAULT 0,
				hits int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (rkey),
				KEY win (win)
			) {$charset};",
			"CREATE TABLE {$p}botctl_stats (
				day date NOT NULL,
				bot_id varchar(64) NOT NULL,
				label varchar(100) NOT NULL DEFAULT '',
				category varchar(32) NOT NULL DEFAULT '',
				allowed int(10) unsigned NOT NULL DEFAULT 0,
				limited int(10) unsigned NOT NULL DEFAULT 0,
				blocked int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (day,bot_id),
				KEY category (category)
			) {$charset};",
			"CREATE TABLE {$p}botctl_events (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ts datetime NOT NULL,
				ip varchar(45) NOT NULL DEFAULT '',
				bot_id varchar(64) NOT NULL DEFAULT '',
				label varchar(100) NOT NULL DEFAULT '',
				category varchar(32) NOT NULL DEFAULT '',
				action varchar(16) NOT NULL DEFAULT '',
				enforced tinyint(1) NOT NULL DEFAULT 1,
				method varchar(8) NOT NULL DEFAULT '',
				uri varchar(255) NOT NULL DEFAULT '',
				ua varchar(255) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY ts (ts),
				KEY ip (ip),
				KEY action (action)
			) {$charset};",
			"CREATE TABLE {$p}botctl_verify (
				ip varchar(45) NOT NULL,
				bot_id varchar(64) NOT NULL,
				status tinyint(1) NOT NULL DEFAULT 2,
				host varchar(255) NOT NULL DEFAULT '',
				checked int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (ip,bot_id),
				KEY checked (checked)
			) {$charset};",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		if ( $wpdb->last_error ) {
			Bot_Control_Logger::error( 'Install', 'dbDelta reported an error', array( 'db_error' => $wpdb->last_error ) );
		}

		update_option( self::DB_OPTION, BOTCTL_DB_VERSION, true );
		Bot_Control_Logger::debug( 'Install', 'Tables created/updated', array( 'db_version' => BOTCTL_DB_VERSION ) );
	}

	/**
	 * Hourly cleanup.
	 *
	 * @return void
	 */
	public static function cleanup() {
		Bot_Control_Rate_Limiter::cleanup();
		Bot_Control_Stats::cleanup();
	}
}
