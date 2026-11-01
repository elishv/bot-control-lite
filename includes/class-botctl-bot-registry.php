<?php
/**
 * Built-in database of known bots, crawlers, AI agents, SEO tools and scrapers.
 *
 * Each bot:
 *  - name        Display name.
 *  - vendor      Operator of the bot.
 *  - category    Category id (see categories()).
 *  - patterns    Case-insensitive substrings matched against the User-Agent.
 *  - robots      robots.txt user-agent tokens used when the bot is blocked.
 *  - verify      Reverse-DNS domains used to prove the bot is genuine (anti-spoofing).
 *  - robots_only true for "tokens" that never appear in a User-Agent and can only be
 *                controlled through robots.txt (e.g. Google-Extended).
 *  - note        Short explanation shown in the admin.
 *
 * Order matters: the first matching bot wins, so more specific entries come first.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bot registry.
 */
final class Bot_Control_Bot_Registry {

	/**
	 * Cached registry.
	 *
	 * @var array|null
	 */
	private static $bots = null;

	/**
	 * Category ids in match/display order.
	 *
	 * @return string[]
	 */
	public static function category_ids() {
		return array(
			'search_engine',
			'ai_search',
			'ai_training',
			'seo_tool',
			'social',
			'monitoring',
			'feed_reader',
			'archiver',
			'scripts',
			'bad_bot',
			'unknown_bot',
			'no_ua',
		);
	}

	/**
	 * Category labels & descriptions (admin only – uses translations).
	 *
	 * @return array
	 */
	public static function categories() {
		return array(
			'search_engine' => array(
				'label'       => __( 'Search engines', 'bot-control' ),
				'description' => __( 'Google, Bing, Apple, Yandex, DuckDuckGo… Your organic traffic depends on them. Blocking them removes you from search results.', 'bot-control' ),
			),
			'ai_search'     => array(
				'label'       => __( 'AI search & assistants', 'bot-control' ),
				'description' => __( 'Bots that fetch pages to answer a user question in ChatGPT, Claude, Perplexity etc. They cite and link to you – this is your AI visibility (AIO/GEO).', 'bot-control' ),
			),
			'ai_training'   => array(
				'label'       => __( 'AI training crawlers', 'bot-control' ),
				'description' => __( 'Bulk crawlers collecting content to train AI models (GPTBot, ClaudeBot, CCBot, Bytespider…). Blocking them does NOT remove you from AI search answers.', 'bot-control' ),
			),
			'seo_tool'      => array(
				'label'       => __( 'SEO & marketing tools', 'bot-control' ),
				'description' => __( 'Ahrefs, Semrush, Majestic, Moz… Heavy crawlers. Your backlink data in those tools comes from them; competitors use them to analyse you.', 'bot-control' ),
			),
			'social'        => array(
				'label'       => __( 'Social link previews', 'bot-control' ),
				'description' => __( 'Facebook, X/Twitter, LinkedIn, WhatsApp, Slack, Telegram… They build the preview card when someone shares your link. Keep allowed.', 'bot-control' ),
			),
			'monitoring'    => array(
				'label'       => __( 'Monitoring & site management', 'bot-control' ),
				'description' => __( 'Uptime monitors, Jetpack, ManageWP, performance tools you probably use yourself.', 'bot-control' ),
			),
			'feed_reader'   => array(
				'label'       => __( 'Feed readers', 'bot-control' ),
				'description' => __( 'RSS readers (Feedly, Inoreader…) delivering your posts to subscribers.', 'bot-control' ),
			),
			'archiver'      => array(
				'label'       => __( 'Web archives', 'bot-control' ),
				'description' => __( 'Internet Archive (Wayback Machine) and similar.', 'bot-control' ),
			),
			'scripts'       => array(
				'label'       => __( 'Scripts, HTTP libraries & headless browsers', 'bot-control' ),
				'description' => __( 'curl, python-requests, Go, Java, Scrapy, headless Chrome… Mostly scrapers, but also some legitimate integrations – rate-limit rather than block.', 'bot-control' ),
			),
			'bad_bot'       => array(
				'label'       => __( 'Vulnerability scanners & known bad bots', 'bot-control' ),
				'description' => __( 'sqlmap, Nikto, WPScan, Nuclei, masscan… Nothing good comes from them.', 'bot-control' ),
			),
			'unknown_bot'   => array(
				'label'       => __( 'Unidentified bots', 'bot-control' ),
				'description' => __( 'User-Agents that look like a bot (contain "bot", "crawler", "spider"…) but are not in the database.', 'bot-control' ),
			),
			'no_ua'         => array(
				'label'       => __( 'Empty User-Agent', 'bot-control' ),
				'description' => __( 'Requests with no User-Agent at all – almost always crude scripts.', 'bot-control' ),
			),
		);
	}

