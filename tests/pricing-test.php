<?php
/** Isolated pricing checks; no WordPress database, API calls or lead delivery. */
if ( PHP_SAPI !== 'cli' ) { exit; }
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error { public function __construct( $code, $message ) {} }
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
echo "Passed $checks pricing checks (isolated doubles; no live calls).\n";
