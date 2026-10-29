<?php
/**
 * Sends each lead to n8n (which creates/updates the GHL contact). Body is JSON. The shared secret goes in header
 * X-HTR-Key (n8n webhook Header Auth) and also signs the body (HMAC-SHA256, header X-HTR-Signature). A failed send is retried by WP-Cron every 15 minutes, up to
 * 12 tries (3 hours); after that the report shows "Lead not delivered" on the dashboard and an email goes to the
 * notify address. A lead is never lost silently.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTR_RR_Leads {
	const HOOK      = 'htr_rr_retry_leads';
	const MAX_TRIES = 12;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'every15' ) );
		add_action( self::HOOK, array( __CLASS__, 'retry_pending' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	public static function every15( $s ) {
		$s['htr_rr_15min'] = array( 'interval' => 900, 'display' => 'Every 15 minutes (Roof Reports)' );
		return $s;
	}

	public static function schedule() {
		add_filter( 'cron_schedules', array( __CLASS__, 'every15' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 900, 'htr_rr_15min', self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** Build the payload sent to n8n. Field names are the contract with the n8n workflow. */
	public static function payload( array $r ) {
		$m = $r['measurements'] ? json_decode( $r['measurements'], true ) : array();
		$e = $r['estimate'] ? json_decode( $r['estimate'], true ) : array();
		return array(
			'event'           => 'roof_report',
			'report_id'       => (int) $r['id'],
			'report_url'      => HTR_RR_Report_Page::url( $r['token'] ),
			'created_at'      => $r['created_at'] . 'Z',
			'status'          => $r['status'],
			'review_reason'   => $r['review_reason'],
			'first_name'      => $r['first_name'],
			'last_name'       => $r['last_name'],
			'email'           => $r['email'],
			'phone'           => $r['phone'],
			'interests'       => array_values( array_filter( explode( ',', $r['interests'] ) ) ),
			'sms_consent_service'   => false !== strpos( $r['sms_consent'], 'service' ),
			'sms_consent_marketing' => false !== strpos( $r['sms_consent'], 'marketing' ),
			'ghl_form_id'     => HTR_RR_Settings::get( 'ghl_form_id' ),
			'address'         => $r['address'],
			'city'            => $r['city'],
			'zip'             => $r['zip'],
			'lat'             => (float) $r['lat'],
			'lng'             => (float) $r['lng'],
			'roof_type'       => isset( $e['material_label'] ) ? $e['material_label'] : ( isset( HTR_RR_Settings::get( 'materials' )[ $r['material'] ]['label'] ) ? HTR_RR_Settings::get( 'materials' )[ $r['material'] ]['label'] : $r['material'] ),
			'roof_area_sqft'  => $r['area_sqft'] !== null ? (int) $r['area_sqft'] : null,
			'roof_squares'    => isset( $m['squares'] ) ? $m['squares'] : null,
			'pitch'           => $r['pitch_x12'] !== null ? $r['pitch_x12'] . '/12' : '',
			'roof_faces'      => $r['faces'] !== null ? (int) $r['faces'] : null,
			'imagery_quality' => $r['imagery_quality'],
			'imagery_date'    => $r['imagery_date'],
			'price_low'       => $r['price_low'] !== null ? (int) $r['price_low'] : null,
			'price_high'      => $r['price_high'] !== null ? (int) $r['price_high'] : null,
			'nearest_town'    => $r['town'],
			'outside_service_area' => null !== $r['town_miles'] && (float) $r['town_miles'] > (float) HTR_RR_Settings::get( 'service_radius_mi' ),
			'town_miles'      => $r['town_miles'] !== null ? (float) $r['town_miles'] : null,
			'source_url'      => $r['source_url'],
			'site'            => home_url(),
		);
	}

	/** @return bool delivered */
	public static function send( $id ) {
		$r   = HTR_RR_DB::get_report( $id );
		$url = HTR_RR_Settings::get( 'n8n_webhook_url' );
		$sec = HTR_RR_Settings::get( 'n8n_secret' );
		if ( ! $r ) {
			return false;
		}
		if ( 'sent' === $r['lead_status'] ) {
			return true; // never send the same lead twice.
		}
		$tries = (int) $r['lead_tries'] + 1;
		if ( ! $url || ! $sec ) {
			HTR_RR_DB::update_report( $id, array( 'lead_status' => 'pending', 'lead_tries' => $tries, 'lead_error' => 'n8n webhook URL or secret not set' ) );
			HTR_RR_DB::log( 'error', 'lead', 'Lead not sent: n8n webhook URL or secret is not set (Roof Reports > Settings)', $id );
			self::maybe_give_up( $id, $tries );
			return false;
		}
		$body = wp_json_encode( self::payload( $r ) );
		$t0   = microtime( true );
		$res  = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'    => 'application/json',
					'X-HTR-Key'       => $sec, // checked by the n8n webhook (Header Auth credential). HTTPS only.
					'X-HTR-Signature' => hash_hmac( 'sha256', $body, $sec ),
					'X-HTR-Report'    => (string) $id,
				),
				'body'    => $body,
			)
		);
		$ms   = (int) ( ( microtime( true ) - $t0 ) * 1000 );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( $code >= 200 && $code < 300 ) {
			HTR_RR_DB::update_report( $id, array( 'lead_status' => 'sent', 'lead_tries' => $tries, 'lead_error' => '' ) );
			HTR_RR_DB::log( 'info', 'lead', 'Lead delivered to n8n', $id, $code, $ms );
			return true;
		}
		$err = is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . $code;
		HTR_RR_DB::update_report( $id, array( 'lead_status' => 'pending', 'lead_tries' => $tries, 'lead_error' => substr( $err, 0, 250 ) ) );
		HTR_RR_DB::log( 'error', 'lead', 'Lead delivery failed (try ' . $tries . '): ' . $err, $id, $code ? $code : null, $ms );
		self::maybe_give_up( $id, $tries );
		return false;
	}

	private static function maybe_give_up( $id, $tries ) {
		if ( $tries < self::MAX_TRIES ) {
			return;
		}
		HTR_RR_DB::update_report( $id, array( 'lead_status' => 'failed' ) );
		$to = HTR_RR_Settings::get( 'notify_email' );
		if ( $to ) {
			wp_mail( $to, '[Roof Reports] Lead not delivered to GHL - report #' . $id, "A roof report lead could not be delivered to n8n/GHL after {$tries} tries.\n\nOpen WordPress > Roof Reports > Reports and resend report #{$id}, or add the customer to GHL by hand.\n\n" . admin_url( 'admin.php?page=htr-rr-reports&view=' . (int) $id ) );
		}
	}

	public static function retry_pending() {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . HTR_RR_DB::reports() . " WHERE lead_status = 'pending' AND lead_tries < %d ORDER BY id ASC LIMIT 20", self::MAX_TRIES ) );
		foreach ( $ids as $id ) {
			self::send( (int) $id );
		}
	}
}
