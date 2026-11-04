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
	const PRICING_FORMAT = 'htr-rr-pricing';
	const PRICING_VERSION = 1;

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
			// Contact step: 'plugin' = the plugin's own contact form (leads reach GHL through n8n); 'ghl_form' = the GHL form above is
			// embedded at the contact step, so every lead is a real GHL form submission (1.1.0, Mark 4 Nov 2026).
			'contact_mode'       => 'plugin',
			'calculator_design'  => self::calculator_design_defaults(),
			'quote_design'       => self::quote_design_defaults(),
			'floating_enabled'   => 0,
			'floating_page'      => 0,
			'floating_position'  => 'right',
			'floating_mobile'    => 1,
			'floating_label'     => 'Instant Roof Quote',
			'floating_heading'   => 'Need a roof quote?',
			'floating_message'   => 'Get your price NOW!',
			'floating_style'     => 'quote',
			'floating_vertical'  => 'center',
			'floating_offset'    => 20,
			'floating_behavior'  => 'hover',
			'floating_open_mode' => 'page',
			'floating_drawer_form' => 'report',
			'floating_desktop'   => 1,
			'floating_tablet'    => 1,
			'floating_color'     => '#0b5ed7',
			'floating_hover'     => '#093a8f',
			'floating_background' => '#ffffff',
			'floating_radius'    => 12,
			'floating_shadow'    => 1,
			'floating_zindex'    => 9990,
			'floating_scope'     => 'all',
			'floating_include_pages' => array(),
			'floating_exclude_pages' => array(),
			'floating_hide_destination' => 1,
			'floating_items'     => array(),
			// Query key of the form's "Type of Roof Do You Have?" field (Form 30), pre-filled with the roof type the customer picked.
			'ghl_roof_type_key'  => 'single_line_60fgq',
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
		$s['calculator_design'] = array_merge( self::calculator_design_defaults(), is_array( $s['calculator_design'] ) ? $s['calculator_design'] : array() );
		$s['quote_design'] = array_merge( self::quote_design_defaults(), is_array( $s['quote_design'] ) ? $s['quote_design'] : array() );
		return $s;
	}

	public static function quote_design_fields() {
		$fields = array();
		$labels = array( 'Find Property', 'Confirm Property', 'Roof Type', 'Contact Info', 'Analyze', 'Roof Report' );
		$headings = array( 'Find your property', 'Confirm your property', 'What type of roof do you have?', 'Your contact information', 'Analyzing your roof', 'Your roof report is ready' );
		$descriptions = array( 'Start typing your full property address and pick it from the list.', 'Zoom in and drag the red pin onto your roof if it is not already there.', 'Pick the main roofing material on the house today.', 'We will send your roof report to this email.', 'We are checking your property and preparing your roof report.', 'Here is your roof analysis and estimated quote.' );
		foreach ( $labels as $i => $label ) {
			$n = $i + 1;
			$fields[ 'step_' . $n . '_label' ] = array( 'Step ' . $n . ' progress label', $label );
			$fields[ 'step_' . $n . '_heading' ] = array( 'Step ' . $n . ' heading', $headings[$i] );
			$fields[ 'step_' . $n . '_description' ] = array( 'Step ' . $n . ' description', $descriptions[$i] );
		}
		return array_merge( $fields, array(
			'sidebar_title' => array( 'Sidebar heading', 'Your roof quote, made simple.' ),
			'sidebar_text' => array( 'Sidebar introduction', 'Find your home, choose your roof type, and get a personalized estimate.' ),
			'reassurance_title' => array( 'Reassurance heading', 'No obligation. No guesswork.' ),
			'reassurance_text' => array( 'Reassurance text', 'Aerial measurements help us prepare your estimate. Final pricing is confirmed during your inspection.' ),
			'back' => array( 'Back button', 'Back' ), 'find' => array( 'Find button', 'Find Property' ),
			'confirm' => array( 'Confirm button', 'Confirm & Continue' ), 'continue' => array( 'Continue button', 'Continue' ),
			'location' => array( 'Current location link', 'Use my current location' ), 'edit' => array( 'Edit address link', 'Edit address' ),
			'preview_heading' => array( 'Preview heading', 'Roof preview' ), 'preview_note' => array( 'Preview helper', 'From Google aerial imagery. Move the pin to update.' ),
			'submit' => array( 'Plugin form submit button', 'Generate my roof report' ),
			'working' => array( 'Analysis status', 'Preparing your report…' ),
			'analysis_1' => array( 'Analysis checklist 1', 'Property confirmed' ), 'analysis_2' => array( 'Analysis checklist 2', 'Roof type selected' ),
			'analysis_3' => array( 'Analysis checklist 3', 'Reading aerial imagery' ), 'analysis_4' => array( 'Analysis checklist 4', 'Measuring roof area and pitch' ), 'analysis_5' => array( 'Analysis checklist 5', 'Preparing your report' ),
			'download' => array( 'Download button', 'Download report (PDF)' ), 'email' => array( 'Email button', 'Email me a copy' ), 'details' => array( 'Full details button', 'View full details online' ),
		) );
	}
	public static function quote_design_defaults() {
		$out = array( 'sidebar' => 0, 'property' => 0 );
		foreach ( self::quote_design_fields() as $key => $field ) { $out[$key] = $field[1]; }
		return $out;
	}
	public static function sanitize_quote_design( $input ) {
		if ( ! is_array( $input ) ) { return new WP_Error( 'design', 'Invalid roof quote design settings.' ); }
		$out = self::get( 'quote_design' );
		foreach ( array( 'sidebar', 'property' ) as $key ) {
			if ( ! isset( $input[$key] ) ) { continue; }
			if ( ! is_numeric( $input[$key] ) ) { return new WP_Error( 'design', 'Choose a valid image from the Media Library.' ); }
			$id = absint( $input[$key] );
			if ( $id && ! wp_attachment_is_image( $id ) ) { return new WP_Error( 'design', 'Choose a valid image from the Media Library.' ); }
			$out[$key] = $id;
		}
		foreach ( self::quote_design_fields() as $key => $field ) {
			if ( isset( $input[$key] ) && is_string( $input[$key] ) ) { $out[$key] = sanitize_textarea_field( $input[$key] ); }
		}
		return $out;
	}
	public static function calculator_design_fields() {
		return array(
			'title' => array( 'Heading prefix', 'Instant' ), 'highlight' => array( 'Highlighted heading', 'Roof Estimate' ),
			'intro' => array( 'Header description', 'Get a quick, personalized estimate for your roof replacement or repair in a few easy steps.' ),
			'roof_heading' => array( 'Roof details heading', 'Step 1: Roof Details' ), 'roof_description' => array( 'Roof details description', 'Tell us about your roof so we can prepare your estimate.' ),
			'address_heading' => array( 'Address heading', 'Step 2: Your Address' ), 'address_description' => array( 'Address description', 'Select your property address and position the pin on your roof.' ),
			'contact_heading' => array( 'Contact heading', 'Step 3: Contact Information' ), 'contact_description' => array( 'Contact description', 'Add your contact details to receive your personalized roof estimate.' ),
			'sidebar_title' => array( 'Sidebar heading', "Your Estimate\nis Just a Few\nSteps Away" ),
			'guide_1_title' => array( 'Guide 1 heading', 'Enter your roof details' ), 'guide_1_text' => array( 'Guide 1 description', 'Type, size, pitch, and roof complexity.' ),
			'guide_2_title' => array( 'Guide 2 heading', 'Add your property address' ), 'guide_2_text' => array( 'Guide 2 description', 'Confirm your location for accurate pricing.' ),
			'guide_3_title' => array( 'Guide 3 heading', 'Provide your contact information' ), 'guide_3_text' => array( 'Guide 3 description', 'So we can follow up about your estimate.' ),
			'guide_4_title' => array( 'Guide 4 heading', 'Get your estimate' ), 'guide_4_text' => array( 'Guide 4 description', 'View your price range and next steps.' ),
			'reassurance_title' => array( 'Reassurance heading', 'No obligation.' ), 'reassurance_text' => array( 'Reassurance description', 'A preliminary estimate to help you plan. Final measurements and pricing are confirmed at your inspection.' ),
			'footer' => array( 'Footer disclaimer', 'Preliminary estimate using dimensions you enter. Final pricing may vary after an on-site inspection.' ),
		);
	}
	public static function calculator_design_defaults() {
		$out = array( 'logo' => 0, 'background' => 0, 'sidebar' => 0 );
		foreach ( self::calculator_design_fields() as $key => $field ) { $out[ $key ] = $field[1]; }
		return $out;
	}
	public static function sanitize_calculator_design( $input ) {
		if ( ! is_array( $input ) ) { return new WP_Error( 'design', 'Invalid calculator design settings.' ); }
		$out = self::get( 'calculator_design' );
		foreach ( array( 'logo', 'background', 'sidebar' ) as $key ) {
			if ( ! isset( $input[ $key ] ) ) { continue; }
			if ( ! is_scalar( $input[ $key ] ) || ! is_numeric( $input[ $key ] ) ) { return new WP_Error( 'design', 'Choose calculator images from the Media Library.' ); }
			$id = absint( $input[ $key ] );
			if ( $id && ! wp_attachment_is_image( $id ) ) { return new WP_Error( 'design', 'Choose valid calculator images from the Media Library.' ); }
			$out[ $key ] = $id;
		}
		foreach ( self::calculator_design_fields() as $key => $field ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) { $out[ $key ] = 'sidebar_title' === $key ? sanitize_textarea_field( $input[ $key ] ) : sanitize_text_field( $input[ $key ] ); }
		}
		return $out;
	}

	public static function get( $key ) {
		$s = self::all();
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	public static function save( array $values ) {
		update_option( self::OPTION, array_merge( self::all(), $values ), false );
	}

	/** Export only portable pricing settings, without credentials or local media IDs. */
	public static function pricing_export() {
		$settings = self::all();
		$pricing = array_intersect_key( $settings, array_flip( array( 'materials', 'areas', 'service_radius_mi', 'waste_pct', 'pitch_bands', 'complexity' ) ) );
		foreach ( $pricing['materials'] as $key => $material ) {
			$pricing['materials'][ $key ] = array_intersect_key( $material, array_flip( array( 'label', 'low', 'high', 'quote' ) ) );
		}
		return array( 'format' => self::PRICING_FORMAT, 'version' => self::PRICING_VERSION, 'pricing' => $pricing );
	}

	/** Validate the entire portable document before any option is updated. Images stay local. */
	public static function pricing_import( $document ) {
		if ( ! is_array( $document ) || self::PRICING_FORMAT !== ( $document['format'] ?? null ) || self::PRICING_VERSION !== ( $document['version'] ?? null ) || ! is_array( $document['pricing'] ?? null ) ) {
			return new WP_Error( 'pricing_import', 'Choose a version 1 HTR Roof Report pricing export.' );
		}
		$p = $document['pricing'];
		foreach ( array( 'materials', 'areas', 'pitch_bands', 'complexity' ) as $key ) {
			if ( ! is_array( $p[ $key ] ?? null ) || ! $p[ $key ] ) {
				return new WP_Error( 'pricing_import', 'The pricing file is missing a populated section: ' . $key . '.' );
			}
		}
		if ( ! self::pricing_number( $p['waste_pct'] ?? null, 0, 50 ) || ! self::pricing_number( $p['service_radius_mi'] ?? null, 5, 300, true ) ) {
			return new WP_Error( 'pricing_import', 'Waste must be between 0 and 50 percent; service radius must be a whole number between 5 and 300 miles.' );
		}
		$current = self::all();
		$materials = array();
		foreach ( $p['materials'] as $key => $row ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[a-z0-9_-]{1,32}$/D', $key ) || ! is_array( $row ) || ! self::pricing_number( $row['low'] ?? null, 0, PHP_FLOAT_MAX ) || ! self::pricing_number( $row['high'] ?? null, $row['low'], PHP_FLOAT_MAX ) || ! in_array( $row['quote'] ?? null, array( 0, 1 ), true ) ) {
				return new WP_Error( 'pricing_import', 'Each roof type needs a valid key, nonnegative rates with high at least low, and a Show price value of 0 or 1.' );
			}
			unset( $row['image_id'] );
			$clean = self::material_row( $row, $current['materials'][ $key ] ?? array() );
			if ( is_wp_error( $clean ) ) { return $clean; }
			$materials[ $key ] = $clean;
		}
		// Default identities are always retained by all(); require them for an exact restore.
		if ( array_diff_key( self::defaults()['materials'], $materials ) ) {
			return new WP_Error( 'pricing_import', 'The pricing file must include all built-in roof types.' );
		}
		foreach ( $p['areas'] as $row ) {
			if ( ! is_array( $row ) || ! is_string( $row['town'] ?? null ) || '' === trim( sanitize_text_field( $row['town'] ) ) || ! self::pricing_number( $row['lat'] ?? null, -90, 90 ) || ! self::pricing_number( $row['lng'] ?? null, -180, 180 ) || ! self::pricing_number( $row['mult'] ?? null, 0.5, 2 ) ) {
				return new WP_Error( 'pricing_import', 'Each service area needs a town, valid coordinates, and a multiplier between 0.5 and 2.' );
			}
		}
		$rows = self::pricing_rows( array( 'ar' => $p['areas'] ), $current );
		if ( is_wp_error( $rows ) ) { return $rows; }
		$bands = array();
		$thresholds = array();
		foreach ( $p['pitch_bands'] as $row ) {
			if ( ! is_array( $row ) || ! self::pricing_number( $row['max'] ?? null, 1, 99, true ) || ! self::pricing_number( $row['mult'] ?? null, 0.5, 3 ) ) {
				return new WP_Error( 'pricing_import', 'Pitch limits must be whole numbers between 1 and 99 with multipliers between 0.5 and 3.' );
			}
			if ( isset( $thresholds[ (int) $row['max'] ] ) ) {
				return new WP_Error( 'pricing_import', 'Pitch bands must have distinct limits.' );
			}
			$thresholds[ (int) $row['max'] ] = true;
			$bands[] = array( 'max' => (int) $row['max'], 'mult' => round( $row['mult'], 2 ) );
		}
		usort( $bands, function ( $a, $b ) { return $a['max'] <=> $b['max']; } );
		$cx = array();
		foreach ( array( 'simple_max', 'medium_max', 'simple', 'medium', 'complex' ) as $key ) {
			$is_max = in_array( $key, array( 'simple_max', 'medium_max' ), true );
			if ( ! self::pricing_number( $p['complexity'][ $key ] ?? null, $is_max ? 1 : 0.01, $is_max ? PHP_INT_MAX - 1 : PHP_FLOAT_MAX, $is_max ) ) {
				return new WP_Error( 'pricing_import', 'Complexity needs positive whole-number face limits and positive multipliers.' );
			}
			$cx[ $key ] = $is_max ? (int) $p['complexity'][ $key ] : round( $p['complexity'][ $key ], 2 );
		}
		if ( $cx['medium_max'] <= $cx['simple_max'] ) {
			return new WP_Error( 'pricing_import', 'The medium complexity face limit must exceed the simple limit.' );
		}
		return array( 'materials' => $materials, 'areas' => $rows['areas'], 'waste_pct' => round( $p['waste_pct'], 1 ), 'service_radius_mi' => (int) $p['service_radius_mi'], 'pitch_bands' => $bands, 'complexity' => $cx );
	}

	/** JSON numbers only: reject numeric strings, booleans, fractions in integer fields, and infinity. */
	private static function pricing_number( $value, $min, $max, $integer = false ) {
		return ( is_int( $value ) || is_float( $value ) )
			&& is_finite( (float) $value )
			&& $value >= $min && $value <= $max
			&& ( ! $integer || floor( $value ) == $value );
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
			// GHL form at the contact step (only when a form ID is set).
			'contactMode'    => ( 'ghl_form' === $s['contact_mode'] && '' !== trim( (string) $s['ghl_form_id'] ) ) ? 'ghl_form' : 'plugin',
			'ghlFormId'      => trim( (string) $s['ghl_form_id'] ),
			'ghlFormBase'    => 'https://api.leadconnectorhq.com/widget/form/',
			'ghlRoofTypeKey' => (string) $s['ghl_roof_type_key'],
		);
	}
}
