# Changelog

All notable changes to All-IN Crawler & AI Agent Manager are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/). Versioning: [SemVer](https://semver.org/).

## [1.2.2] – 2026-11-13
- **Review notice gated on real value.** The optional review notice shows only to admins on the Dashboard and Reports tabs, after 14+ days **and** at least 500 bot requests handled in the last 14 days (`Bot_Control_Admin::REVIEW_MIN_REQUESTS`). It quotes the site's own numbers and offers Leave a review / Maybe later (30-day snooze, `botctl_review_snooze_until` user meta) / I already did. The 1.2.1 dismiss link still works. No incentive, no star wording, no feature gate.
- **Added:** Support forum + Leave a review row-meta links on the Plugins screen, and a support/review line in the email report footer. New `Bot_Control_Stats::totals( $days )`.
- **WordPress.org listing:** new display name (*All-IN Crawler & AI Agent Manager – Block AI Bots, Bad Bots & Rate-Limit Crawlers*), short description, tags (ai crawlers, bot blocker, bad bots, robots.txt, llms.txt), rewritten description and four new FAQ entries. Readme under 10 KB; older changelog entries moved to `changelog.txt`.
- Watch-only remains the default for new installs; no settings or database changes.

## [1.2.1] – 2026-11-07
- **Watch-only by default.** A fresh install saves `mode = monitor`. The firewall still classifies and logs what it would deny, but it does not send 403, 429 or 503, and `robots.txt` is not modified, until an administrator switches to Enforce. Load shedding and rate limits are recorded the same way and are not applied.
- **Upgrades keep saved settings.** `maybe_upgrade()` does not rewrite `botctl_settings`. A 1.2.0 site left on Enforce stays on Enforce, including category rules, verification, load shedding and robots.txt options.
- **Admin:** title badge and a banner on every tab explain Watch-only vs Enforce and switch with one click. A dismissible notice is shown once after a fresh activation. An optional review notice appears only on this plugin's screens after 14 days, with a dismiss control and no incentive or feature gate.
- **Readme:** SEO FAQ no longer says the plugin cannot affect SEO. Tested up to WordPress 7.1 (current stable branch; 7.1.4 is the latest release as of 7 November 2026, and 7.2 is still in beta).

## [1.2.0] – 2026-11-05
WordPress.org review fixes.
- **Renamed** to *All-IN Crawler & AI Agent Manager*: slug, text domain, main file (`all-in-crawler-ai-agent-manager.php`), admin page (`?page=all-in-crawler-ai-agent-manager`), export key, `.pot`, robots.txt / `.htaccess` markers, outbound User-Agent (`all-in-crawler-ai-agent-manager/<version>`), debug header (`X-AllIN-Crawler`) and log prefix (`[AllIN-Crawler]`). **Kept on purpose (no migration needed):** option names (`botctl_settings`, `botctl_db_version`, `botctl_version`, `botctl_runtime`, `botctl_ip_ranges`, `botctl_remote_bots`, `botctl_remote_status`), tables (`{prefix}botctl_rate|stats|events|verify`), cron hooks (`botctl_cleanup|daily|report`), filters/actions (`botctl_*`), constants (`BOTCTL_*`) and PHP classes (`Bot_Control_*`). Old exports (`"plugin": "bot-control"`) still import, and an old `# BEGIN Bot Control` `.htaccess` block is replaced/removed.
- **Removed: early loader.** No file is ever written to `wp-content/mu-plugins`. A loader created by an older build is deleted on upgrade (admin), deactivation and uninstall, only when its header and guard constant show All-IN Crawler & AI Agent Manager generated it. The firewall still runs when WordPress includes the plugin file, before `plugins_loaded`.
- **Security:** new `Bot_Control_Request` reads `REQUEST_URI` (`esc_url_raw`), `HTTP_USER_AGENT` (`sanitize_text_field`), `REQUEST_METHOD` (`sanitize_key`) and cookie names (`sanitize_key`) once, sanitized. Settings import: the decoded JSON is whitelisted against known keys, type-cast and sanitized per field (`Bot_Control_Settings::sanitize_import()` / `clean_input()`) before the strict per-tab validation. Admin form input goes through the same `clean_input()`.
- **i18n:** removed `load_plugin_textdomain()` and the `Domain Path` header; `.po`/`.mo` files are no longer shipped in the WordPress.org zip (translations come from translate.wordpress.org). The `.pot` template stays in the repo.

## [1.1.1] – 2026-10-31
- Bundled `data/bots.json` is always used (no download needed); no default remote feed (the old default pointed at a private repo and returned 404).
- Requires WordPress 6.1.
- WP.org packaging: External services section, LICENSE, `.distignore`, `bin/build-wporg.py`.

## [1.1.0] – 2026-09-24

### Added
- **Official crawler IP lists:** daily download of the IP lists published by Google (Googlebot, user-triggered fetchers, special crawlers), Bing, Apple, OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User) and Perplexity (PerplexityBot, Perplexity-User). Listed IPs are verified without DNS. Bots without reverse DNS (OpenAI, Perplexity) are now verified too, so impostors are blocked. Lists older than 7 days are ignored (fail safe). New **Bot Lists** tab with per-list status and "Download now".
- **Bot-list feed:** optional daily download of extra bot definitions from a JSON feed (default: `data/bots.json` in the repository, 12 newer AI/SEO crawlers), custom feed URL for agencies, optional access key (Bearer) for commercial feeds. Feeds are add-only, HTTPS-only, and patterns matching real browsers are rejected.
- **Server rules:** export blocked bots, empty User-Agents and (optionally) blocked IPs as Apache/LiteSpeed `.htaccess` rules (written at the top of the file with a backup, optional auto-sync) or nginx snippets. Excluded paths and allow-listed IPs are respected. New **Server Rules** tab.
- **Email reports:** weekly or monthly HTML report (totals vs previous period, AI assistant visits, impostors caught, top bots/IPs, estimated server time saved from a measured average page time). White-label brand name, multiple recipients, preview and send-now. New **Reports** tab.
- **llms.txt generator:** virtual `/llms.txt` with summary, details, selected content types, extra links; excludes password-protected and noindex (Yoast SEO, Rank Math, SEOPress) content. New **llms.txt** tab with live preview.
- **Hebrew translation (he_IL)** and translation template (`languages/all-in-crawler-ai-agent-manager.pot`); RTL-aware admin and email layout.
- Bot Rules: "Official IP list" and "feed" badges. User-Agent tester shows IP-list verification.
- New hooks: `botctl_settings_updated`, `botctl_ip_list_sources`, `botctl_llms_txt`, `botctl_llms_exclude_post`.

