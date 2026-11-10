<?php
/**
 * Plugin Name: Hometown Instant Roof Report
 * Description: Homeowners enter their address, confirm their roof on a satellite map, pick a roof type and get a roof measurement report with an estimated price range. Measurements: Google Solar API (free tier). Leads: sent to GoHighLevel through an n8n webhook. Shortcode: [htr_roof_report]
 * Version: 1.7.2
 * Author: Hometown Roofing & Restoration (Systems)
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: htr-roof-report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HTR_RR_VERSION', '1.7.2' );
define( 'HTR_RR_FILE', __FILE__ );
define( 'HTR_RR_DIR', plugin_dir_path( __FILE__ ) );
define( 'HTR_RR_URL', plugin_dir_url( __FILE__ ) );

require_once HTR_RR_DIR . 'includes/class-settings.php';
require_once HTR_RR_DIR . 'includes/class-db.php';
require_once HTR_RR_DIR . 'includes/class-estimator.php';
require_once HTR_RR_DIR . 'includes/class-solar.php';
require_once HTR_RR_DIR . 'includes/class-leads.php';
require_once HTR_RR_DIR . 'includes/class-rest.php';
require_once HTR_RR_DIR . 'includes/class-shortcode.php';
require_once HTR_RR_DIR . 'includes/class-estimate-design.php';
require_once HTR_RR_DIR . 'includes/class-report-layout.php';
HTR_RR_Report_Layout::init();
require_once HTR_RR_DIR . 'includes/class-report-page.php';
require_once HTR_RR_DIR . 'includes/class-admin.php';
require_once HTR_RR_DIR . 'includes/class-floating-tab.php';
HTR_RR_Floating_Tab::init();
require_once HTR_RR_DIR . 'includes/class-embed.php';
HTR_RR_Embed::init();
if ( is_admin() ) {
	require_once HTR_RR_DIR . 'includes/class-floating-admin.php';
	HTR_RR_Floating_Admin::init();
}

register_activation_hook( __FILE__, array( 'HTR_RR_DB', 'install' ) );
register_activation_hook( __FILE__, array( 'HTR_RR_Leads', 'schedule' ) );
register_deactivation_hook( __FILE__, array( 'HTR_RR_Leads', 'unschedule' ) );

add_action( 'plugins_loaded', array( 'HTR_RR_DB', 'maybe_upgrade' ) );
add_action( 'rest_api_init', array( 'HTR_RR_REST', 'routes' ) );
add_action( 'init', array( 'HTR_RR_REST', 'ajax_init' ) );
add_action( 'init', array( 'HTR_RR_Shortcode', 'init' ) );
add_action( 'init', array( 'HTR_RR_Report_Page', 'init' ) );
add_action( 'init', array( 'HTR_RR_Leads', 'init' ) );
if ( is_admin() ) {
	add_action( 'init', array( 'HTR_RR_Admin', 'init' ) );
}
