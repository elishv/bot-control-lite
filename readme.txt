=== All-IN Crawler & AI Agent Manager ===
Contributors: elis85
Tags: bots, rate limit, crawler, ai crawler, robots.txt
Requires at least: 6.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allow, rate-limit or block bots, AI crawlers and scrapers, and stay visible to search engines and AI assistants.

== Description ==

Much of your traffic is bots. Some bring you customers: Google, Bing, ChatGPT search, social previews. Others only cost you server resources: aggressive AI training crawlers, SEO tools, scrapers and vulnerability scanners. All-IN Crawler & AI Agent Manager lets you decide, per category or per bot, who gets in and how fast, without hiding your site from search engines or AI assistants.

* **About 120 known bots in 12 categories**: search engines, AI search & assistants, AI training crawlers, SEO tools, social previews, monitoring, feed readers, archives, scripts & HTTP libraries, vulnerability scanners, unidentified bots and empty User-Agents.
* **Allow, rate-limit or block** per category, with per-bot overrides and custom User-Agent rules (text or regex).
* **One-click presets**: Balanced, Maximum AI visibility, Protect content from AI training, Lockdown.
* **IP / CIDR allow and block lists** (IPv4 and IPv6), and excluded paths for webhooks and payment callbacks.
* **Protect content from AI training, stay in AI answers**: training crawlers (GPTBot, ClaudeBot, CCBot…) are handled separately from AI search and assistant agents (OAI-SearchBot, ChatGPT-User, Claude-User, Perplexity-User…).
* **Real search engines only**: forward-confirmed reverse DNS plus optional official crawler IP lists (Google, Bing, Apple, OpenAI, Perplexity; off until you turn them on) catch fake Googlebots and GPTBots.
* **Overload protection**: rate limits per IP or per bot with 429 + Retry-After, load shedding with 503 + Retry-After when server load per CPU core crosses your threshold, and an optional per-IP limit for "human" visitors (logged-in users are exempt). SEO-safe status codes only, never cached.
* **robots.txt and llms.txt**: manage robots.txt rules (including Google-Extended, Applebot-Extended and an optional Crawl-delay) and generate an llms.txt that skips noindex content.
* **Block before PHP**: export rules to .htaccess (Apache/LiteSpeed) or nginx. Inside WordPress, requests are decided while plugins load, before the theme runs.
* **Dashboard and reports**: 7/14-day statistics, top denied IPs with one-click blocking, a denied-request log, a User-Agent tester and weekly or monthly email reports.
* **Optional bot-list feed** that adds new AI crawlers between releases (add-only and safety-checked). Off by default, and it only downloads from a URL you enter.
* **Fast storage**: APCu, then Redis/Memcached, then the database.
* **Fails open**: if anything goes wrong inside the plugin, visitors are let through and the error is logged.
* Full Hebrew translation, RTL ready.

Full documentation: see `docs/USER-MANUAL.md` (site owners) and `docs/DEVELOPER.md` (hooks & architecture).

= More from A-in =

