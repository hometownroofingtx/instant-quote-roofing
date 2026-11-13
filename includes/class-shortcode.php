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
		add_shortcode( 'htr_roof_report_search', array( __CLASS__, 'render_search' ) );
		add_shortcode( 'htr_roof_calculator', array( __CLASS__, 'render_calculator' ) );
		add_shortcode( 'htr_roof_quote_preview', array( __CLASS__, 'render_preview' ) );
	}

	/** Administrator-only, temporary quotes on a normal WordPress page. */
	public static function render_preview() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		if ( ! headers_sent() ) { nocache_headers(); }
		if ( ! current_user_can( 'manage_options' ) ) {
			return '<p>This quote preview is available to logged-in administrators only.</p>';
		}
		return self::render( array( 'backend' => true ) );
	}

	public static function render_calculator() {
		return self::render( array( 'calculator' => true ) );
	}

	public static function render_search( $atts ) {
		$atts = shortcode_atts( array( 'redirect' => '' ), $atts, 'htr_roof_report_search' );
		$redirect = wp_validate_redirect( esc_url_raw( $atts['redirect'] ), '' );
		if ( ! $redirect ) {
			return current_user_can( 'manage_options' ) ? '<p>Add a local report page URL using the redirect attribute on htr_roof_report_search.</p>' : '';
		}
		return self::render( array( 'search_redirect' => $redirect ) );
	}

	public static function render( $atts = array() ) {
		$backend = ! empty( $atts['backend'] ) && current_user_can( 'manage_options' );
		$search_redirect = isset( $atts['search_redirect'] ) ? $atts['search_redirect'] : '';
		wp_enqueue_style( 'htr-rr', HTR_RR_URL . 'assets/css/roof-report.css', array(), HTR_RR_VERSION . '.' . filemtime( HTR_RR_DIR . 'assets/css/roof-report.css' ) );
		wp_enqueue_script( 'htr-rr-diagram', HTR_RR_URL . 'assets/js/roof-diagram.js', array(), HTR_RR_VERSION, true );
		wp_enqueue_script( 'htr-rr', HTR_RR_URL . 'assets/js/roof-report.js', array( 'htr-rr-diagram' ), HTR_RR_VERSION . '.' . filemtime( HTR_RR_DIR . 'assets/js/roof-report.js' ), true );
		$cfg            = HTR_RR_Settings::public_config();
		$cfg['restUrl'] = esc_url_raw( rest_url( 'htr-rr/v1/' ) );
		$cfg['ajaxUrl'] = esc_url_raw( admin_url( 'admin-ajax.php' ) );
		$cfg['logo']    = esc_url_raw( HTR_RR_URL . 'assets/img/logo.png' );
		if ( $backend ) {
			$cfg['backend'] = true;
			$cfg['adminNonce'] = wp_create_nonce( 'htr_rr_admin_quote' );
			$cfg['contactMode'] = 'internal';
			$cfg['ghlFormId'] = '';
		}
		wp_add_inline_script( 'htr-rr', 'window.HTR_RR = ' . wp_json_encode( $cfg ) . ';', 'before' );
		ob_start();
		include HTR_RR_DIR . ( ! empty( $atts['calculator'] ) ? 'templates/calculator.php' : ( $search_redirect ? 'templates/search.php' : 'templates/form.php' ) );
		return ob_get_clean();
	}
}
