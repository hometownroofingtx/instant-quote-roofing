<?php
/**
 * Google Solar API (buildingInsights:findClosest): roof area, faces and pitch from aerial imagery, by coordinates.
 * Free tier: 10,000 calls a month (Google pricing page, 28 Oct 2026). Called from the server only, with the server key.
 * Asks for MEDIUM imagery first, then BASE. No coverage (HTTP 404) = the report is saved as "measured on site".
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTR_RR_Solar {
	const ENDPOINT = 'https://solar.googleapis.com/v1/buildingInsights:findClosest';

	/**
	 * Cached for 6 hours per spot (~1 m): the step-2 preview and the final report share one Google call.
	 * Only successful measurements and "no coverage" answers are cached (never key/quota errors).
	 */
	public static function measure( $lat, $lng, $report_id = null ) {
		$ck  = 'htr_rr_m_' . md5( round( (float) $lat, 5 ) . ',' . round( (float) $lng, 5 ) );
		$hit = get_transient( $ck );
		if ( is_array( $hit ) ) {
			HTR_RR_DB::log( 'info', 'solar', 'Used cached measurement (no Google call)', $report_id );
			return $hit;
		}
		$m = self::measure_live( $lat, $lng, $report_id );
		if ( ! empty( $m['ok'] ) || ( isset( $m['reason'] ) && 'no_coverage' === $m['reason'] ) ) {
			set_transient( $ck, $m, 6 * HOUR_IN_SECONDS );
		}
		return $m;
	}

	/** @return array ok + measurements, or ok=false + reason (never throws). */
	private static function measure_live( $lat, $lng, $report_id = null ) {
		$key = HTR_RR_Settings::get( 'google_server_key' );
		if ( ! $key ) {
			return array( 'ok' => false, 'reason' => 'no_key', 'message' => 'Google server key is not set (Roof Reports > Settings).' );
		}
		$first  = HTR_RR_Settings::get( 'required_quality' ) === 'HIGH' ? 'HIGH' : 'MEDIUM';
		$levels = array_unique( array( $first, 'MEDIUM', 'BASE' ) );
		$last   = null;
		foreach ( $levels as $q ) {
			$url = add_query_arg(
				array(
					'location.latitude'  => round( (float) $lat, 7 ),
					'location.longitude' => round( (float) $lng, 7 ),
					'requiredQuality'    => $q,
					'key'                => $key,
				),
				apply_filters( 'htr_rr_solar_endpoint', self::ENDPOINT ) // filter exists for offline tests only.
			);
			$t0  = microtime( true );
			$res = wp_remote_get( $url, array( 'timeout' => 20 ) );
			$ms  = (int) ( ( microtime( true ) - $t0 ) * 1000 );
			if ( is_wp_error( $res ) ) {
				HTR_RR_DB::log( 'error', 'solar', 'Network error: ' . $res->get_error_message(), $report_id, null, $ms );
				$last = array( 'ok' => false, 'reason' => 'network', 'message' => 'Could not reach Google (network).' );
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( 200 === $code && is_array( $body ) ) {
				HTR_RR_DB::log( 'info', 'solar', 'Measured with ' . $q . ' imagery', $report_id, $code, $ms );
				return self::parse( $body, $lat, $lng );
			}
			$msg = is_array( $body ) && isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
			HTR_RR_DB::log( 404 === $code ? 'info' : 'error', 'solar', $q . ': ' . $msg, $report_id, $code, $ms );
			if ( 404 === $code ) {
				$last = array( 'ok' => false, 'reason' => 'no_coverage', 'message' => 'No aerial roof data for this address.' );
				continue; // try the next (lower) quality.
			}
			// 400/403/429/5xx: key, quota or Google problem - stop, do not loop.
			return array( 'ok' => false, 'reason' => 'api_error', 'message' => 'Google Solar API error (HTTP ' . $code . ').', 'http' => $code );
		}
		return $last ? $last : array( 'ok' => false, 'reason' => 'no_coverage', 'message' => 'No aerial roof data for this address.' );
	}

	/** Turn a buildingInsights response into the measurements the report uses. Public for tests. */
	public static function parse( array $b, $lat, $lng ) {
		$sp    = isset( $b['solarPotential'] ) ? $b['solarPotential'] : array();
		$whole = isset( $sp['wholeRoofStats']['areaMeters2'] ) ? (float) $sp['wholeRoofStats']['areaMeters2'] : 0;
		$segs  = isset( $sp['roofSegmentStats'] ) && is_array( $sp['roofSegmentStats'] ) ? $sp['roofSegmentStats'] : array();
		$sum   = 0;
		$big   = null;
		$wdeg  = 0;
		$faces = array();
		foreach ( $segs as $s ) {
			$a = isset( $s['stats']['areaMeters2'] ) ? (float) $s['stats']['areaMeters2'] : 0;
			$p = isset( $s['pitchDegrees'] ) ? (float) $s['pitchDegrees'] : 0;
			if ( $a <= 0 ) {
				continue;
			}
			$sum  += $a;
			$wdeg += $a * $p;
			if ( ! $big || $a > $big['a'] ) {
				$big = array( 'a' => $a, 'p' => $p );
			}
			$faces[] = array(
				'area_sqft' => round( $a * 10.7639 ),
				'pitch_x12' => HTR_RR_Estimator::pitch_x12( $p ),
				'azimuth'   => isset( $s['azimuthDegrees'] ) ? round( (float) $s['azimuthDegrees'] ) : null,
				'box'       => isset( $s['boundingBox'] ) ? $s['boundingBox'] : null,
			);
		}
		$area_m2 = $whole > 0 ? $whole : $sum;
		$date    = isset( $b['imageryDate'] ) ? sprintf( '%04d-%02d-%02d', $b['imageryDate']['year'], $b['imageryDate']['month'], isset( $b['imageryDate']['day'] ) ? $b['imageryDate']['day'] : 1 ) : '';
		$box     = isset( $b['boundingBox'] ) ? $b['boundingBox'] : null;
		$inside  = $box ? ( $lat >= $box['sw']['latitude'] && $lat <= $box['ne']['latitude'] && $lng >= $box['sw']['longitude'] && $lng <= $box['ne']['longitude'] ) : null;
		$center  = isset( $b['center'] ) ? $b['center'] : null;
		$off_m   = $center ? round( HTR_RR_Estimator::miles( $lat, $lng, $center['latitude'], $center['longitude'] ) * 1609.34 ) : null;
		return array(
			'ok'              => $area_m2 > 0,
			'reason'          => $area_m2 > 0 ? '' : 'no_roof',
			'message'         => $area_m2 > 0 ? '' : 'Google returned no roof area for this building.',
			'area_sqft'       => (int) round( $area_m2 * 10.7639 ),
			'ground_sqft'     => isset( $sp['wholeRoofStats']['groundAreaMeters2'] ) ? (int) round( $sp['wholeRoofStats']['groundAreaMeters2'] * 10.7639 ) : null,
			'squares'         => round( $area_m2 * 10.7639 / 100, 1 ),
			'faces'           => count( $faces ),
			'pitch_x12'       => $big ? HTR_RR_Estimator::pitch_x12( $big['p'] ) : 0, // predominant = pitch of the largest face
			'avg_pitch_x12'   => $sum > 0 ? HTR_RR_Estimator::pitch_x12( $wdeg / $sum ) : 0,
			'imagery_quality' => isset( $b['imageryQuality'] ) ? $b['imageryQuality'] : '',
			'imagery_date'    => $date,
			'marker_inside'   => $inside,
			'marker_off_m'    => $off_m,
			'building_box'    => $box,
			'faces_detail'    => $faces,
		);
	}
}
