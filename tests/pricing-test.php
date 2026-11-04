<?php
/** Isolated pricing checks; no WordPress database, API calls or lead delivery. */
if ( PHP_SAPI !== 'cli' ) { exit; }
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error {
    private $message;
    public function __construct( $code, $message ) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $value ) ), '-' ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function get_option( $key, $default = array() ) { return $GLOBALS['saved'] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['saved'] = $value; }
function absint( $value ) { return abs( (int) $value ); }
function wp_attachment_is_image( $id ) { return $id === 123; }
require dirname( __DIR__ ) . '/includes/class-settings.php';
require dirname( __DIR__ ) . '/includes/class-estimator.php';
$checks = 0;
function check( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { throw new RuntimeException( $message ); } }
$settings = HTR_RR_Settings::defaults();
$new = array( 'label' => 'Standing Seam', 'low' => 8, 'high' => 12, 'quote' => 1 );
$area = array( 'town' => 'New Braunfels', 'lat' => 29.703, 'lng' => -98.124, 'mult' => 1.1 );
$input = array( 'new_mat' => array( $new ), 'ar' => array_merge( $settings['areas'], array( $area ) ) );
$clean = HTR_RR_Settings::pricing_rows( $input, $settings );
check( ! is_wp_error( $clean ), 'New roof and service area accepted' );
check( array_intersect_key( $clean['materials'], $settings['materials'] ) === $settings['materials'], 'Existing roof keys and rates preserved' );
check( count( $clean['areas'] ) === 6 && $clean['areas'][5]['town'] === 'New Braunfels', 'Service area appended' );
check( isset( $clean['materials']['standing-seam'] ), 'Stable custom roof key generated' );
HTR_RR_Settings::save( $clean );
$saved = HTR_RR_Settings::all();
check( $saved['materials']['standing-seam']['label'] === 'Standing Seam', 'Custom roof survives save and reload' );
check( in_array( 'standing-seam', array_column( HTR_RR_Settings::public_config()['materials'], 'key' ), true ), 'Customer form receives custom roof' );
$estimate = HTR_RR_Estimator::estimate( array( 'area_sqft' => 1000, 'pitch_x12' => 4, 'faces' => 2 ), 'standing-seam', $saved );
check( (float) $estimate['low'] === 8000.0 && (float) $estimate['high'] === 12000.0, 'Custom roof works in existing estimator' );
$nearest = HTR_RR_Estimator::nearest_area( 29.703, -98.124, $saved['areas'] );
check( $nearest['town'] === 'New Braunfels' && $nearest['mult'] === 1.1, 'New service area used for regional adjustment' );
$duplicate = HTR_RR_Settings::pricing_rows( array( 'new_mat' => array( $new ) ), $saved );
check( isset( $duplicate['materials']['standing-seam-2'] ) && $duplicate['materials']['standing-seam'] === $saved['materials']['standing-seam'], 'Duplicate label cannot overwrite an existing roof' );
$renamed = $new; $renamed['label'] = 'Updated label';
$edited = HTR_RR_Settings::pricing_rows( array( 'mat' => array( 'standing-seam' => $renamed ) ), $saved );
check( $edited['materials']['standing-seam']['label'] === 'Updated label', 'Renaming keeps the original key' );
check( $edited['areas'] === $saved['areas'], 'Missing area input preserves service areas' );
$no_price = $new; unset( $no_price['quote'] );
$edited = HTR_RR_Settings::pricing_rows( array( 'mat' => array( 'standing-seam' => $no_price ) ), $saved );
$unpriced = array_merge( $saved, $edited );
check( HTR_RR_Estimator::estimate( array( 'area_sqft' => 1000, 'pitch_x12' => 4, 'faces' => 2 ), 'standing-seam', $unpriced )['low'] === null, 'Unchecked Show price uses on-site quote' );
foreach ( array( array( 'lat' => 91 ), array( 'lng' => -181 ), array( 'lat' => '' ), array( 'lng' => 'invalid' ) ) as $invalid ) {
    check( is_wp_error( HTR_RR_Settings::pricing_rows( array( 'ar' => array( array_merge( $area, $invalid ) ) ), $saved ) ), 'Invalid coordinates rejected' );
}
check( is_wp_error( HTR_RR_Settings::pricing_rows( array( 'new_mat' => array( array_merge( $new, array( 'low' => 'invalid' ) ) ) ), $saved ) ), 'Invalid rates rejected' );
check( HTR_RR_Settings::all() === $saved, 'Validation does not mutate saved settings' );
$long = $new; $long['label'] = str_repeat( 'Long Roof Type ', 10 );
$long_clean = HTR_RR_Settings::pricing_rows( array( 'new_mat' => array( $long ) ), $saved );
check( strlen( array_key_last( $long_clean['materials'] ) ) <= 30, 'Generated keys fit stored report material column' );
$with_image = HTR_RR_Settings::pricing_rows( array( 'mat' => array( 'standing-seam' => array_merge( $new, array( 'image_id' => 123 ) ) ) ), $saved );
check( $with_image['materials']['standing-seam']['image_id'] === 123, 'Media Library image saved on roof type' );
HTR_RR_Settings::save( $with_image );
$image_settings = HTR_RR_Settings::all();
$public_materials = array_column( HTR_RR_Settings::public_config()['materials'], null, 'key' );
check( $public_materials['standing-seam']['image_id'] === 123, 'Frontend receives configured image ID' );
$image_preserved = HTR_RR_Settings::pricing_rows( array( 'mat' => array( 'standing-seam' => $new ) ), $image_settings );
check( $image_preserved['materials']['standing-seam']['image_id'] === 123, 'Older saves without image field preserve image' );
$image_removed = HTR_RR_Settings::pricing_rows( array( 'mat' => array( 'standing-seam' => array_merge( $new, array( 'image_id' => 0 ) ) ) ), $image_settings );
check( $image_removed['materials']['standing-seam']['image_id'] === 0, 'Image removal restores swatch fallback' );
check( is_wp_error( HTR_RR_Settings::pricing_rows( array( 'new_mat' => array( array_merge( $new, array( 'image_id' => 999 ) ) ) ), $saved ) ), 'Non-image attachment rejected' );
$export = HTR_RR_Settings::pricing_export();
check( ! isset( $export['pricing']['google_server_key'] ) && ! isset( $export['pricing']['n8n_secret'] ), 'Export excludes credentials' );
check( ! isset( $export['pricing']['materials']['standing-seam']['image_id'] ), 'Export excludes local attachment IDs' );
$import = HTR_RR_Settings::pricing_import( json_decode( json_encode( $export ), true ) );
check( ! is_wp_error( $import ), 'Export survives JSON round trip' );
check( $import['materials']['standing-seam']['image_id'] === 123, 'Import keeps matching local images' );
check( $import['areas'] === $image_settings['areas'] && $import['pitch_bands'] === $image_settings['pitch_bands'], 'Import restores service areas and pitch factors' );
$portable = $export;
$portable['pricing']['materials']['portable-roof'] = $new;
$portable['pricing']['google_server_key'] = 'must-not-import';
$portable_import = HTR_RR_Settings::pricing_import( $portable );
check( isset( $portable_import['materials']['portable-roof'] ) && ! isset( $portable_import['google_server_key'] ), 'Custom keys imported and unrelated settings ignored' );
foreach ( array( null, array(), array_merge( $export, array( 'version' => 2 ) ) ) as $invalid ) {
    check( is_wp_error( HTR_RR_Settings::pricing_import( $invalid ) ), 'Malformed or unsupported document rejected' );
}
foreach ( array( 'materials', 'areas', 'pitch_bands', 'complexity', 'service_radius_mi', 'waste_pct' ) as $key ) {
    $invalid = $export; unset( $invalid['pricing'][ $key ] );
    check( is_wp_error( HTR_RR_Settings::pricing_import( $invalid ) ), 'Incomplete import rejected: ' . $key );
}
foreach ( array( 'low' => -1, 'high' => 'invalid', 'quote' => array() ) as $key => $value ) {
    $invalid = $export; $invalid['pricing']['materials']['asphalt'][ $key ] = $value;
    check( is_wp_error( HTR_RR_Settings::pricing_import( $invalid ) ), 'Invalid material field rejected: ' . $key );
}
$invalid = $export; $invalid['pricing']['areas'][0]['lat'] = 91;
check( is_wp_error( HTR_RR_Settings::pricing_import( $invalid ) ), 'Import rejects invalid coordinates' );
$invalid = $export; $invalid['pricing']['pitch_bands'][0]['mult'] = 0;
check( is_wp_error( HTR_RR_Settings::pricing_import( $invalid ) ), 'Import rejects invalid pitch multiplier' );
$invalid = $export; $invalid['pricing']['complexity']['medium_max'] = 1;
check( is_wp_error( HTR_RR_Settings::pricing_import( $invalid ) ), 'Import rejects reversed complexity thresholds' );
foreach ( array( 60.5, '60', true, array(), INF, NAN ) as $value ) {
    $invalid = $export; $invalid['pricing']['service_radius_mi'] = $value;
    check( is_wp_error( HTR_RR_Settings::pricing_import( $invalid ) ), 'Import rejects invalid service radius without truncation or coercion' );
}
$invalid = $export; $invalid['pricing']['pitch_bands'][1]['max'] = $invalid['pricing']['pitch_bands'][0]['max'];
$error = HTR_RR_Settings::pricing_import( $invalid );
check( is_wp_error( $error ) && strpos( $error->get_error_message(), 'distinct' ) !== false, 'Duplicate pitch limits produce an actionable error' );
$invalid = $export; unset( $invalid['pricing']['materials']['asphalt'] );
check( is_wp_error( HTR_RR_Settings::pricing_import( $invalid ) ), 'Missing built-in roof identity is rejected' );
$foreign_image = $export; $foreign_image['pricing']['materials']['standing-seam']['image_id'] = 999;
check( HTR_RR_Settings::pricing_import( $foreign_image )['materials']['standing-seam']['image_id'] === 123, 'Foreign attachment IDs cannot overwrite local images' );
$reordered = $export; $reordered['pricing']['pitch_bands'] = array_reverse( $reordered['pricing']['pitch_bands'] );
check( HTR_RR_Settings::pricing_import( $reordered )['pitch_bands'] === $import['pitch_bands'], 'Pitch bands import in estimator order' );
check( HTR_RR_Settings::all() === $image_settings, 'Import validation never writes partial settings' );
HTR_RR_Settings::save( array( 'google_server_key' => 'local-google-key', 'n8n_secret' => 'local-webhook-secret' ) );
HTR_RR_Settings::save( $portable_import );
HTR_RR_Settings::save( $import );
check( HTR_RR_Settings::get( 'google_server_key' ) === 'local-google-key' && HTR_RR_Settings::get( 'n8n_secret' ) === 'local-webhook-secret', 'Applying an import preserves local credentials' );
check( ! isset( HTR_RR_Settings::all()['materials']['portable-roof'] ), 'Restore removes custom roof types absent from backup' );
check( HTR_RR_Settings::pricing_export() == $export, 'Restored pricing matches exported backup' );
echo "Passed $checks pricing checks (isolated doubles; no live calls).\n";
