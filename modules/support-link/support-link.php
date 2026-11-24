<?php
/**
 * Support link: "Need help? Message Target Marketeer" where the owner works.
 *
 * Two places: a "Need help?" item in the top toolbar (front end and admin),
 * and a dashboard box. Both open a message, never a phone call: everything can
 * be done by message (memory: avoid-phone-calls). Kirk can point it at a
 * different address in Settings > Site Tools.
 *
 * The link carries only this site's address (?site=example.com) so Kirk knows
 * which client wrote. Never a name, email, or anything personal.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where the link goes when Kirk has not set one. */
const TMST_SUPPORT_DEFAULT = 'https://targetmarketeer.com/contact/';

/**
 * Field on Settings > Site Tools.
 *
 * @param array $fields Fields so far.
 * @return array
 */
function tmst_support_fields( $fields ) {
	$fields['support_url'] = array(
		'label'       => 'Support link address',
		'help'        => 'Where "Need help?" goes. Leave empty for ' . TMST_SUPPORT_DEFAULT,
		'placeholder' => TMST_SUPPORT_DEFAULT,
		'module'      => 'support-link',
	);
	return $fields;
}
add_filter( 'tmst_settings_fields', 'tmst_support_fields' );

/**
 * Keep only a web address.
 *
 * @param string $clean Sanitized value.
 * @return string
 */
function tmst_support_clean_url( $clean ) {
	return esc_url_raw( trim( (string) $clean ), array( 'https', 'http', 'mailto' ) );
}
add_filter( 'tmst_clean_setting_support_url', 'tmst_support_clean_url' );

/**
 * The support address, with this site's address added.
 *
 * @return string
 */
function tmst_support_url() {
	$url = tmst_setting( 'support_url', TMST_SUPPORT_DEFAULT );
	return 0 === strpos( $url, 'mailto:' ) ? $url : add_query_arg( 'site', rawurlencode( tmst_site_host() ), $url );
}

/**
 * Should this person see the link? Anyone who can edit content.
 *
 * @return bool
 */
function tmst_support_visible() {
	return is_user_logged_in() && current_user_can( 'edit_posts' );
}

/**
 * Toolbar item.
 *
 * @param WP_Admin_Bar $bar Toolbar.
 */
function tmst_support_toolbar( $bar ) {
	if ( ! tmst_support_visible() ) {
		return;
	}
	$bar->add_node(
		array(
			'id'     => 'tmst-support',
			'parent' => 'top-secondary',
			'title'  => '<span class="ab-icon dashicons dashicons-format-chat" style="top:2px" aria-hidden="true"></span><span class="ab-label">Need help?</span>',
			'href'   => tmst_support_url(),
			'meta'   => array(
				'target' => '_blank',
				'rel'    => 'noopener',
				'title'  => 'Message Target Marketeer',
			),
		)
	);
}
add_action( 'admin_bar_menu', 'tmst_support_toolbar', 5 );

/**
 * Dashboard box, placed first.
 */
function tmst_support_dashboard() {
	if ( ! tmst_support_visible() ) {
		return;
	}
	wp_add_dashboard_widget( 'tmst_support', 'Need a hand?', 'tmst_support_dashboard_render', null, null, 'normal', 'high' );
}
add_action( 'wp_dashboard_setup', 'tmst_support_dashboard' );

/**
 * Dashboard box contents.
 */
function tmst_support_dashboard_render() {
	?>
	<p>Want something changed on your website, or not sure how to do it? Send us a message and we will take care of it.</p>
	<p><a class="button button-primary" href="<?php echo esc_url( tmst_support_url() ); ?>" target="_blank" rel="noopener">Message Target Marketeer</a></p>
	<?php if ( current_user_can( TMST_CAP ) ) : ?>
		<p style="margin-top:12px">Quick changes you can make yourself:
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tmst-business' ) ); ?>">hours and contact details</a><?php if ( function_exists( 'tmst_notice' ) ) : ?>,
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tmst-notices' ) ); ?>">a holiday or closing notice</a><?php endif; ?>.
		</p>
	<?php endif; ?>
	<?php
}
