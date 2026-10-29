<?php
/**
 * All plugin settings live in one option (htr_rr_settings). Defaults below are the starting values.
 * Rates are the sample rates from the approved mockup (Mark, 28 Oct 2026: "use mockup rates for now"),
 * always shown to customers as an ESTIMATE. The office changes them under Roof Reports > Pricing.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTR_RR_Settings {
	const OPTION = 'htr_rr_settings';

	public static function defaults() {
		return array(
			// Google: one browser key (Maps JavaScript + Places, restricted to the website) and one server key (Solar API).
			'google_browser_key' => '',
			'google_server_key'  => '',
			'required_quality'   => 'MEDIUM', // Solar imagery quality asked for first; BASE is tried next.
			// Lead delivery to GHL through n8n.
			'n8n_webhook_url'    => '',
			'n8n_secret'         => '',
			// Customer-facing text.
			'company_phone'      => '', // set by the office under Roof Reports > Settings (never guessed).
			'disclaimer'         => 'This is an estimated price range based on aerial measurements and the roof type you selected. Final pricing may vary after an on-site inspection.',
			'notify_email'       => 'systems@hometownroofingtx.com',
			// SMS consent, same two checkboxes as GHL form "Form 30" (9uN85m16cN7dG9QBrsM0), with the business name filled in.
			// Optional for the customer; both answers are sent to GHL. The office should confirm the wording against the A2P campaign.
			'consent_service'    => 'By checking this box, I consent to receive non-marketing text messages from Hometown Roofing & Restoration about my roof report, inspection and appointments. Message frequency varies, message & data rates may apply. Text HELP for assistance, reply STOP to opt out.',
			'consent_marketing'  => 'By checking this box, I consent to receive marketing and promotional messages including special offers, discounts, new product updates among others, from Hometown Roofing & Restoration at the phone number provided. Frequency may vary. Message & data rates may apply. Text HELP for assistance, reply STOP to opt out.',
			'ghl_form_id'        => '9uN85m16cN7dG9QBrsM0', // sent to n8n so GHL records which form the lead belongs to.
			// Limits.
			'rate_limit_hour'    => 5,
			'service_radius_mi'  => 60,
			// Pricing (USD per sq ft of roof surface).
			'materials'          => array(
				'asphalt'   => array( 'label' => 'Asphalt Shingle', 'low' => 4.50, 'high' => 6.50, 'quote' => 1 ),
				'metal'     => array( 'label' => 'Metal', 'low' => 6.00, 'high' => 8.50, 'quote' => 1 ),
				'tile'      => array( 'label' => 'Tile', 'low' => 6.75, 'high' => 10.00, 'quote' => 1 ),
				'flat'      => array( 'label' => 'Flat (TPO/EPDM)', 'low' => 5.50, 'high' => 8.00, 'quote' => 1 ),
				'wood'      => array( 'label' => 'Wood Shake', 'low' => 7.00, 'high' => 11.00, 'quote' => 1 ),
				'slate'     => array( 'label' => 'Slate', 'low' => 8.00, 'high' => 12.00, 'quote' => 1 ),
				'synthetic' => array( 'label' => 'Synthetic', 'low' => 6.50, 'high' => 9.50, 'quote' => 1 ),
				'other'     => array( 'label' => 'Other', 'low' => 0, 'high' => 0, 'quote' => 0 ), // no automatic price
			),
			'waste_pct'          => 0, // extra % added to the measured area (0 = rates already include waste).
			// Pitch multipliers by rise per 12 (steeper roofs cost more to work on).
			'pitch_bands'        => array(
				array( 'max' => 7, 'mult' => 1.00 ),
				array( 'max' => 9, 'mult' => 1.10 ),
				array( 'max' => 12, 'mult' => 1.20 ),
				array( 'max' => 99, 'mult' => 1.35 ),
			),
			// Complexity by number of roof faces Google detects.
			'complexity'         => array(
				'simple_max'  => 4,
				'medium_max'  => 12,
				'simple'      => 1.00,
				'medium'      => 1.00,
				'complex'     => 1.10,
			),
			// Service areas (same town centres as the n8n review routing). mult = regional price adjustment.
			'areas'              => array(
				array( 'town' => 'San Antonio', 'lat' => 29.4246002, 'lng' => -98.4951405, 'mult' => 1.00 ),
				array( 'town' => 'Austin', 'lat' => 30.2649261, 'lng' => -97.7474086, 'mult' => 1.00 ),
				array( 'town' => 'Aransas Pass', 'lat' => 27.909666, 'lng' => -97.1503865, 'mult' => 1.00 ),
				array( 'town' => 'Marble Falls', 'lat' => 30.5777511, 'lng' => -98.2756505, 'mult' => 1.00 ),
				array( 'town' => 'Boerne', 'lat' => 29.7946641, 'lng' => -98.7319703, 'mult' => 1.00 ),
			),
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		$s     = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		// Materials: keep every default key even if an older save lacks it.
		$s['materials'] = array_merge( self::defaults()['materials'], is_array( $s['materials'] ) ? $s['materials'] : array() );
		return $s;
	}

	public static function get( $key ) {
		$s = self::all();
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	public static function save( array $values ) {
		update_option( self::OPTION, array_merge( self::all(), $values ), false );
	}

	/** Sanitize editable pricing rows without changing existing material identities. */
	public static function pricing_rows( array $input, array $settings ) {
		$materials = $settings['materials'];
		foreach ( $materials as $key => $material ) {
			if ( ! isset( $input['mat'][ $key ] ) ) { continue; }
			$clean = self::material_row( $input['mat'][ $key ], $material );
			if ( is_wp_error( $clean ) ) { return $clean; }
			$materials[ $key ] = $clean;
		}
		foreach ( (array) ( $input['new_mat'] ?? array() ) as $row ) {
			if ( is_array( $row ) && isset( $row['label'] ) && is_string( $row['label'] ) && '' === trim( $row['label'] ) ) { continue; }
			$clean = self::material_row( $row );
			if ( is_wp_error( $clean ) ) { return $clean; }
			$base = substr( sanitize_key( sanitize_title( $clean['label'] ) ), 0, 24 );
			$base = $base ?: 'roof-type';
			$key = $base;
			$suffix = 2;
			while ( isset( $materials[ $key ] ) ) { $key = $base . '-' . $suffix++; }
			$materials[ $key ] = $clean;
		}
		$areas = array();
		foreach ( (array) ( $input['ar'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['town'] ) || ! is_string( $row['town'] ) ) { return new WP_Error( 'pricing', 'Enter a valid service-area town.' ); }
			$town = sanitize_text_field( $row['town'] );
			if ( '' === trim( $town ) ) { continue; }
			if ( ! is_numeric( $row['lat'] ?? null ) || ! is_numeric( $row['lng'] ?? null ) || abs( (float) $row['lat'] ) > 90 || abs( (float) $row['lng'] ) > 180 || ! is_numeric( $row['mult'] ?? null ) ) {
				return new WP_Error( 'pricing', 'Each service area needs a latitude from -90 to 90, a longitude from -180 to 180, and a numeric price multiplier.' );
			}
			$areas[] = array( 'town' => $town, 'lat' => (float) $row['lat'], 'lng' => (float) $row['lng'], 'mult' => round( min( 2, max( 0.5, (float) $row['mult'] ) ), 2 ) );
		}
		return array( 'materials' => $materials, 'areas' => $areas ?: $settings['areas'] );
	}

	private static function material_row( $row, $previous = array() ) {
		if ( ! is_array( $row ) || ! isset( $row['label'] ) || ! is_string( $row['label'] ) || '' === trim( sanitize_text_field( $row['label'] ) ) || ! is_numeric( $row['low'] ?? null ) || ! is_numeric( $row['high'] ?? null ) ) {
			return new WP_Error( 'pricing', 'Each roof type needs a name and numeric low and high rates.' );
		}
		$low = round( max( 0, (float) $row['low'] ), 2 );
		$image_id = isset( $row['image_id'] ) ? absint( $row['image_id'] ) : (int) ( $previous['image_id'] ?? 0 );
		if ( $image_id && isset( $row['image_id'] ) && ! wp_attachment_is_image( $image_id ) ) { return new WP_Error( 'pricing', 'Choose an image from the Media Library for each roof type.' ); }
		return array( 'label' => sanitize_text_field( $row['label'] ), 'low' => $low, 'high' => max( $low, round( max( 0, (float) $row['high'] ), 2 ) ), 'quote' => empty( $row['quote'] ) ? 0 : 1, 'image_id' => $image_id );
	}

	/** Settings that are safe to send to the browser. Never the server key or the n8n secret. */
	public static function public_config() {
		$s   = self::all();
		$mat = array();
		foreach ( $s['materials'] as $k => $m ) {
			$mat[] = array( 'key' => $k, 'label' => $m['label'], 'image_id' => (int) ( $m['image_id'] ?? 0 ) );
		}
		return array(
			'browserKey' => $s['google_browser_key'],
			'phone'      => $s['company_phone'],
			'disclaimer' => $s['disclaimer'],
			'materials'  => $mat,
		);
	}
}
