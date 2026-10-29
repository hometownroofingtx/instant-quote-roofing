<?php
/**
 * Estimate maths. Plain code, no AI: every figure can be traced from the measurements and the Pricing settings.
 * low/high = roof area x (1 + waste %) x rate per sq ft x pitch multiplier x complexity multiplier x regional multiplier,
 * rounded to the nearest $25.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTR_RR_Estimator {

	/** Degrees -> rise per 12 (6/12 roof = 26.6 degrees). */
	public static function pitch_x12( $degrees ) {
		return round( tan( deg2rad( max( 0, min( 89, (float) $degrees ) ) ) ) * 12 );
	}

	public static function pitch_mult( $x12, array $bands ) {
		foreach ( $bands as $b ) {
			if ( $x12 <= (float) $b['max'] ) {
				return (float) $b['mult'];
			}
		}
		return 1.0;
	}

	public static function complexity( $faces, array $c ) {
		if ( $faces <= (int) $c['simple_max'] ) {
			return array( 'Simple', (float) $c['simple'] );
		}
		if ( $faces <= (int) $c['medium_max'] ) {
			return array( 'Medium', (float) $c['medium'] );
		}
		return array( 'Complex', (float) $c['complex'] );
	}

	/** Great-circle distance in miles. */
	public static function miles( $lat1, $lng1, $lat2, $lng2 ) {
		$r = 3958.8;
		$a = sin( deg2rad( $lat2 - $lat1 ) / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( deg2rad( $lng2 - $lng1 ) / 2 ) ** 2;
		return 2 * $r * asin( min( 1, sqrt( $a ) ) );
	}

	/** Nearest service town and distance. */
	public static function nearest_area( $lat, $lng, array $areas ) {
		$best = null;
		foreach ( $areas as $a ) {
			$d = self::miles( $lat, $lng, (float) $a['lat'], (float) $a['lng'] );
			if ( ! $best || $d < $best['miles'] ) {
				$best = array( 'town' => $a['town'], 'miles' => round( $d, 1 ), 'mult' => (float) $a['mult'] );
			}
		}
		return $best;
	}

	/**
	 * @param array  $m        Measurements from HTR_RR_Solar::parse() (area_sqft, pitch_x12, faces).
	 * @param string $material Material key.
	 * @param array  $s        Settings.
	 * @param float  $region   Regional multiplier.
	 * @return array estimate (low/high null when the material has no automatic price).
	 */
	public static function estimate( array $m, $material, array $s, $region = 1.0 ) {
		$mat = isset( $s['materials'][ $material ] ) ? $s['materials'][ $material ] : null;
		if ( ! $mat ) {
			return array( 'ok' => false, 'reason' => 'unknown roof type' );
		}
		$area  = (float) $m['area_sqft'];
		$billed = $area * ( 1 + max( 0, (float) $s['waste_pct'] ) / 100 );
		$pm    = self::pitch_mult( (int) $m['pitch_x12'], $s['pitch_bands'] );
		list( $cx_label, $cm ) = self::complexity( (int) $m['faces'], $s['complexity'] );
		$mult  = $pm * $cm * (float) $region;
		$out   = array(
			'ok'              => true,
			'material'        => $material,
			'material_label'  => $mat['label'],
			'area_sqft'       => round( $area ),
			'billed_sqft'     => round( $billed ),
			'pitch_mult'      => $pm,
			'complexity'      => $cx_label,
			'complexity_mult' => $cm,
			'region_mult'     => (float) $region,
			'rate_low'        => (float) $mat['low'],
			'rate_high'       => (float) $mat['high'],
			'low'             => null,
			'high'            => null,
		);
		if ( empty( $mat['quote'] ) || (float) $mat['low'] <= 0 || (float) $mat['high'] < (float) $mat['low'] || $area <= 0 ) {
			$out['reason'] = 'This roof type is priced after an on-site inspection.';
			return $out;
		}
		$out['low']  = self::round25( $billed * (float) $mat['low'] * $mult );
		$out['high'] = self::round25( $billed * (float) $mat['high'] * $mult );
		return $out;
	}

	public static function round25( $v ) {
		return (int) ( round( $v / 25 ) * 25 );
	}
}
