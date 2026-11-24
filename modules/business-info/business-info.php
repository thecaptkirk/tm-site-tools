<?php
/**
 * Business info: hours, holiday hours, phone, address, and service area in ONE
 * place. Always on.
 *
 * The owner edits them under "Your Business" and every place on the site that
 * shows them follows: the footer, the contact page, and the LocalBusiness
 * markup Google reads. Nothing is typed twice, so nothing drifts.
 *
 * SHOWING THEM ON THE SITE
 * Kirk places these once while building the site (a Shortcode block in the
 * footer template and on the contact page):
 *     [tm_business show="phone"]        (tap-to-call link; add link="no" for plain text)
 *     [tm_business show="email"]
 *     [tm_business show="address"]      (add link="map" to link it to Google Maps)
 *     [tm_business show="address_line"]
 *     [tm_business show="hours"]        (the week, plus upcoming holiday hours)
 *     [tm_business show="today"]        ("Open today 9:00 AM to 5:00 PM", holidays included)
 *     [tm_business show="service_area"]
 *     [tm_business show="social"]
 *     [tm_business show="name"] [tm_business show="about"]
 *
 * Other code reads the data with tmst_business().
 *
 * Started from TM Core seo/org-schema.php, but the data lives here rather than
 * in Rank Math's settings: owners should never have to find an SEO plugin's
 * Local SEO tab to change their hours. When Rank Math is active the business is
 * added to its graph; otherwise this prints its own markup.
 *
 * @package TM_Site_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Days of the week, Monday first, keyed the way they are stored.
 *
 * @return array<string, string>
 */
function tmst_days() {
	return array(
		'mon' => 'Monday',
		'tue' => 'Tuesday',
		'wed' => 'Wednesday',
		'thu' => 'Thursday',
		'fri' => 'Friday',
		'sat' => 'Saturday',
		'sun' => 'Sunday',
	);
}

/**
 * Business types for Google's markup, in plain words.
 *
 * @return array<string, string> schema.org type => label.
 */
function tmst_business_types() {
	return array(
		'LocalBusiness'               => 'Local business (general)',
		'Restaurant'                  => 'Restaurant',
		'CafeOrCoffeeShop'            => 'Cafe or coffee shop',
		'Bakery'                      => 'Bakery',
		'BarOrPub'                    => 'Bar or pub',
		'Store'                       => 'Shop or retail store',
		'HomeAndConstructionBusiness' => 'Home services (general)',
		'Plumber'                     => 'Plumber',
		'Electrician'                 => 'Electrician',
		'HVACBusiness'                => 'Heating and air',
		'RoofingContractor'           => 'Roofing',
		'GeneralContractor'           => 'General contractor',
		'HousePainter'                => 'Painter',
		'Locksmith'                   => 'Locksmith',
		'MovingCompany'               => 'Moving company',
		'AutoRepair'                  => 'Auto repair',
		'HairSalon'                   => 'Hair salon or barber',
		'BeautySalon'                 => 'Beauty salon',
		'DaySpa'                      => 'Spa',
		'ExerciseGym'                 => 'Gym or fitness studio',
		'Dentist'                     => 'Dentist',
		'MedicalBusiness'             => 'Medical practice',
		'VeterinaryCare'              => 'Veterinarian',
		'ChildCare'                   => 'Child care',
		'LegalService'                => 'Law office',
		'AccountingService'           => 'Accounting or tax',
		'InsuranceAgency'             => 'Insurance agency',
		'RealEstateAgent'             => 'Real estate',
		'ProfessionalService'         => 'Professional services (other)',
	);
}

/**
 * Social and listing profiles the owner can fill in.
 *
 * @return array<string, string>
 */
function tmst_social_networks() {
	return array(
		'google'    => 'Google Business Profile',
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'yelp'      => 'Yelp',
		'linkedin'  => 'LinkedIn',
		'youtube'   => 'YouTube',
		'tiktok'    => 'TikTok',
		'nextdoor'  => 'Nextdoor',
	);
}

/**
 * The business details, always with every key present.
 *
 * @return array
 */
