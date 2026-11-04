<?php
require __DIR__ . '/pricing-test.php';
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
$GLOBALS['saved'] = HTR_RR_Settings::defaults();
$design = HTR_RR_Settings::sanitize_calculator_design( array( 'title' => '<b>New title</b>', 'logo' => 123, 'sidebar_title' => "My estimate\nstarts here" ) );
check( $design['title'] === 'New title', 'Design text sanitized' );
check( $design['logo'] === 123, 'Valid image selected' );
check( $design['sidebar_title'] === "My estimate\nstarts here", 'Sidebar heading preserves intentional line breaks' );
check( $design['intro'] === HTR_RR_Settings::calculator_design_defaults()['intro'], 'Unspecified fields retain defaults' );
check( is_wp_error( HTR_RR_Settings::sanitize_calculator_design( array( 'background' => 999 ) ) ), 'Non-image attachment rejected before saving' );
check( is_wp_error( HTR_RR_Settings::sanitize_calculator_design( array( 'logo' => array() ) ) ), 'Malformed image value rejected' );
HTR_RR_Settings::save( array( 'calculator_design' => $design, 'n8n_secret' => 'local-secret' ) );
$restored = HTR_RR_Settings::sanitize_calculator_design( array( 'logo' => 0 ) );
check( $restored['logo'] === 0 && $restored['title'] === 'New title', 'Use default image retains other custom text' );
HTR_RR_Settings::save( array( 'calculator_design' => $restored ) );
check( HTR_RR_Settings::get( 'n8n_secret' ) === 'local-secret', 'Design saves preserve integration credentials' );
check( ! isset( HTR_RR_Settings::pricing_export()['pricing']['calculator_design'] ), 'Pricing exports stay scoped to pricing' );
$quote = HTR_RR_Settings::sanitize_quote_design( array( 'sidebar_title' => '<b>Roof help</b>', 'sidebar' => 123, 'step_1_heading' => 'Find your home', 'download' => 'Save PDF' ) );
check( $quote['sidebar_title'] === 'Roof help' && $quote['sidebar'] === 123, 'Quote sidebar text and media sanitized' );
check( $quote['step_1_heading'] === 'Find your home' && $quote['download'] === 'Save PDF', 'Quote headings and action copy editable' );
check( $quote['step_6_label'] === 'Roof Report', 'Partial quote updates preserve defaults' );
check( is_wp_error( HTR_RR_Settings::sanitize_quote_design( array( 'property' => 999 ) ) ), 'Quote design rejects non-image attachments' );
HTR_RR_Settings::save( array( 'quote_design' => $quote ) );
check( HTR_RR_Settings::get( 'n8n_secret' ) === 'local-secret', 'Quote design saves preserve integration settings' );
echo "Calculator and quote design settings checks passed. Total checks: $checks\n";
