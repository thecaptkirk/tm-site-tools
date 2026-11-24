<?php
/**
 * Small shared helpers used by the frame and by modules.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This site's host name, lowercase, without "www." or a port.
 *
 * The module code is tied to this value, so a code made for one client cannot
 * be pasted into another client's site.
 *
 * @return string
 */
function tmst_site_host() {
	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	return preg_replace( '/^www\./', '', $host );
}

/**
 * "09:00" -> "9:00 AM". Empty or bad input comes back as ''.
 *
 * @param string $hhmm 24-hour time from an <input type="time">.
 * @return string
 */
function tmst_time_label( $hhmm ) {
	if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', (string) $hhmm, $m ) ) {
		return '';
	}
	$h      = (int) $m[1];
	$suffix = $h >= 12 ? 'PM' : 'AM';
	$h12    = $h % 12;
	if ( 0 === $h12 ) {
		$h12 = 12;
	}
	return $h12 . ':' . $m[2] . ' ' . $suffix;
}

/**
 * A 24-hour time if valid, else ''.
 *
 * @param mixed $value Raw input.
 * @return string
 */
function tmst_clean_time( $value ) {
	$value = trim( (string) $value );
	return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
}

/**
 * A Y-m-d date if valid, else ''.
 *
 * @param mixed $value Raw input.
 * @return string
 */
function tmst_clean_date( $value ) {
	$value = trim( (string) $value );
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
		return '';
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
}

/**
 * "2026-12-25" -> "December 25, 2026" (US style).
 *
 * @param string $ymd Date.
 * @return string
 */
function tmst_date_label( $ymd ) {
	$ymd = tmst_clean_date( $ymd );
	if ( '' === $ymd ) {
		return '';
	}
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, wp_timezone() );
	return $d ? $d->format( 'F j, Y' ) : '';
}

/**
 * Today's date in the site's own time zone.
 *
 * @return string Y-m-d.
 */
function tmst_today() {
	return wp_date( 'Y-m-d' );
}

/**
 * Clear the page cache so a change the owner just saved shows up right away.
 *
 * Client sites use whatever cache their hosting gives them, so this calls each
 * common one only if it is there. Missing caches are skipped quietly.
 */
function tmst_purge_page_caches() {
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain(); // WP Rocket.
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all(); // W3 Total Cache.
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache(); // WP Super Cache.
	}
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache(); // SiteGround.
	}
	if ( has_action( 'wpfc_clear_all_cache' ) ) {
		do_action( 'wpfc_clear_all_cache' ); // WP Fastest Cache.
	}

	// Jetpack Boost: the call its own Clear Cache button makes (same as TM Core).
	$boost = '\Automattic\Jetpack_Boost\Modules\Optimizations\Page_Cache\Pre_WordPress\Boost_Cache';
	if ( class_exists( $boost ) ) {
		try {
			( new $boost() )->delete_recursive( home_url() );
		} catch ( \Throwable $e ) {
			unset( $e ); // A failed purge must never break the save that triggered it.
		}
	}

	do_action( 'litespeed_purge_all' );    // LiteSpeed Cache.
	do_action( 'breeze_clear_all_cache' ); // Breeze (Cloudways).

	/**
	 * Lets a site-specific cache join in.
	 */
	do_action( 'tmst_purge_page_caches' );
}

/**
 * Print a "Saved." or error notice after a redirect back to one of our screens.
 */
function tmst_saved_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$state = isset( $_GET['tmst'] ) ? sanitize_key( wp_unslash( $_GET['tmst'] ) ) : '';
	if ( 'saved' === $state ) {
		echo '<div class="notice notice-success is-dismissible"><p>Saved. Your website is up to date.</p></div>';
	}
}
