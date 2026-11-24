<?php
/**
 * Plugin Name:       Target Marketeer Site Tools
 * Plugin URI:        https://targetmarketeer.com
 * Description:       Your business details, notices, and site care tools, kept in one place by Target Marketeer.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Target Marketeer
 * License:           GPL-2.0-or-later
 * Text Domain:       tm-site-tools
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS IS
 *
 * The one plugin on every Target Marketeer CLIENT website. It is not TM Core:
 * TM Core runs targetmarketeer.com only and nothing from it (quotes, Square,
 * hubs, Athos) belongs here. Plan: docs/tm-site-core-plan.md in the Hub repo.
 *
 * Features are MODULES. Kirk decides which ones a client gets with a signed
 * "module code" (includes/modules.php). A module that is off is never
 * included, so it costs nothing on that site.
 *
 * WHO CAN DO WHAT
 * - Administrator (Kirk): everything, including Settings > Site Tools, where
 *   the module code is pasted.
 * - Owner (the client, includes/roles.php): pages, posts, media, and the
 *   "Your Business" screens. No plugins, no themes, no module switches.
 *
 * WHEN A CLIENT LEAVES (Kirk 2026-11-23)
 * Modules keep working as they are. Kirk switches "Receive updates" off in
 * Settings > Site Tools and hands over the site. Modules that depend on Kirk's
 * own service (care report, chatbot) stop on their own because that service
 * stops, not because the code locks.
 *
 * ---------------------------------------------------------------------------
 * CONVENTIONS
 * - Functions are prefixed tmst_, options tmst_, constants TMST_.
 * - Modules load from tmst_module_registry() below. Adding a folder is never
 *   enough to put code live; it has to be named there.
 * - Copy for owners is plain US English, Oxford comma, no em dash sentence
 *   breaks, and never says the work is built with AI.
 * - Shipping: bump Version above, TMST_VERSION below, and "Stable tag" in
 *   readme.txt, then `php -l` every changed file.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TMST_VERSION', '0.1.0' );
define( 'TMST_FILE', __FILE__ );
define( 'TMST_PATH', plugin_dir_path( __FILE__ ) );
define( 'TMST_URL', plugin_dir_url( __FILE__ ) );

/**
 * Every module this plugin knows about.
 *
 * 'always' modules load on every site whatever the module code says: they are
 * the base every client gets. The rest load only when the signed code names
 * them. Keys are what goes in the code, so never rename one.
 *
 * @return array<string, array{file:string, label:string, about:string, always?:bool}>
 */
function tmst_module_registry() {
	return array(
		'business-info' => array(
			'file'   => 'modules/business-info/business-info.php',
			'label'  => 'Business info',
			'about'  => 'Hours, holiday hours, phone, address, and service area in one place. Feeds the footer, contact page, and Google business markup.',
			'always' => true,
		),
		'notices'       => array(
			'file'  => 'modules/notices/notices.php',
			'label' => 'Notices',
			'about' => 'A holiday or "closed today" banner with an end date. It removes itself.',
		),
		'seo-basics'    => array(
			'file'  => 'modules/seo-basics/seo-basics.php',
			'label' => 'SEO basics',
			'about' => 'Search Console and Bing verification, llms.txt, and sitemap tidy-up.',
		),
		'support-link'  => array(
			'file'  => 'modules/support-link/support-link.php',
			'label' => 'Support link',
			'about' => '"Need help? Message Target Marketeer" in the owner\'s dashboard and toolbar.',
		),
	);
}

require_once TMST_PATH . 'includes/helpers.php';
require_once TMST_PATH . 'includes/roles.php';
require_once TMST_PATH . 'includes/modules.php';
require_once TMST_PATH . 'includes/self-update.php';
require_once TMST_PATH . 'includes/settings-page.php';

foreach ( tmst_enabled_modules() as $tmst_key ) {
	$tmst_registry = tmst_module_registry();
	$tmst_path     = TMST_PATH . $tmst_registry[ $tmst_key ]['file'];
	if ( is_readable( $tmst_path ) ) {
		require_once $tmst_path;
	}
}
unset( $tmst_key, $tmst_registry, $tmst_path );

register_activation_hook( __FILE__, 'tmst_roles_sync' );