### Changed
- Verification result label "Impostor (DNS mismatch)" is now "Impostor (not genuine)", because it also covers IP-list mismatches.
- Deactivation now also removes the `.htaccess` rules and clears the new cron events. Uninstall removes the new options.

## [1.0.0] – 2026-09-24

Initial release by Eli Shvarzman.

### Added
- **Detection:** built-in database of ~120 bots in 12 categories (search engines, AI search & assistants, AI training, SEO tools, social previews, monitoring, feed readers, archives, scripts/HTTP libraries, scanners, unidentified bots, empty User-Agent). Generic detection of unknown bots, with per-bot statistics by product token.
- **Rules:** allow / rate limit / block per category, per-bot overrides, custom User-Agent rules (contains or regex), rate-limit scope per IP or per bot.
- **Presets:** Balanced, Maximum AI visibility, Protect content from AI training, Lockdown.
- **Anti-spoofing:** forward-confirmed reverse DNS verification for Google, Bing, Apple, Yandex, Baidu, Yahoo, Petal and Amazon, cached per IP; impostor policy.
- **Overload protection:** 429 + Retry-After rate limiting; load shedding with 503 + Retry-After based on load average per CPU core; optional per-IP human rate limit with logged-in users exempt.
- **Storage:** APCu → persistent object cache → database (a single atomic upsert), all failing open.
- **IP handling:** proxy/CDN-aware client IP detection (Cloudflare ranges, private proxies, custom trusted proxies); IPv4/IPv6 CIDR allow and block lists; excluded paths.
- **robots.txt:** Disallow groups for blocked bots, including Google-Extended and Applebot-Extended; optional Crawl-delay that copies the `User-agent: *` rules.
- **Admin:** dashboard (status, 7/14-day statistics, top bots, top denied IPs), rule editor, custom bots, protection, robots.txt preview, throttled log with block/allow actions, User-Agent tester with live DNS check, settings export/import, reset, early loader installer.
- **Early loader:** optional mu-plugin that runs the firewall before all other plugins.
- **Debugging:** `[AllIN-Crawler][Component]` error_log lines and `X-AllIN-Crawler` headers in debug mode.
- **Documentation:** README, user manual, developer guide.
