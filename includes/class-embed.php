<?php
/** Standalone quote with an explicit landing-page frame allowlist. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class HTR_RR_Embed {
	public static function init() { add_action( 'template_redirect', array( __CLASS__, 'page' ), 5 ); }
	public static function page() {
		$demo = isset( $_GET['htr_rr_embed_demo'] ) && '1' === $_GET['htr_rr_embed_demo'];
		$form = isset( $_GET['htr_rr_embed'] ) && 'report' === $_GET['htr_rr_embed'];
		if ( ! $demo && ! $form ) { return; }
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( $form && ! $demo ) {
			// X-Frame-Options cannot express a cross-origin allowlist; CSP enforces it.
			header_remove( 'X-Frame-Options' );
			header( "Content-Security-Policy: frame-ancestors 'self' https://link.hometownroofingtx.com" );
		} else {
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( "Content-Security-Policy: frame-ancestors 'self'" );
		}
		remove_action( 'wp_footer', array( 'HTR_RR_Floating_Tab', 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'remove_chat_assets' ), PHP_INT_MAX );
		add_action( 'wp_footer', array( __CLASS__, 'remove_chat_assets' ), 19 );
		if ( $demo ) {
			$embed_url = add_query_arg( 'htr_rr_embed', 'report', home_url( '/' ) );
			include HTR_RR_DIR . 'templates/embed-demo.php';
		} else {
			$content = do_shortcode( '[htr_roof_report]' );
			include HTR_RR_DIR . 'templates/embed.php';
		}
		exit;
	}
	/** Only registered for standalone embed requests; GHL form assets are separate. */
	public static function remove_chat_assets() {
		foreach ( array( 'leadconnector-chat-widget', 'leadconnector-text-widget', 'leadconnector-public' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
	}
}
