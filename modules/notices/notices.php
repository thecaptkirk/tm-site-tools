<?php
/**
 * Notices: a banner across the top of the site with an end date. It removes
 * itself, so a "Closed for Christmas" note is never still up in February.
 *
 * Owner steps: Your Business > Notices, pick a ready-made message or write one,
 * set the last day, Save. That is the whole job.
 *
 * PAGE CACHES
 * A cached page would keep showing the banner after it ends (or miss it when
 * it starts). So: saving clears the page cache; a one-off WordPress event
 * clears it again when the banner starts and right after it ends; and the
 * banner carries its end time, so a small script hides it even on a page that
 * was cached before the end.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The stored notice, with every key present.
 *
 * @return array{message:string, link_text:string, link_url:string, starts:string, ends:string, tone:string}
 */
function tmst_notice() {
	$n = get_option( 'tmst_notice', array() );
	$n = is_array( $n ) ? $n : array();
	return array_merge(
		array(
			'message'   => '',
			'link_text' => '',
			'link_url'  => '',
			'starts'    => '',
			'ends'      => '',
			'tone'      => 'info',
			'removed'   => '',
		),
		$n
	);
}

/**
 * Unix times the notice starts and stops showing (site time zone).
 *
 * @param array $n Notice.
 * @return array{0:int, 1:int}|null
 */
function tmst_notice_window( $n ) {
	if ( '' === trim( $n['message'] ) || '' === tmst_clean_date( $n['ends'] ) ) {
		return null;
	}
	$tz    = wp_timezone();
	$start = tmst_clean_date( $n['starts'] ) ? new DateTimeImmutable( $n['starts'] . ' 00:00:00', $tz ) : null;
	$end   = new DateTimeImmutable( $n['ends'] . ' 23:59:59', $tz );
	return array( $start ? $start->getTimestamp() : 0, $end->getTimestamp() );
}

/**
 * Is the notice showing right now?
 *
 * @return bool
 */
function tmst_notice_live() {
	$w = tmst_notice_window( tmst_notice() );
	return $w && time() >= $w[0] && time() <= $w[1];
}

/**
 * Ready-made messages for the owner to start from.
 *
 * @return array<string, string>
 */
function tmst_notice_presets() {
	return array(
		'Closed for a holiday' => 'We are closed for the holiday and will reopen on [day]. Thank you, and happy holidays!',
		'Holiday hours'        => 'Holiday hours: we are open [hours] on [day].',
		'Closed today'         => 'We are closed today. We will be back tomorrow at our usual time.',
		'Weather closing'      => 'We are closed today because of the weather. Stay safe, and we will see you soon.',
		'Special or event'     => 'This week only: [your offer or event].',
	);
}

/**
 * Add Your Business > Notices.
 */
function tmst_notices_menu() {
	add_submenu_page( 'tmst-business', 'Notices', 'Notices', TMST_CAP, 'tmst-notices', 'tmst_notices_render' );
}
add_action( 'admin_menu', 'tmst_notices_menu', 20 );

/**
 * Draw the Notices screen.
 */
function tmst_notices_render() {
	if ( ! current_user_can( TMST_CAP ) ) {
		return;
	}
	$n   = tmst_notice();
	$w   = tmst_notice_window( $n );
	$now = time();
	?>
	<div class="wrap">
		<h1>Notices</h1>
		<?php tmst_saved_notice(); ?>

		<?php if ( $w && $now > $w[1] ) : ?>
			<div class="notice notice-info inline"><p>
				<?php if ( ! empty( $n['removed'] ) ) : ?>
					You took your last notice down on <?php echo esc_html( tmst_date_label( $n['removed'] ) ); ?>.
				<?php else : ?>
					Your last notice ended on <?php echo esc_html( tmst_date_label( $n['ends'] ) ); ?>.
				<?php endif; ?>
				It is no longer showing. Write a new one below.</p></div>
		<?php elseif ( $w && $now < $w[0] ) : ?>
			<div class="notice notice-info inline"><p>Your notice will start showing on <?php echo esc_html( tmst_date_label( $n['starts'] ) ); ?>.</p></div>
		<?php elseif ( $w ) : ?>
			<div class="notice notice-success inline"><p><strong>Showing now</strong> on every page, until the end of <?php echo esc_html( tmst_date_label( $n['ends'] ) ); ?>.</p></div>
		<?php endif; ?>

		<p>A short message across the top of your website. Set the last day and it takes itself down.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tmst_save_notice">
			<?php wp_nonce_field( 'tmst_save_notice' ); ?>

			<p><strong>Start from:</strong>
			<?php foreach ( tmst_notice_presets() as $label => $text ) : ?>
				<button type="button" class="button tmst-preset" data-text="<?php echo esc_attr( $text ); ?>"><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
			</p>

			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="tmst-msg">Message</label></th>
					<td><textarea id="tmst-msg" name="n[message]" rows="2" class="large-text" style="max-width:600px" maxlength="200"><?php echo esc_textarea( $n['message'] ); ?></textarea>
					<p class="description">Keep it to one or two short sentences. Replace anything in [brackets].</p></td></tr>
				<tr><th scope="row"><label for="tmst-starts">First day</label></th>
					<td><input type="date" id="tmst-starts" name="n[starts]" value="<?php echo esc_attr( $n['starts'] ); ?>">
					<p class="description">Leave empty to start right away.</p></td></tr>
				<tr><th scope="row"><label for="tmst-ends">Last day</label></th>
					<td><input type="date" id="tmst-ends" name="n[ends]" value="<?php echo esc_attr( $n['ends'] ); ?>" required>
					<p class="description">The notice comes down at midnight at the end of this day.</p></td></tr>
				<tr><th scope="row">Style</th>
					<td><label><input type="radio" name="n[tone]" value="info" <?php checked( 'alert' !== $n['tone'] ); ?>> Friendly (dark blue)</label><br>
					<label><input type="radio" name="n[tone]" value="alert" <?php checked( 'alert', $n['tone'] ); ?>> Important (red), for closings</label></td></tr>
				<tr><th scope="row"><label for="tmst-ltext">Link (optional)</label></th>
					<td><input type="text" id="tmst-ltext" name="n[link_text]" value="<?php echo esc_attr( $n['link_text'] ); ?>" placeholder="See our hours">
					<input type="url" name="n[link_url]" value="<?php echo esc_attr( $n['link_url'] ); ?>" placeholder="https://" class="regular-text" aria-label="Link address"></td></tr>
			</table>

			<?php submit_button( 'Save notice', 'primary', 'submit', false ); ?>
			<?php if ( $w && $now <= $w[1] ) : ?>
				<button type="submit" name="n[remove]" value="1" class="button" style="margin-left:8px">Take it down now</button>
			<?php endif; ?>
		</form>
	</div>
	<script>
	document.querySelectorAll('.tmst-preset').forEach(function (b) {
		b.addEventListener('click', function () {
			var m = document.getElementById('tmst-msg');
			m.value = b.getAttribute('data-text');
			m.focus();
		});
	});
	</script>
	<?php
}

