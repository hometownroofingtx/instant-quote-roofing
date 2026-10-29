<?php
/**
 * [htr_roof_report] - the 6-step homeowner form. Assets load only on pages that use the shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTR_RR_Shortcode {

	public static function init() {
		add_shortcode( 'htr_roof_report', array( __CLASS__, 'render' ) );
	}

	public static function render() {
		wp_enqueue_style( 'htr-rr', HTR_RR_URL . 'assets/css/roof-report.css', array(), HTR_RR_VERSION . '.' . filemtime( HTR_RR_DIR . 'assets/css/roof-report.css' ) );
		wp_enqueue_script( 'htr-rr-diagram', HTR_RR_URL . 'assets/js/roof-diagram.js', array(), HTR_RR_VERSION, true );
		wp_enqueue_script( 'htr-rr', HTR_RR_URL . 'assets/js/roof-report.js', array( 'htr-rr-diagram' ), HTR_RR_VERSION, true );
		$cfg            = HTR_RR_Settings::public_config();
		$cfg['restUrl'] = esc_url_raw( rest_url( 'htr-rr/v1/' ) );
		$cfg['ajaxUrl'] = esc_url_raw( admin_url( 'admin-ajax.php' ) );
		$cfg['logo']    = esc_url_raw( HTR_RR_URL . 'assets/img/logo.png' );
		wp_add_inline_script( 'htr-rr', 'window.HTR_RR = ' . wp_json_encode( $cfg ) . ';', 'before' );
		ob_start();
		include HTR_RR_DIR . 'templates/form.php';
		return ob_get_clean();
	}
}
