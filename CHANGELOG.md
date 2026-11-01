# Changelog

All notable changes to Bot Control are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/). Versioning: [SemVer](https://semver.org/).

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
- **Hebrew translation (he_IL)** and translation template (`languages/bot-control.pot`); RTL-aware admin and email layout.
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
- **Debugging:** `[BotControl][Component]` error_log lines and `X-Bot-Control` headers in debug mode.
- **Documentation:** README, user manual, developer guide.