/**
 * Save the notice, clear caches, and schedule the start and end purges.
 */
function tmst_notice_save() {
	if ( ! current_user_can( TMST_CAP ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'tmst_save_notice' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is cleaned below.
	$in = isset( $_POST['n'] ) && is_array( $_POST['n'] ) ? wp_unslash( $_POST['n'] ) : array();

	if ( ! empty( $in['remove'] ) ) {
		$n         = tmst_notice();
		$n['ends']    = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ); // Ended yesterday: gone now, text kept for reuse.
		$n['removed'] = tmst_today();
	} else {
		$n = array(
			'message'   => sanitize_textarea_field( $in['message'] ?? '' ),
			'link_text' => sanitize_text_field( $in['link_text'] ?? '' ),
			'link_url'  => esc_url_raw( trim( (string) ( $in['link_url'] ?? '' ) ), array( 'https', 'http' ) ),
			'starts'    => tmst_clean_date( $in['starts'] ?? '' ),
			'ends'      => tmst_clean_date( $in['ends'] ?? '' ),
			'tone'      => 'alert' === ( $in['tone'] ?? '' ) ? 'alert' : 'info',
		);
		if ( $n['starts'] && $n['ends'] && $n['ends'] < $n['starts'] ) {
			$n['ends'] = $n['starts']; // A last day before the first day means "one day".
		}
	}
	update_option( 'tmst_notice', $n, true );

	wp_clear_scheduled_hook( 'tmst_notice_purge' );
	$w = tmst_notice_window( $n );
	if ( $w ) {
		if ( $w[0] > time() ) {
			wp_schedule_single_event( $w[0] + 5, 'tmst_notice_purge', array( 'start' ) );
		}
		if ( $w[1] > time() ) {
			wp_schedule_single_event( $w[1] + 5, 'tmst_notice_purge', array( 'end' ) );
		}
	}
	tmst_purge_page_caches();

	wp_safe_redirect( admin_url( 'admin.php?page=tmst-notices&tmst=saved' ) );
	exit;
}
add_action( 'admin_post_tmst_save_notice', 'tmst_notice_save' );
add_action( 'tmst_notice_purge', 'tmst_purge_page_caches' );

/**
 * Print the banner at the top of the page.
 */
function tmst_notice_print() {
	static $done = false;
	if ( $done || is_admin() || ! tmst_notice_live() ) {
		return;
	}
	$done = true;
	$n    = tmst_notice();
	$w    = tmst_notice_window( $n );
	// Each client's theme can set its own brand color with these two CSS variables.
	$bg   = 'alert' === $n['tone'] ? 'var(--tmst-notice-alert,#b42318)' : 'var(--tmst-notice-bg,#1d2b4f)';
	$link = ( $n['link_text'] && $n['link_url'] )
		? ' <a href="' . esc_url( $n['link_url'] ) . '" style="color:inherit;text-decoration:underline;font-weight:600">' . esc_html( $n['link_text'] ) . '</a>'
		: '';
	printf(
		'<div id="tmst-notice" role="status" data-ends="%1$d" style="background:%2$s;color:#fff;text-align:center;padding:10px 16px;font-size:15px;line-height:1.4;position:relative;z-index:9999">%3$s%4$s</div>',
		(int) $w[1],
		esc_attr( $bg ),
		esc_html( $n['message'] ),
		$link // Built from escaped parts above.
	);
	// Hide it on a page cached before the end time.
	echo '<script>(function(){var n=document.getElementById("tmst-notice");if(n&&Date.now()/1000>+n.getAttribute("data-ends")){n.remove();}})();</script>' . "\n";
}
add_action( 'wp_body_open', 'tmst_notice_print', 1 );