function tmst_business() {
	$d     = get_option( 'tmst_business', array() );
	$d     = is_array( $d ) ? $d : array();
	$base  = array(
		'name'         => get_bloginfo( 'name' ),
		'type'         => 'LocalBusiness',
		'about'        => '',
		'phone'        => '',
		'email'        => '',
		'street'       => '',
		'street2'      => '',
		'city'         => '',
		'state'        => '',
		'zip'          => '',
		'show_address' => '1',
		'service_area' => '',
		'hours'        => array(),
		'hours_note'   => '',
		'holidays'     => array(),
		'social'       => array(),
	);
	$d     = array_merge( $base, $d );
	$hours = array();
	foreach ( tmst_days() as $k => $label ) {
		$h           = isset( $d['hours'][ $k ] ) && is_array( $d['hours'][ $k ] ) ? $d['hours'][ $k ] : array();
		$hours[ $k ] = array(
			'closed' => ! empty( $h['closed'] ),
			'open'   => (string) ( $h['open'] ?? '' ),
			'close'  => (string) ( $h['close'] ?? '' ),
		);
	}
	$d['hours'] = $hours;
	return $d;
}

/**
 * Holiday rows from today on, soonest first.
 *
 * @return array<int, array{date:string, label:string, closed:bool, open:string, close:string}>
 */
function tmst_upcoming_holidays() {
	$today = tmst_today();
	$rows  = array();
	foreach ( (array) tmst_business()['holidays'] as $h ) {
		if ( ! is_array( $h ) || empty( $h['date'] ) || $h['date'] < $today ) {
			continue;
		}
		$rows[] = array(
			'date'   => (string) $h['date'],
			'label'  => (string) ( $h['label'] ?? '' ),
			'closed' => ! empty( $h['closed'] ),
			'open'   => (string) ( $h['open'] ?? '' ),
			'close'  => (string) ( $h['close'] ?? '' ),
		);
	}
	usort(
		$rows,
		static function ( $a, $b ) {
			return strcmp( $a['date'], $b['date'] );
		}
	);
	return $rows;
}

/**
 * Digits of a US phone number, or '' when it is not one.
 *
 * @param string $phone As typed.
 * @return string 10 digits.
 */
function tmst_phone_digits( $phone ) {
	$digits = preg_replace( '/\D/', '', (string) $phone );
	if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
		$digits = substr( $digits, 1 );
	}
	return 10 === strlen( $digits ) ? $digits : '';
}

/**
 * "(336) 555-1234", or the number as typed when it is not a US number.
 *
 * @param string $phone As typed.
 * @return string
 */
function tmst_phone_label( $phone ) {
	$d = tmst_phone_digits( $phone );
	return $d ? sprintf( '(%s) %s-%s', substr( $d, 0, 3 ), substr( $d, 3, 3 ), substr( $d, 6 ) ) : trim( (string) $phone );
}

/**
 * One day's hours as words: "9:00 AM to 5:00 PM" or "Closed".
 *
 * @param array $h closed/open/close.
 * @return string
 */
function tmst_hours_label( $h ) {
	if ( ! empty( $h['closed'] ) ) {
		return 'Closed';
	}
	$open  = tmst_time_label( $h['open'] ?? '' );
	$close = tmst_time_label( $h['close'] ?? '' );
	if ( '' === $open || '' === $close ) {
		return '';
	}
	return $open . ' to ' . $close;
}

/**
 * Today's hours, holiday hours first. Null when today has no hours set.
 *
 * @return array{closed:bool, open:string, close:string, label:string}|null
 */
function tmst_hours_today() {
	$today = tmst_today();
	foreach ( tmst_upcoming_holidays() as $h ) {
		if ( $h['date'] === $today ) {
			return $h;
		}
	}
	$key = strtolower( substr( wp_date( 'D' ), 0, 3 ) );
	$h   = tmst_business()['hours'][ $key ] ?? null;
	if ( ! $h || ( ! $h['closed'] && ( '' === $h['open'] || '' === $h['close'] ) ) ) {
		return null;
	}
	$h['label'] = '';
	return $h;
}

