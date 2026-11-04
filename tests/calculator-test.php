<?php
/** Isolated calculator and lead-pipeline checks. Never sends a real lead. */
require __DIR__ . '/pricing-test.php';
require dirname( __DIR__ ) . '/includes/class-rest.php';
define( 'HOUR_IN_SECONDS', 3600 );
class WP_REST_Request {
 public $data;
 public function __construct( $data ) { $this->data = $data; }
 public function get_json_params() { return $this->data; }
}
class WP_REST_Response {
 public $data; public $status;
 public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
}
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; }
function current_user_can( $cap ) { return false; }
function wp_unslash( $value ) { return $value; }
function wp_salt( $scheme ) { return 'isolated-test'; }
function sanitize_email( $value ) { return $value; }
function is_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function current_time( $type, $gmt = false ) { return '2026-11-04 00:00:00'; }
function esc_url_raw( $value ) { return $value; }
function wp_json_encode( $value ) { return json_encode( $value ); }
class Calculator_Test_DB {
 public $insert_id = 0; public $last_error = '';
 public function insert( $table, $data ) { $this->insert_id++; $GLOBALS['report'] = $data; }
}
class HTR_RR_DB {
 public static function reports() { return 'isolated'; }
 public static function update_report( $id, $data ) { $GLOBALS['report'] = array_merge( $GLOBALS['report'], $data ); }
 public static function get_report( $id ) { return $GLOBALS['report']; }
}
class HTR_RR_Leads {
 public static function send( $id ) { $GLOBALS['sent'][] = $id; }
}
class HTR_RR_Report_Page {
 public static function public_view( $r ) { return $r; }
}
class HTR_RR_Solar {
 public static function measure( $lat, $lng, $id = null ) { $GLOBALS['solar_calls']++; return array( 'ok' => false, 'message' => 'Fixture: no imagery' ); }
}
$GLOBALS['saved'] = HTR_RR_Settings::defaults();
$GLOBALS['wpdb'] = new Calculator_Test_DB();
$GLOBALS['solar_calls'] = 0;
function add_action( $name, $callback ) { $GLOBALS['registered_actions'][] = $name; }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['registered_routes'][] = $route; }
HTR_RR_REST::ajax_init();
HTR_RR_REST::routes();
check( ! in_array( 'wp_ajax_nopriv_htr_rr_calculate', $GLOBALS['registered_actions'], true ) && ! in_array( '/calculate', $GLOBALS['registered_routes'], true ), 'No anonymous calculator preview endpoint before lead submission.' );
$valid = array( 'area_sqft' => 2850, 'pitch_x12' => 6, 'faces' => 8, 'town' => 0, 'material' => 'asphalt' );
$response = HTR_RR_REST::calculate( new WP_REST_Request( $valid ) );
check( $response->data['estimate']['low'] === 12825 && $response->data['estimate']['high'] === 18525, 'Public calculator matches saved pricing and mockup.' );
$quote = $valid; $quote['material'] = 'other';
check( HTR_RR_REST::calculate( new WP_REST_Request( $quote ) )->data['estimate']['low'] === null, 'Quote-only materials do not invent a price.' );
$GLOBALS['saved']['areas'][0]['mult'] = 1.2;
$located = array_merge( $valid, array( 'town' => 1, 'lat' => 29.4246002, 'lng' => -98.4951405 ) );
$locatedResult = HTR_RR_REST::calculate( new WP_REST_Request( $located ) );
check( $locatedResult->data['town'] === 'San Antonio' && $locatedResult->data['estimate']['region_mult'] === 1.2, 'Selected address overrides the manually selected region.' );
$GLOBALS['saved']['areas'][0]['mult'] = 1.0;
foreach ( array( array( 'area_sqft', 0 ), array( 'area_sqft', 100001 ), array( 'area_sqft', array() ), array( 'pitch_x12', -1 ), array( 'pitch_x12', 25 ), array( 'faces', 1.5 ), array( 'faces', 101 ), array( 'material', 'unknown' ), array( 'town', 99 ) ) as $invalid ) {
 $bad = $valid; $bad[ $invalid[0] ] = $invalid[1];
 check( ! HTR_RR_REST::calculate( new WP_REST_Request( $bad ) )->data['ok'], 'Reject invalid ' . $invalid[0] );
}
$contact = array( 'first_name' => 'Test', 'last_name' => 'Fixture', 'email' => 'fixture@example.com', 'phone' => '2105550100', 'address' => '123 Main Street, San Antonio, TX', 'lat' => 29.4246002, 'lng' => -98.4951405, 'sms_service' => true, 'sms_marketing' => false );
$response = HTR_RR_REST::calculator_lead( new WP_REST_Request( array_merge( $valid, $contact, array( 'price_low' => 1, 'request_source' => 'instant_roof' ) ) ) );
check( $response->data['ok'], 'Calculator request saved.' );
check( $response->data['estimate']['low'] === 12825, 'Successful lead response unlocks server-calculated estimate.' );
check( $GLOBALS['report']['price_low'] === 12825, 'Submitted price ignored; server recalculates.' );
check( $GLOBALS['report']['status'] === 'review', 'Manual measurements require review.' );
check( $GLOBALS['report']['request_source'] === 'calculator', 'Calculator source saved before lead delivery.' );
check( strpos( $GLOBALS['report']['review_reason'], 'customer-entered' ) !== false, 'Manual source identified.' );
check( $GLOBALS['report']['sms_consent'] === 'service', 'Optional SMS choices retained.' );
check( count( $GLOBALS['sent'] ) === 1 && $GLOBALS['solar_calls'] === 0, 'Existing lead sender reused without Solar for manual estimate.' );
$badContact = $contact; $badContact['phone'] = '123';
check( ! HTR_RR_REST::calculator_lead( new WP_REST_Request( array_merge( $valid, $badContact ) ) )->data['ok'], 'Invalid contact rejected.' );
$honeypot = array_merge( $valid, $contact, array( 'website' => 'spam' ) );
check( ! HTR_RR_REST::calculator_lead( new WP_REST_Request( $honeypot ) )->data['ok'], 'Honeypot preserved.' );
HTR_RR_REST::create( new WP_REST_Request( array_merge( $valid, $contact, array( 'request_source' => 'calculator' ) ) ) );
check( $GLOBALS['solar_calls'] === 1, 'Original report path still calls Solar.' );
check( $GLOBALS['report']['request_source'] === 'instant_roof', 'Instant Roof source saved even when imagery fails.' );
$sentBefore = count( $GLOBALS['sent'] );
$rateKey = 'htr_rr_rl_' . substr( hash( 'sha256', 'isolated-test' ), 0, 20 );
$GLOBALS['transients'][ $rateKey ] = 5;
check( HTR_RR_REST::calculator_lead( new WP_REST_Request( array_merge( $valid, $contact ) ) )->status === 429, 'Lead rate limit retained.' );
check( count( $GLOBALS['sent'] ) === $sentBefore, 'Rejected request never sends a lead.' );
echo "Calculator checks passed. Total checks: $checks\n";
