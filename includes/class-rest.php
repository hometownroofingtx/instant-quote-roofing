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
		foreach ( array( 'htr_rr_report' => 'create', 'htr_rr_email' => 'email_copy', 'htr_rr_preview' => 'preview', 'htr_rr_calculator_lead' => 'calculator_lead', 'htr_rr_prepare' => 'prepare', 'htr_rr_calculator_prepare' => 'calculator_prepare', 'htr_rr_status' => 'status', 'htr_rr_link_contact' => 'link_contact', 'htr_rr_save_contact' => 'save_contact', 'htr_rr_reprice' => 'reprice', 'htr_rr_draft_prepare' => 'draft_prepare', 'htr_rr_calculator_draft_prepare' => 'calculator_draft_prepare' ) as $action => $fn ) {
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
		foreach ( array( 'calculator_lead', 'prepare', 'calculator_prepare', 'status', 'link_contact', 'save_contact', 'reprice', 'draft_prepare', 'calculator_draft_prepare' ) as $method ) {
			register_rest_route( 'htr-rr/v1', '/' . $method, array( 'methods' => 'POST', 'callback' => array( __CLASS__, $method ), 'permission_callback' => '__return_true' ) );
		}
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

	/** Validated manual dimensions; prices are always calculated on the server. */
	public static function calculator_measurements( $p ) {
		foreach ( array( 'area_sqft' => array( 100, 100000 ), 'pitch_x12' => array( 0, 24 ), 'faces' => array( 1, 100 ) ) as $field => $bounds ) {
			if ( ! isset( $p[ $field ] ) || ! is_numeric( $p[ $field ] ) || ! is_finite( (float) $p[ $field ] ) || (float) $p[ $field ] < $bounds[0] || (float) $p[ $field ] > $bounds[1] || (float) $p[ $field ] !== (float) (int) $p[ $field ] ) {
				return null;
			}
		}
		return array( 'ok' => true, 'source' => 'customer_entered', 'area_sqft' => (int) $p['area_sqft'], 'pitch_x12' => (int) $p['pitch_x12'], 'faces' => (int) $p['faces'], 'squares' => round( $p['area_sqft'] / 100, 1 ), 'ground_sqft' => null, 'building_box' => null, 'faces_detail' => array(), 'imagery_quality' => '', 'imagery_date' => '' );
	}

	public static function calculate( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		$m = is_array( $p ) ? self::calculator_measurements( $p ) : null;
		if ( ! $m ) { return self::fail( 'invalid', 'Enter roof area (100–100,000 sq ft), pitch (0–24) and roof faces (1–100) as whole numbers.' ); }
		$s = HTR_RR_Settings::all();
		$town = isset( $p['town'] ) && is_scalar( $p['town'] ) ? (int) $p['town'] : -1;
		if ( ! isset( $s['areas'][ $town ] ) || ! isset( $p['material'] ) || ! is_string( $p['material'] ) || ! isset( $s['materials'][ $p['material'] ] ) ) { return self::fail( 'invalid', 'Choose a roof type and service area.' ); }
		$key = 'htr_rr_calc_' . substr( self::ip_hash(), 0, 20 );
		$count = (int) get_transient( $key );
		if ( $count >= 120 && ! current_user_can( 'manage_options' ) ) { return self::fail( 'rate_limited', 'Please try again in an hour.', 429 ); }
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		$near = isset( $p['lat'], $p['lng'] ) && is_numeric( $p['lat'] ) && is_numeric( $p['lng'] ) && abs( (float) $p['lat'] ) <= 90 && abs( (float) $p['lng'] ) <= 180 ? HTR_RR_Estimator::nearest_area( (float) $p['lat'], (float) $p['lng'], $s['areas'] ) : null;
		$region = $near ? $near['mult'] : $s['areas'][ $town ]['mult'];
		$e = HTR_RR_Estimator::estimate( $m, $p['material'], $s, $region );
		return new WP_REST_Response( array( 'ok' => true, 'estimate' => $e, 'town' => $near ? $near['town'] : $s['areas'][ $town ]['town'], 'waste' => (float) $s['waste_pct'] ) );
	}

	public static function calculator_lead( WP_REST_Request $req ) {
		return self::create( $req, true );
	}

	/**
	 * GHL form mode (1.1.0): steps 1-3 save and price the report WITHOUT contact details and without sending a lead.
	 * The customer then submits the GHL form (hidden field htr_report_id); n8n calls link_contact() and the lead goes out
	 * through the normal path. Reports stay 'awaiting_form' until then (never retried by WP-Cron).
	 */
	public static function prepare( WP_REST_Request $req ) {
		return self::create( $req, false, true );
	}

	public static function calculator_prepare( WP_REST_Request $req ) {
		return self::create( $req, true, true );
	}
	public static function draft_prepare( WP_REST_Request $req ) { return self::create( $req, false, true, true ); }
	public static function calculator_draft_prepare( WP_REST_Request $req ) { return self::create( $req, true, true, true ); }

	private static function contact_record( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{32}$/D', $token ) ) { return null; }
		$report = HTR_RR_DB::get_by_token( $token );
		if ( $report ) { return $report; }
		$draft = get_transient( 'htr_rr_draft_' . $token );
		return is_array( $draft ) && ( $draft['token'] ?? '' ) === $token ? $draft : null;
	}
	private static function authorized_contact( array $r, $expires, $key ) {
		$draft_key = hash_hmac( 'sha256', 'ghl-draft-contact|' . $r['token'] . '|' . $expires, wp_salt( 'auth' ) );
		return hash_equals( self::contact_key( $r, $expires ), $key ) || hash_equals( $draft_key, $key );
	}
	private static function persist_draft( array $draft, array $contact ) {
		$data = array_merge( $draft, $contact );
		unset( $data['id'], $data['_draft'] );
		$id = HTR_RR_DB::insert_contact_report( $data );
		if ( $id ) { delete_transient( 'htr_rr_draft_' . $draft['token'] ); }
		return $id;
	}

	/** Public: has the GHL form for this report been submitted and linked yet? (the page polls this). */
	public static function save_contact( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		if ( ! is_array( $p ) || ! is_string( $p['token'] ?? null ) || ! is_string( $p['contact_key'] ?? null ) || ! is_numeric( $p['expires'] ?? null ) ) { return self::fail( 'invalid', 'Invalid contact-save request.' ); }
		$r = self::contact_record( $p['token'] );
		$expires = (int) $p['expires'];
		if ( ! $r || $expires < time() || $expires > time() + 2 * DAY_IN_SECONDS || ! self::authorized_contact( $r, $expires, $p['contact_key'] ) ) { return self::fail( 'forbidden', 'This contact-save link is invalid or expired.', 403 ); }
		if ( 'awaiting_form' !== $r['lead_status'] ) { return new WP_REST_Response( array( 'ok' => true, 'saved' => true, 'already_saved' => true ), 200 ); }
		$contact = is_array( $p['contact'] ?? null ) ? $p['contact'] : array();
		$clean = array();
		foreach ( array( 'first_name', 'last_name', 'email', 'phone' ) as $key ) {
			$value = $contact[ $key ] ?? '';
			if ( ! is_string( $value ) ) { return self::fail( 'invalid', 'Invalid contact field.' ); }
			$clean[ $key ] = sanitize_text_field( $value );
		}
		$clean['email'] = sanitize_email( $clean['email'] );
		$digits = preg_replace( '/\D/', '', $clean['phone'] );
		$international = preg_match( '/^\s*\+/', $clean['phone'] );
		if ( $international ) {
			$clean['phone'] = preg_match( '/^[1-9][0-9]{6,14}$/', $digits ) ? '+' . $digits : '';
		} else {
			if ( 11 === strlen( $digits ) && '1' === $digits[0] ) { $digits = substr( $digits, 1 ); }
			$clean['phone'] = 10 === strlen( $digits ) ? '+1' . $digits : '';
		}
		if ( '' === trim( $clean['first_name'] . $clean['last_name'] ) || ( ! is_email( $clean['email'] ) && ! $clean['phone'] ) || strlen( $clean['first_name'] ) > 80 || strlen( $clean['last_name'] ) > 80 || strlen( $clean['email'] ) > 160 ) { return self::fail( 'invalid', 'GHL did not provide a valid name and email or phone.' ); }
		if ( ! is_email( $clean['email'] ) ) { $clean['email'] = ''; }
		// This records browser-reported submission, not independently verified GHL delivery.
		$clean['lead_status'] = 'ghl_submitted';
		$clean['lead_error'] = '';
		if ( ! empty( $r['_draft'] ) ) {
			$id = self::persist_draft( $r, $clean );
			if ( ! $id ) { return self::fail( 'save_failed', 'The submitted report could not be saved. Please retry.', 500 ); }
			$r['id'] = $id; $saved = 1;
		} else { $saved = HTR_RR_DB::save_browser_contact( $r['id'], $clean ); }
		if ( false === $saved ) { return self::fail( 'save_failed', 'Contact details could not be saved. Please retry.', 500 ); }
		if ( 0 === $saved ) {
			$current = HTR_RR_DB::get_report( $r['id'] );
			if ( ! $current || 'awaiting_form' === $current['lead_status'] ) { return self::fail( 'save_failed', 'Contact details were not saved. Please retry.', 500 ); }
		}
		HTR_RR_DB::log( 'info', 'lead', 'Contact saved from browser-reported GHL submission; no outbound lead sent.', $r['id'] );
		return new WP_REST_Response( array( 'ok' => true, 'saved' => true, 'phone_saved' => ! empty( $clean['phone'] ) ), 200 );
	}

	private static function contact_key( array $report, $expires ) {
		if ( ! empty( $report['_draft'] ) ) { return hash_hmac( 'sha256', 'ghl-draft-contact|' . $report['token'] . '|' . $expires, wp_salt( 'auth' ) ); }
		return hash_hmac( 'sha256', 'ghl-contact|' . $report['id'] . '|' . $report['token'] . '|' . $expires, wp_salt( 'auth' ) );
	}

	/** Reprice an owned waiting report without creating another row or remeasuring the roof. */
	public static function reprice( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		if ( ! is_array( $p ) || ! is_string( $p['token'] ?? null ) || ! is_string( $p['contact_key'] ?? null ) || ! is_numeric( $p['expires'] ?? null ) || ! is_string( $p['material'] ?? null ) ) { return self::fail( 'invalid', 'Invalid roof selection.' ); }
		$r = self::contact_record( $p['token'] );
		$expires = (int) $p['expires'];
		if ( ! $r || $expires < time() || $expires > time() + 2 * DAY_IN_SECONDS || ! self::authorized_contact( $r, $expires, $p['contact_key'] ) ) { return self::fail( 'forbidden', 'This report cannot be changed.', 403 ); }
		if ( 'awaiting_form' !== $r['lead_status'] ) { return self::fail( 'already_submitted', 'This report has already been submitted. Refresh to start a new request.', 409 ); }
		$s = HTR_RR_Settings::all();
		$material = sanitize_key( $p['material'] );
		if ( ! isset( $s['materials'][ $material ] ) ) { return self::fail( 'invalid', 'Choose a valid roof type.' ); }
		$m = json_decode( $r['measurements'] ?? '', true );
		$near = HTR_RR_Estimator::nearest_area( (float) $r['lat'], (float) $r['lng'], $s['areas'] );
		$e = is_array( $m ) && ! empty( $m['ok'] ) ? HTR_RR_Estimator::estimate( $m, $material, $s, $near ? $near['mult'] : 1 ) : array();
		$update = array( 'material' => $material, 'estimate' => ! empty( $e['ok'] ) ? wp_json_encode( $e ) : null, 'price_low' => $e['low'] ?? null, 'price_high' => $e['high'] ?? null, 'status' => ! empty( $e['low'] ) && empty( $r['review_reason'] ) ? 'completed' : 'review' );
		if ( ! empty( $r['_draft'] ) ) { $r = array_merge( $r, $update ); $result = set_transient( 'htr_rr_draft_' . $r['token'], $r, 2 * DAY_IN_SECONDS ); }
		else { $result = HTR_RR_DB::update_waiting_report( $r['id'], $update ); $r = HTR_RR_DB::get_report( $r['id'] ); }
		if ( false === $result || ! $r || 'awaiting_form' !== $r['lead_status'] ) { return self::fail( 'save_failed', 'The roof selection could not be updated. Refresh if you already submitted.', 409 ); }
		return new WP_REST_Response( array( 'ok' => true, 'report' => HTR_RR_Report_Page::public_view( $r ), 'report_id' => ! empty( $r['_draft'] ) ? 'draft-' . $r['token'] : (int) $r['id'], 'contact_key' => $p['contact_key'], 'contact_expires' => $expires, 'lead_type' => 'Instant quote', 'roof_type' => $s['materials'][ $material ]['label'] ), 200 );
	}

	public static function status( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		$token = is_array( $p ) && is_string( $p['token'] ?? null ) ? $p['token'] : '';
		$r = $token ? self::contact_record( $token ) : null;
		if ( ! $r ) {
			return self::fail( 'not_found', 'Report not found.', 404 );
		}
		$linked = 'awaiting_form' !== $r['lead_status'];
		$response = array( 'ok' => true, 'linked' => $linked );
		if ( $linked ) { $response['report'] = HTR_RR_Report_Page::public_view( $r ); }
		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Called by n8n when a GHL form submission carries htr_report_id. Protected by the shared secret (header X-HTR-Key,
	 * same secret as lead delivery). Saves the customer's details on the report and sends the lead (tags, fields, email).
	 */
	public static function link_contact( WP_REST_Request $req ) {
		$key = (string) $req->get_header( 'x_htr_key' );
		if ( '' === $key && isset( $_SERVER['HTTP_X_HTR_KEY'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HTR_KEY'] ) );
		}
		$sec = (string) HTR_RR_Settings::get( 'n8n_secret' );
		if ( '' === $sec || '' === $key || ! hash_equals( $sec, $key ) ) {
			return self::fail( 'forbidden', 'Not allowed.', 403 );
		}
		$p  = $req->get_json_params();
		$reference = is_array( $p ) ? ( $p['report_id'] ?? '' ) : '';
		$id = is_scalar( $reference ) ? (int) $reference : 0;
		$r = is_string( $reference ) && preg_match( '/^draft-([a-f0-9]{32})$/D', $reference, $match ) ? self::contact_record( $match[1] ) : ( $id ? HTR_RR_DB::get_report( $id ) : null );
		if ( $r ) { $id = (int) $r['id']; }
		if ( ! $r ) {
			return self::fail( 'not_found', 'Report ' . $id . ' not found.', 404 );
		}
		if ( 'awaiting_form' !== $r['lead_status'] ) {
			return new WP_REST_Response( array( 'ok' => true, 'already_linked' => true, 'lead_status' => $r['lead_status'] ), 200 );
		}
		$first = sanitize_text_field( isset( $p['first_name'] ) ? $p['first_name'] : '' );
		$last  = sanitize_text_field( isset( $p['last_name'] ) ? $p['last_name'] : '' );
		$email = sanitize_email( isset( $p['email'] ) ? $p['email'] : '' );
		$digits = preg_replace( '/[^0-9]/', '', isset( $p['phone'] ) ? (string) $p['phone'] : '' );
		if ( strlen( $digits ) === 11 && '1' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}
		$phone = strlen( $digits ) === 10 ? '+1' . $digits : '';
		if ( ( '' === $first && '' === $last ) || ( ! is_email( $email ) && '' === $phone ) ) {
			return self::fail( 'invalid', 'The form submission has no name or no email/phone.' );
		}
		$contact_values = array(
				'first_name'  => $first,
				'last_name'   => $last,
				'email'       => is_email( $email ) ? $email : '',
				'phone'       => $phone,
				'sms_consent' => implode( ',', array_filter( array( ! empty( $p['sms_service'] ) ? 'service' : '', ! empty( $p['sms_marketing'] ) ? 'marketing' : '' ) ) ),
				'lead_status' => 'pending',
				'lead_tries'  => 0,
				'lead_error'  => '',
			);
		if ( ! empty( $r['_draft'] ) ) {
			$id = self::persist_draft( $r, $contact_values );
			if ( ! $id ) { return self::fail( 'save_failed', 'The submitted report could not be saved.', 500 ); }
		} else { HTR_RR_DB::update_report( $id, $contact_values ); }
		HTR_RR_DB::log( 'info', 'lead', 'GHL form submission linked' . ( ! empty( $p['submission_id'] ) ? ' (' . sanitize_text_field( (string) $p['submission_id'] ) . ')' : '' ), $id );
		$sent = HTR_RR_Leads::send( $id );
		return new WP_REST_Response( array( 'ok' => true, 'linked' => true, 'delivered' => (bool) $sent ), 200 );
	}

	public static function create( WP_REST_Request $req, $calculator = false, $deferred = false, $draft = false ) {
		if ( $deferred && 'ghl_form' !== HTR_RR_Settings::public_config()['contactMode'] ) {
			return self::fail( 'disabled', 'The GHL contact step is not enabled.', 403 );
		}
		$p = $req->get_json_params();
		if ( ! is_array( $p ) ) {
			return self::fail( 'bad_request', 'Please fill in the form and try again.' );
		}
		$automatic = $calculator && isset( $p['measurement_mode'] ) && 'aerial' === $p['measurement_mode'];
		$manual = $calculator && ! $automatic ? self::calculator_measurements( $p ) : null;
		if ( $calculator && ! $automatic && ! $manual ) { return self::fail( 'invalid', 'Check your roof area, pitch and number of roof faces.' ); }
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
		$digits = '';
		// GHL form mode: contact details come later from the GHL form submission (link_contact).
		if ( ! $deferred ) {
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
		$phone = $deferred ? '' : '+1' . $digits;
		if ( $deferred ) {
			$first = '';
			$last  = '';
			$email = '';
		}
		// 4. Service area.
		$near   = HTR_RR_Estimator::nearest_area( $lat, $lng, $s['areas'] );
		$inside = $near && $near['miles'] <= (float) $s['service_radius_mi'];
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		global $wpdb;
		$token = bin2hex( random_bytes( 16 ) );
		$record = array(
				'token'      => $token,
				'created_at' => current_time( 'mysql', true ),
				'status'     => 'processing',
				'request_source' => $calculator ? 'calculator' : 'instant_roof',
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
				'lead_status' => $deferred ? 'awaiting_form' : 'pending',
			);
		if ( $draft ) {
			$id = 0;
			$record = array_merge( array( 'id' => 0, '_draft' => true, 'area_sqft' => null, 'pitch_x12' => null, 'faces' => null, 'imagery_quality' => '', 'imagery_date' => '', 'price_low' => null, 'price_high' => null, 'review_reason' => '', 'measurements' => null, 'estimate' => null, 'lead_tries' => 0, 'lead_error' => '' ), $record );
		} else {
			$wpdb->insert( HTR_RR_DB::reports(), $record );
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) { return self::fail( 'server', 'The report could not be saved.', 500 ); }
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
		if ( $calculator && ! $automatic ) {
			$m = $manual;
			$review[] = 'Calculator estimate from customer-entered dimensions; confirm measurements on site.';
			$e = HTR_RR_Estimator::estimate( $m, $mat, $s, $near ? $near['mult'] : 1.0 );
		} else {
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
		$measured = array(
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
			);
		if ( $draft ) {
			$record = array_merge( $record, $measured );
			if ( ! set_transient( 'htr_rr_draft_' . $token, $record, 2 * DAY_IN_SECONDS ) ) { return self::fail( 'server', 'The temporary quote could not be prepared.', 500 ); }
		} else { HTR_RR_DB::update_report( $id, $measured ); }
		// 6. Lead to GHL (through n8n). Failure here never blocks the customer; WP-Cron retries.
		if ( ! $deferred ) {
			HTR_RR_Leads::send( $id );
		}

		$r = $draft ? $record : HTR_RR_DB::get_report( $id );
		$response = array( 'ok' => true, 'report' => HTR_RR_Report_Page::public_view( $r ) );
		if ( $calculator ) {
			$response['estimate'] = $e;
			$response['town'] = $near ? $near['town'] : '';
			$response['waste'] = (float) $s['waste_pct'];
		}
		if ( $deferred ) {
			// Values the page pre-fills into the GHL form (hidden fields + roof type).
			$response['report_id'] = $draft ? 'draft-' . $token : $id;
			$response['contact_expires'] = time() + 2 * DAY_IN_SECONDS;
			$response['contact_key'] = self::contact_key( $r, $response['contact_expires'] );
			$response['lead_type'] = $calculator ? 'Calculator' : 'Instant quote';
			$response['roof_type'] = isset( $s['materials'][ $mat ]['label'] ) ? $s['materials'][ $mat ]['label'] : $mat;
		}
		return new WP_REST_Response( $response, 200 );
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
