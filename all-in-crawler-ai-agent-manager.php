<?php
/**
 * Plugin Name:       All-IN Crawler & AI Agent Manager
 * Author URI:        https://a-in.co.il/
 * Description:       Decide which bots, crawlers, AI agents and scrapers may access your site, rate-limit them so none can overload it, and stay fully visible to search engines and AI assistants (SEO + AIO safe). Includes official IP-list verification, server rules, email reports and an llms.txt generator.
 * Version:           1.2.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            Eli Shvarzman
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       all-in-crawler-ai-agent-manager
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

// Runs the firewall immediately, while WordPress is still including active plugins (before plugins_loaded).
require_once __DIR__ . '/includes/bootstrap.php';
