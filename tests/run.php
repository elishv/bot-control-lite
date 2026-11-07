<?php
/**
 * Fresh-install and upgrade checks for watch-only mode.
 *
 * Run: php tests/run.php
 *
 * @package AllInCrawlerAIAgentManager
 */

require __DIR__ . '/bootstrap.php';

$botctl_failures = 0;

/**
 * @param bool   $ok      Assertion.
 * @param string $message Failure text.
 * @return void
 */
function botctl_assert( $ok, $message ) {
	global $botctl_failures;
	if ( $ok ) {
		echo "ok  - {$message}\n";
		return;
	}
	$botctl_failures++;
	echo "FAIL - {$message}\n";
}

/**
 * Drop per-request caches so the next request is evaluated again.
 *
 * @param bool $load Reset the load-monitor memo.
 * @return void
 */
function botctl_reset_request( $load = true ) {
	$settings = new ReflectionProperty( 'Bot_Control_Settings', 'cache' );
	$settings->setAccessible( true );
	$settings->setValue( null, null );

	$ran = new ReflectionProperty( 'Bot_Control_Firewall', 'ran' );
	$ran->setAccessible( true );
	$ran->setValue( null, false );

	Bot_Control_IP::reset();

	if ( $load ) {
		$state = new ReflectionProperty( 'Bot_Control_Load_Monitor', 'state' );
		$state->setAccessible( true );
		$state->setValue( null, null );
	}
}

/**
 * Pretend a front-end request arrived.
 *
 * @param string $ua User-Agent.
 * @return void
 */
function botctl_request( $ua ) {
	$_SERVER['REQUEST_METHOD']  = 'GET';
	$_SERVER['REQUEST_URI']     = '/';
	$_SERVER['HTTP_USER_AGENT'] = $ua;
	$_SERVER['REMOTE_ADDR']     = '203.0.113.44';
	$_SERVER['SERVER_ADDR']     = '198.51.100.2';
	$_COOKIE                    = array();
	botctl_reset_request( false );
}

/**
 * Settings as a 1.2.0 site would have saved them: Enforce, plus a few real choices.
 *
 * @return array
 */
function botctl_v120_settings() {
	$settings                                     = Bot_Control_Settings::defaults();
	$settings['mode']                             = 'enforce';
	$settings['window']                           = 45;
	$settings['verify_bots']                      = 1;
	$settings['impostor_policy']                  = 'block';
	$settings['load_shedding']                    = 1;
	$settings['load_threshold']                   = 1.5;
	$settings['robots_enabled']                   = 1;
	$settings['robots_crawl_delay']               = 1;
	$settings['ip_blocklist']                     = "203.0.113.50 # scanner";
	$settings['categories']['ai_training']['policy'] = 'block';
	$settings['categories']['ai_training']['limit']  = 7;
	$settings['bots']                             = array(
		'gptbot' => array(
			'policy' => 'limit',
			'limit'  => 3,
		),
	);
	$settings['custom_bots']                      = array(
		array(
			'id'       => 'abc',
			'name'     => 'Partner',
			'pattern'  => 'PartnerBot',
			'match'    => 'contains',
			'category' => 'scripts',
			'policy'   => 'allow',
			'limit'    => 0,
		),
	);
	return $settings;
}

echo "Fresh install defaults\n";
botctl_assert( 'monitor' === Bot_Control_Settings::defaults()['mode'], 'default mode is watch-only (monitor)' );
botctl_assert( 1 === Bot_Control_Settings::defaults()['robots_enabled'], 'robots.txt option stays enabled so Enforce can publish it later' );
botctl_assert( 1 === Bot_Control_Settings::defaults()['load_shedding'], 'load shedding stays saved and is applied only in Enforce' );

echo "\nFresh activation\n";
botctl_test_reset_options();
botctl_reset_request();
Bot_Control_Install::activate();
$fresh = get_option( Bot_Control_Settings::OPTION );
botctl_assert( is_array( $fresh ) && 'monitor' === $fresh['mode'], 'activation stores watch-only mode' );
botctl_assert( 1 === (int) get_option( Bot_Control_Install::WATCH_NOTICE ), 'fresh activation arms the one-time notice' );
botctl_assert( get_option( Bot_Control_Install::ACTIVATED_AT ) > 0, 'fresh activation records an install time' );

$base = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
botctl_reset_request();
$watched = Bot_Control_Robots::filter( $base, '1' );
botctl_assert( $watched === $base, 'fresh install does not change robots.txt' );

// Give the saved rules something robots.txt would publish, and keep watch-only.
$fresh['categories']['ai_training']['policy'] = 'block';
$fresh['load_threshold']                      = 50;
update_option( Bot_Control_Settings::OPTION, $fresh );
botctl_reset_request();
$watched_block = Bot_Control_Robots::filter( $base, '1' );
botctl_assert( $watched_block === $base, 'watch-only leaves robots.txt unchanged even when a blocked bot has a robots token' );

$fresh['mode'] = 'enforce';
update_option( Bot_Control_Settings::OPTION, $fresh );
botctl_reset_request();
$enforced_robots = Bot_Control_Robots::filter( $base, '1' );
botctl_assert( false !== strpos( $enforced_robots, 'User-agent: Google-Extended' ), 'enforce mode publishes a Disallow group' );
botctl_assert( false !== strpos( $enforced_robots, 'Disallow: /' ), 'enforce mode adds Disallow lines' );
botctl_assert( $enforced_robots !== $base, 'enforce mode output differs from the original robots.txt' );

