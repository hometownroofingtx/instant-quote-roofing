<?php
// Uninstall keeps every report, lead and log (tables htr_rr_reports, htr_rr_log) and the settings. Nothing is deleted.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
wp_clear_scheduled_hook( 'htr_rr_retry_leads' );
