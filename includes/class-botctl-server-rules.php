<?php
/**
 * Web-server rules (Apache/LiteSpeed .htaccess and nginx).
 *
 * Requests for blocked bots are rejected by the web server itself, before PHP
 * starts – the cheapest possible block. Only rules that need no PHP logic are
 * exported: blocked User-Agents, empty User-Agents (when blocked) and optionally
 * the IP block list. Rate limits, DNS verification and load shedding stay in PHP.
 *
 * Excluded paths and allow-listed IPs are honoured by the generated rules.
 *
 * @package AllInCrawlerAIAgentManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Server rules.
 */
final class Bot_Control_Server_Rules {

	const MARKER = 'All-IN Crawler & AI Agent Manager';

	/**
	 * Marker written by builds before the rename (1.1.x, then called "Bot Control"). Such a block is
	 * replaced/removed like our own, so an upgrade never leaves two rule blocks behind.
	 */
	const LEGACY_MARKER = 'Bot Control';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'botctl_settings_updated', array( __CLASS__, 'maybe_sync' ) );
	}

	/**
	 * Is this an Apache/LiteSpeed server (reads .htaccess)?
	 *
	 * @return bool
	 */
	public static function is_apache() {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';
		return false !== strpos( $software, 'apache' ) || false !== strpos( $software, 'litespeed' );
	}

	/**
	 * .htaccess path.
	 *
	 * @return string
	 */
	public static function htaccess_path() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return get_home_path() . '.htaccess';
	}

	/**
	 * Regex alternatives for blocked User-Agents.
	 *
	 * @param array $s Settings.
	 * @return string[]
	 */
	public static function blocked_patterns( array $s ) {
		$out = array();
		foreach ( Bot_Control_Bot_Registry::all() as $id => $bot ) {
			if ( $bot['robots_only'] || empty( $bot['patterns'] ) ) {
				continue;
			}
			$rule = Bot_Control_Policy::for_bot( $id, $s );
			if ( 'block' !== $rule['policy'] ) {
				continue;
			}
			foreach ( $bot['patterns'] as $pattern ) {
				$out[] = preg_quote( $pattern, '#' );
			}
		}
		foreach ( $s['custom_bots'] as $row ) {
			$policy = 'inherit' === $row['policy'] ? $s['categories'][ $row['category'] ]['policy'] : $row['policy'];
			if ( 'block' !== $policy ) {
				continue;
			}
			$out[] = 'regex' === $row['match'] ? '(?:' . $row['pattern'] . ')' : preg_quote( $row['pattern'], '#' );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Quote a regex for a double-quoted server config string.
	 *
	 * @param string $regex Regex.
	 * @return string
	 */
	private static function quote( $regex ) {
		return '"' . str_replace( '"', '\"', $regex ) . '"';
	}

	/**
	 * RewriteCond for an IP/CIDR.
	 *
	 * Single IPs and IPv4 /8, /16, /24 ranges become a %{REMOTE_ADDR} regex (works on
	 * Apache and LiteSpeed). Other ranges need Apache 2.4's expr -R.
	 *
	 * @param string $cidr   IP or CIDR.
	 * @param bool   $negate Build a "not in" condition.
	 * @return string
	 */
	public static function ip_condition( $cidr, $negate ) {
		$bang = $negate ? '!' : '';
		if ( false === strpos( $cidr, '/' ) ) {
			return 'RewriteCond %{REMOTE_ADDR} ' . self::quote( $bang . '^' . preg_quote( $cidr, '#' ) . '$' );
		}
		list( $net, $bits ) = explode( '/', $cidr, 2 );
		if ( false === strpos( $net, ':' ) && in_array( (int) $bits, array( 8, 16, 24, 32 ), true ) ) {
			$octets = array_slice( explode( '.', $net ), 0, (int) $bits / 8 );
			$regex  = '^' . implode( '\\.', $octets ) . ( 32 === (int) $bits ? '$' : '\\.' );
			return 'RewriteCond %{REMOTE_ADDR} ' . self::quote( $bang . $regex );
		}
		return 'RewriteCond expr ' . self::quote( ( $negate ? '! ' : '' ) . "-R '" . $cidr . "'" );
	}

	/**
	 * Excluded paths split into prefix regexes and query fragments.
	 *
	 * @param array $s Settings.
	 * @return array { paths: string[], queries: string[] }
	 */
	private static function exclusions( array $s ) {
		$paths   = array();
		$queries = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $s['excluded_paths'] ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( false !== strpos( $line, '?' ) ) {
				$queries[] = preg_quote( ltrim( substr( $line, strpos( $line, '?' ) + 1 ) ), '#' );
			} else {
				// Unanchored so sub-directory installs (/blog/wp-cron.php) match too.
				$paths[] = preg_quote( $line, '#' );
			}
		}
		return array(
			'paths'   => $paths,
			'queries' => array_filter( $queries, 'strlen' ),
		);
	}

	/**
	 * Build the Apache/LiteSpeed rules (without markers).
	 *
	 * @param array $s Settings.
	 * @return string[] Lines (empty when nothing to block).
	 */
	public static function apache( array $s ) {
		$patterns = self::blocked_patterns( $s );
		$block_no_ua = 'block' === $s['categories']['no_ua']['policy'];
		$ips      = ! empty( $s['server_rules_ips'] ) ? Bot_Control_IP::parse_list( $s['ip_blocklist'] ) : array();

		if ( empty( $patterns ) && ! $block_no_ua && empty( $ips ) ) {
			return array();
		}

		$ex    = self::exclusions( $s );
		$allow = Bot_Control_IP::parse_list( $s['ip_allowlist'] );

		// Conditions shared by every rule: not excluded, not allow-listed.
		$guards = array();
		foreach ( $ex['paths'] as $re ) {
			$guards[] = 'RewriteCond %{REQUEST_URI} ' . self::quote( '!' . $re );
		}
		foreach ( $ex['queries'] as $re ) {
			$guards[] = 'RewriteCond %{QUERY_STRING} ' . self::quote( '!' . $re );
		}
		foreach ( $allow as $cidr ) {
			$guards[] = self::ip_condition( $cidr, true );
		}

		$lines   = array();
		$lines[] = '# Generated by the All-IN Crawler & AI Agent Manager plugin – edit the rules in WordPress, not here.';
		$lines[] = '<IfModule mod_rewrite.c>';
		$lines[] = 'RewriteEngine On';

		if ( $patterns ) {
			// Split into chunks to keep each regex a manageable length.
			foreach ( array_chunk( $patterns, 40 ) as $chunk ) {
				$lines   = array_merge( $lines, $guards );
				$lines[] = 'RewriteCond %{HTTP_USER_AGENT} ' . self::quote( '(?:' . implode( '|', $chunk ) . ')' ) . ' [NC]';
				$lines[] = 'RewriteRule .* - [F,L]';
			}
		}

		if ( $block_no_ua ) {
			$lines   = array_merge( $lines, $guards );
			$lines[] = 'RewriteCond %{HTTP_USER_AGENT} ^-?$';
			$lines[] = 'RewriteRule .* - [F,L]';
		}

		if ( $ips ) {
			$lines = array_merge( $lines, $guards );
			$last  = count( $ips ) - 1;
			foreach ( $ips as $i => $cidr ) {
				$lines[] = self::ip_condition( $cidr, false ) . ( $i < $last ? ' [OR]' : '' );
			}
			$lines[] = 'RewriteRule .* - [F,L]';
		}

		$lines[] = '</IfModule>';
		return $lines;
	}

	/**
	 * Build the nginx configuration.
	 *
	 * @param array $s Settings.
	 * @return array { http: string, server: string } Empty strings when nothing to block.
	 */
	public static function nginx( array $s ) {
		$patterns    = self::blocked_patterns( $s );
		$block_no_ua = 'block' === $s['categories']['no_ua']['policy'];
		$ips         = ! empty( $s['server_rules_ips'] ) ? Bot_Control_IP::parse_list( $s['ip_blocklist'] ) : array();

		if ( empty( $patterns ) && ! $block_no_ua && empty( $ips ) ) {
			return array( 'http' => '', 'server' => '' );
		}

		$ex    = self::exclusions( $s );
		$allow = Bot_Control_IP::parse_list( $s['ip_allowlist'] );

		$http   = array( '# All-IN Crawler & AI Agent Manager – place inside the http { } block (e.g. /etc/nginx/conf.d/all-in-crawler-ai-agent-manager.conf)' );
		$http[] = 'map $http_user_agent $botctl_bad_ua {';
		$http[] = '    default 0;';
		foreach ( array_chunk( $patterns, 40 ) as $chunk ) {
			$http[] = '    ' . self::quote( '~*(?:' . implode( '|', $chunk ) . ')' ) . ' 1;';
		}
		if ( $block_no_ua ) {
			$http[] = '    "" 1;';
			$http[] = '    "-" 1;';
		}
		$http[] = '}';
		$http[] = 'geo $botctl_bad_ip {';
		$http[] = '    default 0;';
		foreach ( $ips as $cidr ) {
			$http[] = '    ' . $cidr . ' 1;';
		}
		$http[] = '}';
		$http[] = 'geo $botctl_allow_ip {';
		$http[] = '    default 0;';
		foreach ( $allow as $cidr ) {
			$http[] = '    ' . $cidr . ' 1;';
		}
		$http[] = '}';

		$server   = array( '# All-IN Crawler & AI Agent Manager – place inside your site\'s server { } block, before the location blocks' );
		$server[] = 'set $botctl_block 0;';
		$server[] = 'if ($botctl_bad_ua) { set $botctl_block 1; }';
		$server[] = 'if ($botctl_bad_ip) { set $botctl_block 1; }';
		foreach ( $ex['paths'] as $re ) {
			$server[] = 'if ($uri ~ ' . self::quote( $re ) . ') { set $botctl_block 0; }';
		}
		foreach ( $ex['queries'] as $re ) {
			$server[] = 'if ($args ~ ' . self::quote( $re ) . ') { set $botctl_block 0; }';
		}
		$server[] = 'if ($botctl_allow_ip) { set $botctl_block 0; }';
		$server[] = 'if ($botctl_block) { return 403; }';

		return array(
			'http'   => implode( "\n", $http ),
			'server' => implode( "\n", $server ),
		);
	}

	/**
	 * Write (or remove) our block at the TOP of .htaccess.
	 *
	 * It must come before the WordPress block: WordPress rewrites to index.php with [L],
	 * after which rules further down are never reached for most URLs.
	 *
	 * @param array|null $s Settings (null = current); pass false-y rules to remove.
	 * @param bool       $remove Remove the block instead of writing it.
	 * @return true|WP_Error
	 */
	public static function write_htaccess( $s = null, $remove = false ) {
		$s    = null === $s ? Bot_Control_Settings::get() : $s;
		$path = self::htaccess_path();

		$exists   = file_exists( $path );
		$contents = $exists ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ( $exists && ! is_writable( $path ) ) || ( ! $exists && ! is_writable( dirname( $path ) ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			Bot_Control_Logger::error( 'ServerRules', '.htaccess is not writable', array( 'path' => $path ) );
			return new WP_Error( 'botctl_htaccess', __( '.htaccess is not writable. Copy the rules manually or fix the file permissions.', 'all-in-crawler-ai-agent-manager' ) );
		}

		$begin = '# BEGIN ' . self::MARKER;
		$end   = '# END ' . self::MARKER;
		$clean = $contents;
		foreach ( array( self::MARKER, self::LEGACY_MARKER ) as $marker ) {
			$pattern = '/' . preg_quote( '# BEGIN ' . $marker, '/' ) . '.*?' . preg_quote( '# END ' . $marker, '/' ) . "\\R*/s";
			$clean   = preg_replace( $pattern, '', $clean );
		}

		$lines = $remove ? array() : self::apache( $s );
		$new   = $lines ? $begin . "\n" . implode( "\n", $lines ) . "\n" . $end . "\n\n" . ltrim( $clean ) : ltrim( $clean );

		if ( $new === $contents ) {
			return true;
		}

		// Keep one backup of the previous version.
		if ( $exists ) {
			@copy( $path, $path . '.botctl-backup' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$written = file_put_contents( $path, $new, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written ) {
			Bot_Control_Logger::error( 'ServerRules', 'Writing .htaccess failed', array( 'path' => $path ) );
			return new WP_Error( 'botctl_htaccess', __( 'Writing .htaccess failed.', 'all-in-crawler-ai-agent-manager' ) );
		}

		Bot_Control_Logger::debug( 'ServerRules', $remove ? '.htaccess rules removed' : '.htaccess rules written', array( 'lines' => count( $lines ) ) );
		return true;
	}

	/**
	 * Are our rules currently in .htaccess?
	 *
	 * @return bool
	 */
	public static function htaccess_has_rules() {
		$path = self::htaccess_path();
		if ( ! file_exists( $path ) ) {
			return false;
		}
		$contents = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return false !== strpos( $contents, '# BEGIN ' . self::MARKER ) || false !== strpos( $contents, '# BEGIN ' . self::LEGACY_MARKER . "\n" );
	}

	/**
	 * Keep .htaccess in sync after settings change (when auto-sync is on).
	 *
	 * @param array $s New settings.
	 * @return void
	 */
	public static function maybe_sync( $s ) {
		if ( empty( $s['server_rules_auto'] ) || ! self::is_apache() ) {
			return;
		}
		$enabled = ! empty( $s['enabled'] ) && 'monitor' !== $s['mode'];
		$result  = self::write_htaccess( $s, ! $enabled );
		if ( is_wp_error( $result ) && function_exists( 'add_settings_error' ) ) {
			add_settings_error( 'botctl', 'botctl_htaccess', $result->get_error_message(), 'warning' );
		}
	}
}
