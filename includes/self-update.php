<?php
/**
 * Update this plugin from its GitHub repository. Copied from TM Core
 * includes/core/self-update.php (2026-09-20 design), minus the Elementor
 * parts, which client sites do not need.
 *
 * The SITE asks GitHub for updates; nothing reaches into the server. It is
 * WordPress's own plugin update path pointed at a different source, so the
 * "Check for updates" link, maintenance mode, and rollback on failure are all
 * core behavior.
 *
 * PUBLIC REPOSITORY, SO NO TOKEN ON CLIENT SITES
 * The code is GPL and unlocks nothing by itself (modules need Kirk's signed
 * code, paid modules need his service), so the repository can be public. Then
 * client sites need no GitHub token in wp-config.php. If the repository is
 * ever made private, add to each site:
 *     define( 'TMST_GH_TOKEN', 'github_pat_...' ); // fine-grained, Contents: read, this repo only
 *
 * Optional instant updates while working on a site:
 *     define( 'TMST_DEPLOY_SECRET', '<long random>' );
 *     POST https://example.com/wp-json/tm-site-tools/v1/deploy
 *     header: X-TMST-Deploy-Secret: <secret>
 *
 * VERSIONS COME FROM THE PLUGIN HEADER, SO BUMP IT. Push without bumping
 * Version and nothing updates, silently.
 *
 * WHEN A CLIENT LEAVES, untick "Receive updates" in Settings > Site Tools. The
 * site keeps the version it has and stops checking.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The repository this plugin updates from. */
const TMST_GH_REPO = 'https://github.com/thecaptkirk/tm-site-tools';

/** The branch releases are cut from. */
const TMST_GH_BRANCH = 'main';

/** The plugin's folder on client sites. Never rename: WordPress keys plugins by folder. */
const TMST_SLUG = 'tm-site-tools';

/**
 * Are updates switched on for this site?
 *
 * @return bool
 */
function tmst_updates_on() {
	return '0' !== (string) get_option( 'tmst_updates', '1' );
}

/**
 * Wire up the update checker. On `init`, so a broken library cannot take the
 * site down before WordPress can show an error.
 */
function tmst_selfupdate_boot() {
	if ( ! tmst_updates_on() ) {
		return;
	}
	$loader = TMST_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
	if ( ! is_readable( $loader ) ) {
		return;
	}
	require_once $loader;

	if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
		return;
	}

	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		TMST_GH_REPO,
		TMST_FILE,
		TMST_SLUG
	);

	// Kept so the deploy endpoint can force a fresh check (see TM Core's notes).
	$GLOBALS['tmst_update_checker'] = $checker;

	if ( defined( 'TMST_GH_TOKEN' ) && TMST_GH_TOKEN ) {
		$checker->setAuthentication( TMST_GH_TOKEN );
	}

	// Track the branch; the Version header decides whether an update exists.
	$checker->setBranch( TMST_GH_BRANCH );
}
add_action( 'init', 'tmst_selfupdate_boot' );

/**
 * Register the deploy endpoint, only when a secret is configured.
 */
function tmst_selfupdate_routes() {
	if ( ! defined( 'TMST_DEPLOY_SECRET' ) || ! TMST_DEPLOY_SECRET || ! tmst_updates_on() ) {
		return;
	}
	register_rest_route(
		'tm-site-tools/v1',
		'/deploy',
		array(
			'methods'             => 'POST',
			'callback'            => 'tmst_selfupdate_handle',
			'permission_callback' => 'tmst_selfupdate_authorized',
		)
	);
}
add_action( 'rest_api_init', 'tmst_selfupdate_routes' );

/**
 * Is this request carrying the shared secret?
 *
 * @param WP_REST_Request $request Request.
 * @return bool
 */
function tmst_selfupdate_authorized( $request ) {
	$sent = (string) $request->get_header( 'x-tmst-deploy-secret' );
	return '' !== $sent && hash_equals( (string) TMST_DEPLOY_SECRET, $sent );
}

/**
 * Force an update check, install what it finds, and switch the plugin back on.
 *
 * WordPress deactivates a plugin before swapping its files, and only the admin
 * update screen switches it back on. From a REST request it would stay off, so
 * this reactivates it (silently, like core) and reports the result.
 *
 * @return WP_REST_Response
 */
function tmst_selfupdate_handle() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';

	$slug_file  = plugin_basename( TMST_FILE );
	$before     = tmst_selfupdate_installed_version();
	$was_active = is_plugin_active( $slug_file );

	if ( isset( $GLOBALS['tmst_update_checker'] ) && is_object( $GLOBALS['tmst_update_checker'] ) ) {
		$GLOBALS['tmst_update_checker']->checkForUpdates();
	}
	delete_site_transient( 'update_plugins' );
	wp_clean_plugins_cache( true );
	wp_update_plugins();

	$updates = get_site_transient( 'update_plugins' );
	if ( empty( $updates->response[ $slug_file ] ) ) {
		return new WP_REST_Response(
			array(
				'updated' => false,
				'version' => $before,
				'message' => 'Already current.',
			),
			200
		);
	}

	$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
	$result   = $upgrader->upgrade( $slug_file );

	if ( is_wp_error( $result ) || false === $result ) {
		return new WP_REST_Response(
			array(
				'updated' => false,
				'version' => $before,
				'message' => is_wp_error( $result ) ? $result->get_error_message() : 'The upgrader declined to run. Check file permissions.',
			),
			500
		);
	}

	$reactivated = null;
	if ( $was_active && ! is_plugin_active( $slug_file ) ) {
		$activation  = activate_plugin( $slug_file, '', false, true );
		$reactivated = is_wp_error( $activation ) ? $activation->get_error_message() : true;
	}

	return new WP_REST_Response(
		array(
			'updated'     => true,
			'from'        => $before,
			'version'     => tmst_selfupdate_installed_version(),
			'active'      => is_plugin_active( $slug_file ),
			'reactivated' => $reactivated,
		),
		200
	);
}

/**
 * The version on disk, read fresh.
 *
 * @return string
 */
function tmst_selfupdate_installed_version() {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$data = get_plugin_data( TMST_FILE, false, false );
	return isset( $data['Version'] ) ? (string) $data['Version'] : 'unknown';
}
