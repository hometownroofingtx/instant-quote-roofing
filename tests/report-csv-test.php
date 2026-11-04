<?php
/** Isolated CSV escaping checks; no WordPress database or live requests. */
if ( PHP_SAPI !== 'cli' ) { exit; }
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/class-admin.php';
$method = new ReflectionMethod( 'HTR_RR_Admin', 'report_csv_cell' );
$method->setAccessible( true );
$cases = array(
    array( '=1+1', "'=1+1" ),
    array( '+11234567890', "'+11234567890" ),
    array( "\t=1+1", "'\t=1+1" ),
    array( ' @SUM(A1)', "' @SUM(A1)" ),
    array( '-1+2', "'-1+2" ),
    array( 'San Antonio', 'San Antonio' ),
    array( '123 Main St, Suite "A"', '123 Main St, Suite "A"' ),
    array( null, '' ),
    array( 30000, '30000' ),
);
foreach ( $cases as $case ) {
    if ( $method->invoke( null, $case[0] ) !== $case[1] ) {
        throw new RuntimeException( 'CSV formula escaping failed.' );
    }
}
echo 'Passed ' . count( $cases ) . " CSV escaping checks (isolated; no live calls).\n";
