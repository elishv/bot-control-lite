<?php
/**
 * Bootstrap: defines constants, loads classes and runs the firewall as early as possible.
 *
 * Loaded by the main plugin file. WordPress includes active plugins before the
 * `plugins_loaded` action fires, so running the firewall right here is the earliest
 * point a regular plugin gets, without writing any file outside its own folder.
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'BOTCTL_VERSION' ) ) {
	return;
}

define( 'BOTCTL_VERSION', '1.2.1' );
define( 'BOTCTL_DB_VERSION', '1' );
define( 'BOTCTL_DIR', dirname( __DIR__ ) . '/' );
define( 'BOTCTL_FILE', BOTCTL_DIR . 'all-in-crawler-ai-agent-manager.php' );

require_once BOTCTL_DIR . 'includes/class-botctl-request.php';
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
require_once BOTCTL_DIR . 'includes/class-botctl-legacy.php';
require_once BOTCTL_DIR . 'includes/class-botctl-plugin.php';

Bot_Control_Plugin::init();

// Decide on the request right now – before other plugins and the theme spend CPU on it.
Bot_Control_Firewall::run();
