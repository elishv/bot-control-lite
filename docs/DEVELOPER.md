# Bot Control – Developer Guide

**Author:** Eli Shvarzman · **Version:** 1.1.1

## Architecture

```
bot-control.php                 Plugin header → requires includes/bootstrap.php
includes/bootstrap.php          Constants, class loading, Bot_Control_Firewall::run()
includes/
  class-botctl-firewall.php     Decision engine (runs once, as early as possible)
  class-botctl-detector.php     User-Agent → bot/category (custom → registry → generic → human)
  class-botctl-bot-registry.php Built-in bot database + category ids/labels
  class-botctl-policy.php       Resolves allow/limit/block + limit + scope
  class-botctl-verifier.php     Forward-confirmed reverse DNS (anti-spoofing) + cache
  class-botctl-rate-limiter.php Fixed-window counters: APCu → object cache → DB (fail-open)
  class-botctl-load-monitor.php Load average per core → load shedding
  class-botctl-ip.php           Proxy-aware client IP, IPv4/IPv6 CIDR matching
  class-botctl-stats.php        Daily aggregates + cleanup
  class-botctl-logger.php       error_log debug lines + throttled event log
  class-botctl-robots.php       robots.txt rules
  class-botctl-settings.php     Defaults, presets, sanitization (one autoloaded option)
  class-botctl-install.php      Tables (dbDelta), cron, activation/deactivation
  class-botctl-early-loader.php mu-plugin loader management
  class-botctl-ip-lists.php     Official crawler IP lists (Google, Bing, Apple, OpenAI, Perplexity)
  class-botctl-remote-list.php  Remote bot-definition feed (validated, add-only)
  class-botctl-server-rules.php .htaccess / nginx rule generation and .htaccess writer
  class-botctl-reports.php      Weekly/monthly HTML email reports + page-time sampler
  class-botctl-llms.php         /llms.txt generator
  class-botctl-plugin.php       Hook wiring
admin/                          Admin page, views, CSS/JS
data/bots.json                  Default bot-list feed
languages/                      bot-control.pot, Hebrew (he_IL) .po/.mo
uninstall.php                   Removes data unless "keep data" is set
```

### Request flow

1. `bootstrap.php` runs when the plugin file is included (or earlier, from the mu-plugin loader), **before** `plugins_loaded`.
2. `Bot_Control_Firewall::run()` skips CLI, cron, installs, excluded paths, the server's own IP and allow-listed IPs.
3. Block-listed IP → 403.
4. `Bot_Control_Detector::detect()` classifies the User-Agent.
5. Bots with `verify` domains and/or fresh official IP lists are verified: IP list first (listed → verified), then reverse DNS. Vendors without reverse DNS (OpenAI, Perplexity): not listed → impostor. With no PTR → treated as an unidentified bot. Lists older than 7 days are ignored (fail safe).
6. Load shedding (503) for non-exempt categories when overloaded.
7. Policy → allow / 429 / 403.
8. Humans: an optional per-IP limit. Requests carrying a login cookie are re-checked on `init` with `is_user_logged_in()`, so a forged cookie gains nothing.

Every failure path **fails open** and logs `[BotControl][...][ERROR]` to the PHP error log.

### Database tables (`{prefix}` = `$wpdb->prefix`)

| Table | Purpose | Cleanup |
|---|---|---|
| `{prefix}botctl_rate` | DB-backend counters (`rkey`, `win`, `hits`) | hourly, rows older than 2 h |
| `{prefix}botctl_stats` | Daily aggregates per bot | `stats_retention` days |
| `{prefix}botctl_events` | Denied-request log (throttled to 3 rows / IP / action / window) | `log_retention` days |
| `{prefix}botctl_verify` | DNS verification cache | 8 days |

The DB counter uses a single atomic statement:

```sql
INSERT INTO t (rkey, win, hits) VALUES (?, ?, 1)
ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(IF(win = ?, hits + 1, 1)), win = ?
```

