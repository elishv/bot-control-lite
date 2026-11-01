<?php
/**
 * llms.txt generator (https://llmstxt.org).
 *
 * Serves a virtual /llms.txt: a Markdown overview of the site that helps AI
 * assistants understand what the site is about and which pages matter.
 * Pages marked "noindex" in Yoast SEO, Rank Math or SEOPress are left out.
 *
 * @package BotControl
 */

defined( 'ABSPATH' ) || exit;

/**
 * llms.txt.
 */
final class Bot_Control_Llms {

	const CACHE = 'botctl_llms_txt';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 1 );
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush' ) );
		add_action( 'botctl_settings_updated', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Clear the cached output.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::CACHE );
	}

	/**
	 * Is a physical llms.txt shadowing the virtual one?
	 *
	 * @return bool
	 */
	public static function physical_file_exists() {
		return file_exists( ABSPATH . 'llms.txt' );
	}

	/**
	 * Serve /llms.txt.
	 *
	 * @return void
	 */
	public static function maybe_serve() {
		$s = Bot_Control_Settings::get();
		if ( empty( $s['llms_enabled'] ) ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( $path !== $home . '/llms.txt' ) {
			return;
		}

		Bot_Control_Logger::debug( 'Llms', 'Serving llms.txt' );

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=3600' );
		echo self::content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text document.
		exit;
	}

	/**
	 * Get (cached) content.
	 *
	 * @param bool $use_cache Use the transient.
	 * @return string
	 */
	public static function content( $use_cache = true ) {
		if ( $use_cache ) {
			$cached = get_transient( self::CACHE );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}
		$out = self::build( Bot_Control_Settings::get() );
		set_transient( self::CACHE, $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Clean text for a single Markdown line.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function line( $text ) {
		$text = wp_strip_all_tags( strip_shortcodes( (string) $text ) );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Is the post marked noindex by a popular SEO plugin?
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function is_noindex( $post_id ) {
		if ( '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			return true;
		}
		$rank_math = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $rank_math ) && in_array( 'noindex', $rank_math, true ) ) {
			return true;
		}
		if ( 'noindex' === (string) get_post_meta( $post_id, '_seopress_robots_index', true ) || 'yes' === (string) get_post_meta( $post_id, '_seopress_robots_index', true ) ) {
			return true;
		}
		/**
		 * Filter whether a post is excluded from llms.txt.
		 *
		 * @param bool $noindex Excluded.
		 * @param int  $post_id Post id.
		 */
		return (bool) apply_filters( 'botctl_llms_exclude_post', false, $post_id );
	}

	/**
	 * Build the document.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function build( array $s ) {
		$lines   = array();
		$lines[] = '# ' . self::line( get_bloginfo( 'name' ) );
		$lines[] = '';

		$summary = self::line( '' !== trim( (string) $s['llms_summary'] ) ? $s['llms_summary'] : get_bloginfo( 'description' ) );
		if ( '' !== $summary ) {
			$lines[] = '> ' . $summary;
			$lines[] = '';
		}

		$details = trim( wp_strip_all_tags( (string) $s['llms_details'] ) );
		if ( '' !== $details ) {
			$lines[] = $details;
			$lines[] = '';
		}

		$limit = max( 1, min( 500, (int) $s['llms_limit'] ) );
		$front = (int) get_option( 'page_on_front' );

		foreach ( (array) $s['llms_post_types'] as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object || empty( $object->public ) ) {
				continue;
			}
			$query = new WP_Query(
				array(
					'post_type'              => $type,
					'post_status'            => 'publish',
					'posts_per_page'         => $limit * 2, // Headroom for noindex / protected posts.
					'has_password'           => false,
					'orderby'                => 'page' === $type ? array( 'menu_order' => 'ASC', 'title' => 'ASC' ) : 'date',
					'order'                  => 'page' === $type ? 'ASC' : 'DESC',
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_term_cache' => false,
				)
			);

			$items = array();
			foreach ( $query->posts as $post ) {
				if ( count( $items ) >= $limit ) {
					break;
				}
				if ( self::is_noindex( $post->ID ) ) {
					continue;
				}
				$title = self::line( get_the_title( $post ) );
				if ( '' === $title ) {
					continue;
				}
				$desc = self::line( has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 30, '…' ) );
				if ( $post->ID === $front ) {
					$title .= ' (home page)';
				}
				$items[] = '- [' . str_replace( array( '[', ']' ), array( '(', ')' ), $title ) . '](' . get_permalink( $post ) . ')' . ( '' !== $desc ? ': ' . $desc : '' );
			}

			if ( $items ) {
				$lines[] = '## ' . self::line( $object->labels->name );
				$lines[] = '';
				$lines   = array_merge( $lines, $items );
				$lines[] = '';
			}
		}

		// Extra links: "Title | URL | description" per line.
		$extra = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $s['llms_extra'] ) as $row ) {
			$parts = array_map( 'trim', explode( '|', $row ) );
			if ( count( $parts ) < 2 || '' === $parts[0] || ! wp_http_validate_url( $parts[1] ) ) {
				continue;
			}
			$extra[] = '- [' . self::line( $parts[0] ) . '](' . esc_url_raw( $parts[1] ) . ')' . ( ! empty( $parts[2] ) ? ': ' . self::line( $parts[2] ) : '' );
		}
		if ( $extra ) {
			$lines[] = '## ' . self::line( '' !== trim( (string) $s['llms_extra_title'] ) ? $s['llms_extra_title'] : 'Resources' );
			$lines[] = '';
			$lines   = array_merge( $lines, $extra );
			$lines[] = '';
		}

		/**
		 * Filter the final llms.txt content.
		 *
		 * @param string $content Markdown.
		 * @param array  $s       Settings.
		 */
		return (string) apply_filters( 'botctl_llms_txt', rtrim( implode( "\n", $lines ) ) . "\n", $s );
	}
}
