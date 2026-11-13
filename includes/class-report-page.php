<?php
/**
 * The customer's report: https://site/?htr_roof_report=<token> (32-char random token, not guessable).
 * It is a print-ready page; "Download PDF" opens the browser's Save as PDF. Customer contact details are included only in the report view.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTR_RR_Report_Page {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function url( $token ) {
		return add_query_arg( 'htr_roof_report', $token, home_url( '/' ) );
	}

	/** Older reports have no explicit source; manual dimensions identify calculator requests. */
	public static function request_source( array $report ) {
		if ( in_array( $report['request_source'] ?? '', array( 'calculator', 'instant_roof' ), true ) ) {
			return $report['request_source'];
		}
		$measurements = json_decode( $report['measurements'] ?? '', true );
		return is_array( $measurements ) && 'customer_entered' === ( $measurements['source'] ?? '' ) ? 'calculator' : 'instant_roof';
	}

	public static function source_label( array $report ) {
		return 'calculator' === self::request_source( $report ) ? 'Calculator' : 'Instant Roof';
	}

	/** What the browser may see about a report. */
	public static function public_view( array $r ) {
		$m = $r['measurements'] ? json_decode( $r['measurements'], true ) : null;
		$e = $r['estimate'] ? json_decode( $r['estimate'], true ) : null;
		$faces = array();
		if ( $m && ! empty( $m['faces_detail'] ) ) {
			foreach ( $m['faces_detail'] as $f ) {
				$faces[] = array( 'box' => $f['box'], 'pitch' => $f['pitch_x12'], 'area' => $f['area_sqft'] );
			}
		}
		$measured = null !== $r['area_sqft'];
		$manual = $m && isset( $m['source'] ) && 'customer_entered' === $m['source'];
		$mats     = HTR_RR_Settings::get( 'materials' );
		$label    = isset( $mats[ $r['material'] ]['label'] ) ? $mats[ $r['material'] ]['label'] : $r['material'];
		return array(
			'token'        => $r['token'],
			'url'          => self::url( $r['token'] ),
			'address'      => $r['address'],
			'firstName'    => $r['first_name'],
			'lat'          => (float) $r['lat'],
			'lng'          => (float) $r['lng'],
			'measured'     => $measured,
			'customerEntered' => $manual,
			'outsideArea'  => null !== $r['town_miles'] && (float) $r['town_miles'] > (float) HTR_RR_Settings::get( 'service_radius_mi' ),
			'serviceTowns' => implode( ', ', wp_list_pluck( HTR_RR_Settings::get( 'areas' ), 'town' ) ),
			'needsReview'  => 'completed' !== $r['status'],
			'areaSqft'     => $measured ? (int) $r['area_sqft'] : null,
			'squares'      => $m ? $m['squares'] : null,
			'pitch'        => $measured ? $r['pitch_x12'] . '/12' : null,
			'faces'        => $measured ? (int) $r['faces'] : null,
			'groundSqft'   => $m ? $m['ground_sqft'] : null,
			'imageryDate'  => $r['imagery_date'],
			'imageryQuality' => $r['imagery_quality'],
			'roofType'     => $e ? $e['material_label'] : $label,
			'priceLow'     => null !== $r['price_low'] ? (int) $r['price_low'] : null,
			'priceHigh'    => null !== $r['price_high'] ? (int) $r['price_high'] : null,
			'rateLow'      => $e ? $e['rate_low'] : null,
			'rateHigh'     => $e ? $e['rate_high'] : null,
			'complexity'   => $e ? $e['complexity'] : null,
			'facesDetail'  => $faces,
			'buildingBox'  => $m ? $m['building_box'] : null,
			'town'         => $r['town'],
			'phone'        => HTR_RR_Settings::get( 'company_phone' ),
			'disclaimer'   => $manual ? 'Preliminary estimate based on customer-entered dimensions, not aerial measurements. Final measurements and pricing require an on-site inspection.' : HTR_RR_Settings::get( 'disclaimer' ),
			'createdAt'    => $r['created_at'],
		);
	}

	/** Report-only contact details; excluded from the REST status payload. */
 public static function report_view( array $report ) {
  $view = self::public_view( $report );
  $view['customerDetails'] = array(
   'name' => trim( ( $report['first_name'] ?? '' ) . ' ' . ( $report['last_name'] ?? '' ) ),
   'email' => $report['email'] ?? '',
   'phone' => $report['phone'] ?? '',
  );
  return $view;
 }

	public static function maybe_render() {
		if ( isset( $_GET['htr_roof_preview'] ) ) {
			$token = is_string( $_GET['htr_roof_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['htr_roof_preview'] ) ) : '';
			$r = current_user_can( 'manage_options' ) && preg_match( '/^[a-f0-9]{32}$/', $token ) ? get_transient( 'htr_rr_preview_' . $token ) : false;
			if ( ! is_array( $r ) || (int) ( $r['_preview_user'] ?? 0 ) !== get_current_user_id() ) {
				wp_die( 'This temporary quote has expired or is unavailable to this administrator.', 'Quote preview unavailable', array( 'response' => 404 ) );
			}
			nocache_headers();
			header( 'Referrer-Policy: same-origin' );
			header( 'X-Robots-Tag: noindex, nofollow' );
			$v = self::report_view( $r );
			$v['temporaryPreview'] = true;
			$v['url'] = add_query_arg( 'htr_roof_preview', $token, home_url( '/' ) );
			include HTR_RR_DIR . 'templates/report.php';
			exit;
		}
		if ( empty( $_GET['htr_roof_report'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$r = HTR_RR_DB::get_by_token( sanitize_text_field( wp_unslash( $_GET['htr_roof_report'] ) ) ); // phpcs:ignore
		if ( ! $r || in_array( $r['lead_status'] ?? '', array( 'awaiting_form' ), true ) ) {
			status_header( 404 );
			wp_die( 'This roof report link is not valid.', 'Report not found', array( 'response' => 404 ) );
		}
		nocache_headers();
        header( 'Referrer-Policy: same-origin' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		$v = self::report_view( $r );
		include HTR_RR_DIR . 'templates/report.php';
		exit;
	}

	public static function money( $v ) {
		return '$' . number_format( (float) $v );
	}
}
