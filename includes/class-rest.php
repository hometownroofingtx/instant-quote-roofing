<?php
/**
 * Public endpoint the form calls: POST /wp-json/htr-rr/v1/report
 * Guards: honeypot field, per-IP hourly limit, field validation, Texas service-area check, coordinates required.
 * The Google server key and the n8n secret never leave the server.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTR_RR_REST {

	/**
	 * admin-ajax.php transport (the form's main channel). Some sites/security plugins block the REST API for
	 * logged-out visitors ("Authentication is required..."), found on the staging site 28 Oct 2026.
	 * Same handlers, same guards; body is JSON (php://input).
	 */
	public static function ajax_init() {
		foreach ( array( 'htr_rr_report' => 'create', 'htr_rr_email' => 'email_copy', 'htr_rr_preview' => 'preview' ) as $action => $fn ) {
			$cb = function () use ( $fn ) {
				$req = new WP_REST_Request( 'POST' );
				$req->set_header( 'content-type', 'application/json' );
				$req->set_body( (string) file_get_contents( 'php://input' ) );
				$res = call_user_func( array( __CLASS__, $fn ), $req );
				wp_send_json( $res->get_data(), $res->get_status() );
			};
			add_action( 'wp_ajax_' . $action, $cb );
			add_action( 'wp_ajax_nopriv_' . $action, $cb );
		}
	}

	public static function routes() {
		register_rest_route(
			'htr-rr/v1',
			'/report',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create' ),
				'permission_callback' => '__return_true', // public form; protected by the guards below.
			)
		);
		register_rest_route(
			'htr-rr/v1',
			'/email',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'email_copy' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private static function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return hash( 'sha256', $ip . wp_salt( 'nonce' ) );
	}

	private static function fail( $code, $message, $status = 400 ) {
		return new WP_REST_Response( array( 'ok' => false, 'code' => $code, 'message' => $message ), $status );
	}

	public static function create( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		if ( ! is_array( $p ) ) {
			return self::fail( 'bad_request', 'Please fill in the form and try again.' );
		}
		// 1. Honeypot: real people never fill the hidden "website" field.
		if ( ! empty( $p['website'] ) ) {
			return self::fail( 'bad_request', 'Please try again.' );
		}
		// 2. Rate limit per visitor.
		$ip    = self::ip_hash();
		$key   = 'htr_rr_rl_' . substr( $ip, 0, 20 );
		$count = (int) get_transient( $key );
		$limit = max( 1, (int) HTR_RR_Settings::get( 'rate_limit_hour' ) );
		// Logged-in admins (staff testing from the office connection) are not limited; visitors are.
		if ( $count >= $limit && ! current_user_can( 'manage_options' ) ) {
			return self::fail( 'rate_limited', 'You have requested several reports already. Please call us or try again in an hour.', 429 );
		}
		// 3. Validate.
		$s     = HTR_RR_Settings::all();
		$first = sanitize_text_field( isset( $p['first_name'] ) ? $p['first_name'] : '' );
		$last  = sanitize_text_field( isset( $p['last_name'] ) ? $p['last_name'] : '' );
		$email = sanitize_email( isset( $p['email'] ) ? $p['email'] : '' );
		$phone = preg_replace( '/[^0-9+]/', '', isset( $p['phone'] ) ? (string) $p['phone'] : '' );
		$addr  = sanitize_text_field( isset( $p['address'] ) ? $p['address'] : '' );
		$city  = sanitize_text_field( isset( $p['city'] ) ? $p['city'] : '' );
		$zip   = preg_replace( '/[^0-9]/', '', isset( $p['zip'] ) ? (string) $p['zip'] : '' );
		$lat   = isset( $p['lat'] ) ? (float) $p['lat'] : 0;
		$lng   = isset( $p['lng'] ) ? (float) $p['lng'] : 0;
		$mat   = sanitize_key( isset( $p['material'] ) ? $p['material'] : '' );
		$allow = array( 'replacement', 'repair', 'storm', 'inspection' );
		$ints  = array_values( array_intersect( $allow, array_map( 'sanitize_key', isset( $p['interests'] ) && is_array( $p['interests'] ) ? $p['interests'] : array() ) ) );
		$err   = array();
		if ( '' === $first || '' === $last ) {
			$err[] = 'your first and last name';
		}
		if ( ! is_email( $email ) ) {
			$err[] = 'a valid email';
		}
		$digits = preg_replace( '/[^0-9]/', '', $phone );
		if ( strlen( $digits ) === 11 && '1' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}
		if ( strlen( $digits ) !== 10 ) {
			$err[] = 'a 10-digit US phone number';
		}
		if ( '' === $addr || ! $lat || ! $lng || abs( $lat ) > 90 || abs( $lng ) > 180 ) {
			$err[] = 'your property address (pick it from the list)';
		} elseif ( ! preg_match( '/\d/', strtok( $addr, ',' ) ) ) {
			// A city or ZIP alone puts the pin in the town centre and would measure the wrong building.
			$err[] = 'your full street address with house number (not just the city)';
		}
		if ( ! isset( $s['materials'][ $mat ] ) ) {
			$err[] = 'your roof type';
		}
		if ( $err ) {
			return self::fail( 'invalid', 'Please add ' . implode( ', ', $err ) . '.' );
		}
		$phone = '+1' . $digits;
		// 4. Service area.
		$near   = HTR_RR_Estimator::nearest_area( $lat, $lng, $s['areas'] );
		$inside = $near && $near['miles'] <= (float) $s['service_radius_mi'];
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		global $wpdb;
		$token = bin2hex( random_bytes( 16 ) );
		$wpdb->insert(
			HTR_RR_DB::reports(),
			array(
				'token'      => $token,
				'created_at' => current_time( 'mysql', true ),
				'status'     => 'processing',
				'first_name' => $first,
				'last_name'  => $last,
				'email'      => $email,
				'phone'      => $phone,
				'interests'  => implode( ',', $ints ),
				'sms_consent' => implode( ',', array_filter( array( ! empty( $p['sms_service'] ) ? 'service' : '', ! empty( $p['sms_marketing'] ) ? 'marketing' : '' ) ) ),
				'address'    => $addr,
				'city'       => $city,
				'zip'        => substr( $zip, 0, 5 ),
				'lat'        => $lat,
				'lng'        => $lng,
				'material'   => $mat,
				'town'       => $near ? $near['town'] : '',
				'town_miles' => $near ? $near['miles'] : null,
				'source_url' => esc_url_raw( isset( $p['source_url'] ) ? substr( (string) $p['source_url'], 0, 250 ) : '' ),
				'ip_hash'    => $ip,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			HTR_RR_DB::log( 'error', 'rest', 'Could not save the report: ' . $wpdb->last_error );
			return self::fail( 'server', 'Something went wrong on our side. Please call us.', 500 );
		}

		// 5. Measure + estimate. Any problem = saved for review; the customer is told honestly and the lead still goes out.
		$review = array();
		$m      = array( 'ok' => false );
		$e      = array( 'ok' => false );
		// Mark, 28 Oct 2026: no area limit - every address is measured and priced; outside-area leads are flagged
		// (tag in GHL) so Kaeleigh can tell the customer. The customer page does not mention the service area.
		if ( ! $inside ) {
			$review[] = 'Outside the service area (' . ( $near ? $near['miles'] . ' mi from ' . $near['town'] : 'unknown' ) . ')';
		}
		{
			$m = HTR_RR_Solar::measure( $lat, $lng, $id );
			if ( empty( $m['ok'] ) ) {
				$review[] = isset( $m['message'] ) ? $m['message'] : 'No measurement';
			} else {
				if ( 'BASE' === $m['imagery_quality'] ) {
					$review[] = 'Low-detail imagery (BASE) - confirm measurements';
				}
				if ( false === $m['marker_inside'] && $m['marker_off_m'] !== null && $m['marker_off_m'] > 25 ) {
					$review[] = 'Marker is ' . $m['marker_off_m'] . ' m from the measured building - confirm the right roof';
				}
				if ( $m['imagery_date'] && strtotime( $m['imagery_date'] ) < strtotime( '-5 years' ) ) {
					$review[] = 'Imagery is from ' . substr( $m['imagery_date'], 0, 4 ) . ' - roof may have changed';
				}
				if ( $m['area_sqft'] < 400 || $m['area_sqft'] > 15000 ) {
					$review[] = 'Unusual roof area (' . $m['area_sqft'] . ' sq ft)';
				}
				$e = HTR_RR_Estimator::estimate( $m, $mat, $s, $near['mult'] );
				if ( empty( $e['low'] ) ) {
					$review[] = isset( $e['reason'] ) ? $e['reason'] : 'No automatic price';
				}
			}
		}
		$has_measure = ! empty( $m['ok'] );
		$status      = $has_measure ? ( $review ? 'review' : 'completed' ) : 'review';
		HTR_RR_DB::update_report(
			$id,
			array(
				'status'          => $status,
				'area_sqft'       => $has_measure ? $m['area_sqft'] : null,
				'pitch_x12'       => $has_measure ? $m['pitch_x12'] : null,
				'faces'           => $has_measure ? $m['faces'] : null,
				'imagery_quality' => $has_measure ? $m['imagery_quality'] : '',
				'imagery_date'    => $has_measure ? $m['imagery_date'] : '',
				'price_low'       => ! empty( $e['low'] ) ? $e['low'] : null,
				'price_high'      => ! empty( $e['high'] ) ? $e['high'] : null,
				'review_reason'   => substr( implode( '; ', $review ), 0, 250 ),
				'measurements'    => $has_measure ? wp_json_encode( $m ) : null,
				'estimate'        => ! empty( $e['ok'] ) ? wp_json_encode( $e ) : null,
			)
		);
		// 6. Lead to GHL (through n8n). Failure here never blocks the customer; WP-Cron retries.
		HTR_RR_Leads::send( $id );

		$r = HTR_RR_DB::get_report( $id );
		return new WP_REST_Response( array( 'ok' => true, 'report' => HTR_RR_Report_Page::public_view( $r ) ), 200 );
	}

	/**
	 * Step-2 roof preview (no lead, nothing saved): measurements only, for the property card beside the map.
	 * Guards: 30 previews per visitor per hour; outside the service area = no Google call.
	 */
	public static function preview( WP_REST_Request $req ) {
		$p   = $req->get_json_params();
		$lat = isset( $p['lat'] ) ? (float) $p['lat'] : 0;
		$lng = isset( $p['lng'] ) ? (float) $p['lng'] : 0;
		if ( ! $lat || ! $lng || abs( $lat ) > 90 || abs( $lng ) > 180 ) {
			return self::fail( 'invalid', 'Pick an address first.' );
		}
		$key   = 'htr_rr_pv_' . substr( self::ip_hash(), 0, 20 );
		$count = (int) get_transient( $key );
		if ( $count >= 30 && ! current_user_can( 'manage_options' ) ) {
			return self::fail( 'rate_limited', 'Too many lookups. Please continue to the next step.', 429 );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		$s    = HTR_RR_Settings::all();
		$near = HTR_RR_Estimator::nearest_area( $lat, $lng, $s['areas'] ); // every address is measured (no area limit).
		$m    = HTR_RR_Solar::measure( $lat, $lng );
		if ( empty( $m['ok'] ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'measured' => false, 'message' => 'Aerial roof data is not available for this spot. We will measure your roof in person, free of charge.' ), 200 );
		}
		return new WP_REST_Response(
			array(
				'ok'           => true,
				'measured'     => true,
				'areaSqft'     => $m['area_sqft'],
				'groundSqft'   => $m['ground_sqft'],
				'faces'        => $m['faces'],
				'pitch'        => $m['pitch_x12'] . '/12',
				'imageryDate'  => $m['imagery_date'],
				'quality'      => $m['imagery_quality'],
				'markerOnRoof' => false !== $m['marker_inside'],
				'town'         => $near ? $near['town'] : '',
			),
			200
		);
	}

	/** "Email me a copy": sends the report link to the email on the report (never to another address). */
	public static function email_copy( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		$r = HTR_RR_DB::get_by_token( isset( $p['token'] ) ? $p['token'] : '' );
		if ( ! $r ) {
			return self::fail( 'not_found', 'Report not found.', 404 );
		}
		$k = 'htr_rr_mail_' . $r['id'];
		if ( get_transient( $k ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'message' => 'Already sent - check your inbox.' ), 200 );
		}
		$sent = wp_mail(
			$r['email'],
			'Your Hometown Roofing roof report',
			'Hi ' . $r['first_name'] . ",\n\nHere is your roof report for " . $r['address'] . ":\n" . HTR_RR_Report_Page::url( $r['token'] ) . "\n\n" . HTR_RR_Settings::get( 'disclaimer' ) . "\n\nHometown Roofing & Restoration"
		);
		set_transient( $k, 1, 10 * MINUTE_IN_SECONDS );
		HTR_RR_DB::log( $sent ? 'info' : 'error', 'email', $sent ? 'Report link emailed to customer' : 'wp_mail failed', $r['id'] );
		return new WP_REST_Response( array( 'ok' => (bool) $sent, 'message' => $sent ? 'Sent! Check your inbox.' : 'We could not send the email. Use Download PDF instead.' ), 200 );
	}
}