/* ---------------------------------------------------------------------------
 * THE "YOUR BUSINESS" MENU
 * ------------------------------------------------------------------------ */

/**
 * Add the top-level menu. Other modules (Notices) add their pages under it.
 */
function tmst_business_menu() {
	add_menu_page( 'Your Business', 'Your Business', TMST_CAP, 'tmst-business', 'tmst_business_render', 'dashicons-store', 3 );
	add_submenu_page( 'tmst-business', 'Business info', 'Business info', TMST_CAP, 'tmst-business', 'tmst_business_render' );
}
add_action( 'admin_menu', 'tmst_business_menu' );

/**
 * Draw the Business info screen.
 */
function tmst_business_render() {
	if ( ! current_user_can( TMST_CAP ) ) {
		return;
	}
	$b        = tmst_business();
	$holidays = tmst_upcoming_holidays();
	while ( count( $holidays ) < count( tmst_upcoming_holidays() ) + 3 ) {
		$holidays[] = array(
			'date'   => '',
			'label'  => '',
			'closed' => true,
			'open'   => '',
			'close'  => '',
		);
	}
	?>
	<div class="wrap tmst-wrap">
		<h1>Business info</h1>
		<?php tmst_saved_notice(); ?>
		<p>Change anything here and your website updates everywhere it shows: the footer, the contact page, and the details Google reads.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tmst_save_business">
			<?php wp_nonce_field( 'tmst_save_business' ); ?>

			<h2>Contact</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="tmst-name">Business name</label></th>
					<td><input type="text" class="regular-text" id="tmst-name" name="b[name]" value="<?php echo esc_attr( $b['name'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="tmst-phone">Phone</label></th>
					<td><input type="tel" class="regular-text" id="tmst-phone" name="b[phone]" value="<?php echo esc_attr( tmst_phone_label( $b['phone'] ) ); ?>" placeholder="(336) 555-1234"></td></tr>
				<tr><th scope="row"><label for="tmst-email">Email</label></th>
					<td><input type="email" class="regular-text" id="tmst-email" name="b[email]" value="<?php echo esc_attr( $b['email'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="tmst-about">About, in a sentence or two</label></th>
					<td><textarea class="large-text" rows="2" id="tmst-about" name="b[about]" style="max-width:600px"><?php echo esc_textarea( $b['about'] ); ?></textarea>
					<p class="description">Shown to search engines. Example: Family-owned bakery in Greensboro, North Carolina, baking fresh bread and custom cakes since 1998.</p></td></tr>
			</table>

			<h2>Address</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="tmst-street">Street</label></th>
					<td><input type="text" class="regular-text" id="tmst-street" name="b[street]" value="<?php echo esc_attr( $b['street'] ); ?>"><br>
					<input type="text" class="regular-text" name="b[street2]" value="<?php echo esc_attr( $b['street2'] ); ?>" placeholder="Suite or unit (optional)" aria-label="Suite or unit"></td></tr>
				<tr><th scope="row"><label for="tmst-city">City, state, ZIP</label></th>
					<td><input type="text" id="tmst-city" name="b[city]" value="<?php echo esc_attr( $b['city'] ); ?>" placeholder="City">
					<input type="text" name="b[state]" value="<?php echo esc_attr( $b['state'] ); ?>" placeholder="NC" size="4" maxlength="2" aria-label="State">
					<input type="text" name="b[zip]" value="<?php echo esc_attr( $b['zip'] ); ?>" placeholder="ZIP" size="8" maxlength="10" aria-label="ZIP code" inputmode="numeric"></td></tr>
				<tr><th scope="row">Show the address</th>
					<td><label><input type="checkbox" name="b[show_address]" value="1" <?php checked( '1', $b['show_address'] ); ?>> Show my street address on the website</label>
					<p class="description">Untick if you work from home or go to your customers. Google still gets your city and service area.</p></td></tr>
				<tr><th scope="row"><label for="tmst-area">Service area</label></th>
					<td><input type="text" class="large-text" id="tmst-area" name="b[service_area]" value="<?php echo esc_attr( $b['service_area'] ); ?>" style="max-width:600px" placeholder="Greensboro, High Point, Winston-Salem, and nearby towns"></td></tr>
			</table>

			<h2>Hours</h2>
			<table class="widefat striped" style="max-width:600px">
				<thead><tr><th>Day</th><th>Closed</th><th>Opens</th><th>Closes</th></tr></thead>
				<tbody>
				<?php foreach ( tmst_days() as $k => $label ) : $h = $b['hours'][ $k ]; ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><input type="checkbox" name="b[hours][<?php echo esc_attr( $k ); ?>][closed]" value="1" <?php checked( $h['closed'] ); ?> aria-label="Closed <?php echo esc_attr( $label ); ?>"></td>
						<td><input type="time" name="b[hours][<?php echo esc_attr( $k ); ?>][open]" value="<?php echo esc_attr( $h['open'] ); ?>" aria-label="<?php echo esc_attr( $label ); ?> opens"></td>
						<td><input type="time" name="b[hours][<?php echo esc_attr( $k ); ?>][close]" value="<?php echo esc_attr( $h['close'] ); ?>" aria-label="<?php echo esc_attr( $label ); ?> closes"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><label for="tmst-hours-note">Note under your hours (optional)</label><br>
			<input type="text" class="large-text" id="tmst-hours-note" name="b[hours_note]" value="<?php echo esc_attr( $b['hours_note'] ); ?>" style="max-width:600px" placeholder="Evenings by appointment"></p>

			<h2>Holiday hours</h2>
			<p>Days you are closed or keep different hours. Each one disappears on its own once the day has passed.</p>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr><th>Date</th><th>Name (optional)</th><th>Closed all day</th><th>Opens</th><th>Closes</th></tr></thead>
				<tbody>
				<?php foreach ( $holidays as $i => $h ) : ?>
					<tr>
						<td><input type="date" name="b[holidays][<?php echo (int) $i; ?>][date]" value="<?php echo esc_attr( $h['date'] ); ?>" aria-label="Date"></td>
						<td><input type="text" name="b[holidays][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $h['label'] ); ?>" placeholder="Christmas Day" aria-label="Name"></td>
						<td><input type="checkbox" name="b[holidays][<?php echo (int) $i; ?>][closed]" value="1" <?php checked( $h['closed'] ); ?> aria-label="Closed all day"></td>
						<td><input type="time" name="b[holidays][<?php echo (int) $i; ?>][open]" value="<?php echo esc_attr( $h['open'] ); ?>" aria-label="Opens"></td>
						<td><input type="time" name="b[holidays][<?php echo (int) $i; ?>][close]" value="<?php echo esc_attr( $h['close'] ); ?>" aria-label="Closes"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">Need more rows? Save, and three new empty rows appear.</p>

			<h2>Profiles</h2>
			<table class="form-table" role="presentation">
			<?php foreach ( tmst_social_networks() as $k => $label ) : ?>
				<tr><th scope="row"><label for="tmst-s-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="url" class="regular-text" id="tmst-s-<?php echo esc_attr( $k ); ?>" name="b[social][<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $b['social'][ $k ] ?? '' ); ?>" placeholder="https://"></td></tr>
			<?php endforeach; ?>
			</table>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<h2>For Target Marketeer</h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="tmst-type">Business type (for Google)</label></th>
						<td><select id="tmst-type" name="b[type]">
						<?php foreach ( tmst_business_types() as $type => $label ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $b['type'], $type ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
						</select></td></tr>
				</table>
			<?php endif; ?>

			<?php submit_button( 'Save business info' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Save the Business info screen.
 */
function tmst_business_save() {
	if ( ! current_user_can( TMST_CAP ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'tmst_save_business' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is cleaned below.
	$in  = isset( $_POST['b'] ) && is_array( $_POST['b'] ) ? wp_unslash( $_POST['b'] ) : array();
	$old = tmst_business();
	$out = array(
		'name'         => sanitize_text_field( $in['name'] ?? '' ),
		'type'         => $old['type'],
		'about'        => sanitize_textarea_field( $in['about'] ?? '' ),
		'phone'        => sanitize_text_field( $in['phone'] ?? '' ),
		'email'        => sanitize_email( $in['email'] ?? '' ),
		'street'       => sanitize_text_field( $in['street'] ?? '' ),
		'street2'      => sanitize_text_field( $in['street2'] ?? '' ),
		'city'         => sanitize_text_field( $in['city'] ?? '' ),
		'state'        => strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) ( $in['state'] ?? '' ) ), 0, 2 ) ),
		'zip'          => preg_replace( '/[^0-9\-]/', '', (string) ( $in['zip'] ?? '' ) ),
		'show_address' => empty( $in['show_address'] ) ? '0' : '1',
		'service_area' => sanitize_text_field( $in['service_area'] ?? '' ),
		'hours'        => array(),
		'hours_note'   => sanitize_text_field( $in['hours_note'] ?? '' ),
		'holidays'     => array(),
		'social'       => array(),
	);

	// Only administrators see the type field; owners keep what is set.
	if ( current_user_can( 'manage_options' ) && isset( $in['type'] ) && isset( tmst_business_types()[ $in['type'] ] ) ) {
		$out['type'] = (string) $in['type'];
	}

	foreach ( tmst_days() as $k => $label ) {
		$h                  = isset( $in['hours'][ $k ] ) && is_array( $in['hours'][ $k ] ) ? $in['hours'][ $k ] : array();
		$out['hours'][ $k ] = array(
			'closed' => ! empty( $h['closed'] ),
			'open'   => tmst_clean_time( $h['open'] ?? '' ),
			'close'  => tmst_clean_time( $h['close'] ?? '' ),
		);
	}

	$today = tmst_today();
	foreach ( (array) ( $in['holidays'] ?? array() ) as $h ) {
		$date = is_array( $h ) ? tmst_clean_date( $h['date'] ?? '' ) : '';
		if ( '' === $date || $date < $today ) {
			continue; // Empty rows and past days drop out.
		}
		$open             = tmst_clean_time( $h['open'] ?? '' );
		$close            = tmst_clean_time( $h['close'] ?? '' );
		$out['holidays'][] = array(
			'date'   => $date,
			'label'  => sanitize_text_field( $h['label'] ?? '' ),
			// A row with no times is a closed day, whatever the box says.
			'closed' => ! empty( $h['closed'] ) || '' === $open || '' === $close,
			'open'   => $open,
			'close'  => $close,
		);
	}

	foreach ( tmst_social_networks() as $k => $label ) {
		$url = esc_url_raw( trim( (string) ( $in['social'][ $k ] ?? '' ) ), array( 'https', 'http' ) );
		if ( $url ) {
			$out['social'][ $k ] = $url;
		}
	}

	update_option( 'tmst_business', $out, true );
	tmst_purge_page_caches();

	/** Lets other modules (llms.txt) react to new details. */
	do_action( 'tmst_business_saved', $out );

	wp_safe_redirect( admin_url( 'admin.php?page=tmst-business&tmst=saved' ) );
	exit;
}
add_action( 'admin_post_tmst_save_business', 'tmst_business_save' );

/* ---------------------------------------------------------------------------
 * SHORTCODE
 * ------------------------------------------------------------------------ */

/**
 * [tm_business show="..."]
 *
 * @param array|string $atts Attributes.
 * @return string
 */
function tmst_business_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'show' => 'name',
			'link' => 'yes',
		),
		$atts,
		'tm_business'
	);
	$b    = tmst_business();
	$link = 'no' !== strtolower( (string) $atts['link'] );

	switch ( strtolower( (string) $atts['show'] ) ) {
		case 'name':
			return esc_html( $b['name'] );

		case 'about':
			return esc_html( $b['about'] );

		case 'phone':
			$digits = tmst_phone_digits( $b['phone'] );
			$label  = tmst_phone_label( $b['phone'] );
			if ( '' === $label ) {
				return '';
			}
			return ( $link && $digits )
				? '<a class="tmst-phone" href="tel:+1' . esc_attr( $digits ) . '">' . esc_html( $label ) . '</a>'
				: esc_html( $label );

		case 'email':
			if ( '' === $b['email'] ) {
				return '';
			}
			$email = antispambot( $b['email'] );
			return $link ? '<a class="tmst-email" href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : esc_html( $email );

		case 'address':
		case 'address_line':
			$lines = tmst_address_lines( $b );
			if ( ! $lines ) {
				return '';
			}
			$one = 'address_line' === strtolower( (string) $atts['show'] );
			$txt = $one ? esc_html( implode( ', ', $lines ) ) : implode( '<br>', array_map( 'esc_html', $lines ) );
			if ( 'map' === strtolower( (string) $atts['link'] ) ) {
				$txt = '<a href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $b['name'] . ', ' . implode( ', ', $lines ) ) ) . '" target="_blank" rel="noopener">' . $txt . '</a>';
			}
			return $one ? '<span class="tmst-address">' . $txt . '</span>' : '<address class="tmst-address" style="font-style:normal">' . $txt . '</address>';

		case 'service_area':
			return esc_html( $b['service_area'] );

		case 'today':
			$t = tmst_hours_today();
			if ( ! $t ) {
				return '';
			}
			$label = tmst_hours_label( $t );
			return '<span class="tmst-today">' . esc_html( 'Closed' === $label ? 'Closed today' : 'Open today ' . $label ) . ( $t['label'] ? ' (' . esc_html( $t['label'] ) . ')' : '' ) . '</span>';

		case 'hours':
			return tmst_hours_table( $b );

		case 'social':
			$items = '';
			foreach ( tmst_social_networks() as $k => $label ) {
				if ( ! empty( $b['social'][ $k ] ) ) {
					$items .= '<li><a href="' . esc_url( $b['social'][ $k ] ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a></li>';
				}
			}
			return $items ? '<ul class="tmst-social">' . $items . '</ul>' : '';
	}
	return '';
}
add_shortcode( 'tm_business', 'tmst_business_shortcode' );

