<?php
/**
 * The customer's report: https://site/?htr_roof_report=<token> (32-char random token, not guessable).
 * It is a print-ready page; "Download PDF" opens the browser's Save as PDF. No customer phone/email is shown on it.
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
			'disclaimer'   => HTR_RR_Settings::get( 'disclaimer' ),
			'createdAt'    => $r['created_at'],
		);
	}

	public static function maybe_render() {
		if ( empty( $_GET['htr_roof_report'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$r = HTR_RR_DB::get_by_token( sanitize_text_field( wp_unslash( $_GET['htr_roof_report'] ) ) ); // phpcs:ignore
		if ( ! $r ) {
			status_header( 404 );
			wp_die( 'This roof report link is not valid.', 'Report not found', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		$v = self::public_view( $r );
		include HTR_RR_DIR . 'templates/report.php';
		exit;
	}

	public static function money( $v ) {
		return '$' . number_format( (float) $v );
	}
}
