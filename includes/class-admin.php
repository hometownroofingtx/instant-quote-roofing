<?php
/**
 * WordPress admin: Roof Reports menu (Overview, Reports, Pricing, Calculator, Settings, Logs).
 * Only administrators (manage_options). Every save is nonce-checked.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTR_RR_Admin {
	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_htr_rr_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_htr_rr_resend', array( __CLASS__, 'resend' ) );
		add_action( 'admin_post_htr_rr_test_solar', array( __CLASS__, 'test_solar' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_menu_page( 'Roof Reports', 'Roof Reports', self::CAP, 'htr-rr', array( __CLASS__, 'overview' ), 'dashicons-admin-home', 26 );
		add_submenu_page( 'htr-rr', 'Overview', 'Overview', self::CAP, 'htr-rr', array( __CLASS__, 'overview' ) );
		add_submenu_page( 'htr-rr', 'Reports', 'Reports', self::CAP, 'htr-rr-reports', array( __CLASS__, 'reports' ) );
		add_submenu_page( 'htr-rr', 'Pricing', 'Pricing', self::CAP, 'htr-rr-pricing', array( __CLASS__, 'pricing' ) );
		add_submenu_page( 'htr-rr', 'Calculator', 'Calculator', self::CAP, 'htr-rr-calc', array( __CLASS__, 'calculator' ) );
		add_submenu_page( 'htr-rr', 'Settings', 'Settings', self::CAP, 'htr-rr-settings', array( __CLASS__, 'settings' ) );
		add_submenu_page( 'htr-rr', 'Logs', 'Logs', self::CAP, 'htr-rr-logs', array( __CLASS__, 'logs' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'htr-rr' ) ) {
			return;
		}
		wp_enqueue_style( 'htr-rr-admin', HTR_RR_URL . 'assets/css/admin.css', array(), HTR_RR_VERSION . '.' . filemtime( HTR_RR_DIR . 'assets/css/admin.css' ) );
		if ( false !== strpos( $hook, 'htr-rr-pricing' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'htr-rr-pricing-admin', HTR_RR_URL . 'assets/js/pricing-admin.js', array(), HTR_RR_VERSION . '.' . filemtime( HTR_RR_DIR . 'assets/js/pricing-admin.js' ), true );
		}
	}

	private static function head( $title, $sub = '' ) {
		echo '<div class="wrap htr-rr-admin"><div class="htr-rr-ah htr-overview-header"><img src="' . esc_url( HTR_RR_URL . 'assets/img/logo.png' ) . '" alt="Hometown Roofing"><div class="htr-header-copy"><span class="htr-eyebrow">Roof report management</span><h1>' . esc_html( $title ) . '</h1>' . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) . '</div>';
		$current = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'htr-rr';
		$pages = array(
			'htr-rr' => array( 'Overview', 'dashboard' ),
			'htr-rr-reports' => array( 'Reports', 'media-document' ),
			'htr-rr-pricing' => array( 'Pricing', 'tag' ),
			'htr-rr-calc' => array( 'Calculator', 'calculator' ),
			'htr-rr-settings' => array( 'Settings', 'admin-generic' ),
			'htr-rr-logs' => array( 'Logs', 'list-view' ),
		);
		echo '<nav class="htr-quick-links" aria-label="Roof report management">';
		foreach ( $pages as $slug => $page ) {
			$active = $slug === $current;
			echo '<a class="button' . ( $active ? ' button-primary' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . ' href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><span class="dashicons dashicons-' . esc_attr( $page[1] ) . '" aria-hidden="true"></span>' . esc_html( $page[0] ) . '</a>';
		}
		echo '</nav>';
		echo '</div>';
		if ( ! empty( $_GET['saved'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
		}
		if ( ! empty( $_GET['msg'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ) . '</p></div>'; // phpcs:ignore
		}
	}

	private static function status_pill( $s ) {
		$map = array( 'completed' => 'ok', 'review' => 'warn', 'processing' => 'info', 'pending' => 'info', 'sent' => 'ok', 'failed' => 'bad' );
		$labels = array( 'completed' => 'Completed', 'review' => 'Needs review', 'processing' => 'Processing', 'pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Delivery failed' );
		return '<span class="htr-pill htr-pill--' . esc_attr( isset( $map[ $s ] ) ? $map[ $s ] : 'info' ) . ' htr-status--' . esc_attr( sanitize_key( $s ) ) . '">' . esc_html( $labels[ $s ] ?? ucfirst( $s ) ) . '</span>';
	}

	/* ---------------- Overview ---------------- */
	public static function overview() {
		global $wpdb;
		$t   = HTR_RR_DB::reports();
		// Portable SQL (no MySQL-only date functions): the 30-day cutoff is computed in PHP.
		$cut = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$c   = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN status='review' THEN 1 ELSE 0 END) AS review, SUM(CASE WHEN lead_status<>'sent' THEN 1 ELSE 0 END) AS lead_open, SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS last30 FROM $t", $cut ), ARRAY_A ); // phpcs:ignore
		$sum = $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM((price_low+price_high)/2.0),0) FROM $t WHERE price_low IS NOT NULL AND created_at >= %s", $cut ) ); // phpcs:ignore
		self::head( 'Instant Roof Report', 'Roof estimates, customer reports and lead delivery in one place.' );
		$s = HTR_RR_Settings::all();
		echo '<div class="htr-tiles">';
		self::tile( 'Total reports', (int) $c['total'], 'Last 30 days: ' . (int) $c['last30'], 'media-document', 'blue' );
		self::tile( 'Completed', (int) $c['completed'], 'Measured and priced automatically', 'yes-alt', 'green' );
		self::tile( 'Needs review', (int) $c['review'], 'Measurement or pricing needs attention', 'visibility', 'amber' );
		self::tile( 'Leads not yet in GHL', (int) $c['lead_open'], 'Automatic retries every 15 minutes, up to 3 hours', 'groups', 'blue' );
		self::tile( 'Estimated value (30 days)', '$' . number_format( (float) $sum ), 'Combined estimate midpoints, not revenue', 'chart-line', 'blue' );
		echo '</div><section class="htr-box htr-systems htr-systems-strip"><div class="htr-box-heading"><div><h2>System status</h2><p>Saved setup and scheduled jobs. Connections are not tested live.</p></div><a href="' . esc_url( admin_url( 'admin.php?page=htr-rr-settings' ) ) . '">Settings &rarr;</a></div><dl class="htr-health-list">';
		$last = function ( $src, $lvl ) use ( $wpdb ) {
			return $wpdb->get_row( $wpdb->prepare( 'SELECT created_at, message FROM ' . HTR_RR_DB::log_table() . ' WHERE source=%s AND level=%s ORDER BY id DESC LIMIT 1', $src, $lvl ), ARRAY_A );
		};
		$rows = array(
			array( 'Maps & address search', $s['google_browser_key'] ? 'Set' : 'Missing' ),
			array( 'Roof measurements', $s['google_server_key'] ? 'Set' : 'Missing' ),
			array( 'GHL lead integration', ( $s['n8n_webhook_url'] && $s['n8n_secret'] ) ? 'Set' : 'Missing' ),
			array( 'Lead retry job', wp_next_scheduled( HTR_RR_Leads::HOOK ) ? 'Scheduled' : 'Not scheduled' ),
		);
		foreach ( array( 'solar' => 'Last Google Solar error', 'lead' => 'Last lead delivery error' ) as $src => $label ) {
			$e      = $last( $src, 'error' );
			if ( $e ) {
				$rows[] = array( $label, $e['created_at'] . ' UTC: ' . $e['message'], 'error' );
			}
		}
		foreach ( $rows as $r ) {
			$bad = in_array( $r[1], array( 'Missing', 'Not scheduled' ), true );
			echo '<div class="htr-health-row' . ( isset( $r[2] ) && 'error' === $r[2] ? ' htr-health-row--error' : '' ) . '"><dt>' . esc_html( $r[0] ) . '</dt><dd>';
			if ( isset( $r[2] ) && 'error' === $r[2] ) {
				echo '<details class="htr-health-error"><summary>View last recorded error</summary><p>' . esc_html( $r[1] ) . '</p><a href="' . esc_url( admin_url( 'admin.php?page=htr-rr-logs' ) ) . '">Open logs &rarr;</a></details>';
			} elseif ( in_array( $r[1], array( 'Set', 'Scheduled', 'Missing', 'Not scheduled' ), true ) ) {
				echo '<span class="htr-pill htr-pill--' . ( $bad ? 'bad' : 'ok' ) . '">' . esc_html( $r[1] ) . '</span>';
			} else {
				echo '<span class="htr-health-value">' . esc_html( $r[1] ) . '</span>';
			}
			echo '</dd></div>';
		}
		echo '</dl></section><section class="htr-box htr-recent"><div class="htr-box-heading"><div><h2>Recent reports</h2><p>Up to 8 latest reports &middot; Times shown in UTC</p></div><a href="' . esc_url( admin_url( 'admin.php?page=htr-rr-reports' ) ) . '">View all &rarr;</a></div>';
		self::table( $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC LIMIT 8", ARRAY_A ) ); // phpcs:ignore
		echo '</section></div>';
	}

	private static function tile( $label, $val, $sub, $icon = 'media-document', $tone = 'blue' ) {
		echo '<div class="htr-tile htr-tile--' . esc_attr( $tone ) . '"><div class="htr-tile-top"><span>' . esc_html( $label ) . '</span><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span></div><b>' . esc_html( $val ) . '</b><small>' . esc_html( $sub ) . '</small></div>';
	}

	private static function table( $rows ) {
		if ( ! $rows ) {
			echo '<div class="htr-empty"><span class="dashicons dashicons-media-document" aria-hidden="true"></span><h3>No reports yet</h3><p>Submitted roof reports will appear here with their estimate and lead delivery status.</p></div>';
			return;
		}
		echo '<div class="htr-report-table-wrap"><table class="widefat htr-report-table"><thead><tr><th scope="col">Report</th><th scope="col">Customer &amp; property</th><th scope="col">Roof</th><th scope="col">Estimate</th><th scope="col">Report status</th><th scope="col">GHL delivery</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$est = null !== $r['price_low'] ? '$' . number_format( $r['price_low'] ) . ' – $' . number_format( $r['price_high'] ) : '—';
			echo '<tr><td><strong>#' . (int) $r['id'] . '</strong><span class="htr-cell-sub htr-date">' . esc_html( $r['created_at'] ) . ' UTC</span></td><td class="htr-property"><strong>' . esc_html( $r['first_name'] . ' ' . $r['last_name'] ) . '</strong><span class="htr-cell-sub">' . esc_html( $r['address'] ) . '</span></td><td><span class="htr-material">' . esc_html( $r['material'] ) . '</span><span class="htr-cell-sub">' . ( null !== $r['area_sqft'] ? esc_html( number_format( $r['area_sqft'] ) ) . ' sq ft' : '—' ) . '</span></td><td class="htr-estimate">' . esc_html( $est ) . '</td><td>' . self::status_pill( $r['status'] ) . '</td><td>' . self::status_pill( $r['lead_status'] ) . '</td><td><a class="htr-view-link" href="' . esc_url( admin_url( 'admin.php?page=htr-rr-reports&view=' . (int) $r['id'] ) ) . '" aria-label="View report ' . (int) $r['id'] . '">View &rarr;</a></td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table></div>';
	}

	/* ---------------- Reports ---------------- */
	public static function reports() {
		global $wpdb;
		$view = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0; // phpcs:ignore
		if ( $view ) {
			$r = HTR_RR_DB::get_report( $view );
			$report_context = $r ? trim( $r['first_name'] . ' ' . $r['last_name'] ) . ( ! empty( $r['address'] ) ? ' · ' . $r['address'] : '' ) : '';
			self::head( 'Report #' . $view, $report_context );
			if ( ! $r ) {
				echo '<p>Not found.</p></div>';
				return;
			}
			echo '<nav class="htr-detail-actions" aria-label="Report actions"><a class="htr-detail-back" href="' . esc_url( admin_url( 'admin.php?page=htr-rr-reports' ) ) . '"><span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span> All reports</a><span class="htr-detail-action-divider" aria-hidden="true"></span><a class="button button-primary" href="' . esc_url( HTR_RR_Report_Page::url( $r['token'] ) ) . '" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> Open customer report<span class="screen-reader-text"> (opens in a new tab)</span></a><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=htr_rr_resend&id=' . $view ), 'htr_rr_resend_' . $view ) ) . '"><span class="dashicons dashicons-update" aria-hidden="true"></span> Resend lead to GHL</a></nav>';
			self::report_details( $r );
			self::log_table( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HTR_RR_DB::log_table() . ' WHERE report_id=%d ORDER BY id', $view ), ARRAY_A ) );
			echo '</div>';
			return;
		}
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore
		$where  = $status ? $wpdb->prepare( ' WHERE status=%s', $status ) : '';
		self::head( 'Reports' );
		echo '<nav class="htr-status-filters" aria-label="Filter reports by status">';
		foreach ( array( '' => 'All', 'completed' => 'Completed', 'review' => 'Needs review', 'processing' => 'Processing' ) as $k => $l ) {
			echo '<a class="htr-status-filter htr-status-filter--' . esc_attr( $k ?: 'all' ) . ( $k === $status ? ' is-current' : '' ) . '"' . ( $k === $status ? ' aria-current="page"' : '' ) . ' href="' . esc_url( admin_url( 'admin.php?page=htr-rr-reports' . ( $k ? '&status=' . $k : '' ) ) ) . '"><span class="htr-filter-dot" aria-hidden="true"></span>' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';
		self::table( $wpdb->get_results( 'SELECT * FROM ' . HTR_RR_DB::reports() . $where . ' ORDER BY id DESC LIMIT 200', ARRAY_A ) ); // phpcs:ignore
		echo '<p class="description">Newest 200 shown.</p></div>';
	}

	private static function report_details( array $r ) {
		$estimate = json_decode( $r['estimate'] ?? '', true );
		$estimate = is_array( $estimate ) ? $estimate : array();
		$settings = HTR_RR_Settings::all();
		$material = $estimate['material_label'] ?? ( $settings['materials'][ $r['material'] ]['label'] ?? ucfirst( $r['material'] ) );
		$price = null !== $r['price_low'] && null !== $r['price_high'] ? '$' . number_format( $r['price_low'] ) . ' – $' . number_format( $r['price_high'] ) : 'Not available';
		echo '<div class="htr-detail-summary"><div><span>Report status</span>' . self::status_pill( $r['status'] ) . '</div><div><span>GHL delivery</span>' . self::status_pill( $r['lead_status'] ) . '</div><div><span>Estimated price range</span><strong>' . esc_html( $price ) . '</strong></div><div><span>Created (UTC)</span><strong class="htr-detail-created">' . esc_html( $r['created_at'] ) . '</strong></div></div>';
		if ( ! empty( $r['review_reason'] ) ) {
			echo '<div class="htr-detail-alert"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><div><strong>Review note</strong><p>' . esc_html( $r['review_reason'] ) . '</p></div></div>';
		}
		echo '<div class="htr-detail-grid">';
		self::detail_card( 'Customer details', array(
			'Name' => trim( $r['first_name'] . ' ' . $r['last_name'] ), 'Email' => $r['email'], 'Phone' => $r['phone'],
			'Interested in' => ucwords( str_replace( ',', ', ', $r['interests'] ) ),
			'SMS consent recorded' => ucwords( str_replace( ',', ', ', $r['sms_consent'] ) ),
		) );
		self::detail_card( 'Property & service area', array(
			'Address' => $r['address'], 'City' => $r['city'], 'ZIP code' => $r['zip'], 'Nearest service town' => $r['town'],
			'Distance to town' => null !== $r['town_miles'] ? $r['town_miles'] . ' miles' : '',
			'Coordinates' => null !== $r['lat'] && null !== $r['lng'] ? $r['lat'] . ', ' . $r['lng'] : '',
		) );
		self::detail_card( 'Roof measurements', array(
			'Roof type' => $material,
			'Roof area' => null !== $r['area_sqft'] ? number_format( $r['area_sqft'] ) . ' sq ft' : '',
			'Roof pitch' => null !== $r['pitch_x12'] ? $r['pitch_x12'] . '/12' : '', 'Roof faces' => $r['faces'],
			'Imagery quality' => $r['imagery_quality'], 'Imagery date' => $r['imagery_date'],
		) );
		echo '<section class="htr-box htr-detail-card"><h2>Lead delivery</h2><dl class="htr-detail-list"><div><dt>Delivery status</dt><dd>' . self::status_pill( $r['lead_status'] ) . '</dd></div><div><dt>Delivery attempts</dt><dd>' . (int) $r['lead_tries'] . '</dd></div></dl>';
		if ( ! empty( $r['lead_error'] ) ) { echo '<div class="htr-detail-delivery-error"><strong>Last recorded delivery error</strong><p>' . esc_html( $r['lead_error'] ) . '</p></div>'; }
		echo '<p class="htr-detail-note">Use Resend lead to GHL to retry delivery. Check the activity log below for request details.</p></section></div>';
		if ( $estimate ) {
			$fields = array( 'billed_sqft' => 'Area used for pricing (sq ft)', 'rate_low' => 'Low rate ($/sq ft)', 'rate_high' => 'High rate ($/sq ft)', 'pitch_mult' => 'Pitch multiplier', 'complexity' => 'Roof complexity', 'complexity_mult' => 'Complexity multiplier', 'region_mult' => 'Regional multiplier' );
			$values = array();
			foreach ( $fields as $key => $label ) { if ( isset( $estimate[ $key ] ) && is_scalar( $estimate[ $key ] ) ) { $values[ $label ] = (string) $estimate[ $key ]; } }
			echo '<section class="htr-box htr-detail-estimate"><div class="htr-box-heading"><div><h2>Estimate breakdown</h2><p>Values saved with this report. Current pricing changes do not alter this snapshot.</p></div></div><dl class="htr-detail-estimate-grid">';
			foreach ( $values as $label => $value ) { echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>'; }
			echo '</dl></section>';
		}
		echo '<details class="htr-detail-technical"><summary>Technical details</summary><dl class="htr-detail-list"><div><dt>Report ID</dt><dd>' . (int) $r['id'] . '</dd></div><div><dt>Submission page</dt><dd>' . esc_html( $r['source_url'] ) . '</dd></div></dl><h3>Saved estimate data</h3><pre class="htr-pre">' . esc_html( $estimate ? wp_json_encode( $estimate, JSON_PRETTY_PRINT ) : ( $r['estimate'] ?: 'No estimate data recorded.' ) ) . '</pre></details>';
	}

	private static function detail_card( $title, array $values ) {
		echo '<section class="htr-box htr-detail-card"><h2>' . esc_html( $title ) . '</h2><dl class="htr-detail-list">';
		foreach ( $values as $label => $value ) {
			echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . ( null !== $value && '' !== (string) $value ? esc_html( (string) $value ) : '<span class="htr-detail-muted">Not recorded</span>' ) . '</dd></div>';
		}
		echo '</dl></section>';
	}

	/* ---------------- Pricing ---------------- */
	public static function pricing() {
		$s = HTR_RR_Settings::all();
		self::head( 'Pricing', 'Manage roof types, service areas and the factors used in customer estimates.' );
		echo '<form class="htr-pricing-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'htr_rr_save' );
		echo '<input type="hidden" name="action" value="htr_rr_save"><input type="hidden" name="section" value="pricing"><div class="htr-pricing-grid">';
		echo '<section class="htr-box htr-pricing-section"><div class="htr-box-heading"><div><h2>Roof types</h2><p>USD per square foot of roof surface.</p></div><button type="button" class="button" data-htr-add="material">+ Add roof type</button></div><div class="htr-pricing-scroll"><table class="widefat htr-pricing-table htr-material-pricing-table"><thead><tr><th scope="col">Roof type</th><th scope="col">Image</th><th scope="col">Low $/sq ft</th><th scope="col">High $/sq ft</th><th scope="col">Show price</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead><tbody id="htr-material-rows">';
		foreach ( $s['materials'] as $key => $material ) { self::pricing_material_row( $key, $material ); }
		echo '</tbody></table></div><p class="htr-pricing-help">Turn off Show price to request an on-site quote for that roof type. New types appear in the customer form after saving.</p></section>';
		echo '<section class="htr-box htr-pricing-section"><div class="htr-box-heading"><div><h2>Service areas</h2><p>Town centers and regional price adjustments.</p></div><button type="button" class="button" data-htr-add="area">+ Add service area</button></div><div class="htr-pricing-scroll"><table class="widefat htr-pricing-table"><thead><tr><th scope="col">Town</th><th scope="col">Latitude</th><th scope="col">Longitude</th><th scope="col">Price &times;</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead><tbody id="htr-area-rows">';
		foreach ( $s['areas'] as $index => $area ) { self::pricing_area_row( $index, $area ); }
		echo '</tbody></table></div><div class="htr-pricing-radius"><label>Service radius <input type="number" min="5" max="300" name="service_radius_mi" value="' . esc_attr( $s['service_radius_mi'] ) . '" required> <span>miles from the nearest town</span></label></div><p class="htr-pricing-help">1.00 keeps base pricing. For example, 1.10 adds 10% for that region.</p></section>';
		echo '<section class="htr-box htr-pricing-factors"><div class="htr-box-heading"><div><h2>Additional factors</h2><p>Fine-tune the estimate using roof area, pitch and complexity.</p></div></div><div class="htr-factor-grid"><div class="htr-factor"><h3>Extra area</h3><label>Waste / extra area (%)<input type="number" step="0.5" min="0" max="50" name="waste_pct" value="' . esc_attr( $s['waste_pct'] ) . '"></label><p>Use 0 when your rates already include waste.</p></div><div class="htr-factor"><h3>Roof pitch</h3><div class="htr-factor-labels"><span>Pitch up to (/12)</span><span>Multiplier</span></div>';
		foreach ( $s['pitch_bands'] as $index => $band ) {
			echo '<div class="htr-factor-pair"><input aria-label="Pitch band ' . (int) ( $index + 1 ) . ' maximum" type="number" min="1" max="99" name="pb[' . (int) $index . '][max]" value="' . esc_attr( $band['max'] ) . '"><input aria-label="Pitch band ' . (int) ( $index + 1 ) . ' multiplier" type="number" step="0.01" min="0.5" max="3" name="pb[' . (int) $index . '][mult]" value="' . esc_attr( $band['mult'] ) . '"></div>';
		}
		$c = $s['complexity'];
		echo '</div><div class="htr-factor"><h3>Roof complexity</h3><div class="htr-factor-labels"><span>Maximum roof faces</span><span>Multiplier</span></div>';
		foreach ( array( 'simple' => 'Simple', 'medium' => 'Medium' ) as $key => $label ) {
			echo '<div class="htr-factor-pair"><label>' . esc_html( $label ) . '<input type="number" min="1" name="cx[' . esc_attr( $key ) . '_max]" value="' . esc_attr( $c[ $key . '_max' ] ) . '"></label><label><span class="screen-reader-text">' . esc_html( $label ) . ' multiplier</span><input aria-label="' . esc_attr( $label ) . ' complexity multiplier" type="number" step="0.01" name="cx[' . esc_attr( $key ) . ']" value="' . esc_attr( $c[ $key ] ) . '"></label></div>';
		}
		echo '<label>Complex multiplier<input type="number" step="0.01" name="cx[complex]" value="' . esc_attr( $c['complex'] ) . '"></label></div></div></section></div><div class="htr-pricing-save">';
		submit_button( 'Save pricing', 'primary', 'submit', false );
		echo '<span>Changes apply to future estimates after saving.</span><span class="htr-pricing-feedback screen-reader-text" role="status" aria-live="polite"></span></div></form>';
		echo '<template id="htr-material-template">';
		self::pricing_material_row( '__INDEX__', array( 'label' => '', 'low' => 0, 'high' => 0, 'quote' => 1 ), true );
		echo '</template><template id="htr-area-template">';
		self::pricing_area_row( '__INDEX__', array( 'town' => '', 'lat' => '', 'lng' => '', 'mult' => 1 ), true );
		echo '</template></div>';
	}

	private static function pricing_material_row( $key, $material, $new = false ) {
		$name = ( $new ? 'new_mat' : 'mat' ) . '[' . $key . ']';
		echo '<tr><td><input aria-label="Roof type name" name="' . esc_attr( $name ) . '[label]" value="' . esc_attr( $material['label'] ) . '" placeholder="Roof type name" required></td>';
		$image_id = (int) ( $material['image_id'] ?? 0 );
		echo '<td><div class="htr-material-image"><input type="hidden" name="' . esc_attr( $name ) . '[image_id]" value="' . $image_id . '"><button type="button" class="htr-material-image__choose" aria-label="Choose roof type image" title="Choose or change image">';
		$image = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'alt' => '' ) ) : '';
		echo $image ?: '<span class="dashicons dashicons-format-image" aria-hidden="true"></span><span class="htr-image-add-label">Add image</span>';
		echo '</button><button type="button" class="htr-material-image__clear" aria-label="Remove roof type image" title="Remove image"' . ( $image_id ? '' : ' hidden' ) . '>&times;</button></div></td>';
		foreach ( array( 'low' => 'Low rate', 'high' => 'High rate' ) as $field => $label ) {
			echo '<td><input aria-label="' . esc_attr( $label ) . ' per square foot" type="number" step="0.01" min="0" name="' . esc_attr( $name ) . '[' . esc_attr( $field ) . ']" value="' . esc_attr( $material[ $field ] ) . '" required></td>';
		}
		echo '<td><input aria-label="Show automatic price" type="checkbox" name="' . esc_attr( $name ) . '[quote]" value="1" ' . checked( ! empty( $material['quote'] ), true, false ) . '></td><td>' . ( $new ? '<button type="button" class="button-link-delete htr-remove-pricing-row" aria-label="Remove new roof type" title="Remove new roof type"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' : '' ) . '</td></tr>';
	}

	private static function pricing_area_row( $index, $area, $new = false ) {
		$name = 'ar[' . $index . ']';
		echo '<tr><td><input aria-label="Service area town" name="' . esc_attr( $name ) . '[town]" value="' . esc_attr( $area['town'] ) . '" placeholder="Town name" required></td>';
		foreach ( array( 'lat' => array( 'Latitude', -90, 90 ), 'lng' => array( 'Longitude', -180, 180 ), 'mult' => array( 'Regional price multiplier', 0.5, 2 ) ) as $field => $props ) {
			echo '<td><input aria-label="' . esc_attr( $props[0] ) . '" type="number" step="' . ( 'mult' === $field ? '0.01' : 'any' ) . '" min="' . esc_attr( $props[1] ) . '" max="' . esc_attr( $props[2] ) . '" name="' . esc_attr( $name ) . '[' . esc_attr( $field ) . ']" value="' . esc_attr( $area[ $field ] ) . '" required></td>';
		}
		echo '<td>' . ( $new ? '<button type="button" class="button-link-delete htr-remove-pricing-row" aria-label="Remove new service area" title="Remove new service area"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' : '' ) . '</td></tr>';
	}

	/* ---------------- Calculator preview ---------------- */
	public static function calculator() {
		$s    = HTR_RR_Settings::all();
		$area = isset( $_GET['area'] ) ? max( 0, (int) $_GET['area'] ) : 2850; // phpcs:ignore
		$p    = isset( $_GET['pitch'] ) ? max( 0, (int) $_GET['pitch'] ) : 6; // phpcs:ignore
		$f    = isset( $_GET['faces'] ) ? max( 1, (int) $_GET['faces'] ) : 8; // phpcs:ignore
		$mat  = isset( $_GET['mat'] ) ? sanitize_key( $_GET['mat'] ) : 'asphalt'; // phpcs:ignore
		$town = isset( $_GET['town'] ) ? (int) $_GET['town'] : 0; // phpcs:ignore
		$reg  = isset( $s['areas'][ $town ] ) ? (float) $s['areas'][ $town ]['mult'] : 1.0;
		$e    = HTR_RR_Estimator::estimate( array( 'area_sqft' => $area, 'pitch_x12' => $p, 'faces' => $f ), $mat, $s, $reg );
		self::head( 'Estimate calculator', 'Preview an estimate using your saved roof pricing and regional adjustments.' );
		echo '<div class="htr-calculator-layout"><section class="htr-box htr-calculator-inputs"><div class="htr-box-heading"><div><h2>Roof details</h2><p>Adjust the inputs, then calculate the estimate.</p></div><span class="dashicons dashicons-calculator" aria-hidden="true"></span></div><form method="get"><input type="hidden" name="page" value="htr-rr-calc"><div class="htr-calculator-fields"><label class="htr-calculator-full">Roof type<select name="mat">';
		foreach ( $s['materials'] as $k => $m ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $mat, false ) . '>' . esc_html( $m['label'] ) . '</option>';
		}
		echo '</select></label><label class="htr-calculator-full">Roof area <span class="htr-input-unit">Square feet of roof surface</span><input type="number" name="area" value="' . esc_attr( $area ) . '"></label><label>Pitch (/12)<input type="number" name="pitch" value="' . esc_attr( $p ) . '"><span class="htr-field-note">Rise for every 12 inches of run</span></label><label>Roof faces<input type="number" name="faces" value="' . esc_attr( $f ) . '"><span class="htr-field-note">Number of roof sections</span></label><label class="htr-calculator-full">Service area<select name="town">';
		foreach ( $s['areas'] as $j => $a ) {
			echo '<option value="' . (int) $j . '"' . selected( $j, $town, false ) . '>' . esc_html( $a['town'] ) . '</option>';
		}
		echo '</select></label></div><div class="htr-calculator-actions">';
		submit_button( 'Calculate estimate', 'primary', 'submit', false );
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=htr-rr-calc' ) ) . '">Reset</a></div></form></section><section class="htr-box htr-calculator-result"><div class="htr-box-heading"><div><h2>Estimate preview</h2><p>Based on your saved Pricing settings.</p></div><span class="htr-pill htr-pill--info">Preview</span></div><div class="htr-calculator-range"><span class="htr-calculator-kicker">Estimated roof price</span>';
		if ( ! empty( $e['low'] ) ) {
			echo '<p class="htr-calculator-price">$' . esc_html( number_format( $e['low'] ) ) . ' <span>&ndash;</span> $' . esc_html( number_format( $e['high'] ) ) . '</p><p class="htr-calculator-context">' . esc_html( $e['material_label'] ) . ' &middot; ' . esc_html( $s['areas'][ $town ]['town'] ?? 'Base region' ) . '</p><div class="htr-calculator-bounds"><div><span>Low estimate</span><strong>$' . esc_html( number_format( $e['low'] ) ) . '</strong></div><div><span>High estimate</span><strong>$' . esc_html( number_format( $e['high'] ) ) . '</strong></div></div>';
		} else {
			echo '<p class="htr-calculator-price htr-calculator-price--quote">On-site quote</p><p class="htr-calculator-context">' . esc_html( $e['reason'] ?? '' ) . '</p>';
		}
		echo '</div>';
		if ( ! empty( $e['ok'] ) ) {
			$breakdown = array(
				'Roof area entered' => number_format( $area ) . ' sq ft',
				'Extra area allowance' => $s['waste_pct'] . '%',
				'Area used for pricing' => number_format( $e['billed_sqft'] ) . ' sq ft',
				'Base rate' => '$' . number_format( $e['rate_low'], 2 ) . ' – $' . number_format( $e['rate_high'], 2 ) . ' / sq ft',
				'Pitch adjustment' => '× ' . $e['pitch_mult'],
				'Roof complexity (' . $e['complexity'] . ')' => '× ' . $e['complexity_mult'],
				'Regional adjustment' => '× ' . $e['region_mult'],
			);
			echo '<div class="htr-calculator-breakdown"><h3>How it is calculated</h3><dl>';
			foreach ( $breakdown as $label => $value ) { echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>'; }
			echo '</dl><p>Area &times; rate &times; pitch &times; complexity &times; region. Priced estimates are rounded to the nearest $25.</p></div>';
		}
		echo '<div class="htr-calculator-result-footer"><span>Final pricing may vary after an on-site inspection.</span><a href="' . esc_url( admin_url( 'admin.php?page=htr-rr-pricing' ) ) . '">Edit pricing &rarr;</a></div></section></div></div>';
	}

	/* ---------------- Settings ---------------- */
	public static function settings() {
		$s = HTR_RR_Settings::all();
		self::head( 'Settings', 'Keys are stored in WordPress and never shown to visitors (except the browser key, which Google requires on the page).' );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'htr_rr_save' );
		echo '<input type="hidden" name="action" value="htr_rr_save"><input type="hidden" name="section" value="settings"><table class="form-table"><tbody>';
		self::field( 'google_browser_key', 'Google browser key', $s['google_browser_key'], 'Maps JavaScript API + Places API (New) + Geocoding + Maps Static. Restrict to your website address in Google Cloud.' );
		self::secret( 'google_server_key', 'Google server key', $s['google_server_key'], 'Solar API only. Restrict to the Solar API in Google Cloud.' );
		echo '<tr><th>Imagery quality asked first</th><td><select name="required_quality"><option value="MEDIUM"' . selected( $s['required_quality'], 'MEDIUM', false ) . '>Medium (then base)</option><option value="HIGH"' . selected( $s['required_quality'], 'HIGH', false ) . '>High (then medium, base)</option></select></td></tr>';
		self::field( 'n8n_webhook_url', 'n8n webhook URL', $s['n8n_webhook_url'], 'Leads go here; n8n creates/updates the GHL contact.' );
		self::secret( 'n8n_secret', 'n8n shared secret', $s['n8n_secret'], 'Signs every lead (HMAC-SHA256). Must match the n8n workflow.' );
		self::field( 'company_phone', 'Company phone shown to customers', $s['company_phone'], '' );
		self::field( 'notify_email', 'Alert email (lead not delivered)', $s['notify_email'], '' );
		echo '<tr><th>SMS consent - service texts</th><td><textarea name="consent_service" rows="3" class="large-text">' . esc_textarea( $s['consent_service'] ) . '</textarea></td></tr>';
		echo '<tr><th>SMS consent - marketing texts</th><td><textarea name="consent_marketing" rows="3" class="large-text">' . esc_textarea( $s['consent_marketing'] ) . '</textarea><p class="description">Same two checkboxes as GHL form "Form 30". Confirm the wording matches your A2P campaign.</p></td></tr>';
		self::field( 'ghl_form_id', 'GHL form ID (for reference)', $s['ghl_form_id'], '' );
		echo '<tr><th>Customer disclaimer</th><td><textarea name="disclaimer" rows="3" class="large-text">' . esc_textarea( $s['disclaimer'] ) . '</textarea></td></tr>';
		echo '<tr><th>Reports per visitor per hour</th><td><input type="number" min="1" max="50" name="rate_limit_hour" value="' . esc_attr( $s['rate_limit_hour'] ) . '" class="small-text"></td></tr>';
		echo '</tbody></table>';
		submit_button( 'Save settings' );
		echo '</form><h2>Test the Google Solar API</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'htr_rr_test_solar' );
		echo '<input type="hidden" name="action" value="htr_rr_test_solar">Latitude <input name="lat" value="29.7946641"> Longitude <input name="lng" value="-98.7319703"> ';
		submit_button( 'Test (1 free call)', 'secondary', 'submit', false );
		echo '</form></div>';
	}

	private static function field( $k, $label, $v, $help ) {
		echo '<tr><th><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">' . ( $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
	}

	/** Secrets are never printed back; leave blank to keep the saved value. */
	private static function secret( $k, $label, $v, $help ) {
		echo '<tr><th><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="password" autocomplete="new-password" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="" placeholder="' . ( $v ? 'Saved (ending ' . esc_attr( substr( $v, -4 ) ) . ') - leave blank to keep' : 'Not set' ) . '"><p class="description">' . esc_html( $help ) . '</p></td></tr>';
	}

	/* ---------------- Logs ---------------- */
	public static function logs() {
		global $wpdb;
		self::head( 'Logs', 'Review API activity, measurement results and lead delivery errors. Showing the latest 300 entries.' );
		self::log_table( $wpdb->get_results( 'SELECT * FROM ' . HTR_RR_DB::log_table() . ' ORDER BY id DESC LIMIT 300', ARRAY_A ) ); // phpcs:ignore
		echo '</div>';
	}

	private static function log_table( $rows ) {
		if ( ! $rows ) {
			echo '<div class="htr-box htr-empty"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><h3>No activity recorded yet</h3><p>Measurement requests and lead delivery activity will appear here.</p></div>';
			return;
		}
		$errors = count( array_filter( $rows, function ( $row ) { return 'error' === $row['level']; } ) );
		echo '<section class="htr-log-panel"><div class="htr-log-heading"><div><h2>Activity log <span>' . count( $rows ) . ' entries</span></h2><p>All times are UTC. A dash means the value was not recorded.</p></div><span class="htr-pill htr-pill--' . ( $errors ? 'bad' : 'info' ) . '">' . (int) $errors . ' recorded ' . ( 1 === $errors ? 'error' : 'errors' ) . '</span></div><div class="htr-log-scroll"><table class="widefat htr-log-table"><thead><tr><th scope="col">Time (UTC)</th><th scope="col">Level</th><th scope="col">Source</th><th scope="col">Message</th><th scope="col">Report</th><th scope="col">HTTP</th><th scope="col">Duration</th></tr></thead><tbody>';
		$sources = array( 'solar' => 'Google Solar', 'lead' => 'Lead delivery', 'email' => 'Customer email', 'rest' => 'Reports API' );
		foreach ( $rows as $r ) {
			$tone = 'error' === $r['level'] ? 'bad' : ( in_array( $r['level'], array( 'warning', 'warn' ), true ) ? 'warn' : 'info' );
			$http = (int) $r['http_code'];
			$http_tone = $http >= 400 ? 'bad' : ( $http >= 200 && $http < 300 ? 'ok' : 'info' );
			echo '<tr class="' . ( 'error' === $r['level'] ? 'htr-log-error' : '' ) . '"><td class="htr-log-time">' . esc_html( $r['created_at'] ) . '</td><td><span class="htr-pill htr-pill--' . esc_attr( $tone ) . '">' . esc_html( ucfirst( $r['level'] ) ) . '</span></td><td class="htr-log-source">' . esc_html( $sources[ $r['source'] ] ?? ucfirst( $r['source'] ) ) . '</td><td class="htr-log-message">' . esc_html( $r['message'] ) . '</td><td>';
			if ( $r['report_id'] ) {
				echo '<a class="htr-log-report" href="' . esc_url( admin_url( 'admin.php?page=htr-rr-reports&view=' . (int) $r['report_id'] ) ) . '">#' . (int) $r['report_id'] . '</a>';
			} else { echo '<span class="htr-log-muted">&mdash;</span>'; }
			echo '</td><td>' . ( $http ? '<span class="htr-log-http htr-pill--' . esc_attr( $http_tone ) . '">' . $http . '</span>' : '<span class="htr-log-muted">&mdash;</span>' ) . '</td><td class="htr-log-duration">' . ( null !== $r['ms'] && '' !== $r['ms'] ? esc_html( number_format( (float) $r['ms'] ) ) . '<span> ms</span>' : '<span class="htr-log-muted">&mdash;</span>' ) . '</td></tr>';
		}
		echo '</tbody></table></div></section>';
	}

	/* ---------------- Actions ---------------- */
	public static function save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'htr_rr_save' );
		$section = isset( $_POST['section'] ) ? sanitize_key( $_POST['section'] ) : '';
		$s       = HTR_RR_Settings::all();
		$new     = array();
		if ( 'pricing' === $section ) {
			$rows = HTR_RR_Settings::pricing_rows( wp_unslash( $_POST ), $s ); // phpcs:ignore
			if ( is_wp_error( $rows ) ) { wp_die( esc_html( $rows->get_error_message() ), 'Invalid pricing', array( 'back_link' => true, 'response' => 400 ) ); }
			$new['materials'] = $rows['materials'];
			$new['areas'] = $rows['areas'];
			$new['waste_pct'] = round( min( 50, max( 0, (float) ( isset( $_POST['waste_pct'] ) ? $_POST['waste_pct'] : 0 ) ) ), 1 ); // phpcs:ignore
			$pb               = array();
			foreach ( (array) ( isset( $_POST['pb'] ) ? wp_unslash( $_POST['pb'] ) : array() ) as $b ) { // phpcs:ignore
				$pb[] = array( 'max' => max( 1, (int) $b['max'] ), 'mult' => round( min( 3, max( 0.5, (float) $b['mult'] ) ), 2 ) );
			}
			usort( $pb, function ( $a, $b ) { return $a['max'] - $b['max']; } );
			$new['pitch_bands'] = $pb ? $pb : $s['pitch_bands'];
			$cx                 = isset( $_POST['cx'] ) ? wp_unslash( $_POST['cx'] ) : array(); // phpcs:ignore
			$new['complexity']  = array(
				'simple_max' => max( 1, (int) $cx['simple_max'] ),
				'medium_max' => max( (int) $cx['simple_max'] + 1, (int) $cx['medium_max'] ),
				'simple'     => round( (float) $cx['simple'], 2 ),
				'medium'     => round( (float) $cx['medium'], 2 ),
				'complex'    => round( (float) $cx['complex'], 2 ),
			);
			$new['service_radius_mi'] = min( 300, max( 5, (int) ( isset( $_POST['service_radius_mi'] ) ? $_POST['service_radius_mi'] : 60 ) ) ); // phpcs:ignore
			$page                     = 'htr-rr-pricing';
		} else {
			foreach ( array( 'consent_service', 'consent_marketing' ) as $k ) {
				$new[ $k ] = sanitize_textarea_field( isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : '' ); // phpcs:ignore
			}
			foreach ( array( 'google_browser_key', 'n8n_webhook_url', 'company_phone', 'notify_email', 'ghl_form_id' ) as $k ) {
				$new[ $k ] = sanitize_text_field( isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : '' ); // phpcs:ignore
			}
			$new['n8n_webhook_url'] = esc_url_raw( $new['n8n_webhook_url'] );
			$new['notify_email']    = sanitize_email( $new['notify_email'] );
			foreach ( array( 'google_server_key', 'n8n_secret' ) as $k ) {
				$v = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore
				if ( '' !== $v ) {
					$new[ $k ] = $v; // blank = keep the saved secret.
				}
			}
			// Google API keys look like "AIza..." (39 chars). Refuse anything else (28 Oct 2026: the n8n secret was pasted as the server key).
			$bad = array();
			foreach ( array( 'google_browser_key' => 'Google browser key', 'google_server_key' => 'Google server key' ) as $k => $label ) {
				if ( isset( $new[ $k ] ) && '' !== $new[ $k ] && ! preg_match( '/^AIza[0-9A-Za-z_\-]{30,}$/', $new[ $k ] ) ) {
					unset( $new[ $k ] ); // keep the previously saved value.
					$bad[] = $label;
				}
			}
			if ( isset( $new['n8n_secret'] ) && 0 === strpos( $new['n8n_secret'], 'AIza' ) ) {
				unset( $new['n8n_secret'] );
				$bad[] = 'n8n shared secret (that looks like a Google key)';
			}
			$new['required_quality'] = isset( $_POST['required_quality'] ) && 'HIGH' === $_POST['required_quality'] ? 'HIGH' : 'MEDIUM'; // phpcs:ignore
			$new['disclaimer']       = sanitize_textarea_field( isset( $_POST['disclaimer'] ) ? wp_unslash( $_POST['disclaimer'] ) : '' ); // phpcs:ignore
			$new['rate_limit_hour']  = min( 50, max( 1, (int) ( isset( $_POST['rate_limit_hour'] ) ? $_POST['rate_limit_hour'] : 5 ) ) ); // phpcs:ignore
			$page                    = 'htr-rr-settings';
		}
		HTR_RR_Settings::save( $new );
		$msg = ! empty( $bad ) ? '&msg=' . rawurlencode( 'Not saved (kept the old value): ' . implode( ', ', $bad ) . '. A Google API key starts with "AIza".' ) : '';
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&saved=1' . $msg ) );
		exit;
	}

	public static function resend() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'htr_rr_resend_' . $id );
		HTR_RR_DB::update_report( $id, array( 'lead_status' => 'pending', 'lead_tries' => 0 ) );
		$ok = HTR_RR_Leads::send( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=htr-rr-reports&view=' . $id . '&msg=' . rawurlencode( $ok ? 'Lead delivered to n8n.' : 'Delivery failed - see the log below. It will retry every 15 minutes.' ) ) );
		exit;
	}

	public static function test_solar() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'htr_rr_test_solar' );
		$m   = HTR_RR_Solar::measure( (float) $_POST['lat'], (float) $_POST['lng'] ); // phpcs:ignore
		$msg = ! empty( $m['ok'] ) ? 'Solar API works: ' . $m['area_sqft'] . ' sq ft, pitch ' . $m['pitch_x12'] . '/12, ' . $m['faces'] . ' faces, imagery ' . $m['imagery_quality'] . ' ' . $m['imagery_date'] : 'Solar API test failed: ' . ( isset( $m['message'] ) ? $m['message'] : 'unknown' );
		wp_safe_redirect( admin_url( 'admin.php?page=htr-rr-settings&msg=' . rawurlencode( $msg ) ) );
		exit;
	}
}