/**
 * The address as display lines. Street lines are left out when the owner
 * chose not to show them.
 *
 * @param array $b Business.
 * @return string[]
 */
function tmst_address_lines( $b ) {
	$lines = array();
	if ( '1' === $b['show_address'] ) {
		$lines[] = $b['street'];
		$lines[] = $b['street2'];
	}
	$city    = trim( $b['city'] . ( $b['city'] && $b['state'] ? ', ' : '' ) . $b['state'] . ' ' . $b['zip'] );
	$lines[] = $city;
	return array_values( array_filter( array_map( 'trim', $lines ) ) );
}

/**
 * The week's hours as a small table, then upcoming holiday hours.
 *
 * @param array $b Business.
 * @return string
 */
function tmst_hours_table( $b ) {
	$rows = '';
	foreach ( tmst_days() as $k => $label ) {
		$text = tmst_hours_label( $b['hours'][ $k ] );
		if ( '' !== $text ) {
			$rows .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $text ) . '</td></tr>';
		}
	}
	if ( '' === $rows ) {
		return '';
	}
	$html = '<table class="tmst-hours"><tbody>' . $rows . '</tbody></table>';

	$soon = array_slice(
		array_filter(
			tmst_upcoming_holidays(),
			static function ( $h ) {
				return $h['date'] <= wp_date( 'Y-m-d', time() + 60 * DAY_IN_SECONDS );
			}
		),
		0,
		5
	);
	if ( $soon ) {
		$items = '';
		foreach ( $soon as $h ) {
			$items .= '<li>' . esc_html( ( $h['label'] ? $h['label'] . ', ' : '' ) . tmst_date_label( $h['date'] ) . ': ' . tmst_hours_label( $h ) ) . '</li>';
		}
		$html .= '<p class="tmst-holiday-title"><strong>Holiday hours</strong></p><ul class="tmst-holidays">' . $items . '</ul>';
	}
	if ( '' !== $b['hours_note'] ) {
		$html .= '<p class="tmst-hours-note">' . esc_html( $b['hours_note'] ) . '</p>';
	}
	return $html;
}

