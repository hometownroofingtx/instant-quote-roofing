<?php
/** Isolated source/tag contract checks; no database, Google, n8n or GHL calls. */
require __DIR__ . '/pricing-test.php';
require dirname( __DIR__ ) . '/includes/class-report-page.php';
require dirname( __DIR__ ) . '/includes/class-leads.php';
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function add_query_arg( $key, $value, $url ) { return $url . '?' . $key . '=' . $value; }
$GLOBALS['saved'] = HTR_RR_Settings::defaults();
$report = array_fill_keys( array(
    'measurements', 'estimate', 'id', 'token', 'created_at', 'status', 'review_reason',
    'first_name', 'last_name', 'email', 'phone', 'interests', 'sms_consent', 'address',
    'city', 'zip', 'lat', 'lng', 'material', 'area_sqft', 'pitch_x12', 'faces',
    'imagery_quality', 'imagery_date', 'price_low', 'price_high', 'town', 'town_miles', 'source_url',
), '' );
$report['id'] = 1;
$report['material'] = 'asphalt';
$report['town_miles'] = null;
$report['request_source'] = 'calculator';
$payload = HTR_RR_Leads::payload( $report );
check( $payload['request_source'] === 'calculator' && $payload['tags'] === array( 'Calculator' ), 'Calculator source and CRM tag match' );
check( $payload['event'] === 'roof_report', 'Existing webhook event contract preserved' );
check( HTR_RR_Report_Page::source_label( $report ) === 'Calculator', 'Admin source label matches saved source' );
$report['request_source'] = 'instant_roof';
$payload = HTR_RR_Leads::payload( $report );
check( $payload['request_source'] === 'instant_roof' && $payload['tags'] === array( 'Instant Roof Request' ), 'Instant Roof source and CRM tag match even without measurements' );
unset( $report['request_source'] );
$report['measurements'] = json_encode( array( 'source' => 'customer_entered' ) );
check( HTR_RR_Report_Page::request_source( $report ) === 'calculator', 'Legacy calculator reports retain their source' );
$report['request_source'] = '';
check( HTR_RR_Leads::payload( $report )['tags'] === array( 'Calculator' ), 'Migrated legacy rows retain calculator tag' );
$report['measurements'] = '{}';
check( HTR_RR_Report_Page::request_source( $report ) === 'instant_roof', 'Legacy aerial reports use Instant Roof source' );
$report['measurements'] = 'invalid';
check( HTR_RR_Report_Page::source_label( $report ) === 'Instant Roof', 'Unavailable legacy measurement data has a safe fallback' );
class HTR_RR_DB {
    public static function get_report( $id ) { return array( 'lead_status' => 'awaiting_form' ); }
}
check( HTR_RR_Leads::send( 1 ) === false, 'Waiting GHL reports never reach the outbound sender' );
echo "Request-source checks passed. Total checks: $checks\n";
