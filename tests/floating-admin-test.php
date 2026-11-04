<?php
/** Render the real admin template with isolated WordPress doubles; optional browser fixture. */
require __DIR__ . '/floating-tab-test.php';
require dirname( __DIR__ ) . '/includes/class-floating-admin.php';
require dirname( __DIR__ ) . '/includes/class-admin.php';
define( 'HTR_RR_URL', '../' );
$_GET['page'] = 'htr-rr-floating';
function current_user_can( $cap ) { return true; }
function wp_unslash( $value ) { return $value; }
function get_pages( $args ) { return array( (object) array( 'ID' => 10, 'post_title' => 'Instant Roof Report' ) ); }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=fixture'; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="fixture">'; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_attr( $text ); }
function selected( $actual, $expected = true, $echo = true ) { $text = (string) $actual === (string) $expected ? ' selected' : ''; if ( $echo ) { echo $text; } return $text; }
function checked( $actual, $expected = true, $echo = true ) { $text = (string) $actual === (string) $expected ? ' checked' : ''; if ( $echo ) { echo $text; } return $text; }
function wp_json_encode( $data, $options = 0 ) { return json_encode( $data, $options ); }
define( 'HTR_RR_DIR', dirname( __DIR__ ) . '/' );
$GLOBALS['saved'] = HTR_RR_Settings::defaults();
$GLOBALS['saved']['floating_enabled'] = 1;
$GLOBALS['saved']['floating_style'] = 'icon_stack';
$GLOBALS['saved']['floating_items'] = array(
    array( 'label' => 'Home', 'url' => 'https://example.test/', 'icon' => 'home', 'new_tab' => 0 ),
    array( 'label' => 'Our team', 'url' => 'https://example.test/team', 'icon' => 'users', 'new_tab' => 0 ),
    array( 'label' => 'Service areas', 'url' => 'https://example.test/locations', 'icon' => 'map', 'new_tab' => 0 ),
    array( 'label' => 'Get a quote', 'url' => 'https://example.test/quote', 'icon' => 'heart', 'new_tab' => 0 ),
);
ob_start(); HTR_RR_Floating_Admin::render(); $html = ob_get_clean();
check( substr_count( $html, 'role="tabpanel"' ) === 5, 'Five settings panels rendered' );
check( strpos( $html, 'name="n8n_secret"' ) === false, 'Floating page cannot overwrite integration settings' );
check( strpos( $html, 'data-icons' ) !== false && strpos( $html, 'data-item-template' ) !== false, 'Live preview data and item template rendered' );
if ( in_array( '--preview', $argv, true ) ) {
    $css = 'body{margin:0;padding:24px;background:#f0f2f5;font:14px system-ui,sans-serif}.button{display:inline-block;border:1px solid #c3cfdd;background:white;color:#19344e;cursor:pointer;text-decoration:none;font:inherit}.button-primary{color:white}input,select{padding:6px;border:1px solid #c3cfdd;border-radius:4px;font:inherit}.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}';
    $css .= file_get_contents( HTR_RR_DIR . 'assets/css/admin.css' ) . file_get_contents( HTR_RR_DIR . 'assets/css/floating-tab.css' ) . file_get_contents( HTR_RR_DIR . 'assets/css/floating-admin.css' );
    $js = file_get_contents( HTR_RR_DIR . 'assets/js/floating-tab.js' ) . file_get_contents( HTR_RR_DIR . 'assets/js/floating-admin.js' );
    ob_start(); HTR_RR_Floating_Tab::drawer_markup( HTR_RR_Settings::all() ); $drawer = ob_get_clean();
    file_put_contents( __DIR__ . '/.floating-preview.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . $css . '</style></head><body>' . $html . $drawer . '<script>' . $js . '</script></body></html>' );
}
echo "Floating admin template checks passed.\n";
