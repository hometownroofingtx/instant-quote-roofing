<?php
/** Isolated floating settings/visibility checks; no WordPress database or external calls. */
require __DIR__ . '/pricing-test.php';
require dirname( __DIR__ ) . '/includes/class-floating-tab.php';
function esc_url_raw( $url, $protocols = array() ) { $scheme = parse_url( $url, PHP_URL_SCHEME ); return in_array( $scheme, $protocols, true ) ? $url : ''; }
function is_admin() { return $GLOBALS['admin'] ?? false; }
function is_feed() { return $GLOBALS['feed'] ?? false; }
function get_post_status( $id ) { return 99 === $id ? 'draft' : 'publish'; }
function get_post_type( $id ) { return 'page'; }
function is_page( $id = null ) { return $GLOBALS['is_page'] ?? true; }
function get_queried_object_id() { return $GLOBALS['page_id'] ?? 1; }
function get_permalink( $id ) { return 'https://example.test/page/' . $id; }
function url_to_postid( $url ) { return strpos( $url, '?htr_rr_drawer=' ) !== false ? 1 : (int) basename( $url ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function add_query_arg( $key, $value, $url ) { return $url . '?' . $key . '=' . $value; }
$base = HTR_RR_Floating_Tab::defaults();
check( $base['floating_enabled'] === 0, 'Floating tab is opt-in' );
check( ! isset( $base['n8n_secret'] ) && ! isset( $base['google_server_key'] ), 'Floating settings never contain credentials' );
$input = array_merge( $base, array( 'floating_enabled' => 1, 'floating_page' => 10, 'n8n_secret' => 'must-not-save', 'floating_zindex' => 999999, 'floating_radius' => -1, 'floating_color' => 'red;display:none' ) );
$clean = HTR_RR_Floating_Tab::sanitize( $input );
check( ! is_wp_error( $clean ) && ! isset( $clean['n8n_secret'] ), 'Save/import only allow floating settings' );
check( $clean['floating_zindex'] === 99999 && $clean['floating_radius'] === 0, 'Numeric styles bounded' );
check( $clean['floating_color'] === $base['floating_color'], 'Unsafe CSS value cannot enter markup' );
check( HTR_RR_Floating_Tab::visible( $clean ), 'Enabled valid quote destination displays on another page' );
$GLOBALS['page_id'] = 10;
check( ! HTR_RR_Floating_Tab::visible( $clean ), 'Destination page hidden by default' );
$GLOBALS['page_id'] = 1;
$clean['floating_scope'] = 'include';
check( ! HTR_RR_Floating_Tab::visible( $clean ), 'Empty include selection displays nowhere' );
$clean['floating_include_pages'] = array( 1 );
check( HTR_RR_Floating_Tab::visible( $clean ), 'Selected page included' );
$clean['floating_exclude_pages'] = array( 1 );
check( ! HTR_RR_Floating_Tab::visible( $clean ), 'Exclusion overrides inclusion' );
$clean['floating_exclude_pages'] = array();
$_GET['htr_roof_report'] = 'fixture';
check( ! HTR_RR_Floating_Tab::visible( $clean ), 'Customer reports always hide floating links' );
unset( $_GET['htr_roof_report'] );
$GLOBALS['admin'] = true;
check( ! HTR_RR_Floating_Tab::visible( $clean ), 'Admin screens excluded' );
$GLOBALS['admin'] = false;
$GLOBALS['feed'] = true;
check( ! HTR_RR_Floating_Tab::visible( $clean ), 'Feeds excluded' );
$GLOBALS['feed'] = false;
$input['floating_items'] = array( array( 'label' => '<b>Call</b>', 'url' => 'tel:+12105550100', 'icon' => array(), 'new_tab' => 1 ), array( 'label' => 'Report', 'url' => 'https://example.test/report', 'icon' => 'report' ) );
$input['floating_style'] = 'icon_stack';
$clean = HTR_RR_Floating_Tab::sanitize( $input );
check( $clean['floating_items'][0]['label'] === 'Call' && $clean['floating_items'][0]['icon'] === 'report', 'Labels sanitized and unknown icon uses safe fallback' );
check( HTR_RR_Floating_Tab::items( $clean )[1]['label'] === 'Report', 'Link ordering retained' );
$input['floating_items'][0]['url'] = 'javascript:alert(1)';
check( is_wp_error( HTR_RR_Floating_Tab::sanitize( $input ) ), 'Executable URL rejected before saving' );
$input['floating_items'] = array_fill( 0, 11, array() );
check( is_wp_error( HTR_RR_Floating_Tab::sanitize( $input ) ), 'Excess links rejected without silently losing entries' );
$input['floating_items'] = array( array( 'label' => '', 'url' => '' ) );
check( HTR_RR_Floating_Tab::sanitize( $input )['floating_items'] === array(), 'Unused blank row omitted' );
check( strpos( HTR_RR_Floating_Tab::icon( 'unknown' ), '<script' ) === false, 'Icons only contain fixed trusted SVG' );
$document = HTR_RR_Floating_Tab::export_document( array_merge( HTR_RR_Settings::defaults(), array( 'n8n_secret' => 'fixture-secret' ) ) );
check( ! isset( $document['settings']['n8n_secret'], $document['settings']['materials'] ), 'Export excludes secrets and pricing' );
check( ! is_wp_error( HTR_RR_Floating_Tab::import_document( json_decode( json_encode( $document ), true ) ) ), 'JSON export can be reimported' );
$bad = $document; $bad['version'] = 2;
check( is_wp_error( HTR_RR_Floating_Tab::import_document( $bad ) ), 'Unknown document version rejected' );
$bad = $document; unset( $bad['settings']['floating_enabled'] );
check( is_wp_error( HTR_RR_Floating_Tab::import_document( $bad ) ), 'Incomplete import rejected before writing' );
$bad = $document; $bad['settings']['floating_items'] = array( array( 'label' => 'Bad', 'url' => 'javascript:alert(1)' ) );
check( is_wp_error( HTR_RR_Floating_Tab::import_document( $bad ) ), 'Invalid imported link rejected atomically' );
$document['settings']['n8n_secret'] = 'must-not-save';
check( ! isset( HTR_RR_Floating_Tab::import_document( $document )['n8n_secret'] ), 'Injected unrelated settings cannot be imported' );
$input['floating_label'] = str_repeat( 'é', 170 );
check( json_encode( HTR_RR_Floating_Tab::sanitize( $input ) ) !== false, 'Truncated Unicode labels remain valid UTF-8' );
$drawer = array_merge( $base, array( 'floating_enabled' => 1, 'floating_open_mode' => 'drawer', 'floating_drawer_form' => 'calculator' ) );
check( strpos( HTR_RR_Floating_Tab::items( $drawer )[0]['url'], 'htr_rr_drawer=calculator' ) !== false, 'Quote drawer works without a destination page' );
check( HTR_RR_Floating_Tab::visible( $drawer ), 'Drawer endpoint resolving to homepage does not hide the homepage tab' );
$_GET['htr_rr_drawer'] = 'calculator';
check( ! HTR_RR_Floating_Tab::visible( $drawer ), 'Embedded forms cannot recursively display the tab' );
unset( $_GET['htr_rr_drawer'] );
$drawer['floating_open_mode'] = 'invalid';
check( is_wp_error( HTR_RR_Floating_Tab::sanitize( $drawer ) ), 'Enabled tab without a destination cannot silently save' );
$drawer['floating_open_mode'] = 'drawer';
check( ! is_wp_error( HTR_RR_Floating_Tab::sanitize( $drawer ) ), 'Drawer needs no destination page' );
echo "Floating tab checks passed. Total checks: $checks\n";
