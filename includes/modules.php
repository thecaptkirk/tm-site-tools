<?php
/**
 * Which modules are on: a code Kirk signs, checked here.
 *
 * WHY A SIGNED CODE (Kirk asked "what's stopping them?", 2026-11-23)
 * A checkbox in the database can be flipped by anyone with database access.
 * A signed code cannot: Kirk makes it with his private key (client-plugin/
 * tools/sign-modules.mjs, key kept on his computer, never in this repo), and
 * the plugin checks it with the public key below. Change one letter of the
 * module list and the check fails.
 *
 * The code is also tied to one site's address, so a code bought for one
 * business does not switch modules on for another.
 *
 * FORMAT
 *     TMST1.<payload>.<signature>
 * payload   = base64url of JSON {"v":1,"site":"example.com","modules":[...],"issued":"2026-11-23","note":"..."}
 *             "site" may also be a list, for a site with a staging copy.
 * signature = base64url of an Ed25519 signature over "TMST1.<payload>".
 *
 * Ed25519 is checked with sodium_crypto_sign_verify_detached(). WordPress
 * ships sodium_compat, so this works even where PHP's sodium extension is off.
 *
 * WHAT A FAILED CHECK DOES
 * Only the 'always' modules load, and administrators see a notice. Owners see
 * nothing alarming: their business info keeps working.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kirk's public signing key (base64, 32 bytes). Public by design; the private
 * half never leaves his computer. Replacing it means re-signing every site.
 */
const TMST_PUBLIC_KEY = 'JnXHabHwiYKABTuIEpjqJJG5cHDLpH70asoO44sHiYM=';

/**
 * Check a module code. Never throws.
 *
 * @param string $code The pasted code.
 * @return array{ok:bool, error:string, modules:string[], site:string[], issued:string, note:string}
 */
function tmst_verify_module_code( $code ) {
	$out  = array(
		'ok'      => false,
		'error'   => '',
		'modules' => array(),
		'site'    => array(),
		'issued'  => '',
		'note'    => '',
	);
	$code = trim( preg_replace( '/\s+/', '', (string) $code ) );

	if ( '' === $code ) {
		$out['error'] = 'No module code yet. Only the base modules are on.';
		return $out;
	}

	$parts = explode( '.', $code );
	if ( 3 !== count( $parts ) || 'TMST1' !== $parts[0] ) {
		$out['error'] = 'That does not look like a module code. Copy the whole line, starting with TMST1.';
		return $out;
	}

	$key = base64_decode( TMST_PUBLIC_KEY, true );
	$sig = tmst_b64url_decode( $parts[2] );
	if ( false === $key || 32 !== strlen( $key ) || false === $sig || 64 !== strlen( $sig ) ) {
		$out['error'] = 'The code is damaged, or this copy of the plugin has no signing key.';
		return $out;
	}

	if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) && is_readable( ABSPATH . WPINC . '/sodium_compat/autoload.php' ) ) {
		require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
	}

	try {
		$valid = sodium_crypto_sign_verify_detached( $sig, $parts[0] . '.' . $parts[1], $key );
	} catch ( \Throwable $e ) {
		$valid = false;
	}
	if ( ! $valid ) {
		$out['error'] = 'The code\'s signature does not match. It was changed, or it was not made by Target Marketeer.';
		return $out;
	}

	$json    = tmst_b64url_decode( $parts[1] );
	$payload = false === $json ? null : json_decode( $json, true );
	if ( ! is_array( $payload ) || 1 !== (int) ( $payload['v'] ?? 0 ) ) {
		$out['error'] = 'The code is signed but its contents are unreadable. Make a new one.';
		return $out;
	}

	$sites = array_map( 'strtolower', array_map( 'strval', (array) ( $payload['site'] ?? array() ) ) );
	$sites = array_map(
		static function ( $s ) {
			return preg_replace( '/^www\./', '', $s );
		},
		$sites
	);

	$out['site']    = $sites;
	$out['issued']  = (string) ( $payload['issued'] ?? '' );
	$out['note']    = (string) ( $payload['note'] ?? '' );
	$out['modules'] = array_values( array_intersect( array_map( 'strval', (array) ( $payload['modules'] ?? array() ) ), array_keys( tmst_module_registry() ) ) );

	if ( ! in_array( tmst_site_host(), $sites, true ) ) {
		$out['error'] = 'This code was made for ' . implode( ', ', $sites ) . ', not ' . tmst_site_host() . '.';
		return $out;
	}

	$out['ok'] = true;
	return $out;
}

/**
 * The stored code's check result, worked out once per request.
 *
 * @return array See tmst_verify_module_code().
 */
function tmst_module_status() {
	static $status = null;
	if ( null === $status ) {
		$status = tmst_verify_module_code( (string) get_option( 'tmst_module_code', '' ) );
	}
	return $status;
}

/**
 * Keys of the modules to load on this request, in registry order.
 *
 * @return string[]
 */
function tmst_enabled_modules() {
	$status = tmst_module_status();
	$on     = array();
	foreach ( tmst_module_registry() as $key => $mod ) {
		if ( ! empty( $mod['always'] ) || ( $status['ok'] && in_array( $key, $status['modules'], true ) ) ) {
			$on[] = $key;
		}
	}
	return $on;
}

/**
 * Is one module on?
 *
 * @param string $key Module key.
 * @return bool
 */
function tmst_module_on( $key ) {
	return in_array( $key, tmst_enabled_modules(), true );
}

/**
 * Decode base64url (no padding).
 *
 * @param string $s Input.
 * @return string|false
 */
function tmst_b64url_decode( $s ) {
	$s   = strtr( (string) $s, '-_', '+/' );
	$pad = strlen( $s ) % 4;
	if ( $pad ) {
		$s .= str_repeat( '=', 4 - $pad );
	}
	return base64_decode( $s, true );
}

/**
 * Tell administrators when a stored code fails. Owners never see this.
 */
function tmst_module_code_notice() {
	if ( ! current_user_can( 'manage_options' ) || '' === (string) get_option( 'tmst_module_code', '' ) ) {
		return;
	}
	$status = tmst_module_status();
	if ( $status['ok'] ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p><strong>Site Tools:</strong> %s Only the base modules are on. <a href="%s">Open Site Tools</a></p></div>',
		esc_html( $status['error'] ),
		esc_url( admin_url( 'options-general.php?page=tmst-site-tools' ) )
	);
}
add_action( 'admin_notices', 'tmst_module_code_notice' );
