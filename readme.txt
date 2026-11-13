=== All-IN Crawler & AI Agent Manager – Block AI Bots, Bad Bots & Rate-Limit Crawlers ===
Contributors: elis85
Tags: ai crawlers, bot blocker, bad bots, robots.txt, llms.txt
Requires at least: 6.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block AI crawlers and bad bots, rate-limit scrapers, manage robots.txt and llms.txt, and stay visible in Google, Bing and AI search answers.

== Description ==

A bot blocker and AI crawler manager for WordPress. Block AI crawlers like GPTBot, ClaudeBot and CCBot, stop bad bots and scrapers, and rate-limit aggressive crawlers, while Google, Bing and AI assistants like ChatGPT search can still find you. New installs start in Watch-only mode, so nothing is blocked until you choose.

= Why use it =

* **Protect your content from AI training** without disappearing from AI answers.
* **Cut server load** from aggressive crawlers, SEO tools and scrapers.
* **Stay in search**: real search engines stay allowed and verified.

= Block AI crawlers, keep AI search =

About 120 known bots in 12 categories. Training crawlers (GPTBot, ClaudeBot, CCBot…) are handled separately from AI search and assistant agents (OAI-SearchBot, ChatGPT-User, Claude-User, Perplexity-User…). Allow, rate-limit or block per category, with per-bot overrides, custom User-Agent rules (text or regex) and one-click presets: Balanced, Maximum AI visibility, Protect content from AI training, Lockdown.

= Stop bad bots, fake Googlebots and scrapers =

Forward-confirmed reverse DNS plus optional official crawler IP lists (Google, Bing, Apple, OpenAI, Perplexity; off until you turn them on) catch impostors. Scanners, HTTP libraries and empty User-Agents get their own categories. IP / CIDR allow and block lists (IPv4 and IPv6) and excluded paths for webhooks.

= Rate limit bots and protect server load =

Rate limits per IP or per bot answer with 429 + Retry-After. Load shedding answers with 503 + Retry-After when server load per CPU core crosses your threshold. An optional per-IP limit covers "human" visitors (logged-in users are exempt). SEO-safe status codes only, never cached.

= robots.txt and llms.txt manager =

Manage robots.txt rules (including Google-Extended, Applebot-Extended and an optional Crawl-delay) and generate an llms.txt that skips noindex content.

= Block before PHP =

Export rules to .htaccess (Apache/LiteSpeed) or nginx. Inside WordPress, requests are decided while plugins load, before the theme runs. It fails open: if anything goes wrong inside the plugin, visitors are let through.

= Reports and logs =

7/14-day statistics, top denied IPs with one-click blocking, a denied-request log, a User-Agent tester and weekly or monthly email reports. Full Hebrew translation, RTL ready.

= More from A-in =

This plugin is complete on its own: no licence key, no locked features. Premium plugins, add-ons and support from the same developer are available at [a-in.co.il](https://a-in.co.il/plugins/).

== Installation ==

1. Install and activate the plugin. It starts in **Watch-only** mode: it logs what it would do and does not block, rate-limit, shed load or change robots.txt.
2. On **All-IN Crawler → Dashboard**, check that "Your IP" is your real public IP (else set Advanced → IP source).
3. Pick a preset on Bot Rules, then switch to **Enforce** when the logs look right. Sites already on Enforce keep it after an update.
4. Optionally export server rules (.htaccess / nginx) so blocked bots are rejected before PHP runs.

== Frequently Asked Questions ==

= Will this hurt my SEO? =

Installing it does not change how crawlers are treated: a new install starts in Watch-only mode. With the default Balanced rules, search engines stay allowed; verification only catches scrapers pretending to be a known crawler. Rate limits and load shedding answer with 429 or 503 and Retry-After, which major search engines treat as "try again later". It can affect SEO if you choose that: blocking a search engine, the Lockdown preset, or robots.txt Disallow lines for a blocked bot tell that bot to stay out.

= How do I block GPTBot, ClaudeBot and other AI crawlers? =

Apply the "Protect content from AI training" preset on Bot Rules, or set the AI training category to Block, then switch to Enforce. AI search and assistant agents (ChatGPT search, Claude, Perplexity) stay allowed, so you stay in AI answers.

= How do I block bad bots and scrapers? =

Set vulnerability scanners, scripts & HTTP libraries, unidentified bots and empty User-Agents to Block or Rate-limit, block abusive IPs from the Dashboard, and keep impostor protection on so fake Googlebots are rejected.

= How do I rate-limit aggressive bots without hurting SEO? =

Use Rate-limit instead of Block. Over-limit requests get 429 with Retry-After, which tells crawlers to slow down rather than drop your pages. Verified search engines are not limited by the default rules.

= Does it create llms.txt? =

Yes. Turn it on in the robots.txt tab. It lists your public content and skips anything marked noindex.

= Can I block Google's AI Overviews? =

Not without blocking Google Search. AI Overviews use regular Googlebot. Google-Extended only controls Gemini training/grounding use.

= Does it replace Cloudflare or a server firewall? =

No. It complements them. Large DDoS attacks must be stopped before they reach PHP.

= Does it contact any outside service by default? =

No. Search engine bots are verified with DNS lookups through your own server's resolver. The official crawler IP lists and a bot-list feed URL of your own are off until you turn them on. See "External services".

= Why would I turn on the official crawler IP lists? =

OpenAI and Perplexity bots have no reverse DNS, so without their published IP lists a real GPTBot can't be told apart from a scraper using the same name. With the lists on, a request claiming to be one of those bots from an unlisted IP is treated as an impostor. A list is only used while under 7 days old, so a failed download never blocks a genuine bot.

= Does a page cache bypass it? =

Cached pages don't run WordPress, so the plugin doesn't see them, but they are cheap. Denials are sent with `Cache-Control: no-store` and never cached.

= Where do I get help? =

Found a bug or have a question? Use the support forum. If the plugin helps you, a review helps other site owners find it.

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

= 1.2.2 =
* Improved: the optional review notice now appears only after the plugin has handled real traffic (14+ days and 500+ bot requests), only on the Dashboard and Reports tabs, with Maybe later / don't ask again options.
* Added: Support forum and review links in the plugin's row on the Plugins screen and in the email report footer.
* Docs: clearer name, short description, tags and FAQ. Older changelog entries moved to changelog.txt.

= 1.2.1 =
* Changed: a new install starts in Watch-only mode. It logs and reports only until an administrator switches to Enforce. Upgrades keep every saved setting, including Enforce.
* Added: mode banner with one-click switch, and a dismissible notice after a fresh activation.

Full changelog: see changelog.txt.

== Upgrade Notice ==

= 1.2.2 =
Review notice only after real traffic, with Maybe later. Watch-only stays the default for new installs. No database changes.