/* ---------------------------------------------------------------------------
 * GOOGLE MARKUP (LocalBusiness JSON-LD)
 * ------------------------------------------------------------------------ */

/**
 * The business as a schema.org entity, or null when there is no name.
 *
 * @return array|null
 */
function tmst_business_schema() {
	$b = tmst_business();
	if ( '' === trim( $b['name'] ) ) {
		return null;
	}
	$home = trailingslashit( home_url() );
	$e    = array(
		'@type' => $b['type'] ? $b['type'] : 'LocalBusiness',
		'@id'   => $home . '#business',
		'name'  => $b['name'],
		'url'   => $home,
	);
	if ( $b['about'] ) {
		$e['description'] = $b['about'];
	}
	$digits = tmst_phone_digits( $b['phone'] );
	if ( $digits ) {
		$e['telephone'] = '+1-' . substr( $digits, 0, 3 ) . '-' . substr( $digits, 3, 3 ) . '-' . substr( $digits, 6 );
	}
	if ( $b['email'] ) {
		$e['email'] = $b['email'];
	}
	$addr = array_filter(
		array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => '1' === $b['show_address'] ? trim( $b['street'] . ' ' . $b['street2'] ) : '',
			'addressLocality' => $b['city'],
			'addressRegion'   => $b['state'],
			'postalCode'      => '1' === $b['show_address'] ? $b['zip'] : '',
			'addressCountry'  => 'US',
		)
	);
	if ( count( $addr ) > 2 ) {
		$e['address'] = $addr;
	}
	if ( $b['service_area'] ) {
		$e['areaServed'] = $b['service_area'];
	}
	$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' ) : get_site_icon_url( 512 );
	if ( $logo ) {
		$e['logo']  = $logo;
		$e['image'] = $logo;
	}

	$spec = array();
	foreach ( tmst_days() as $k => $day ) {
		$h = $b['hours'][ $k ];
		if ( ! $h['closed'] && $h['open'] && $h['close'] ) {
			$spec[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => 'https://schema.org/' . $day,
				'opens'     => $h['open'],
				'closes'    => $h['close'],
			);
		}
	}
	if ( $spec ) {
		$e['openingHoursSpecification'] = $spec;
	}

	$special = array();
	foreach ( tmst_upcoming_holidays() as $h ) {
		// Google's convention for "closed all day" is opens = closes = 00:00.
		$special[] = array(
			'@type'        => 'OpeningHoursSpecification',
			'validFrom'    => $h['date'],
			'validThrough' => $h['date'],
			'opens'        => $h['closed'] ? '00:00' : $h['open'],
			'closes'       => $h['closed'] ? '00:00' : $h['close'],
		);
	}
	if ( $special ) {
		$e['specialOpeningHoursSpecification'] = $special;
	}

	$same = array_values( array_filter( array_map( 'strval', (array) $b['social'] ) ) );
	if ( $same ) {
		$e['sameAs'] = $same;
	}

	/** Lets a module or a site add to the business markup. */
	return apply_filters( 'tmst_business_schema', $e, $b );
}

/**
 * Is Rank Math printing the page's structured data?
 *
 * @return bool
 */
function tmst_rank_math_active() {
	return class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' );
}

/**
 * Without Rank Math: print the business on every front-end page.
 */
function tmst_business_schema_print() {
	if ( tmst_rank_math_active() || is_admin() ) {
		return;
	}
	$e = tmst_business_schema();
	if ( ! $e ) {
		return;
	}
	$e = array( '@context' => 'https://schema.org' ) + $e;
	echo '<script type="application/ld+json">' . wp_json_encode( $e, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'tmst_business_schema_print', 5 );

/**
 * With Rank Math: add the business to its graph, so there is one set of
 * markup. Our details win; the owner's screen is the one place they change.
 *
 * @param array $data Rank Math's entities.
 * @return array
 */
function tmst_business_schema_rank_math( $data ) {
	if ( ! is_array( $data ) || is_admin() ) {
		return $data;
	}
	$e = tmst_business_schema();
	if ( $e ) {
		$data['tmst_business'] = $e;
	}
	return $data;
}
add_filter( 'rank_math/json_ld', 'tmst_business_schema_rank_math', 99 );