	/**
	 * Cached built-in bots.
	 *
	 * @var array|null
	 */
	private static $builtin = null;

	/**
	 * All known bots: built-in + bundled data/bots.json + bots from the remote feed (if enabled) + the botctl_bot_registry filter.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$bots ) {
			return self::$bots;
		}

		$bots = self::builtin();
		// Bundled extra definitions (data/bots.json): add-only, always on, no network.
		foreach ( Bot_Control_Remote_List::bundled() as $id => $bot ) {
			if ( ! isset( $bots[ $id ] ) && is_array( $bot ) ) {
				$bots[ $id ] = self::normalize( $id, $bot );
			}
		}
		$settings = Bot_Control_Settings::get();
		if ( ! empty( $settings['remote_list_enabled'] ) ) {
			foreach ( Bot_Control_Remote_List::bots() as $id => $bot ) {
				if ( ! isset( $bots[ $id ] ) && is_array( $bot ) ) {
					$bots[ $id ] = self::normalize( $id, $bot );
				}
			}
		}

		/**
		 * Filter the bot registry.
		 *
		 * NOTE: the firewall runs very early. To make this filter apply to firewall
		 * decisions, add it from a must-use plugin (see docs/DEVELOPER.md).
		 *
		 * @param array $bots Registry keyed by bot id.
		 */
		self::$bots = (array) apply_filters( 'botctl_bot_registry', $bots );

