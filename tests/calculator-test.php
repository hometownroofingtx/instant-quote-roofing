<?php
/** Isolated calculator and lead-pipeline checks. Never sends a real lead. */
require __DIR__ . '/pricing-test.php';
require dirname( __DIR__ ) . '/includes/class-rest.php';
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
class WP_REST_Request {
 public $data;
 public function __construct( $data ) { $this->data = $data; }
 public function get_json_params() { return $this->data; }
 public function get_header( $name ) { return $GLOBALS['request_headers'][ $name ] ?? ''; }
}
class WP_REST_Response {
 public $data; public $status;
 public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
}
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function current_user_can( $cap ) { return ! empty( $GLOBALS['test_admin'] ); }
function get_current_user_id() { return ! empty( $GLOBALS['test_admin'] ) ? 42 : 0; }
function home_url( $path ) { return 'https://example.test' . $path; }
function add_query_arg( $key, $value, $url ) { return $url . '?' . urlencode( $key ) . '=' . urlencode( $value ); }
function wp_unslash( $value ) { return $value; }
function wp_salt( $scheme ) { return 'isolated-test'; }
function sanitize_email( $value ) { return $value; }
function is_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function current_time( $type, $gmt = false ) { return '2026-11-04 00:00:00'; }
function esc_url_raw( $value ) { return $value; }
function wp_json_encode( $value ) { return json_encode( $value ); }
class Calculator_Test_DB {
 public $insert_id = 0; public $last_error = '';
 public function insert( $table, $data ) { $this->insert_id++; $GLOBALS['report'] = array_merge( $data, array( 'id' => $this->insert_id ) ); }
}
class HTR_RR_DB {
 public static function reports() { return 'isolated'; }
 public static function update_report( $id, $data ) { $GLOBALS['report'] = array_merge( $GLOBALS['report'], $data ); }
 public static function get_report( $id ) { return $GLOBALS['report']; }
 public static function get_by_token( $token ) { return ( $GLOBALS['report']['token'] ?? '' ) === $token ? $GLOBALS['report'] : null; }
 public static function log( $level, $source, $message, $id = null ) {}
 public static function save_browser_contact( $id, $data ) {
  if ( ! empty( $GLOBALS['fail_contact_save'] ) ) { return false; }
  if ( 'awaiting_form' !== $GLOBALS['report']['lead_status'] ) { return 0; }
  self::update_report( $id, $data ); return 1;
 }
 public static function update_waiting_report( $id, $data ) { return self::save_browser_contact( $id, $data ); }
 public static function insert_contact_report( $data ) {
  if ( ! empty( $GLOBALS['fail_contact_save'] ) ) { return 0; }
  $GLOBALS['wpdb']->insert( self::reports(), $data ); return $GLOBALS['wpdb']->insert_id;
 }
}
class HTR_RR_Leads {
 public static function send( $id ) { $GLOBALS['sent'][] = $id; }
}
class HTR_RR_Report_Page {
 public static function public_view( $r ) { return $r; }
}
class HTR_RR_Solar {
 public static function measure( $lat, $lng, $id = null ) { $GLOBALS['solar_calls']++; return isset( $GLOBALS['solar_fixture'] ) ? $GLOBALS['solar_fixture'] : array( 'ok' => false, 'message' => 'Fixture: no imagery' ); }
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
// GHL mode uses the same calculator, source and lead pipeline, deferring contact capture.
$GLOBALS['transients'] = array();
check( HTR_RR_REST::calculator_prepare( new WP_REST_Request( array_merge( $valid, $contact ) ) )->status === 403, 'Plugin mode cannot bypass contact validation through prepare.' );
$GLOBALS['saved']['contact_mode'] = 'ghl_form';
$GLOBALS['saved']['n8n_secret'] = 'fixture-shared-secret';
$prepared = HTR_RR_REST::calculator_prepare( new WP_REST_Request( array_merge( $valid, array_intersect_key( $contact, array_flip( array( 'address', 'lat', 'lng' ) ) ) ) ) );
check( $prepared->data['ok'] && $GLOBALS['report']['lead_status'] === 'awaiting_form', 'GHL calculator saves a waiting report without contact details.' );
check( $GLOBALS['report']['request_source'] === 'calculator', 'GHL calculator retains source tagging.' );
check( count( $GLOBALS['sent'] ) === $sentBefore, 'Waiting reports never send leads.' );
$token = $GLOBALS['report']['token'];
check( ! HTR_RR_REST::status( new WP_REST_Request( array( 'token' => $token ) ) )->data['linked'], 'Waiting status does not reveal completion.' );
check( HTR_RR_REST::status( new WP_REST_Request( array( 'token' => array() ) ) )->status === 404, 'Malformed polling token is rejected.' );
$link = array_merge( $contact, array( 'report_id' => $GLOBALS['wpdb']->insert_id ) );
check( HTR_RR_REST::link_contact( new WP_REST_Request( $link ) )->status === 403, 'Contact linking requires the shared secret.' );
$GLOBALS['request_headers']['x_htr_key'] = 'fixture-shared-secret';
check( HTR_RR_REST::link_contact( new WP_REST_Request( array( 'report_id' => 1 ) ) )->status === 400, 'Invalid contact details do not link a waiting report.' );
$linked = HTR_RR_REST::link_contact( new WP_REST_Request( $link ) );
check( $linked->data['linked'] && $GLOBALS['report']['first_name'] === 'Test', 'Authenticated callback saves contact details.' );
check( $GLOBALS['report']['request_source'] === 'calculator' && count( $GLOBALS['sent'] ) === $sentBefore + 1, 'Linked calculator sends through the existing pipeline with source intact.' );
check( HTR_RR_REST::status( new WP_REST_Request( array( 'token' => $token ) ) )->data['report']['first_name'] === 'Test', 'Polling returns the updated linked report.' );
HTR_RR_REST::link_contact( new WP_REST_Request( $link ) );
check( count( $GLOBALS['sent'] ) === $sentBefore + 1, 'Duplicate link callbacks do not send again.' );
$GLOBALS['transients'] = array();
$prepared = HTR_RR_REST::prepare( new WP_REST_Request( array_merge( $valid, $contact ) ) );
check( $prepared->data['ok'] && $GLOBALS['report']['request_source'] === 'instant_roof' && $GLOBALS['report']['lead_status'] === 'awaiting_form', 'Instant Roof GHL prepare keeps source and waiting status.' );
$direct = array( 'token' => $GLOBALS['report']['token'], 'contact_key' => $prepared->data['contact_key'], 'expires' => $prepared->data['contact_expires'], 'contact' => $contact );
$bad = $direct; $bad['contact_key'] = 'wrong';
check( HTR_RR_REST::save_contact( new WP_REST_Request( $bad ) )->status === 403, 'Browser save rejects an invalid capability' );
$bad = $direct; unset( $bad['contact_key'] );
check( HTR_RR_REST::save_contact( new WP_REST_Request( $bad ) )->status === 400, 'Public report token alone cannot write contacts' );
$bad = $direct; $bad['expires'] = time() - 1;
check( HTR_RR_REST::save_contact( new WP_REST_Request( $bad ) )->status === 403, 'Expired capabilities rejected' );
$bad = $direct; $bad['contact']['email'] = array();
check( HTR_RR_REST::save_contact( new WP_REST_Request( $bad ) )->status === 400, 'Malformed contact fields rejected' );
$bad = $direct; $bad['contact'] = array();
check( HTR_RR_REST::save_contact( new WP_REST_Request( $bad ) )->status === 400, 'Empty contact data never records success' );
$GLOBALS['fail_contact_save'] = true;
check( HTR_RR_REST::save_contact( new WP_REST_Request( $direct ) )->status === 500 && $GLOBALS['report']['lead_status'] === 'awaiting_form', 'Database failures remain retryable and do not claim saved' );
$GLOBALS['fail_contact_save'] = false;
$sentBefore = count( $GLOBALS['sent'] );
$result = HTR_RR_REST::save_contact( new WP_REST_Request( $direct ) );
check( $result->data['saved'] && $GLOBALS['report']['first_name'] === 'Test' && $GLOBALS['report']['phone'] === '+12105550100', 'Direct browser save persists normalized contact data' );
$GLOBALS['report']['lead_status'] = 'awaiting_form';
$international = $direct; $international['contact']['phone'] = '+63 917 555 0123';
$savedInternational = HTR_RR_REST::save_contact( new WP_REST_Request( $international ) );
check( $savedInternational->data['phone_saved'] && $GLOBALS['report']['phone'] === '+639175550123', 'International GHL phone is preserved with country code' );
check( $GLOBALS['report']['lead_status'] === 'ghl_submitted' && count( $GLOBALS['sent'] ) === $sentBefore, 'Existing browser-save action records submission without duplicate outbound lead' );
check( HTR_RR_REST::status( new WP_REST_Request( array( 'token' => $direct['token'] ) ) )->data['linked'], 'Saved browser submission retains original report access' );
HTR_RR_REST::link_contact( new WP_REST_Request( array_merge( $contact, array( 'report_id' => $GLOBALS['report']['id'] ) ) ) );
$direct['contact']['first_name'] = 'Overwrite attempt';
check( HTR_RR_REST::save_contact( new WP_REST_Request( $direct ) )->data['already_saved'] && $GLOBALS['report']['first_name'] === 'Test', 'Duplicate browser saves cannot overwrite linked contacts' );
check( $GLOBALS['report']['request_source'] === 'instant_roof', 'Contact save preserves report source' );
$GLOBALS['transients'] = array();
$draft = HTR_RR_REST::prepare( new WP_REST_Request( array_merge( $valid, $contact ) ) );
$draftId = $GLOBALS['wpdb']->insert_id;
$solarBefore = $GLOBALS['solar_calls'];
$selection = array( 'token' => $GLOBALS['report']['token'], 'contact_key' => $draft->data['contact_key'], 'expires' => $draft->data['contact_expires'], 'material' => 'metal' );
$repriced = HTR_RR_REST::reprice( new WP_REST_Request( $selection ) );
check( $repriced->data['ok'] && $GLOBALS['report']['material'] === 'metal', 'New roof selection updates pending report' );
check( $GLOBALS['wpdb']->insert_id === $draftId && $GLOBALS['solar_calls'] === $solarBefore, 'Repricing neither inserts another report nor calls Solar again' );
$bad = $selection; $bad['contact_key'] = 'wrong';
check( HTR_RR_REST::reprice( new WP_REST_Request( $bad ) )->status === 403, 'Repricing requires report-specific authorization' );
$GLOBALS['report']['lead_status'] = 'ghl_submitted';
check( HTR_RR_REST::reprice( new WP_REST_Request( $selection ) )->status === 409, 'Submitted reports cannot be repriced' );
$GLOBALS['transients'] = array();
$before = $GLOBALS['wpdb']->insert_id;
$draft = HTR_RR_REST::calculator_draft_prepare( new WP_REST_Request( array_merge( $valid, $contact ) ) );
check( $draft->data['ok'] && strpos( $draft->data['report_id'], 'draft-' ) === 0, 'Frontend prepares an expiring draft reference' );
check( $GLOBALS['wpdb']->insert_id === $before, 'Roof selection does not insert into Reports' );
$selection = array( 'token' => $draft->data['report']['token'], 'contact_key' => $draft->data['contact_key'], 'expires' => $draft->data['contact_expires'], 'material' => 'metal' );
check( HTR_RR_REST::reprice( new WP_REST_Request( $selection ) )->data['ok'] && $GLOBALS['wpdb']->insert_id === $before, 'Changing roof type only updates temporary draft' );
$save = array_merge( $selection, array( 'contact' => $contact ) );
$GLOBALS['fail_contact_save'] = true;
check( HTR_RR_REST::save_contact( new WP_REST_Request( $save ) )->status === 500 && $GLOBALS['wpdb']->insert_id === $before, 'Failed finalization remains retryable without adding empty reports' );
$GLOBALS['fail_contact_save'] = false;
check( HTR_RR_REST::save_contact( new WP_REST_Request( $save ) )->data['saved'] && $GLOBALS['wpdb']->insert_id === $before + 1, 'Successful submission creates exactly one contact-complete report' );
check( $GLOBALS['report']['first_name'] === 'Test' && $GLOBALS['report']['material'] === 'metal', 'Final report contains contact and latest roof selection' );
HTR_RR_REST::link_contact( new WP_REST_Request( array_merge( $contact, array( 'report_id' => $GLOBALS['report']['id'] ) ) ) );
check( HTR_RR_REST::save_contact( new WP_REST_Request( $save ) )->data['already_saved'] && $GLOBALS['wpdb']->insert_id === $before + 1, 'Repeated submission with draft key does not create duplicate reports' );
$GLOBALS['transients'] = array();
$before = $GLOBALS['wpdb']->insert_id;
$draft = HTR_RR_REST::draft_prepare( new WP_REST_Request( array_merge( $valid, $contact ) ) );
check( $draft->data['ok'] && $GLOBALS['wpdb']->insert_id === $before, 'Instant Roof draft also avoids empty Reports rows' );
$linked = HTR_RR_REST::link_contact( new WP_REST_Request( array_merge( $contact, array( 'report_id' => $draft->data['report_id'] ) ) ) );
check( $linked->data['linked'] && $GLOBALS['wpdb']->insert_id === $before + 1, 'Optional authenticated callback can finalize a draft reference' );
$GLOBALS['transients'] = array();
$before = $GLOBALS['wpdb']->insert_id;
$solarBefore = $GLOBALS['solar_calls'];
$aerial = $valid;
unset( $aerial['area_sqft'], $aerial['pitch_x12'], $aerial['faces'], $aerial['town'] );
$aerial['measurement_mode'] = 'aerial';
$draft = HTR_RR_REST::calculator_draft_prepare( new WP_REST_Request( array_merge( $aerial, $contact ) ) );
check( $draft->data['ok'] && $GLOBALS['solar_calls'] === $solarBefore + 1, 'Aerial calculator measures roof without customer dimensions' );
check( $GLOBALS['wpdb']->insert_id === $before && ! $draft->data['estimate']['ok'], 'Missing imagery does not invent a price or create Reports entry' );
$GLOBALS['solar_fixture'] = array_merge( HTR_RR_REST::calculator_measurements( $valid ), array( 'source' => 'solar', 'imagery_quality' => 'HIGH', 'marker_inside' => true, 'marker_off_m' => 0 ) );
$GLOBALS['transients'] = array();
$draft = HTR_RR_REST::calculator_draft_prepare( new WP_REST_Request( array_merge( $aerial, $contact ) ) );
check( ! $draft->data['estimate']['ok'] && $draft->data['estimate']['locked'] && $GLOBALS['wpdb']->insert_id === $before, 'Available aerial pricing remains server-side before verification' );
check( ! isset( $draft->data['report']['priceLow'] ) && ! isset( $draft->data['report']['url'] ), 'Draft response contains no price or report URL' );
check( HTR_RR_REST::create( new WP_REST_Request( array_merge( $valid, $contact ) ) )->status === 403, 'Native report route cannot bypass configured GHL gate' );
echo "Calculator checks passed. Total checks: $checks\n";

$GLOBALS['report']['lead_status'] = 'awaiting_form';
check( HTR_RR_REST::email_copy( new WP_REST_Request( array( 'token' => $GLOBALS['report']['token'] ) ) )->status === 404, 'Unverified report cannot be emailed' );
$GLOBALS['report']['lead_status'] = 'ghl_submitted';
check( HTR_RR_REST::status( new WP_REST_Request( array( 'token' => $GLOBALS['report']['token'] ) ) )->data['linked'], 'Historical browser submissions retain report access' );
echo "Security gate checks complete. Total checks: $checks\n";

$internal_input = array( 'address' => '123 Main Street', 'lat' => 29.42, 'lng' => -98.49, 'material' => array_key_first( $GLOBALS['saved']['materials'] ) );
check( HTR_RR_REST::create( new WP_REST_Request( $internal_input ), false, true, false, true )->status === 403, 'Non-admin cannot create an internal quote' );
$GLOBALS['test_admin'] = true;
$sent_before = count( $GLOBALS['sent'] ?? array() );
$inserts_before = $GLOBALS['wpdb']->insert_id;
foreach ( array( 'ghl_form', 'plugin' ) as $mode ) {
 $GLOBALS['saved']['contact_mode'] = $mode;
 $internal = HTR_RR_REST::create( new WP_REST_Request( $internal_input ), false, true, false, true );
 check( $internal->status === 200 && $internal->data['ok'], 'Admin quote works in ' . $mode . ' mode without contact details' );
 check( $GLOBALS['wpdb']->insert_id === $inserts_before, 'Admin quote creates no Reports row' );
 $preview = get_transient( 'htr_rr_preview_' . $internal->data['report']['token'] );
 check( $preview['lead_status'] === 'internal' && $preview['_preview_user'] === 42, 'Temporary preview belongs to the requesting administrator' );
 check( empty( $internal->data['report_id'] ) && ! empty( $internal->data['temporary'] ), 'Preview response has no saved report identifier' );
 check( strpos( $internal->data['report']['url'], 'htr_roof_preview=' ) !== false, 'Download uses temporary preview URL' );
 check( empty( $internal->data['report']['locked'] ), 'Admin quote report is accessible without a GHL submission' );
 check( count( $GLOBALS['sent'] ?? array() ) === $sent_before, 'Admin quote sends no outbound lead' );
}
$GLOBALS['saved']['contact_mode'] = 'ghl_form';
check( HTR_RR_REST::create( new WP_REST_Request( array_merge( $internal_input, array( 'backend' => true ) ) ) )->status === 403, 'Request data cannot enable the backend bypass' );
$GLOBALS['test_admin'] = false;
echo "Internal quote security checks complete. Total checks: $checks\n";