`LAST_INSERT_ID(expr)` returns the new counter through `$wpdb->insert_id`, so no second query is needed.

### Options

- `botctl_settings`: all settings (autoloaded, a single array).
- `botctl_db_version`: schema version.
- `botctl_runtime`: detected CPU cores (cached for a day) and the average page time (`avg_page_ms`, `page_samples`).
- `botctl_ip_ranges`: downloaded official IP lists per URL (not autoloaded).
- `botctl_remote_bots`: validated feed bots (autoloaded, read by the registry).
- `botctl_remote_status`: last feed download result.
- Transient `botctl_llms_txt`: cached llms.txt (12 h, cleared on `save_post`/`deleted_post`/settings change).

### Cron events

| Hook | Schedule | Purpose |
|---|---|---|
| `botctl_cleanup` | hourly | Counter, stats, log and verification-cache cleanup |
| `botctl_daily` | daily | Refresh official IP lists and the bot feed (then re-sync .htaccess if auto-sync is on) |
| `botctl_report` | single event, re-scheduled after each send | Weekly (Monday 08:00) or monthly (1st, 08:00) report |

### Bot-list feed format

```json
{
  "format": 1,
  "updated": "2026-09-24",
  "bots": {
    "newbot": {
      "name": "NewBot",
      "vendor": "Acme",
      "category": "ai_training",
      "patterns": ["NewBot"],
      "robots": ["NewBot"],
      "verify": ["crawl.acme.example"],
      "note": "Optional explanation"
    }
  }
}
```

Validation (`Bot_Control_Remote_List::validate()`):
- HTTPS only; up to 500 bots.
- Ids that already exist in the built-in registry are rejected (feeds are add-only).
- `category` must be a known category id.
- A pattern must be at least 3 characters and must not match any User-Agent in `Bot_Control_Remote_List::BROWSER_SAMPLES`.
- `robots` tokens must match `/^[A-Za-z0-9._\- ]{2,60}$/`; `verify` accepts up to 5 hostnames.

An optional access key is sent as `Authorization: Bearer <key>`, so a commercial feed can be served from any endpoint that checks it.

### Server rules

`Bot_Control_Server_Rules::apache()` / `::nginx()` export only rules that need no PHP logic: blocked User-Agents (registry, feed and custom bots with an effective Block policy), empty User-Agent (when blocked) and optionally the IP block list. Guards are generated for excluded paths/queries and allow-listed IPs.

- Apache IP matching uses `%{REMOTE_ADDR}` regexes for single IPs and IPv4 /8, /16, /24, /32 (portable to LiteSpeed), and `expr -R` (Apache 2.4) otherwise.
- The .htaccess block is always written at the **top** of the file (before `# BEGIN WordPress`), with a backup in `.htaccess.botctl-backup`.
- The generated rules were validated on Apache 2.4.58 (`.htaccess`, `AllowOverride All`) and with `nginx -t`.

## Hooks

> The firewall runs very early. Filters that affect firewall decisions must be registered **before** Bot Control loads, i.e. from a must-use plugin (`wp-content/mu-plugins/*.php`). If you use the Bot Control early loader, make sure your mu-plugin file sorts alphabetically before `bot-control-early-loader.php` (e.g. `a-botctl-custom.php`).

### Filters

| Filter | Args | Purpose |
|---|---|---|
| `botctl_bot_registry` | `array $bots` | Add, change or remove built-in bots. |
| `botctl_client_ip` | `array $result {ip, source}`, `array $settings` | Override client IP detection. |
| `botctl_policy` | `array $rule {policy, limit, scope, origin}`, `array $match`, `array $settings` | Change the rule for a request. |
| `botctl_should_deny` | `bool $deny`, `array $decision` | Return `false` to let a denied request through. |
| `botctl_denied_message` | `string $message`, `int $code`, `string $action` | Plain-text body of 403/429/503 responses. |

