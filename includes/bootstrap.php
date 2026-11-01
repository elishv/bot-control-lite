<?php
/**
 * Bootstrap: defines constants, loads classes and runs the firewall as early as possible.
 *
 * This file is loaded either by the main plugin file or, earlier, by the optional
 * mu-plugin "early loader" (see Bot_Control_Early_Loader). require_once guarantees
 * it only ever runs once per request.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'BOTCTL_VERSION' ) ) {
	return;
}

define( 'BOTCTL_VERSION', '1.1.1' );
define( 'BOTCTL_DB_VERSION', '1' );
define( 'BOTCTL_DIR', dirname( __DIR__ ) . '/' );
// The early loader passes the plugin path as WordPress sees it (keeps symlinked installs working).
define( 'BOTCTL_FILE', defined( 'BOTCTL_PLUGIN_FILE' ) ? BOTCTL_PLUGIN_FILE : BOTCTL_DIR . 'bot-control.php' );

require_once BOTCTL_DIR . 'includes/class-botctl-logger.php';
require_once BOTCTL_DIR . 'includes/class-botctl-bot-registry.php';
require_once BOTCTL_DIR . 'includes/class-botctl-settings.php';
require_once BOTCTL_DIR . 'includes/class-botctl-ip.php';
require_once BOTCTL_DIR . 'includes/class-botctl-detector.php';
require_once BOTCTL_DIR . 'includes/class-botctl-policy.php';
require_once BOTCTL_DIR . 'includes/class-botctl-verifier.php';
require_once BOTCTL_DIR . 'includes/class-botctl-rate-limiter.php';
require_once BOTCTL_DIR . 'includes/class-botctl-load-monitor.php';
require_once BOTCTL_DIR . 'includes/class-botctl-stats.php';
require_once BOTCTL_DIR . 'includes/class-botctl-firewall.php';
require_once BOTCTL_DIR . 'includes/class-botctl-robots.php';
require_once BOTCTL_DIR . 'includes/class-botctl-ip-lists.php';
require_once BOTCTL_DIR . 'includes/class-botctl-remote-list.php';
require_once BOTCTL_DIR . 'includes/class-botctl-server-rules.php';
require_once BOTCTL_DIR . 'includes/class-botctl-reports.php';
require_once BOTCTL_DIR . 'includes/class-botctl-llms.php';
require_once BOTCTL_DIR . 'includes/class-botctl-install.php';
require_once BOTCTL_DIR . 'includes/class-botctl-early-loader.php';
require_once BOTCTL_DIR . 'includes/class-botctl-plugin.php';

Bot_Control_Plugin::init();

// Decide on the request right now – before other plugins and the theme spend CPU on it.
Bot_Control_Firewall::run();
