<?php
/**
 * The "Owner" role for the client.
 *
 * Kirk keeps the administrator account. The business owner signs in as an
 * Owner: everything an Editor can do (pages, posts, media, comments) plus the
 * "Your Business" screens. No plugins, themes, users, or Settings > Site Tools,
 * so the module code and the site's setup stay in Kirk's hands.
 *
 * Deliberately NOT given edit_theme_options: on block themes that opens the
 * whole Site Editor, which is the fastest way for an owner to break a header.
 * Menu and footer details come from Business info instead.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The capability every "Your Business" screen checks. */
const TMST_CAP = 'tmst_manage_business';

/** Bump when the role's capabilities change, so live sites pick it up. */
const TMST_ROLES_VERSION = '1';

/**
 * Create or refresh the Owner role and give administrators the business cap.
 */
function tmst_roles_sync() {
	$editor = get_role( 'editor' );
	$caps   = $editor ? $editor->capabilities : array(
		'read'         => true,
		'upload_files' => true,
		'edit_posts'   => true,
		'edit_pages'   => true,
	);

	/*
	 * Shops: an owner should be able to see orders and edit products. If
	 * WooCommerce is there, borrow the shop manager's store caps, minus the
	 * ones that reach site setup.
	 */
	$shop = get_role( 'shop_manager' );
	if ( $shop ) {
		foreach ( $shop->capabilities as $cap => $grant ) {
			if ( in_array( $cap, array( 'edit_theme_options', 'list_users', 'edit_users', 'promote_users' ), true ) ) {
				continue;
			}
			$caps[ $cap ] = $grant;
		}
	}

	$caps[ TMST_CAP ] = true;

	remove_role( 'tm_owner' );
	add_role( 'tm_owner', 'Owner', $caps );

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( TMST_CAP );
	}

	update_option( 'tmst_roles_version', TMST_ROLES_VERSION, false );
}

/**
 * Re-run the sync after an update changed the role, or after WooCommerce
 * arrived later than this plugin.
 */
function tmst_roles_maybe_sync() {
	$woo_now = get_role( 'shop_manager' ) ? 'w' : '';
	if ( get_option( 'tmst_roles_version' ) !== TMST_ROLES_VERSION . $woo_now ) {
		tmst_roles_sync();
		update_option( 'tmst_roles_version', TMST_ROLES_VERSION . $woo_now, false );
	}
}
add_action( 'admin_init', 'tmst_roles_maybe_sync' );