| `botctl_ip_list_sources` | `array $sources` (bot_id => URLs) | Add or replace official IP-list sources (same JSON format). |
| `botctl_llms_txt` | `string $content`, `array $settings` | Modify the generated llms.txt. |
| `botctl_llms_exclude_post` | `bool $exclude`, `int $post_id` | Exclude a post from llms.txt. |

### Actions

| Action | Args | Purpose |
|---|---|---|
| `botctl_settings_updated` | `array $new`, `array $old` | Fires after settings are saved. Used internally for .htaccess sync, the report schedule and the llms.txt cache. |
| `botctl_cleanup` | – | Hourly cron. |
| `botctl_daily` | – | Daily cron (IP lists, feed). |
| `botctl_report` | – | Report cron. |

### Examples

```php
<?php
// wp-content/mu-plugins/a-botctl-custom.php

// Add a bot to the registry.
add_filter( 'botctl_bot_registry', function ( $bots ) {
	$bots['acme_bot'] = array(
		'name'     => 'AcmeBot',
		'vendor'   => 'Acme Inc.',
		'category' => 'seo_tool',
		'patterns' => array( 'AcmeBot' ),
		'robots'   => array( 'AcmeBot' ),
		'verify'   => array( 'crawl.acme.example' ),
	);
	return $bots;
} );

// Never rate-limit requests to the REST API from AI search bots.
add_filter( 'botctl_policy', function ( $rule, $match ) {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	if ( 'ai_search' === $match['category'] && 0 === strpos( $uri, '/wp-json/' ) ) {
		$rule['policy'] = 'allow';
	}
	return $rule;
}, 10, 2 );
```

## Debugging

Enable **Advanced → Debug mode** or `define( 'BOTCTL_DEBUG', true );` in `wp-config.php`.

- The PHP error log gets lines such as:
  ```
  [BotControl][Firewall] Rate limit exceeded | {"bot":"gptbot","ip":"198.51.100.21","hits":21,"limit":20,"scope":"bot"}
  [BotControl][Verifier] DNS verification performed | {"ip":"66.249.66.1","bot":"googlebot","status":1,"host":"crawl-66-249-66-1.googlebot.com"}
  ```
- Allowed bot responses get `X-Bot-Control: allow; bot=<id>; category=<cat>`.
- Denied responses always carry `X-Bot-Control: <action>`.

Components: `Firewall`, `Verifier`, `RateLimiter`, `LoadMonitor`, `Stats`, `Logger`, `Settings`, `Install`, `EarlyLoader`, `Admin`, `IPLists`, `RemoteList`, `ServerRules`, `Reports`, `Llms`.

## Releasing

1. Bump the version in **three** places: the `bot-control.php` header, `BOTCTL_VERSION` in `includes/bootstrap.php`, and `Stable tag` in `readme.txt`.
2. Bump `BOTCTL_DB_VERSION` only when the table schema changes (dbDelta runs automatically on the next admin page load).
3. Add a `CHANGELOG.md` entry and a `readme.txt` changelog entry.
4. Regenerate translations: `wp i18n make-pot . languages/bot-control.pot --domain=bot-control --exclude=data,docs`, update `languages/bot-control-he_IL.po` (e.g. `wp i18n update-po`), translate new strings, then `wp i18n make-mo languages`.
5. The early loader rewrites itself automatically when the version changes.

## Testing checklist

```bash
# Syntax
find . -name '*.php' -exec php -l {} \;

# Behaviour (the site behind a local proxy: send X-Forwarded-For from 127.0.0.1)
curl -A "sqlmap/1.8" -H "X-Forwarded-For: 203.0.113.6" -I https://site/          # 403
curl -A "Googlebot/2.1" -H "X-Forwarded-For: 8.8.8.8" -I https://site/           # 403 impostor
curl -A "Googlebot/2.1" -H "X-Forwarded-For: 66.249.66.1" -I https://site/       # 200 verified
for i in $(seq 25); do curl -s -o /dev/null -w "%{http_code}\n" -A "GPTBot/1.2" https://site/; done  # 429 after 20
```
