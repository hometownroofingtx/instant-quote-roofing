<?php
/**
 * Tables: {prefix}htr_rr_reports (one row per report request) and {prefix}htr_rr_log (API calls, errors).
 * Uninstalling the plugin never drops these tables: reports and leads are kept.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTR_RR_DB {
	const SCHEMA = 3;

	public static function reports() {
		global $wpdb;
		return $wpdb->prefix . 'htr_rr_reports';
	}

	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'htr_rr_log';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::reports() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token VARCHAR(40) NOT NULL,
			created_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			first_name VARCHAR(80) NOT NULL DEFAULT '',
			last_name VARCHAR(80) NOT NULL DEFAULT '',
			email VARCHAR(160) NOT NULL DEFAULT '',
			phone VARCHAR(40) NOT NULL DEFAULT '',
			interests VARCHAR(200) NOT NULL DEFAULT '',
			sms_consent VARCHAR(40) NOT NULL DEFAULT '',
			address VARCHAR(255) NOT NULL DEFAULT '',
			city VARCHAR(120) NOT NULL DEFAULT '',
			zip VARCHAR(20) NOT NULL DEFAULT '',
			lat DECIMAL(10,7) NULL,
			lng DECIMAL(10,7) NULL,
			material VARCHAR(30) NOT NULL DEFAULT '',
			area_sqft INT NULL,
			pitch_x12 TINYINT NULL,
			faces SMALLINT NULL,
			imagery_quality VARCHAR(10) NOT NULL DEFAULT '',
			imagery_date VARCHAR(12) NOT NULL DEFAULT '',
			price_low INT NULL,
			price_high INT NULL,
			town VARCHAR(40) NOT NULL DEFAULT '',
			town_miles DECIMAL(6,1) NULL,
			review_reason VARCHAR(255) NOT NULL DEFAULT '',
			measurements LONGTEXT NULL,
			estimate LONGTEXT NULL,
			lead_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			lead_tries TINYINT NOT NULL DEFAULT 0,
			lead_error VARCHAR(255) NOT NULL DEFAULT '',
			source_url VARCHAR(255) NOT NULL DEFAULT '',
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY created_at (created_at),
			KEY status (status),
			KEY lead_status (lead_status)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . self::log_table() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			level VARCHAR(10) NOT NULL DEFAULT 'info',
			source VARCHAR(30) NOT NULL DEFAULT '',
			report_id BIGINT UNSIGNED NULL,
			message VARCHAR(500) NOT NULL DEFAULT '',
			http_code SMALLINT NULL,
			ms INT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY level (level)
			) $c;"
		);
		update_option( 'htr_rr_schema', self::SCHEMA, false );
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( 'htr_rr_schema', 0 ) < self::SCHEMA ) {
			self::install();
		}
	}

	/** Write a log line. Never pass keys, secrets or full API responses in $message. */
	public static function log( $level, $source, $message, $report_id = null, $http_code = null, $ms = null ) {
		global $wpdb;
		$wpdb->insert(
			self::log_table(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'level'      => substr( $level, 0, 10 ),
				'source'     => substr( $source, 0, 30 ),
				'report_id'  => $report_id,
				'message'    => substr( wp_strip_all_tags( (string) $message ), 0, 500 ),
				'http_code'  => $http_code,
				'ms'         => $ms,
			)
		);
	}

	public static function get_report( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::reports() . ' WHERE id = %d', $id ), ARRAY_A );
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $token ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::reports() . ' WHERE token = %s', $token ), ARRAY_A );
	}

	public static function update_report( $id, array $data ) {
		global $wpdb;
		return $wpdb->update( self::reports(), $data, array( 'id' => (int) $id ) );
	}
}
