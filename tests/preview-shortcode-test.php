<?php
/** Local WordPress integration check; no quotes generated and no customer data changed. */
require dirname( __DIR__, 4 ) . '/wp-load.php';
function preview_check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
preview_check( shortcode_exists( 'htr_roof_quote_preview' ), 'Preview shortcode must be registered.' );
wp_set_current_user( 0 );
$visitor = do_shortcode( '[htr_roof_quote_preview]' );
preview_check( strpos( $visitor, 'logged-in administrators only' ) !== false && strpos( $visitor, 'id="htr-rr"' ) === false, 'Visitors must not see the tool.' );
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
preview_check( ! empty( $admins ), 'Local WordPress needs an existing administrator.' );
wp_set_current_user( (int) $admins[0] );
$admin = do_shortcode( '[htr_roof_quote_preview]' );
preview_check( strpos( $admin, 'data-internal-quote' ) !== false, 'Frontend admin must see the internal quote tool.' );
preview_check( strpos( $admin, 'data-card="4"' ) === false && strpos( $admin, 'name="first_name"' ) === false, 'Preview must not render contact collection.' );
preview_check( strpos( $admin, 'Generate internal quote' ) !== false, 'Preview must offer quote generation.' );
preview_check( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, 'Preview must disable page caching.' );
wp_set_current_user( 0 );
echo "Preview shortcode integration checks passed: registered, visitor denied, frontend administrator allowed, no contact step, non-cacheable.\n";
