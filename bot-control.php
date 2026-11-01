<?php
/**
 * Plugin Name:       Bot Control – Crawler, AI Agent & Scraper Manager
 * Plugin URI:        https://a-in.co.il/plugins/bot-control/
 * Author URI:        https://a-in.co.il/
 * Description:       Decide which bots, crawlers, AI agents and scrapers may access your site, rate-limit them so none can overload it, and stay fully visible to search engines and AI assistants (SEO + AIO safe). Includes official IP-list verification, server rules, email reports and an llms.txt generator.
 * Version:           1.1.1
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            Eli Shvarzman
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bot-control
 * Domain Path:       /languages
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

// The bootstrap may already have been loaded by the early (mu-plugin) loader.
require_once __DIR__ . '/includes/bootstrap.php';
