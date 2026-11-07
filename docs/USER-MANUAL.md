# All-IN Crawler & AI Agent Manager – User Manual

**Plugin:** All-IN Crawler & AI Agent Manager
**Version:** 1.2.1
**Author:** Eli Shvarzman

This manual is for site owners, marketers and agencies. You don't need to be a developer to follow it.

---

## Contents

1. [What All-IN Crawler & AI Agent Manager does](#1-what-all-in-crawler--ai-agent-manager-does)
2. [Installation](#2-installation)
3. [First-time setup (10 minutes)](#3-first-time-setup-10-minutes)
4. [The Dashboard](#4-the-dashboard)
5. [Bot Rules: presets, categories and individual bots](#5-bot-rules)
6. [Custom Bots](#6-custom-bots)
7. [Protection settings](#7-protection-settings)
8. [robots.txt](#8-robotstxt)
9. [Logs](#9-logs)
10. [Tools](#10-tools)
11. [Advanced settings](#11-advanced-settings)
12. [Bot Lists: official IP lists & bot-list feed](#12-bot-lists)
13. [Server Rules (.htaccess / nginx)](#13-server-rules)
14. [llms.txt](#14-llmstxt)
15. [Email reports](#15-email-reports)
16. [Language: Hebrew & RTL](#16-language-hebrew--rtl)
17. [SEO & AI visibility (AIO): how to stay found](#17-seo--ai-visibility-aio)
18. [Recipes for common situations](#18-recipes)
19. [Troubleshooting](#19-troubleshooting)
20. [FAQ](#20-faq)
21. [Glossary](#21-glossary)

---

## 1. What All-IN Crawler & AI Agent Manager does

Much of the traffic to a typical website comes from bots, not people. Some bots are essential (Google, Bing, social-media previews), some bring new visibility (ChatGPT, Claude, Perplexity), and some only consume resources (aggressive AI training crawlers, SEO tools, scrapers, vulnerability scanners).

All-IN Crawler & AI Agent Manager lets you:

| You want to… | All-IN Crawler & AI Agent Manager… |
|---|---|
| Choose who may access the site | Recognises ~120 known bots in 12 categories and applies **Allow**, **Rate limit** or **Block** per category or per bot. |
| Stop bots overloading the site | Limits requests per minute per bot (or per IP). Returns `429 Too Many Requests` + `Retry-After`, the standard "slow down" signal. |
| Survive traffic spikes | **Load shedding**: when the server is overloaded, non-essential bots get `503 – come back later` until it recovers. |
| Stop fake Googlebots | Verifies search engines with a reverse + forward DNS lookup, the method Google and Bing document. Impostors are blocked. |
| Keep SEO and AI visibility | Search engines, social previews and AI assistants are allowed by default. Every response is an SEO-safe status code. |
| Stop AI training without losing AI search | Separates *AI training crawlers* (GPTBot, ClaudeBot…) from *AI search/assistant bots* (OAI-SearchBot, ChatGPT-User, Claude-User…). |
| Manage robots.txt | Adds `Disallow` rules for blocked bots, including robots-only tokens such as **Google-Extended**. |
| Verify AI bots too | Optional (off by default): checks GPTBot, ChatGPT-User, PerplexityBot and others against the vendors' **official IP lists** (updated daily once you opt in). |
| Block before PHP | Exports blocked bots as **.htaccess** or **nginx** rules. |
| Help AI understand you | Generates **llms.txt**. |
| Prove the value | Sends **weekly/monthly email reports**, white-label ready. |

---

## 2. Installation

1. Download the plugin as a ZIP (from GitHub: *Code → Download ZIP*).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the ZIP, click **Install Now**, then **Activate**.
3. A new **All-IN Crawler & AI Agent Manager** menu item (shield icon) appears in the admin sidebar.

**Requirements:** WordPress 6.1+, PHP 7.4+, MySQL 5.7+/MariaDB 10.3+.
**Recommended:** APCu or Redis/Memcached (persistent object cache) for the fastest rate limiting.

On activation the plugin creates four small database tables. A **new install starts in Watch-only mode** with the *Balanced* category rules saved but not applied: nothing is blocked, rate-limited or load-shed, and robots.txt is not changed. Switch to **Enforce** when you have looked at the logs. An update from 1.2.0 keeps the mode you already saved, including Enforce.

---

## 3. First-time setup (10 minutes)

Follow these steps in order:

1. **Check your IP detection.** Open **All-IN Crawler → Dashboard** and look at *"Your IP as seen by All-IN Crawler & AI Agent Manager"*.
   - If it shows **your real public IP** (compare with any "what is my IP" site) → good.
   - If it shows a private address such as `10.x`, `172.16–31.x`, `192.168.x` or `127.0.0.1`, or your hosting provider's IP, you are behind a proxy/CDN. Go to **Advanced → Client IP detection** and pick the right source (usually *Automatic* works, or *Cloudflare* when you use Cloudflare). If your proxy has a public IP, add it under *Additional trusted proxies*.
2. **Stay in Watch-only until the logs look right.** A new install is already in Watch-only. For a few days All-IN Crawler & AI Agent Manager logs what it *would* deny without denying anything, without rate limits, load shedding or robots.txt edits. Review the **Logs**, then switch to *Enforce* from the banner or from **Protection → Mode**.
3. **Choose a preset** on **Bot Rules** (see §5). *Balanced* is right for most sites.
4. **Optional: export server rules** (**Server Rules** tab) so the web server rejects blocked bots before PHP starts. Inside WordPress, All-IN Crawler & AI Agent Manager decides while plugins load, before the theme runs.
5. **Add your own services to the allow list.** On **Protection → Always allow**, add the IPs of your office, your SEO agency's crawler, or integrations that call your site (ERP, CRM, stock sync). On **Excluded paths**, add payment-gateway callback URLs and webhooks.
6. **Check robots.txt** (**robots.txt** tab). If a red notice says a physical robots.txt exists, read §8.

That's it. Look at the Dashboard again after a day or two.

---

## 4. The Dashboard

**Status cards**

- **Protection:** *Enforcing*, *Watch-only* or *Disabled*.
- **Rate-limit storage:** where counters are kept.
  - *APCu*: fastest (shared memory).
  - *Object Cache*: Redis/Memcached, fast and shared across servers.
  - *Database*: works everywhere, but adds one small query per counted request. If you see *Database* on a busy site, ask your host to enable APCu or Redis.
- **Your IP:** see step 1 of the setup.
- **Server load:** the current 1-minute load per CPU core compared with your load-shedding threshold.

**Last 7 days by category:** allowed, limited and blocked requests per category.

**Last 14 days:** daily bars (green = allowed, amber = rate-limited or shed, red = blocked).

**Top bots:** the busiest bots. *Unidentified* bots appear under their own name (e.g. "NewFancyBot"). If one keeps showing up, give it a rule on **Custom Bots**.

**Most denied IPs (24 h):** the IPs denied most often. **Block IP** adds one to the block list.

---

## 5. Bot Rules

### 5.1 Presets

| Preset | Best for |
|---|---|
| **Balanced (recommended)** | Most sites. Search engines and social previews allowed, AI assistants lightly limited, AI training / SEO tools / scripts throttled, scanners blocked. |
| **Maximum AI visibility** | Publishers, content marketers and SaaS sites that want to be cited by ChatGPT, Claude and Perplexity. AI assistants fully allowed, training crawlers gently limited. |
| **Protect content from AI training** | Sites with original, valuable content (news, courses, research, recipes). Blocks AI training crawlers (in the firewall *and* robots.txt, including Google-Extended and Applebot-Extended) while staying visible in AI search and Google. |
| **Lockdown (under attack)** | Temporary use while the site is being hammered. Only verified search engines, social previews and monitoring get through freely. Switch back afterwards. |

A preset replaces the **category defaults**. Individual bot overrides stay in place.

### 5.2 Category defaults

Each category has three settings:

- **Policy**
  - **Allow**: never limited.
  - **Rate limit**: allowed up to *Limit* requests per window (default 60 seconds). Beyond that the bot gets `429` + `Retry-After` until the window ends.
  - **Block**: always `403 Forbidden`.
- **Limit**: requests per window.
- **Count**
  - **Per IP**: each IP address has its own counter. Good for bots that visit from few IPs.
  - **Per bot**: all IPs of the same bot share one counter. Use it for crawlers spread over hundreds of IPs (e.g. Bytespider); otherwise each IP stays under the limit while the total overloads you.

| Category | Default | Why |
|---|---|---|
| Search engines | Allow | Organic traffic. Only *DNS-verified* requests count as search engines. |
| AI search & assistants | 120/min per IP | These bots cite you in AI answers, so keep them in. The limit only stops abuse. |
| AI training crawlers | 20/min per bot | Heavy, and they bring no direct traffic. Change to *Block* if you don't want your content used for training. |
| SEO & marketing tools | 20/min per bot | Heavy crawlers. |
| Social link previews | Allow | Without them, shared links show no image or title. |
| Monitoring | Allow | Your own uptime monitors, Jetpack, ManageWP… |
| Feed readers | 30/min per IP | RSS subscribers. |
| Web archives | 10/min per bot | Wayback Machine. |
| Scripts & HTTP libraries | 30/min per IP | curl, python-requests, Go, Java, headless Chrome. Mostly scrapers, but some integrations use them too, so they are limited rather than blocked. |
| Vulnerability scanners | Block | sqlmap, Nikto, WPScan, Nuclei… |
| Unidentified bots | 20/min per IP | Look like bots but are not in the database. |
| Empty User-Agent | 10/min per IP | Almost always crude scripts. |

### 5.3 Individual bots

Open a category to see its bots. For each bot you can:

- set a **Policy** (*Category default*, *Allow*, *Rate limit*, *Block*);
- set a **Limit override** (leave empty to use the category limit).

The **Effective** column shows the rule that actually applies. Use the filter box to find a bot by name, vendor or User-Agent text.

Badges:
- **DNS verified**: the bot's identity is checked (see §7.2).
- **robots.txt only**: the bot never shows itself in a User-Agent (e.g. *Google-Extended*). It can only be *Allowed* or *Blocked*, and blocking adds a `Disallow: /` rule to robots.txt.

**Precedence:** Custom rule → individual bot override → category default.

---

## 6. Custom Bots

Use this tab to:

- add a bot that is not in the built-in list;
- give special treatment to a specific User-Agent, e.g. allow your own SEO agency's crawler, or block a scraper that calls itself "MyScraper/1.0".

| Field | Meaning |
|---|---|
| Name | Any label. It is shown in statistics and logs. |
| User-Agent pattern | Text to look for in the User-Agent. |
| Match | **Contains** (case-insensitive text) or **Regex** (a regular expression without delimiters, e.g. `^PartnerBot/\d+`). |
| Category | Used for the category default and the load-shedding exemptions. |
| Policy / Limit | Leave on *Category default* / empty to use the category rule. |

Custom bots are checked **before** the built-in list, so they always win. Use **Tools → User-Agent tester** to check a rule.

---

## 7. Protection settings

### 7.1 General

- **Enable All-IN Crawler & AI Agent Manager**: master switch.
- **Mode**
  - *Watch-only* (stored as monitor): log and report only. Nothing is blocked, rate-limited or load-shed, and robots.txt is not changed. The Logs mark those rows "watch-only". This is the default on a new install.
  - *Enforce*: apply the rules. Matching requests can be denied, rate-limited or load-shed, and robots.txt rules are published when that option is on.
- **Rate-limit window**: the length of a counting period (default 60 s). All limits are "requests per window".

### 7.2 Fake search-engine protection

Scrapers often pretend to be Googlebot because most sites let Google in. All-IN Crawler & AI Agent Manager checks the claim like this:

1. It looks up the hostname of the IP (reverse DNS), e.g. `crawl-66-249-66-1.googlebot.com`.
2. It checks that the hostname belongs to the vendor (`googlebot.com` / `google.com`).
3. It looks up the hostname again to confirm it points back to the same IP (forward DNS).

The outcome is cached per IP (7 days, or 1 hour if there was no DNS answer), so the lookup happens only once.

- **Verified**: treated as the real search engine.
- **Impostor**: the hostname belongs to someone else. By default the request is **blocked**.
- **Unverified**: there is no reverse DNS at all. The request is treated as an *Unidentified bot* (rate-limited), not blocked. That way a temporary DNS problem can never lock Google out.

Verified vendors: Google (Googlebot, AdsBot, AdSense, GoogleOther), Bing, Apple, Yandex, Baidu, Yahoo, Petal, Amazon.

### 7.3 Server load protection (load shedding)

When the **1-minute load average per CPU core** reaches the threshold, bots in non-exempt categories get `503 Service Unavailable` with `Retry-After: 120`.

- **Threshold:** `1.0` means every core is fully busy. The default `2.0` is conservative. Use `1.0–1.5` on a VPS or dedicated server. On shared hosting the load includes other customers' sites, so keep `2.0` or higher.
- **CPU cores:** 0 = detect automatically. Set it manually if the Dashboard says load is unavailable.
- **Never shed:** by default *Search engines*, *Social link previews* and *Monitoring*. Impostors never count as search engines.

Google and Bing treat a short-lived 503 as "try again later". It does not hurt rankings as long as it doesn't last for days.

### 7.4 Human visitors

This is off by default. It limits page requests per IP for visitors that look like normal browsers, which catches scrapers that disguise themselves as Chrome.

- **Before enabling it, check the Dashboard IP (§3 step 1).** If All-IN Crawler & AI Agent Manager sees your proxy's IP instead of visitors' IPs, all visitors would share one counter.
- Logged-in users are never limited.
- Static files (images, CSS, JS) are normally served without WordPress and are not counted.
- Keep the limit generous (120/min or more). Offices, schools and mobile networks put many people behind one IP.

### 7.5 IP lists & exclusions

- **Always allow**: one IP or CIDR range per line; `#` starts a comment. These IPs bypass every rule.
- **Always block**: same format. These IPs get `403` on every request.
- **Excluded paths**: requests whose path starts with one of these lines are never filtered. Lines containing `?` match anywhere in the URL (e.g. `?wc-api=`). The defaults are `/wp-cron.php` and `/wc-api/`. Add your payment gateway and webhook callback URLs here.

Your own server (loopback requests, WP-Cron spawns, Site Health) is always allowed automatically.

---

## 8. robots.txt

Well-behaved bots (all major search engines and AI companies) read `robots.txt` before crawling. Telling them *Disallow* means they never send those requests, which is cheaper than blocking them one by one. Bad bots ignore robots.txt, which is why the firewall exists too.

In **Watch-only** mode the live robots.txt is left untouched. The preview still shows the lines Enforce would add.

- **Manage robots.txt**: adds `User-agent: X / Disallow: /` for every bot whose effective policy is *Block*. This includes the robots-only tokens **Google-Extended** and **Applebot-Extended**.
- **Crawl-delay**: for rate-limited bots, adds `Crawl-delay: N` so polite bots (Bing, Yandex, most SEO tools) slow down to your limit instead of running into 429s. Google ignores Crawl-delay, so All-IN Crawler & AI Agent Manager never adds a Google group. It only adds a delay of 2 seconds or more.

> **Why do the Crawl-delay groups repeat my `User-agent: *` rules?**
> A bot that finds a group with its own name *ignores* the `*` group. Without the copy, adding a Crawl-delay for Bingbot would silently allow Bing into `/wp-admin/`. All-IN Crawler & AI Agent Manager copies the `*` rules for you.

**Physical robots.txt file:** if a real `robots.txt` file exists in your site root, the web server serves it directly and WordPress cannot change it. Either copy the preview lines into that file manually, or delete/rename the file to let All-IN Crawler & AI Agent Manager (and your SEO plugin) manage it.

All-IN Crawler & AI Agent Manager adds its rules at the end and never removes lines added by Yoast, Rank Math or other plugins.

---

## 9. Logs

Every denied request is counted in the statistics. The **log** keeps up to 3 rows per IP and action per window, so a bot hammering you can't flood your database.

Each row shows the time, IP, identified bot and category, action, request and User-Agent.

| Action | HTTP | Meaning |
|---|---|---|
| Blocked | 403 | Policy is Block. |
| Rate limited | 429 | Limit exceeded; `Retry-After` tells the bot when to return. |
| Impostor | 403 | Claimed to be a search engine, DNS says otherwise. |
| Load shed | 503 | Server overloaded; `Retry-After: 120`. |
| IP blocked | 403 | IP is on the block list. |

Buttons: **Block IP** / **Allow IP** add the IP to the corresponding list (and remove it from the other). **Clear log** empties the log.

Retention is set on **Advanced** (default 14 days for the log, 90 days for statistics).

---

## 10. Tools

- **User-Agent tester:** paste any User-Agent (and optionally an IP) to see the bot, category, policy and, for verified bots, the live DNS verification result. It includes sample User-Agents.
- **Export / import:** copy your configuration between sites (staging → production, or your standard set-up across client sites).
- **Maintenance:** clear statistics, clear the DNS verification cache, or reset all settings.

---

## 11. Advanced settings

- **IP source**
  - *Automatic*: trusts Cloudflare's published IP ranges and private-network proxies.
  - *REMOTE_ADDR*: no proxy.
  - *Cloudflare*, *X-Forwarded-For*, *X-Real-IP*: force a specific header.

  Forwarded headers are only trusted when the request comes from a trusted proxy, so attackers can't fake them.
- **Additional trusted proxies**: public IPs of your own load balancer or CDN (Sucuri, Fastly, Bunny, AWS ALB…).
- **Rate-limit counters**: Automatic (APCu → object cache → database) or forced. On multi-server setups use the object cache (Redis) so all servers share counters. APCu is per server.
- **Statistics / Event log**: switch them off to save database writes on very busy sites.
- **Debug mode**: writes every decision to the PHP error log (search for `[AllIN-Crawler]`) and adds an `X-AllIN-Crawler` response header. Turn it off when done. You can also add `define( 'BOTCTL_DEBUG', true );` to `wp-config.php`.
- **Keep data on uninstall**: keep settings and statistics when you delete the plugin.

---

## 12. Bot Lists

### 12.1 Official crawler IP lists

Google, Bing, Apple, OpenAI and Perplexity publish the IP addresses their crawlers use. **This is off by default.** Tick **Download official crawler IP lists** on the Bot Lists tab and save to opt in; until then nothing is downloaded. Once on, All-IN Crawler & AI Agent Manager downloads these lists once a day. It then uses them to prove that a request really comes from the bot it claims to be:

| Bot | List |
|---|---|
| Googlebot (incl. user-triggered fetchers), GoogleOther, Google Ads/AdSense | Google |
| Bingbot | Bing |
| Applebot | Apple |
| GPTBot, OAI-SearchBot, ChatGPT-User | OpenAI |
| PerplexityBot, Perplexity-User | Perplexity |

How the lists are used:

- **IP is on the list:** the bot is **verified** immediately, with no DNS lookup needed.
- **IP is not on the list, and the vendor also supports reverse DNS** (Google, Bing, Apple): All-IN Crawler & AI Agent Manager falls back to the DNS check, in case the list is a few hours behind.
- **IP is not on the list, and the vendor has no reverse DNS** (OpenAI, Perplexity): the request is treated as an **impostor**. This closes a common gap, because scrapers love to pretend to be GPTBot or ChatGPT-User.

**Safety net:** a list is only used while it is **fresh** (downloaded within the last 7 days). If downloads keep failing (for example, your host blocks outgoing connections), All-IN Crawler & AI Agent Manager stops using the list. The bot is then treated as it was in version 1.0 (DNS check, or unverified). An outdated list can never cause a genuine bot to be blocked.

The **Bot Lists** tab shows every list with its number of IP ranges, last update and status. **Download official IP lists** updates them immediately.

### 12.2 Bot-list feed

New AI crawlers appear every month. The feed adds new bot definitions daily, so you don't have to wait for a plugin update.

- **Enable feed**: turns daily downloads on or off. It is off by default.
- **Feed URL**: leave it empty for the default All-IN Crawler & AI Agent Manager feed (`data/bots.json` in the plugin's GitHub repository). **Agencies** can host their own feed in the same format and point all client sites to it.
- **Access key**: optional. It is sent as a `Bearer` token, for private or commercial feeds.

**Safety rules**, so a broken or hostile feed can never hurt your site:
- Only `https://` URLs are accepted.
- A feed can only **add** bots. It cannot change or weaken the built-in ones (for example, it cannot re-define Googlebot).
- Any pattern shorter than 3 characters, or one that matches a real browser (Chrome, Safari, Firefox, Edge, Samsung Internet…), is rejected. A feed therefore cannot turn your human visitors into "bots".
- Rejected entries are listed on the Bot Lists tab.

Feed bots appear on the **Bot Rules** tab with a *feed* badge. You can override them like any other bot.

---

## 13. Server Rules

The fastest block is one PHP never sees. The **Server Rules** tab turns everything you have set to **Block** into web-server rules:

- blocked bots and blocked categories (by User-Agent),
- custom bots with the Block policy (text or regex),
- empty User-Agents (when that category is blocked),
- optionally, the **Always block** IP list.

Your **Excluded paths** and **Always allow** IPs are respected in the generated rules as well.

What stays in PHP: rate limits, DNS/IP-list verification (impostors) and load shedding. These need logic a web server config can't express.

**Apache / LiteSpeed (.htaccess)**
- **Write to .htaccess** inserts the rules at the **top** of `.htaccess`. They must come before the WordPress block, because WordPress's rewrite ends processing for most URLs, and rules placed below it would never run.
- A backup of the previous file is saved as `.htaccess.botctl-backup`.
- **Keep .htaccess in sync** rewrites the rules automatically whenever you change bot rules. The rules are removed automatically in Monitor mode, when All-IN Crawler & AI Agent Manager is disabled, and when the plugin is deactivated.

**nginx**
nginx has no `.htaccess`. The tab shows two snippets:
1. an `http { }` snippet (e.g. save as `/etc/nginx/conf.d/all-in-crawler-ai-agent-manager.conf`);
2. a `server { }` snippet (paste inside your site's server block).

Then run `nginx -t && systemctl reload nginx`. Re-export after changing your rules.

**IP rules behind a proxy/CDN:** if your site is behind Cloudflare or a load balancer, the web server only sees the real visitor IP when it is configured for it (Apache `mod_remoteip`, nginx `real_ip`). Otherwise, leave *Include IP block list* off.

---

## 14. llms.txt

`llms.txt` is a proposed standard ([llmstxt.org](https://llmstxt.org)): a short Markdown file at `https://your-site/llms.txt` that tells AI assistants what your site is about and which pages matter.

> **Honest expectation:** support by AI companies is still emerging. Treat llms.txt as a cheap, low-risk bet on AI visibility, not as a ranking factor. Your robots.txt and bot rules matter far more.

Settings:

- **Serve /llms.txt**: turns the generated file on.
- **Summary**: the most important line. Describe who you are and what you offer in one sentence, written for an AI that has never heard of you. If empty, your site tagline is used.
- **Details**: optional paragraphs (service area, key facts, how to contact you).
- **Content types**: which content to list (pages, posts, products…).
- **Items per type**: pages are listed in menu order, other types newest first. Keep it focused; llms.txt is a curated overview, not a sitemap.
- **Extra links**: one per line, in the form `Title | URL | description` (e.g. price list, booking page, documentation).

What's left out automatically: password-protected content, and anything marked **noindex** in Yoast SEO, Rank Math or SEOPress.

The output is cached for 12 hours and refreshed whenever you publish or update content. A live preview is shown on the tab.

If a physical `llms.txt` file already exists in your site root, the web server serves that one instead. Delete or rename it to use the generated version.

---

## 15. Email reports

A clear email summary, sent **weekly** (Monday 08:00, last 7 days) or **monthly** (1st of the month, last 30 days):

- bot requests, compared with the previous period;
- requests stopped (rate-limited or blocked);
- **AI assistant visits**: how often ChatGPT, Claude, Perplexity & co. fetched your pages (your AI visibility);
- fake search-engine bots caught;
- breakdown by category, top bots, top AI assistants and most-denied IPs;
- **estimated server time saved**.

**How is "time saved" calculated?** When reports are enabled, about 2% of normal page views record how long WordPress took to build the page (a rolling average). Time saved = requests stopped × average page time. The estimate only appears once at least 20 samples exist, so it's measured, not guessed.

Settings:

- **Recipients**: comma-separated. If empty, the site admin email is used.
- **Brand name (white-label)**: shown in the subject, header and footer. Agencies can send the report to clients under their own name.

Buttons:

- **Preview report** opens the report in a new tab.
- **Send report now** sends it immediately.

Tip: use an SMTP plugin for reliable delivery. WordPress's default mail often lands in spam.

---

## 16. Language: Hebrew & RTL

All-IN Crawler & AI Agent Manager ships with a full **Hebrew (עברית)** translation. It is used automatically when your site language, or your personal profile language (Users → Profile → Language), is set to Hebrew. The admin screens and the email reports are right-to-left aware.

To add another language, translate `languages/all-in-crawler-ai-agent-manager.pot` with Poedit or Loco Translate.

---

## 17. SEO & AI visibility (AIO)

**Rules that protect your rankings**

1. **Search engines stay allowed** by default and are DNS-verified, so allowing them doesn't open a hole for fake Googlebots.
2. **Rate limits return `429` + `Retry-After`** and overload returns **`503`**. Google and Bing both slow down on these and try again. They are never treated as "page removed". All-IN Crawler & AI Agent Manager never uses `403` for rate limiting.
3. **Denials are never cached.** Every denial is sent with `Cache-Control: no-store`, so a CDN or page cache can't serve a 429 to real visitors or Google later.
4. **Google ignores Crawl-delay**, so All-IN Crawler & AI Agent Manager doesn't pretend otherwise. If Googlebot overloads your server, load shedding (503) handles it. You can also give Googlebot a high limit (e.g. 300/min per IP) instead of *Allow*.
5. **Don't block Google Ads/AdSense bots** (`AdsBot-Google`, `Mediapartners-Google`) if you run ads. Ads quality checks and AdSense targeting depend on them.

**AI visibility (AIO / GEO), in brief**

- *AI search & assistants* (OAI-SearchBot, ChatGPT-User, Claude-SearchBot, Claude-User, PerplexityBot, Perplexity-User…) fetch your pages to **answer users and link to you**. Keep them allowed.
- *AI training crawlers* (GPTBot, ClaudeBot, CCBot, Bytespider, Meta-ExternalAgent…) collect content to **train models**. Blocking them does **not** remove you from ChatGPT/Claude/Perplexity answers, because those use the separate search/user agents.
- **Google AI Overviews and AI Mode use normal Googlebot.** You cannot block them without blocking Google Search. *Google-Extended* only controls use of your content for Gemini training and grounding. Apple works the same way: *Applebot-Extended* controls AI training, while *Applebot* powers Siri and Spotlight.

**Business decision:** blocking training is a content-licensing decision, not a technical one. News publishers, course creators and premium-content businesses usually block it. Brands that want maximum reach in AI answers usually allow it. All-IN Crawler & AI Agent Manager lets you choose per bot.

---

## 18. Recipes

**"My site is slow and the logs are full of one crawler."**
Bot Rules → find the bot → set a Limit override (e.g. 10) or Block. If it spreads over many IPs, set its category Count to *Per bot*.

**"We're under attack right now."**
Apply the **Lockdown** preset. Lower the load-shedding threshold to 1.0. Block the top offending IPs from the Dashboard. When things calm down, switch back to Balanced.

**"I don't want AI companies training on my content, but I want to appear in ChatGPT answers."**
Apply **Protect content from AI training**. Check the robots.txt tab: GPTBot, ClaudeBot, Google-Extended, Applebot-Extended, CCBot and others are now disallowed.

**"Our SEO agency's crawler is being limited."**
Custom Bots → add its User-Agent (e.g. "Screaming Frog") with policy *Allow*. Or add the agency's IP to *Always allow*.

**"A payment / ERP integration stopped working."**
Look in Logs for the integration's IP or User-Agent. Add the IP to *Always allow*, or its callback path to *Excluded paths*.

**"I use Cloudflare."**
*Automatic* IP detection handles it. Confirm on the Dashboard that your real IP is shown. Consider also enabling Cloudflare's own bot features: blocking at the edge is cheaper than blocking in WordPress.

---

## 19. Troubleshooting

| Symptom | Check |
|---|---|
| Everyone gets 429 | The human rate limit is on and the Dashboard IP shows a proxy IP. Fix the IP source (§11) or turn the human limit off. |
| Google Search Console shows many 429/503 errors | Raise the search-engine limit or keep it on *Allow*; raise the load threshold; check server capacity. |
| Social shares show no preview | The *Social link previews* category or the specific bot must be *Allowed*. |
| robots.txt doesn't change | A physical robots.txt file exists (see §8), or a page/CDN cache serves an old copy. |
| Rules don't seem to apply | A full-page cache (WP Rocket, LiteSpeed, Varnish, Cloudflare APO) can serve cached pages without running WordPress. Those requests are cheap and are not seen by All-IN Crawler & AI Agent Manager, which is expected. Denials are never cached. |
| "Database tables are missing" | Deactivate and reactivate the plugin. |
| Bot Lists show "Failed" | Your host may block outgoing connections. Ask them to allow HTTPS to developers.google.com, bing.com, search.developer.apple.com, openai.com and perplexity.com. Until then, verification falls back to DNS or "unverified"; nothing gets blocked because of it. |
| Site shows "500 Internal Server Error" after writing .htaccess | Very rare (an unusual server configuration). Restore `.htaccess.botctl-backup` via FTP, or delete the `# BEGIN All-IN Crawler & AI Agent Manager … # END All-IN Crawler & AI Agent Manager` block. |
| /llms.txt shows a 404 | Save Settings → Permalinks once, check that no physical llms.txt exists, and purge your page cache/CDN. |
| Reports don't arrive | Install an SMTP plugin; check spam; use **Send report now** to test. |
| Need details | Enable Debug mode and search the PHP error log for `[AllIN-Crawler]`. |

Emergency off switch: rename the folder `wp-content/plugins/all-in-crawler-ai-agent-manager` via FTP/file manager. If you wrote server rules to `.htaccess`, also remove the `# BEGIN All-IN Crawler & AI Agent Manager … # END All-IN Crawler & AI Agent Manager` block: renaming the folder does not trigger deactivation.

---

## 20. FAQ

**Does it slow my site down?**
For humans, the cost is a User-Agent check (~microseconds) and no database query unless the human limit is on. For bots, it's one counter update plus one statistics write. Denied requests end before the theme loads, so they cost far less than a normal page.

**Will blocking a bot remove my pages from Google?**
Only if you block *Googlebot* itself. Nothing in the default configuration does that.

**Does it replace Cloudflare / a WAF?**
No. A PHP plugin runs after the web server has accepted the request. For large DDoS attacks, use an edge service (Cloudflare, your host's firewall). All-IN Crawler & AI Agent Manager complements them: it knows WordPress, knows your SEO priorities, and needs no DNS changes.

**Does it work on multisite?**
Yes. Each site has its own settings, tables and statistics. When network-activated, a sub-site's tables are created the first time an administrator opens that site's dashboard. Until then that site runs with default rules but keeps no statistics.

**What happens if something breaks inside the plugin?**
It fails **open**: the request is allowed and the error is written to the PHP error log. A plugin error can never lock visitors out.

---

## 21. Glossary

- **User-Agent**: the name a browser or bot sends with every request.
- **Rate limit**: maximum requests per time window.
- **429 Too Many Requests**: the standard "slow down" response. `Retry-After` says how many seconds to wait.
- **503 Service Unavailable**: "temporarily unavailable, come back later".
- **403 Forbidden**: access denied.
- **Reverse DNS**: looking up the hostname that belongs to an IP address.
- **CIDR**: a way to write IP ranges, e.g. `192.168.1.0/24` = 192.168.1.0–192.168.1.255.
- **AIO / GEO**: AI (or generative-engine) optimisation, meaning visibility in AI assistants' answers.
- **Load average**: how many processes are waiting for the CPU. Per core, 1.0 means fully busy.
