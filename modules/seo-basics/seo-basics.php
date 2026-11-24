<?php
/**
 * SEO basics: search engine verification, llms.txt, and sitemap tidy-up.
 *
 * Started from TM Core seo/search-console.php, seo/llms-txt.php, and
 * seo/sitemap-exclude.php, with everything Target Marketeer-specific taken out.
 * Kirk sets the verification codes in Settings > Site Tools; owners never see
 * any of this.
 *
 * VERIFICATION: the meta tags go on the home page only, which is where Google
 * and Bing look. Removing a code unverifies the property at their next check.
 *
 * LLMS.TXT: /llms.txt is a short Markdown summary for AI assistants, written
 * from Business info and the site's published pages, so it never needs hand
 * edits. If a real llms.txt file exists in the site root, the web server serves
 * that instead and this stays out of the way.
 *
 * SITEMAP: WordPress's own sitemap lists user profile pages, which hands out
 * login names. That list is removed. Site search results are kept out of Google.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fields on Settings > Site Tools.
 *
 * @param array $fields Fields so far.
 * @return array
 */
function tmst_seo_fields( $fields ) {
	$fields['google_verify'] = array(
		'label'       => 'Google Search Console code',
		'help'        => 'Paste the whole meta tag or just the code from Search Console > Settings > Ownership verification > HTML tag.',
		'placeholder' => '<meta name="google-site-verification" content="...">',
		'module'      => 'seo-basics',
	);
	$fields['bing_verify']   = array(
		'label'       => 'Bing Webmaster code',
		'help'        => 'Optional. Bing can also import the site straight from Search Console.',
		'placeholder' => '<meta name="msvalidate.01" content="...">',
		'module'      => 'seo-basics',
	);
	return $fields;
}
add_filter( 'tmst_settings_fields', 'tmst_seo_fields' );

/**
 * Keep only the code when a whole meta tag is pasted.
 *
 * @param string $clean Sanitized value (tags already stripped, so usually empty).
 * @param string $raw   As pasted.
 * @return string
 */
function tmst_seo_clean_token( $clean, $raw ) {
	if ( preg_match( '/content\s*=\s*["\']([^"\']+)["\']/i', (string) $raw, $m ) ) {
		$raw = $m[1];
	}
	return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $raw );
}
add_filter( 'tmst_clean_setting_google_verify', 'tmst_seo_clean_token', 10, 2 );
add_filter( 'tmst_clean_setting_bing_verify', 'tmst_seo_clean_token', 10, 2 );

/**
 * Print the verification tags on the home page.
 */
function tmst_seo_verify_tags() {
	if ( ! is_front_page() ) {
		return;
	}
	$google = tmst_setting( 'google_verify' );
	$bing   = tmst_setting( 'bing_verify' );
	if ( $google ) {
		echo '<meta name="google-site-verification" content="' . esc_attr( $google ) . '" />' . "\n";
	}
	if ( $bing ) {
		echo '<meta name="msvalidate.01" content="' . esc_attr( $bing ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'tmst_seo_verify_tags', 1 );

/* ---------------------------------------------------------------------------
 * LLMS.TXT
 * ------------------------------------------------------------------------ */

/**
 * The llms.txt body. Markdown, H1 first (PageSpeed checks for it).
 *
 * @return string
 */
function tmst_llms_body() {
	$b     = function_exists( 'tmst_business' ) ? tmst_business() : array();
	$name  = trim( (string) ( $b['name'] ?? '' ) ) ? $b['name'] : get_bloginfo( 'name' );
	$about = trim( (string) ( $b['about'] ?? '' ) ) ? $b['about'] : get_bloginfo( 'description' );
	$out   = '# ' . $name . "\n\n";
	if ( $about ) {
		$out .= '> ' . preg_replace( '/\s+/', ' ', $about ) . "\n\n";
	}

	$facts = array();
	if ( ! empty( $b['phone'] ) ) {
		$facts[] = 'Phone: ' . tmst_phone_label( $b['phone'] );
	}
	if ( ! empty( $b['email'] ) ) {
		$facts[] = 'Email: ' . $b['email'];
	}
	if ( function_exists( 'tmst_address_lines' ) && $b ) {
		$addr = tmst_address_lines( $b );
		if ( $addr ) {
			$facts[] = 'Address: ' . implode( ', ', $addr );
		}
	}
	if ( ! empty( $b['service_area'] ) ) {
		$facts[] = 'Service area: ' . $b['service_area'];
	}
	if ( ! empty( $b['hours'] ) ) {
		$days = array();
		foreach ( tmst_days() as $k => $label ) {
			$text = tmst_hours_label( $b['hours'][ $k ] );
			if ( '' !== $text ) {
				$days[] = $label . ' ' . $text;
			}
		}
		if ( $days ) {
			$facts[] = 'Hours: ' . implode( '; ', $days );
		}
	}
	if ( $facts ) {
		$out .= '- ' . implode( "\n- ", $facts ) . "\n\n";
	}

	$pages = get_pages(
		array(
			'sort_column' => 'menu_order,post_title',
			'post_status' => 'publish',
			'number'      => 40,
		)
	);
	$lines = array();
	foreach ( $pages as $p ) {
		if ( post_password_required( $p ) ) {
			continue;
		}
		$excerpt = has_excerpt( $p ) ? wp_strip_all_tags( get_the_excerpt( $p ) ) : '';
		$lines[] = '- [' . get_the_title( $p ) . '](' . get_permalink( $p ) . ')' . ( $excerpt ? ': ' . $excerpt : '' );
	}
	if ( $lines ) {
		$out .= "## Pages\n\n" . implode( "\n", $lines ) . "\n";
	}
	return $out;
}

/**
 * Answer /llms.txt. Cached until a page or the business info changes.
 */
function tmst_llms_serve() {
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
	$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $path !== $home . '/llms.txt' ) {
		return;
	}
	$body = get_transient( 'tmst_llms_body' );
	if ( ! is_string( $body ) ) {
		$body = tmst_llms_body();
		set_transient( 'tmst_llms_body', $body, DAY_IN_SECONDS );
	}
	status_header( 200 );
	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Cache-Control: public, max-age=3600' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text file.
	exit;
}
add_action( 'init', 'tmst_llms_serve', 1 );

/**
 * Forget the cached llms.txt.
 */
function tmst_llms_flush() {
	delete_transient( 'tmst_llms_body' );
}
add_action( 'tmst_business_saved', 'tmst_llms_flush' );
add_action( 'save_post_page', 'tmst_llms_flush' );
add_action( 'deleted_post', 'tmst_llms_flush' );

/* ---------------------------------------------------------------------------
 * SITEMAP AND SEARCH TIDY-UP
 * ------------------------------------------------------------------------ */

/**
 * Drop the users list from WordPress's own sitemap.
 *
 * @param WP_Sitemaps_Provider|false $provider Provider.
 * @param string                     $name     Provider name.
 * @return WP_Sitemaps_Provider|false
 */
function tmst_sitemap_no_users( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'tmst_sitemap_no_users', 10, 2 );

/**
 * Keep site search results out of Google.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function tmst_noindex_search( $robots ) {
	if ( is_search() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'tmst_noindex_search' );
