<?php
/**
 * One-shot request probe. Prints SURVIVED when the firewall returns.
 * Enforce + a blocked bot calls exit() inside the plugin and never prints that line.
 *
 * Usage: php tests/probe-request.php <monitor|enforce> <user-agent>
 *
 * @package AllInCrawlerAIAgentManager
 */

require __DIR__ . '/bootstrap.php';

$mode = isset( $argv[1] ) ? $argv[1] : 'monitor';
$ua   = isset( $argv[2] ) ? $argv[2] : 'sqlmap/1.0';

$settings         = Bot_Control_Settings::defaults();
$settings['mode'] = ( 'enforce' === $mode ) ? 'enforce' : 'monitor';
update_option( Bot_Control_Settings::OPTION, $settings, true );

$cache = new ReflectionProperty( 'Bot_Control_Settings', 'cache' );
$cache->setAccessible( true );
$cache->setValue( null, null );

$ran = new ReflectionProperty( 'Bot_Control_Firewall', 'ran' );
$ran->setAccessible( true );
$ran->setValue( null, false );

$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['HTTP_USER_AGENT'] = $ua;
$_SERVER['REMOTE_ADDR']     = '203.0.113.10';
$_SERVER['SERVER_ADDR']     = '198.51.100.2';
$_COOKIE                    = array();
Bot_Control_IP::reset();

Bot_Control_Firewall::run();

echo "SURVIVED\n";
echo wp_json_encode( Bot_Control_Firewall::decision() );
echo "\n";
