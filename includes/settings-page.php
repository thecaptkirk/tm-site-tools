<?php
/**
 * Settings > Site Tools: Kirk's screen (administrators only).
 *
 * The module code goes here, plus the few settings an owner never needs to
 * see (verification codes, the support address, updates). Modules add their
 * own fields through the `tmst_settings_fields` filter, so a module that is
 * off adds nothing to this screen.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read one Site Tools setting.
 *
 * @param string $key     Field key.
 * @param string $default Fallback.
 * @return string
 */
function tmst_setting( $key, $default = '' ) {
	$all = get_option( 'tmst_settings', array() );
	return ( is_array( $all ) && isset( $all[ $key ] ) && '' !== $all[ $key ] ) ? (string) $all[ $key ] : (string) $default;
}

/**
 * Fields modules add. Each: key => [label, help, placeholder, module].
 *
 * @return array<string, array{label:string, help?:string, placeholder?:string, module:string}>
 */
function tmst_settings_fields() {
	return (array) apply_filters( 'tmst_settings_fields', array() );
}

/**
 * Add the screen under Settings.
 */
function tmst_settings_menu() {
	add_options_page( 'Site Tools', 'Site Tools', 'manage_options', 'tmst-site-tools', 'tmst_settings_render' );
}
add_action( 'admin_menu', 'tmst_settings_menu' );

/**
 * Draw the screen.
 */
function tmst_settings_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$code   = (string) get_option( 'tmst_module_code', '' );
	$status = tmst_module_status();
	$on     = tmst_enabled_modules();
	$fields = tmst_settings_fields();
	?>
	<div class="wrap">
		<h1>Site Tools</h1>
		<?php tmst_saved_notice(); ?>
		<p>Target Marketeer Site Tools <?php echo esc_html( TMST_VERSION ); ?> on <strong><?php echo esc_html( tmst_site_host() ); ?></strong>. Only administrators see this screen.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tmst_save_site_tools">
			<?php wp_nonce_field( 'tmst_save_site_tools' ); ?>

			<h2>Modules</h2>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Module</th><th>What it does</th><th>Status</th></tr></thead>
				<tbody>
				<?php foreach ( tmst_module_registry() as $key => $mod ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $mod['label'] ); ?></strong><br><code><?php echo esc_html( $key ); ?></code></td>
						<td><?php echo esc_html( $mod['about'] ); ?></td>
						<td>
							<?php
							if ( ! empty( $mod['always'] ) ) {
								echo 'On (every site)';
							} else {
								echo in_array( $key, $on, true ) ? '<strong>On</strong>' : 'Off';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Module code</h2>
			<?php if ( $status['ok'] ) : ?>
				<p style="color:#0a7d32"><strong>Code checked.</strong> Made for <?php echo esc_html( implode( ', ', $status['site'] ) ); ?>
				<?php echo $status['issued'] ? ' on ' . esc_html( tmst_date_label( $status['issued'] ) ?: $status['issued'] ) : ''; ?>
				<?php echo $status['note'] ? ' (' . esc_html( $status['note'] ) . ')' : ''; ?>.</p>
			<?php else : ?>
				<p style="color:#b32d2e"><?php echo esc_html( $status['error'] ); ?></p>
			<?php endif; ?>
			<p><textarea name="tmst_module_code" rows="4" class="large-text code" style="max-width:900px" spellcheck="false" placeholder="TMST1...."><?php echo esc_textarea( $code ); ?></textarea></p>
			<p class="description">Make one on Kirk's computer: <code>node client-plugin/tools/sign-modules.mjs sign --site <?php echo esc_html( tmst_site_host() ); ?> --modules notices,seo-basics,support-link</code></p>

			<?php if ( $fields ) : ?>
				<h2>Module settings</h2>
				<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $f ) : ?>
					<tr>
						<th scope="row"><label for="tmst-f-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f['label'] ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="tmst-f-<?php echo esc_attr( $key ); ?>" name="tmst_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( tmst_setting( $key ) ); ?>" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>">
							<?php if ( ! empty( $f['help'] ) ) : ?>
								<p class="description"><?php echo esc_html( $f['help'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</table>
			<?php endif; ?>

			<h2>Updates</h2>
			<p><label><input type="checkbox" name="tmst_updates" value="1" <?php checked( tmst_updates_on() ); ?>> Receive updates from Target Marketeer</label></p>
			<p class="description">Untick when a client leaves. Everything keeps working; the site just stays on the version it has.</p>

			<?php submit_button( 'Save' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Save the screen.
 */
function tmst_settings_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'tmst_save_site_tools' );

	$code = isset( $_POST['tmst_module_code'] ) ? preg_replace( '/[^A-Za-z0-9_.\-]/', '', wp_unslash( $_POST['tmst_module_code'] ) ) : '';
	update_option( 'tmst_module_code', $code, true );

	update_option( 'tmst_updates', empty( $_POST['tmst_updates'] ) ? '0' : '1', true );

	// Keep values for modules that are off, so switching one back on restores them.
	$stored = get_option( 'tmst_settings', array() );
	$stored = is_array( $stored ) ? $stored : array();
	$posted = isset( $_POST['tmst_settings'] ) && is_array( $_POST['tmst_settings'] ) ? wp_unslash( $_POST['tmst_settings'] ) : array();
	foreach ( tmst_settings_fields() as $key => $f ) {
		$raw = isset( $posted[ $key ] ) ? (string) $posted[ $key ] : '';
		/** Lets a module clean its own value (e.g. pull a token out of a pasted tag). */
		$stored[ $key ] = (string) apply_filters( 'tmst_clean_setting_' . $key, sanitize_text_field( $raw ), $raw );
	}
	update_option( 'tmst_settings', $stored, true );

	tmst_purge_page_caches();
	wp_safe_redirect( admin_url( 'options-general.php?page=tmst-site-tools&tmst=saved' ) );
	exit;
}
add_action( 'admin_post_tmst_save_site_tools', 'tmst_settings_save' );
