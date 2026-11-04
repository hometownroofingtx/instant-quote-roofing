<?php
require __DIR__ . '/settings-tabs-test.php';
$_GET['page'] = 'htr-rr-pricing';
ob_start(); HTR_RR_Admin::pricing(); $html = ob_get_clean();
check( substr_count( $html, 'data-settings-panel=' ) === 4, 'Pricing has four panels' );
check( substr_count( $html, '<form ' ) === 3, 'Pricing save/export/import retain separate forms' );
$css = 'body{margin:0;padding:20px;background:#f0f2f5;font:14px system-ui}.button{display:inline-block;padding:6px 12px;border:1px solid #ccd6e1;border-radius:4px;text-decoration:none;color:#19344e;background:#fff}';
$css .= file_get_contents( HTR_RR_DIR . 'assets/css/admin.css' );
$js = file_get_contents( HTR_RR_DIR . 'assets/js/pricing-admin.js' ) . file_get_contents( HTR_RR_DIR . 'assets/js/settings-admin.js' );
file_put_contents( __DIR__ . '/.pricing-preview.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . $css . '</style></head><body>' . $html . '<script>' . $js . '</script></body></html>' );
echo "Pricing tab render checks passed.\n";