		return self::$bots;
	}

	/**
	 * Reset caches (after a feed update).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$bots = null;
	}

	/**
	 * Fill missing keys.
	 *
	 * @param string $id  Bot id.
	 * @param array  $bot Bot.
	 * @return array
	 */
	private static function normalize( $id, array $bot ) {
		return array_merge(
			array(
				'name'        => $id,
				'vendor'      => '',
				'category'    => 'unknown_bot',
				'patterns'    => array(),
				'robots'      => array(),
				'verify'      => array(),
				'robots_only' => false,
				'note'        => '',
				'remote'      => false,
			),
			$bot
		);
	}

	/**
	 * Built-in bots shipped with the plugin.
	 *
	 * @return array
	 */
	public static function builtin() {
		if ( null !== self::$builtin ) {
			return self::$builtin;
		}

		$google = array( 'googlebot.com', 'google.com' );

		$bots = array(
			// ---------------------------------------------------------------- Bad bots (checked first).
			'sqlmap'          => array( 'name' => 'sqlmap', 'vendor' => 'Open source', 'category' => 'bad_bot', 'patterns' => array( 'sqlmap' ) ),
			'nikto'           => array( 'name' => 'Nikto', 'vendor' => 'Open source', 'category' => 'bad_bot', 'patterns' => array( 'Nikto' ) ),
			'nmap'            => array( 'name' => 'Nmap', 'vendor' => 'Open source', 'category' => 'bad_bot', 'patterns' => array( 'Nmap Scripting Engine' ) ),
			'masscan'         => array( 'name' => 'masscan', 'vendor' => 'Open source', 'category' => 'bad_bot', 'patterns' => array( 'masscan' ) ),
			'zgrab'           => array( 'name' => 'ZGrab', 'vendor' => 'Open source', 'category' => 'bad_bot', 'patterns' => array( 'zgrab' ) ),
			'wpscan'          => array( 'name' => 'WPScan', 'vendor' => 'WPScan', 'category' => 'bad_bot', 'patterns' => array( 'WPScan' ) ),
			'nuclei'          => array( 'name' => 'Nuclei', 'vendor' => 'ProjectDiscovery', 'category' => 'bad_bot', 'patterns' => array( 'Nuclei' ) ),
			'acunetix'        => array( 'name' => 'Acunetix', 'vendor' => 'Invicti', 'category' => 'bad_bot', 'patterns' => array( 'Acunetix' ) ),
			'netsparker'      => array( 'name' => 'Netsparker', 'vendor' => 'Invicti', 'category' => 'bad_bot', 'patterns' => array( 'Netsparker' ) ),
			'dirbuster'       => array( 'name' => 'DirBuster', 'vendor' => 'OWASP', 'category' => 'bad_bot', 'patterns' => array( 'DirBuster' ) ),
			'ffuf'            => array( 'name' => 'ffuf', 'vendor' => 'Open source', 'category' => 'bad_bot', 'patterns' => array( 'Fuzz Faster U Fool' ) ),
			'censys'          => array( 'name' => 'Censys', 'vendor' => 'Censys', 'category' => 'bad_bot', 'patterns' => array( 'CensysInspect' ) ),
			'expanse'         => array( 'name' => 'Expanse', 'vendor' => 'Palo Alto Networks', 'category' => 'bad_bot', 'patterns' => array( 'Expanse, a Palo Alto' ) ),
			'leakix'          => array( 'name' => 'LeakIX', 'vendor' => 'LeakIX', 'category' => 'bad_bot', 'patterns' => array( 'l9explore', 'l9tcpid', 'LeakIX' ) ),

			// ---------------------------------------------------------------- AI search & assistants (before search engines: some share vendor names).
			'oai_searchbot'   => array( 'name' => 'OAI-SearchBot', 'vendor' => 'OpenAI', 'category' => 'ai_search', 'patterns' => array( 'OAI-SearchBot' ), 'robots' => array( 'OAI-SearchBot' ), 'note' => 'Indexes pages for ChatGPT search results.' ),
			'chatgpt_user'    => array( 'name' => 'ChatGPT-User', 'vendor' => 'OpenAI', 'category' => 'ai_search', 'patterns' => array( 'ChatGPT-User', 'ChatGPT Agent' ), 'robots' => array( 'ChatGPT-User' ), 'note' => 'Fetches a page when a ChatGPT user asks about it.' ),
			'claude_search'   => array( 'name' => 'Claude-SearchBot', 'vendor' => 'Anthropic', 'category' => 'ai_search', 'patterns' => array( 'Claude-SearchBot' ), 'robots' => array( 'Claude-SearchBot' ), 'note' => 'Indexes pages for Claude search results.' ),
			'claude_user'     => array( 'name' => 'Claude-User', 'vendor' => 'Anthropic', 'category' => 'ai_search', 'patterns' => array( 'Claude-User' ), 'robots' => array( 'Claude-User' ), 'note' => 'Fetches a page when a Claude user asks about it.' ),
			'perplexity_user' => array( 'name' => 'Perplexity-User', 'vendor' => 'Perplexity', 'category' => 'ai_search', 'patterns' => array( 'Perplexity-User' ), 'robots' => array( 'Perplexity-User' ), 'note' => 'Fetches a page when a Perplexity user asks about it.' ),
			'perplexitybot'   => array( 'name' => 'PerplexityBot', 'vendor' => 'Perplexity', 'category' => 'ai_search', 'patterns' => array( 'PerplexityBot' ), 'robots' => array( 'PerplexityBot' ), 'note' => 'Indexes pages for Perplexity answers.' ),
			'duckassistbot'   => array( 'name' => 'DuckAssistBot', 'vendor' => 'DuckDuckGo', 'category' => 'ai_search', 'patterns' => array( 'DuckAssistBot' ), 'robots' => array( 'DuckAssistBot' ) ),
			'mistral_user'    => array( 'name' => 'MistralAI-User', 'vendor' => 'Mistral AI', 'category' => 'ai_search', 'patterns' => array( 'MistralAI-User' ), 'robots' => array( 'MistralAI-User' ) ),
			'meta_fetcher'    => array( 'name' => 'Meta-ExternalFetcher', 'vendor' => 'Meta', 'category' => 'ai_search', 'patterns' => array( 'meta-externalfetcher' ), 'robots' => array( 'meta-externalfetcher' ), 'note' => 'User-initiated fetches from Meta AI.' ),
			'amzn_searchbot'  => array( 'name' => 'Amzn-SearchBot', 'vendor' => 'Amazon', 'category' => 'ai_search', 'patterns' => array( 'Amzn-SearchBot' ), 'robots' => array( 'Amzn-SearchBot' ) ),
			'amzn_user'       => array( 'name' => 'Amzn-User', 'vendor' => 'Amazon', 'category' => 'ai_search', 'patterns' => array( 'Amzn-User' ), 'robots' => array( 'Amzn-User' ) ),
			'youbot'          => array( 'name' => 'YouBot', 'vendor' => 'You.com', 'category' => 'ai_search', 'patterns' => array( 'YouBot' ), 'robots' => array( 'YouBot' ) ),
			'iaskspider'      => array( 'name' => 'iAskSpider', 'vendor' => 'iAsk.ai', 'category' => 'ai_search', 'patterns' => array( 'iaskspider' ), 'robots' => array( 'iaskspider' ) ),
			'google_vertex'   => array( 'name' => 'Google-CloudVertexBot', 'vendor' => 'Google', 'category' => 'ai_search', 'patterns' => array( 'Google-CloudVertexBot' ), 'robots' => array( 'Google-CloudVertexBot' ), 'note' => 'Crawls on behalf of site owners building Vertex AI agents.' ),

			// ---------------------------------------------------------------- AI training.
			'gptbot'          => array( 'name' => 'GPTBot', 'vendor' => 'OpenAI', 'category' => 'ai_training', 'patterns' => array( 'GPTBot' ), 'robots' => array( 'GPTBot' ), 'note' => 'Collects training data for OpenAI models. Separate from ChatGPT search.' ),
			'claudebot'       => array( 'name' => 'ClaudeBot', 'vendor' => 'Anthropic', 'category' => 'ai_training', 'patterns' => array( 'ClaudeBot', 'Claude-Web', 'anthropic-ai' ), 'robots' => array( 'ClaudeBot', 'anthropic-ai' ), 'note' => 'Collects training data for Anthropic models. Separate from Claude search.' ),
			'google_extended' => array( 'name' => 'Google-Extended', 'vendor' => 'Google', 'category' => 'ai_training', 'patterns' => array(), 'robots' => array( 'Google-Extended' ), 'robots_only' => true, 'note' => 'robots.txt token only. Controls use of your content for Gemini training/grounding. Does NOT affect Google Search or AI Overviews.' ),
			'applebot_ext'    => array( 'name' => 'Applebot-Extended', 'vendor' => 'Apple', 'category' => 'ai_training', 'patterns' => array(), 'robots' => array( 'Applebot-Extended' ), 'robots_only' => true, 'note' => 'robots.txt token only. Controls use of your content to train Apple AI models. Does NOT affect Siri/Spotlight search.' ),
			'ccbot'           => array( 'name' => 'CCBot', 'vendor' => 'Common Crawl', 'category' => 'ai_training', 'patterns' => array( 'CCBot' ), 'robots' => array( 'CCBot' ), 'note' => 'Open dataset used by most AI labs for training.' ),
			'bytespider'      => array( 'name' => 'Bytespider', 'vendor' => 'ByteDance', 'category' => 'ai_training', 'patterns' => array( 'Bytespider' ), 'robots' => array( 'Bytespider' ), 'note' => 'Known for aggressive crawling.' ),
			'meta_agent'      => array( 'name' => 'Meta-ExternalAgent', 'vendor' => 'Meta', 'category' => 'ai_training', 'patterns' => array( 'meta-externalagent', 'FacebookBot' ), 'robots' => array( 'meta-externalagent', 'FacebookBot' ) ),
			'amazonbot'       => array( 'name' => 'Amazonbot', 'vendor' => 'Amazon', 'category' => 'ai_training', 'patterns' => array( 'Amazonbot' ), 'robots' => array( 'Amazonbot' ), 'verify' => array( 'crawl.amazonbot.amazon' ) ),
			'cohere'          => array( 'name' => 'Cohere', 'vendor' => 'Cohere', 'category' => 'ai_training', 'patterns' => array( 'cohere-ai', 'cohere-training-data-crawler' ), 'robots' => array( 'cohere-ai', 'cohere-training-data-crawler' ) ),
			'diffbot'         => array( 'name' => 'Diffbot', 'vendor' => 'Diffbot', 'category' => 'ai_training', 'patterns' => array( 'Diffbot' ), 'robots' => array( 'Diffbot' ) ),
			'ai2bot'          => array( 'name' => 'AI2Bot', 'vendor' => 'Allen Institute for AI', 'category' => 'ai_training', 'patterns' => array( 'AI2Bot' ), 'robots' => array( 'AI2Bot' ) ),
			'omgili'          => array( 'name' => 'Omgili / Webz.io', 'vendor' => 'Webz.io', 'category' => 'ai_training', 'patterns' => array( 'omgili' ), 'robots' => array( 'omgili', 'omgilibot' ) ),
			'timpibot'        => array( 'name' => 'Timpibot', 'vendor' => 'Timpi', 'category' => 'ai_training', 'patterns' => array( 'Timpibot' ), 'robots' => array( 'Timpibot' ) ),
			'imagesift'       => array( 'name' => 'ImagesiftBot', 'vendor' => 'Hive', 'category' => 'ai_training', 'patterns' => array( 'ImagesiftBot' ), 'robots' => array( 'ImagesiftBot' ) ),
			'pangubot'        => array( 'name' => 'PanguBot', 'vendor' => 'Huawei', 'category' => 'ai_training', 'patterns' => array( 'PanguBot' ), 'robots' => array( 'PanguBot' ) ),
			'img2dataset'     => array( 'name' => 'img2dataset', 'vendor' => 'Open source', 'category' => 'ai_training', 'patterns' => array( 'img2dataset' ), 'robots' => array( 'img2dataset' ) ),
			'velen'           => array( 'name' => 'VelenPublicWebCrawler', 'vendor' => 'Velen', 'category' => 'ai_training', 'patterns' => array( 'VelenPublicWebCrawler' ), 'robots' => array( 'VelenPublicWebCrawler' ) ),

			// ---------------------------------------------------------------- Search engines.
			'google_ads'      => array( 'name' => 'Google Ads / AdSense', 'vendor' => 'Google', 'category' => 'search_engine', 'patterns' => array( 'AdsBot-Google', 'Mediapartners-Google' ), 'robots' => array( 'AdsBot-Google', 'Mediapartners-Google' ), 'verify' => $google, 'note' => 'Blocking breaks Google Ads landing-page checks and AdSense ad targeting (= lost revenue).' ),
			'googleother'     => array( 'name' => 'GoogleOther', 'vendor' => 'Google', 'category' => 'search_engine', 'patterns' => array( 'GoogleOther' ), 'robots' => array( 'GoogleOther' ), 'verify' => $google, 'note' => 'Google research & development crawls. Not used for Search ranking.' ),
			'googlebot'       => array( 'name' => 'Googlebot', 'vendor' => 'Google', 'category' => 'search_engine', 'patterns' => array( 'Googlebot', 'Storebot-Google', 'Google-InspectionTool', 'APIs-Google', 'FeedFetcher-Google', 'Google-Read-Aloud', 'Google-Site-Verification' ), 'robots' => array( 'Googlebot' ), 'verify' => $google, 'note' => 'Google Search, Images, News, Video, Shopping and AI Overviews.' ),
			'bingbot'         => array( 'name' => 'Bingbot', 'vendor' => 'Microsoft', 'category' => 'search_engine', 'patterns' => array( 'bingbot', 'BingPreview', 'msnbot', 'adidxbot' ), 'robots' => array( 'bingbot', 'msnbot' ), 'verify' => array( 'search.msn.com' ), 'note' => 'Bing, Copilot, DuckDuckGo and Yahoo results rely on the Bing index.' ),
			'applebot'        => array( 'name' => 'Applebot', 'vendor' => 'Apple', 'category' => 'search_engine', 'patterns' => array( 'Applebot' ), 'robots' => array( 'Applebot' ), 'verify' => array( 'applebot.apple.com' ), 'note' => 'Siri, Spotlight and Safari suggestions.' ),
			'yandex'          => array( 'name' => 'YandexBot', 'vendor' => 'Yandex', 'category' => 'search_engine', 'patterns' => array( 'Yandex' ), 'robots' => array( 'Yandex' ), 'verify' => array( 'yandex.ru', 'yandex.net', 'yandex.com' ) ),
			'baidu'           => array( 'name' => 'Baiduspider', 'vendor' => 'Baidu', 'category' => 'search_engine', 'patterns' => array( 'Baiduspider' ), 'robots' => array( 'Baiduspider' ), 'verify' => array( 'baidu.com', 'baidu.jp' ) ),
			'duckduckbot'     => array( 'name' => 'DuckDuckBot', 'vendor' => 'DuckDuckGo', 'category' => 'search_engine', 'patterns' => array( 'DuckDuckBot' ), 'robots' => array( 'DuckDuckBot' ) ),
			'yahoo'           => array( 'name' => 'Yahoo Slurp', 'vendor' => 'Yahoo', 'category' => 'search_engine', 'patterns' => array( 'Yahoo! Slurp' ), 'robots' => array( 'Slurp' ), 'verify' => array( 'crawl.yahoo.net' ) ),
			'petalbot'        => array( 'name' => 'PetalBot', 'vendor' => 'Huawei', 'category' => 'search_engine', 'patterns' => array( 'PetalBot' ), 'robots' => array( 'PetalBot' ), 'verify' => array( 'petalsearch.com', 'aspiegel.com' ) ),
			'seznam'          => array( 'name' => 'SeznamBot', 'vendor' => 'Seznam', 'category' => 'search_engine', 'patterns' => array( 'SeznamBot' ), 'robots' => array( 'SeznamBot' ) ),
			'naver'           => array( 'name' => 'Naver Yeti', 'vendor' => 'Naver', 'category' => 'search_engine', 'patterns' => array( 'Yeti/' ), 'robots' => array( 'Yeti' ) ),
			'qwant'           => array( 'name' => 'Qwantbot', 'vendor' => 'Qwant', 'category' => 'search_engine', 'patterns' => array( 'Qwantbot', 'Qwantify' ), 'robots' => array( 'Qwantbot', 'Qwantify' ) ),
			'mojeek'          => array( 'name' => 'MojeekBot', 'vendor' => 'Mojeek', 'category' => 'search_engine', 'patterns' => array( 'MojeekBot' ), 'robots' => array( 'MojeekBot' ) ),
			'sogou'           => array( 'name' => 'Sogou', 'vendor' => 'Sogou', 'category' => 'search_engine', 'patterns' => array( 'Sogou' ), 'robots' => array( 'Sogou' ) ),
			'coccoc'          => array( 'name' => 'Coc Coc', 'vendor' => 'Coc Coc', 'category' => 'search_engine', 'patterns' => array( 'coccocbot' ), 'robots' => array( 'coccocbot' ) ),

			// ---------------------------------------------------------------- SEO & marketing tools.
			'ahrefsbot'       => array( 'name' => 'AhrefsBot', 'vendor' => 'Ahrefs', 'category' => 'seo_tool', 'patterns' => array( 'AhrefsBot', 'AhrefsSiteAudit' ), 'robots' => array( 'AhrefsBot', 'AhrefsSiteAudit' ) ),
			'semrushbot'      => array( 'name' => 'SemrushBot', 'vendor' => 'Semrush', 'category' => 'seo_tool', 'patterns' => array( 'SemrushBot', 'SiteAuditBot', 'SplitSignalBot' ), 'robots' => array( 'SemrushBot', 'SiteAuditBot', 'SplitSignalBot' ) ),
			'mj12bot'         => array( 'name' => 'MJ12bot', 'vendor' => 'Majestic', 'category' => 'seo_tool', 'patterns' => array( 'MJ12bot' ), 'robots' => array( 'MJ12bot' ) ),
			'moz'             => array( 'name' => 'Moz (DotBot / rogerbot)', 'vendor' => 'Moz', 'category' => 'seo_tool', 'patterns' => array( 'DotBot', 'rogerbot' ), 'robots' => array( 'DotBot', 'rogerbot' ) ),
			'blexbot'         => array( 'name' => 'BLEXBot', 'vendor' => 'WebMeUp', 'category' => 'seo_tool', 'patterns' => array( 'BLEXBot' ), 'robots' => array( 'BLEXBot' ) ),
			'dataforseo'      => array( 'name' => 'DataForSeoBot', 'vendor' => 'DataForSEO', 'category' => 'seo_tool', 'patterns' => array( 'DataForSeoBot' ), 'robots' => array( 'DataForSeoBot' ) ),
			'serpstat'        => array( 'name' => 'Serpstatbot', 'vendor' => 'Serpstat', 'category' => 'seo_tool', 'patterns' => array( 'serpstatbot' ), 'robots' => array( 'serpstatbot' ) ),
			'seranking'       => array( 'name' => 'SE Ranking', 'vendor' => 'SE Ranking', 'category' => 'seo_tool', 'patterns' => array( 'SERankingBacklinksBot' ), 'robots' => array( 'SERankingBacklinksBot' ) ),
			'barkrowler'      => array( 'name' => 'Barkrowler', 'vendor' => 'Babbar', 'category' => 'seo_tool', 'patterns' => array( 'Barkrowler' ), 'robots' => array( 'Barkrowler' ) ),
			'megaindex'       => array( 'name' => 'MegaIndex', 'vendor' => 'MegaIndex', 'category' => 'seo_tool', 'patterns' => array( 'MegaIndex' ), 'robots' => array( 'MegaIndex.ru' ) ),
			'seokicks'        => array( 'name' => 'SEOkicks', 'vendor' => 'SEOkicks', 'category' => 'seo_tool', 'patterns' => array( 'SEOkicks' ), 'robots' => array( 'SEOkicks' ) ),
			'brightedge'      => array( 'name' => 'BrightEdge', 'vendor' => 'BrightEdge', 'category' => 'seo_tool', 'patterns' => array( 'BrightEdge Crawler' ), 'robots' => array( 'BrightEdge Crawler' ) ),
			'zoominfo'        => array( 'name' => 'ZoominfoBot', 'vendor' => 'ZoomInfo', 'category' => 'seo_tool', 'patterns' => array( 'ZoominfoBot' ), 'robots' => array( 'ZoominfoBot' ) ),
			'screamingfrog'   => array( 'name' => 'Screaming Frog', 'vendor' => 'Screaming Frog', 'category' => 'seo_tool', 'patterns' => array( 'Screaming Frog' ), 'robots' => array( 'Screaming Frog SEO Spider' ), 'note' => 'Often run by your own SEO agency – consider allowing.' ),
			'sitebulb'        => array( 'name' => 'Sitebulb', 'vendor' => 'Sitebulb', 'category' => 'seo_tool', 'patterns' => array( 'Sitebulb' ), 'robots' => array( 'Sitebulb' ), 'note' => 'Often run by your own SEO agency – consider allowing.' ),

			// ---------------------------------------------------------------- Social link previews.
			'facebook'        => array( 'name' => 'Facebook preview', 'vendor' => 'Meta', 'category' => 'social', 'patterns' => array( 'facebookexternalhit', 'facebookcatalog' ), 'robots' => array( 'facebookexternalhit' ) ),
			'twitter'         => array( 'name' => 'X / Twitterbot', 'vendor' => 'X Corp', 'category' => 'social', 'patterns' => array( 'Twitterbot' ), 'robots' => array( 'Twitterbot' ) ),
			'linkedin'        => array( 'name' => 'LinkedInBot', 'vendor' => 'LinkedIn', 'category' => 'social', 'patterns' => array( 'LinkedInBot' ), 'robots' => array( 'LinkedInBot' ) ),
			'pinterest'       => array( 'name' => 'Pinterest', 'vendor' => 'Pinterest', 'category' => 'social', 'patterns' => array( 'Pinterestbot', 'Pinterest/' ), 'robots' => array( 'Pinterestbot' ) ),
			'whatsapp'        => array( 'name' => 'WhatsApp', 'vendor' => 'Meta', 'category' => 'social', 'patterns' => array( 'WhatsApp' ), 'robots' => array( 'WhatsApp' ) ),
			'telegram'        => array( 'name' => 'TelegramBot', 'vendor' => 'Telegram', 'category' => 'social', 'patterns' => array( 'TelegramBot' ), 'robots' => array( 'TelegramBot' ) ),
			'slack'           => array( 'name' => 'Slackbot', 'vendor' => 'Slack', 'category' => 'social', 'patterns' => array( 'Slackbot', 'Slack-ImgProxy' ), 'robots' => array( 'Slackbot' ) ),
			'discord'         => array( 'name' => 'Discordbot', 'vendor' => 'Discord', 'category' => 'social', 'patterns' => array( 'Discordbot' ), 'robots' => array( 'Discordbot' ) ),
			'skype'           => array( 'name' => 'Skype / Teams preview', 'vendor' => 'Microsoft', 'category' => 'social', 'patterns' => array( 'SkypeUriPreview' ), 'robots' => array( 'SkypeUriPreview' ) ),
			'reddit'          => array( 'name' => 'Redditbot', 'vendor' => 'Reddit', 'category' => 'social', 'patterns' => array( 'redditbot' ), 'robots' => array( 'redditbot' ) ),
			'bluesky'         => array( 'name' => 'Bluesky preview', 'vendor' => 'Bluesky', 'category' => 'social', 'patterns' => array( 'Cardyb' ), 'robots' => array( 'Cardyb' ) ),
			'mastodon'        => array( 'name' => 'Mastodon', 'vendor' => 'Mastodon', 'category' => 'social', 'patterns' => array( 'Mastodon/' ), 'robots' => array( 'Mastodon' ) ),
			'vkshare'         => array( 'name' => 'VK share', 'vendor' => 'VK', 'category' => 'social', 'patterns' => array( 'vkShare' ), 'robots' => array( 'vkShare' ) ),
			'embedly'         => array( 'name' => 'Embedly', 'vendor' => 'Embedly', 'category' => 'social', 'patterns' => array( 'Embedly' ), 'robots' => array( 'Embedly' ) ),
			'iframely'        => array( 'name' => 'Iframely', 'vendor' => 'Iframely', 'category' => 'social', 'patterns' => array( 'Iframely' ), 'robots' => array( 'Iframely' ) ),

			// ---------------------------------------------------------------- Monitoring & management.
			'jetpack'         => array( 'name' => 'Jetpack', 'vendor' => 'Automattic', 'category' => 'monitoring', 'patterns' => array( 'Jetpack by WordPress.com' ) ),
			'uptimerobot'     => array( 'name' => 'UptimeRobot', 'vendor' => 'UptimeRobot', 'category' => 'monitoring', 'patterns' => array( 'UptimeRobot' ) ),
			'pingdom'         => array( 'name' => 'Pingdom', 'vendor' => 'SolarWinds', 'category' => 'monitoring', 'patterns' => array( 'Pingdom' ) ),
			'statuscake'      => array( 'name' => 'StatusCake', 'vendor' => 'StatusCake', 'category' => 'monitoring', 'patterns' => array( 'StatusCake' ) ),
			'site24x7'        => array( 'name' => 'Site24x7', 'vendor' => 'Zoho', 'category' => 'monitoring', 'patterns' => array( 'Site24x7' ) ),
			'betterstack'     => array( 'name' => 'Better Stack', 'vendor' => 'Better Stack', 'category' => 'monitoring', 'patterns' => array( 'Better Uptime', 'Betterstack' ) ),
			'hetrix'          => array( 'name' => 'HetrixTools', 'vendor' => 'HetrixTools', 'category' => 'monitoring', 'patterns' => array( 'HetrixTools' ) ),
			'newrelic'        => array( 'name' => 'New Relic', 'vendor' => 'New Relic', 'category' => 'monitoring', 'patterns' => array( 'NewRelicPinger' ) ),
			'gtmetrix'        => array( 'name' => 'GTmetrix', 'vendor' => 'GTmetrix', 'category' => 'monitoring', 'patterns' => array( 'GTmetrix' ) ),
			'lighthouse'      => array( 'name' => 'Lighthouse / PageSpeed', 'vendor' => 'Google', 'category' => 'monitoring', 'patterns' => array( 'Chrome-Lighthouse', 'Google Page Speed' ) ),
			'managewp'        => array( 'name' => 'ManageWP', 'vendor' => 'GoDaddy', 'category' => 'monitoring', 'patterns' => array( 'ManageWP' ) ),
			'mainwp'          => array( 'name' => 'MainWP', 'vendor' => 'MainWP', 'category' => 'monitoring', 'patterns' => array( 'MainWP' ) ),

			// ---------------------------------------------------------------- Feed readers.
			'feedly'          => array( 'name' => 'Feedly', 'vendor' => 'Feedly', 'category' => 'feed_reader', 'patterns' => array( 'Feedly' ) ),
			'inoreader'       => array( 'name' => 'Inoreader', 'vendor' => 'Inoreader', 'category' => 'feed_reader', 'patterns' => array( 'Inoreader' ) ),
			'newsblur'        => array( 'name' => 'NewsBlur', 'vendor' => 'NewsBlur', 'category' => 'feed_reader', 'patterns' => array( 'NewsBlur' ) ),
			'feedbin'         => array( 'name' => 'Feedbin', 'vendor' => 'Feedbin', 'category' => 'feed_reader', 'patterns' => array( 'Feedbin' ) ),
			'theoldreader'    => array( 'name' => 'The Old Reader', 'vendor' => 'The Old Reader', 'category' => 'feed_reader', 'patterns' => array( 'theoldreader' ) ),
			'netnewswire'     => array( 'name' => 'NetNewsWire', 'vendor' => 'NetNewsWire', 'category' => 'feed_reader', 'patterns' => array( 'NetNewsWire' ) ),
			'ttrss'           => array( 'name' => 'Tiny Tiny RSS', 'vendor' => 'Open source', 'category' => 'feed_reader', 'patterns' => array( 'Tiny Tiny RSS' ) ),

			// ---------------------------------------------------------------- Archives.
			'internet_archive' => array( 'name' => 'Internet Archive', 'vendor' => 'Internet Archive', 'category' => 'archiver', 'patterns' => array( 'ia_archiver', 'archive.org_bot', 'Wayback' ), 'robots' => array( 'ia_archiver', 'archive.org_bot' ) ),
			'archive_it'      => array( 'name' => 'Archive-It', 'vendor' => 'Internet Archive', 'category' => 'archiver', 'patterns' => array( 'Archive-It' ), 'robots' => array( 'Archive-It' ) ),
			'heritrix'        => array( 'name' => 'Heritrix', 'vendor' => 'Open source', 'category' => 'archiver', 'patterns' => array( 'heritrix' ), 'robots' => array( 'heritrix' ) ),

			// ---------------------------------------------------------------- Scripts & HTTP libraries.
			'headless'        => array( 'name' => 'Headless browsers', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'HeadlessChrome', 'PhantomJS' ) ),
			'scrapy'          => array( 'name' => 'Scrapy', 'vendor' => 'Open source', 'category' => 'scripts', 'patterns' => array( 'Scrapy' ), 'robots' => array( 'Scrapy' ) ),
			'python'          => array( 'name' => 'Python HTTP clients', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'python-requests', 'python-urllib', 'Python-urllib', 'python-httpx', 'aiohttp' ) ),
			'curl'            => array( 'name' => 'curl', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'curl/' ) ),
			'wget'            => array( 'name' => 'Wget', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'Wget/' ) ),
			'go_http'         => array( 'name' => 'Go HTTP client', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'Go-http-client' ) ),
			'java'            => array( 'name' => 'Java HTTP clients', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'Java/', 'Apache-HttpClient', 'okhttp' ) ),
			'node'            => array( 'name' => 'Node.js HTTP clients', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'node-fetch', 'axios/', 'undici' ) ),
			'php_http'        => array( 'name' => 'PHP HTTP clients', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'GuzzleHttp' ) ),
			'perl'            => array( 'name' => 'Perl LWP', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'libwww-perl', 'LWP::Simple' ) ),
			'colly'           => array( 'name' => 'Colly', 'vendor' => 'Open source', 'category' => 'scripts', 'patterns' => array( 'colly' ) ),
			'wordpress_http'  => array( 'name' => 'Other WordPress sites', 'vendor' => '—', 'category' => 'scripts', 'patterns' => array( 'WordPress/' ), 'note' => 'Pingbacks/oEmbed from other WordPress sites. Your own site\'s loopback requests are always allowed.' ),
		);

		foreach ( $bots as $id => $bot ) {
			$bots[ $id ] = self::normalize( $id, $bot );
		}

		self::$builtin = $bots;
		return self::$builtin;
	}

	/**
	 * Get a single bot.
	 *
	 * @param string $id Bot id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}
}