// Back to watch-only. Restore the default AI-training limit so the next check
// is a rate limit, not a block. Keep the threshold high so a busy test host
// does not take the load-shedding branch instead.
$fresh['mode']                                = 'monitor';
$fresh['categories']['ai_training']['policy'] = 'limit';
$fresh['load_threshold']                      = 50;
update_option( Bot_Control_Settings::OPTION, $fresh );
botctl_reset_request( true );

$GLOBALS['botctl_test_status'] = 0;
botctl_request( 'sqlmap/1.8.0' );
Bot_Control_Firewall::run();
$blocked = Bot_Control_Firewall::decision();
botctl_assert( isset( $blocked['action'], $blocked['enforced'] ) && 'blocked' === $blocked['action'] && false === $blocked['enforced'], 'a scanner is logged as blocked but not enforced' );
botctl_assert( 0 === (int) $GLOBALS['botctl_test_status'], 'watch-only does not send a denial status' );

botctl_reset_request();
$limited = array();
for ( $i = 1; $i <= 21; $i++ ) {
	botctl_request( 'Mozilla/5.0 (compatible; GPTBot/1.2)' );
	Bot_Control_Firewall::run();
	$limited = Bot_Control_Firewall::decision();
}
botctl_assert( isset( $limited['action'], $limited['enforced'] ) && 'limited' === $limited['action'] && false === $limited['enforced'], 'an AI crawler over its limit is not rate-limited in watch-only' );
botctl_assert( 0 === (int) $GLOBALS['botctl_test_status'], 'rate limit in watch-only does not send 429' );

$overloaded = new ReflectionProperty( 'Bot_Control_Load_Monitor', 'state' );
$overloaded->setAccessible( true );
$overloaded->setValue(
	null,
	array(
		'supported'  => true,
		'load'       => 9.0,
		'cores'      => 1,
		'per_core'   => 9.0,
		'overloaded' => true,
	)
);
botctl_request( 'Mozilla/5.0 (compatible; ClaudeBot/1.0)' );
Bot_Control_Firewall::run();
$shed = Bot_Control_Firewall::decision();
botctl_assert( isset( $shed['action'], $shed['enforced'] ) && 'shed' === $shed['action'] && false === $shed['enforced'], 'load shedding is logged and not applied in watch-only' );

echo "\nEnforce still denies (separate process)\n";
$probe = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/probe-request.php' );
$watch = shell_exec( $probe . ' monitor sqlmap/1.8.0 2>/dev/null' );
$force = shell_exec( $probe . ' enforce sqlmap/1.8.0 2>/dev/null' );
botctl_assert( is_string( $watch ) && false !== strpos( $watch, 'SURVIVED' ) && false === strpos( $watch, 'Access denied.' ), 'watch-only probe lets the scanner through' );
botctl_assert( is_string( $force ) && false === strpos( (string) $force, 'SURVIVED' ) && false !== strpos( (string) $force, 'Access denied.' ), 'enforce probe blocks the scanner' );

echo "\nUpgrade from enforcing 1.2.0\n";
botctl_test_reset_options();
$saved = botctl_v120_settings();
update_option( Bot_Control_Settings::OPTION, $saved );
update_option( Bot_Control_Install::VERSION_OPTION, '1.2.0' );
update_option( Bot_Control_Install::DB_OPTION, BOTCTL_DB_VERSION );
$before = get_option( Bot_Control_Settings::OPTION );
Bot_Control_Install::maybe_upgrade();
$after = get_option( Bot_Control_Settings::OPTION );
botctl_assert( $before === $after, 'upgrade does not rewrite the saved settings array' );
botctl_assert( 'enforce' === $after['mode'], 'saved Enforce mode is preserved' );
botctl_assert( 45 === $after['window'], 'saved rate-limit window is preserved' );
botctl_assert( 'block' === $after['categories']['ai_training']['policy'], 'saved category policy is preserved' );
botctl_assert( 3 === $after['bots']['gptbot']['limit'], 'saved per-bot override is preserved' );
botctl_assert( 'PartnerBot' === $after['custom_bots'][0]['pattern'], 'saved custom bot is preserved' );
botctl_assert( 1 === (int) $after['robots_enabled'] && 1 === (int) $after['load_shedding'] && 1 === (int) $after['verify_bots'], 'robots, load shedding and verification choices are preserved' );
botctl_assert( BOTCTL_VERSION === get_option( Bot_Control_Install::VERSION_OPTION ), 'version option moves to ' . BOTCTL_VERSION );
botctl_assert( false === get_option( Bot_Control_Install::WATCH_NOTICE, false ), 'upgrade does not show the fresh-install notice' );

botctl_reset_request();
$merged = Bot_Control_Settings::get();
botctl_assert( 'enforce' === $merged['mode'], 'reading settings after upgrade still returns Enforce' );
botctl_assert( 1.5 === (float) $merged['load_threshold'], 'reading settings keeps the saved load threshold' );

$again = $after;
Bot_Control_Install::activate();
botctl_assert( get_option( Bot_Control_Settings::OPTION ) === $again, 'reactivating does not replace an existing Enforce configuration' );
botctl_assert( false === get_option( Bot_Control_Install::WATCH_NOTICE, false ), 'reactivating an existing site does not arm the fresh-install notice' );

$imported = Bot_Control_Settings::sanitize_all( $saved );
botctl_assert( 'enforce' === $imported['mode'], 'importing a 1.2.0 export keeps Enforce' );
botctl_assert( 45 === (int) $imported['window'], 'importing a 1.2.0 export keeps the window' );

echo "\n";
if ( $botctl_failures > 0 ) {
	echo $botctl_failures . " failed\n";
	exit( 1 );
}
echo "all passed\n";
exit( 0 );
