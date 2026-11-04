<?php
/** Render real tabbed settings with isolated WordPress doubles. No settings are saved. */
require __DIR__ . '/floating-admin-test.php';
function esc_textarea( $value ) { return esc_html( $value ); }
function submit_button( $text, $type = 'primary', $name = 'submit', $wrap = true ) { echo '<button class="button" type="submit" name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>'; }
$_GET['page'] = 'htr-rr-settings';
ob_start(); HTR_RR_Admin::settings(); $html = ob_get_clean();
check( substr_count( $html, 'data-settings-panel=' ) === 7, 'All existing settings sections represented' );
check( substr_count( $html, '<form ' ) === 2, 'Save and Solar test remain separate forms' );
check( strpos( $html, 'name="contact_mode"' ) !== false, 'Existing contact mode retained' );
$css = 'body{margin:0;padding:20px;background:#f0f2f5;font:14px system-ui}.button{display:inline-block;padding:6px 12px;border:1px solid #ccd6e1;border-radius:4px;text-decoration:none;color:#19344e;background:#fff}';
$css .= file_get_contents( HTR_RR_DIR . 'assets/css/admin.css' );
$js = file_get_contents( HTR_RR_DIR . 'assets/js/settings-admin.js' );
file_put_contents( __DIR__ . '/.settings-preview.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . $css . '</style></head><body>' . $html . '<script>' . $js . '</script></body></html>' );
echo "Settings tab render checks passed.\n";