This plugin is complete on its own: no licence key, no locked features. Premium plugins, add-ons and support from the same developer are available at [a-in.co.il](https://a-in.co.il/plugins/).

== Installation ==

1. Upload the plugin ZIP via Plugins → Add New → Upload Plugin, then activate it.
2. A new install starts in **Watch-only** mode. It logs what it would do and does not block, rate-limit, shed load, or change robots.txt.
3. Open **All-IN Crawler → Dashboard** and check that "Your IP" shows your real public IP. If it doesn't, configure Advanced → IP source.
4. When the logs look right, switch to **Enforce** (the banner on every tab, or Protection → Mode). Sites that already saved Enforce keep it after an update.
5. Pick a preset on Bot Rules.
6. Optionally export server rules (.htaccess / nginx) on the Server Rules tab so blocked bots are rejected before PHP runs. Server rules are not written while Watch-only is on.

== Frequently Asked Questions ==

= Will this hurt my SEO? =

Installing it does not change how crawlers are treated. A new install starts in Watch-only mode: it logs decisions and does not block, rate-limit, shed load, or edit robots.txt until you switch to Enforce.

With the default Balanced rules, search engines stay allowed. Verification is there to catch scrapers that pretend to be a known crawler, not to turn away a crawler that passes the check. In Enforce mode, rate limits and load shedding answer with 429 or 503 and a Retry-After header, which major search engines treat as "try again later".

It can affect SEO if you choose that. Blocking a search engine, using the Lockdown preset, or publishing robots.txt Disallow lines for a bot you set to Block tells that bot to stay out. Read the Bot Rules and the robots.txt preview before you switch to Enforce.

= Can I block AI training but stay in ChatGPT / Claude / Perplexity answers? =

Yes. Apply the "Protect content from AI training" preset. AI search and assistant agents stay allowed.

= Can I block Google's AI Overviews? =

Not without blocking Google Search. AI Overviews use regular Googlebot. Google-Extended only controls Gemini training/grounding use.

= Does it replace Cloudflare or a server firewall? =

No. It complements them. Large DDoS attacks must be stopped before they reach PHP.

= Does All-IN Crawler & AI Agent Manager contact any outside service by default? =

No. Out of the box it makes no outgoing requests: search engine bots are verified with DNS lookups through your own server's resolver. Two features can download data, and both are off until you turn them on in the Bot Lists tab: the official crawler IP lists (from Google, Bing, Apple, OpenAI and Perplexity) and a bot-list feed URL of your own. See "External services" below for exactly what is requested.

= Why would I turn on the official crawler IP lists? =

OpenAI and Perplexity bots have no reverse DNS, so without their published IP lists All-IN Crawler & AI Agent Manager can't tell a real GPTBot or PerplexityBot from a scraper using the same name; they stay "unverified". With the lists on, a request claiming to be one of those bots from an unlisted IP is treated as an impostor. A list is only used while it is fresh (under 7 days old), so a failed download never blocks a genuine bot.

= Does a page cache bypass it? =

Pages served from a full-page cache don't run WordPress, so All-IN Crawler & AI Agent Manager doesn't see them. Those requests are cheap anyway. Denials are sent with `Cache-Control: no-store` and are never cached.

== External services ==

All-IN Crawler & AI Agent Manager works without any external service. These features contact third parties:

* **Official crawler IP lists** (Bot Lists tab → "Download official crawler IP lists", **off by default, opt-in**). Nothing is downloaded until you tick the box and save; the "Download official IP lists" button is also disabled until then. Once enabled, the site downloads the public IP range files published by the crawler operators once a day, to verify that a request claiming to be their bot really comes from them. No data about your site or visitors is sent: it is a plain HTTPS GET with a generic `all-in-crawler-ai-agent-manager/<version>` User-Agent, so the publisher only sees your server's IP address. Untick the box to stop the downloads at any time. Sources:
  * Google: `https://developers.google.com/static/search/apis/ipranges/` ([Terms](https://policies.google.com/terms), [Privacy](https://policies.google.com/privacy))
  * Bing: `https://www.bing.com/toolbox/bingbot.json` ([Terms](https://www.microsoft.com/servicesagreement), [Privacy](https://privacy.microsoft.com/privacystatement))
  * Apple: `https://search.developer.apple.com/applebot.json` ([Terms](https://www.apple.com/legal/internet-services/terms/site.html), [Privacy](https://www.apple.com/legal/privacy/))
  * OpenAI: `https://openai.com/gptbot.json`, `searchbot.json`, `chatgpt-user.json` ([Terms](https://openai.com/policies/terms-of-use), [Privacy](https://openai.com/policies/privacy-policy))
  * Perplexity: `https://www.perplexity.com/perplexitybot.json`, `perplexity-user.json` ([Terms](https://www.perplexity.ai/hub/legal/terms-of-service), [Privacy](https://www.perplexity.ai/hub/legal/privacy-policy))
* **Bot-list feed** (Lists → Bot list updates, off by default). The plugin ships its extra bot definitions in `data/bots.json` and needs no download. If you enter a feed URL of your own (for example your agency's feed), the site requests that URL once a day, sending your site URL in the User-Agent and, if you set one, your access key as a Bearer token. There is no default remote feed.
* **DNS verification** of search engine bots uses reverse and forward DNS lookups through your server's resolver. No third-party API is called.

== Screenshots ==

1. Dashboard with statistics per category and bot, server load and top denied IPs.
2. Bot rules with one-click presets, category defaults and per-bot overrides.
3. Protection settings: monitor or enforce mode, fake search-engine protection and server-load shedding.
4. User-Agent tester and settings export / import.
5. robots.txt management with a live preview of the added lines.

== Changelog ==

= 1.2.1 =
* Changed: a new install starts in Watch-only mode. It logs and reports only — no blocking, rate limiting, load shedding, or robots.txt changes — until an administrator switches to Enforce.
* Changed: upgrading keeps every saved setting. A site that was already on Enforce in 1.2.0 stays on Enforce.
* Added: the settings screens show the current mode, explain what it does, and switch with one click. A dismissible notice after a fresh activation says the plugin is in Watch-only mode.
* Added: an optional dismissible review notice on this plugin's own screens, shown only after 14 days. No incentive and no effect on features.
* Docs: the SEO FAQ no longer claims the plugin cannot affect SEO. Tested up to WordPress 7.1 (latest stable release is 7.1.4; 7.2 is still in beta).

= 1.2.0 =
* Changed: new name, All-IN Crawler & AI Agent Manager (slug and text domain all-in-crawler-ai-agent-manager). Settings, statistics and logs are kept: the internal botctl_ option and table names are unchanged, and settings exports from earlier versions can still be imported.
* Removed: the optional early loader. The plugin no longer writes any file to wp-content/mu-plugins; a loader created by an older build is removed on upgrade, deactivation and uninstall (only if All-IN Crawler & AI Agent Manager generated it).
* Security: the request URI, User-Agent, method and cookie names are sanitized as soon as they are read; imported settings and admin form input are whitelisted, type-cast and sanitized field by field.
* Changed: translations are loaded by WordPress from translate.wordpress.org; no translation files are bundled.

= 1.1.1 =
* Changed: extra bot definitions (`data/bots.json`) are bundled and always used; no network request is needed for them.
* Changed: there is no default remote feed any more. The feed downloads only from a URL you enter.
* Changed: official crawler IP list downloads are opt-in and off by default. Nothing is downloaded until you enable them on the Bot Lists tab, and a setting saved by an older version never turns them on. The IP-list requests no longer include your site URL.
* Changed: requires WordPress 6.1 (object-cache group flush).
* Docs: External services section, GPL LICENSE file, Tested up to 7.1. Description condensed, Donate link, 5 screenshots with captions.

= 1.1.0 =
* New: official crawler IP lists (Google, Bing, Apple, OpenAI, Perplexity) with fail-safe freshness checks.
* New: remote bot-list feed (add-only, browser-safe validation, optional access key).
* New: .htaccess / nginx server rules with auto-sync.
* New: weekly/monthly white-label email reports.
* New: llms.txt generator.
* New: Hebrew translation.

= 1.0.0 =
* Initial release: bot detection (~120 bots, 12 categories), per-category/per-bot/custom rules, presets, rate limiting (APCu / object cache / DB), DNS verification of search engines, load shedding, human rate limiting, IP allow/block lists, robots.txt management, dashboard, logs, UA tester, import/export, early loader, debug logging.

== Upgrade Notice ==

= 1.2.1 =
New installs start in Watch-only mode and do not block or edit robots.txt until you switch to Enforce. Upgrades keep your saved settings, including Enforce. No database changes.

= 1.2.0 =
New name (All-IN Crawler & AI Agent Manager); your settings and data are kept. Removes the optional mu-plugin early loader (and deletes a leftover copy). Sanitization hardening. No database changes.

= 1.1.1 =
Bundled bot list, no default remote feed, readme updates. No database changes.

= 1.1.0 =
Adds IP-list verification for AI bots, server rules, email reports, llms.txt and Hebrew. No database changes.

= 1.0.0 =
First release.
