<?php
require __DIR__ . '/floating-admin-test.php';
foreach ( array( 'plugin', 'ghl_form' ) as $mode ) {
    $GLOBALS['saved']['contact_mode'] = $mode;
    ob_start(); include HTR_RR_DIR . 'templates/calculator.php'; $html = ob_get_clean();
    check( substr_count( $html, 'data-calc-stage=' ) === 4, 'All calculator steps rendered' );
    $css = 'body{margin:0;padding:24px;background:#eff5f9}';
    $css .= file_get_contents( HTR_RR_DIR . 'assets/css/roof-report.css' );
    $js = file_get_contents( HTR_RR_DIR . 'assets/js/roof-report.js' );
    file_put_contents( __DIR__ . '/.calculator-' . $mode . '-preview.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . $css . '</style></head><body>' . $html . '<script>' . $js . '</script></body></html>' );
}
echo "Calculator design template checks passed.\n";
$GLOBALS['saved']['contact_mode'] = 'ghl_form';
$GLOBALS['saved']['google_browser_key'] = 'fixture';
ob_start(); include HTR_RR_DIR . 'templates/form.php'; $instant = ob_get_clean();
file_put_contents( __DIR__ . '/.instant-preview.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . $css . '</style></head><body>' . $instant . '<script>' . $js . '</script></body></html>' );
