<?php
/** Isolated pagination bounds and per-user settings checks. */
if ( PHP_SAPI !== 'cli' ) { exit; }
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/class-admin.php';
function get_user_option( $option ) { return $GLOBALS['options'][ $option ] ?? false; }
function check_page( $condition ) { if ( ! $condition ) { throw new RuntimeException( 'Pagination check failed.' ); } }
$method = new ReflectionMethod( 'HTR_RR_Admin', 'page_state' );
$method->setAccessible( true );
$option = 'htr_rr_reports_per_page';
$state = $method->invoke( null, 51, $option );
check_page( $state['per_page'] === 20 && $state['pages'] === 3 && $state['offset'] === 0 );
$_GET['paged'] = 3;
$state = $method->invoke( null, 51, $option );
check_page( $state['page'] === 3 && $state['offset'] === 40 );
$_GET['paged'] = 999;
check_page( $method->invoke( null, 51, $option )['page'] === 3 );
$_GET['paged'] = -2;
check_page( $method->invoke( null, 51, $option )['page'] === 1 );
check_page( $method->invoke( null, 0, $option )['offset'] === 0 );
$GLOBALS['options'][ $option ] = 50;
check_page( $method->invoke( null, 51, $option )['pages'] === 2 );
check_page( $method->invoke( null, 51, 'htr_rr_logs_per_page' )['per_page'] === 20 );
check_page( HTR_RR_Admin::save_screen_option( false, $option, 500 ) === 200 );
check_page( HTR_RR_Admin::save_screen_option( false, $option, 0 ) === 1 );
check_page( HTR_RR_Admin::save_screen_option( false, $option, array() ) === 20 );
check_page( HTR_RR_Admin::save_screen_option( 'unchanged', 'unrelated', 50 ) === 'unchanged' );
echo "Passed 11 isolated pagination checks.\n";
