<?php
/** Configurable floating navigation; uses the existing plugin option only. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HTR_RR_Floating_Tab {
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'drawer_page' ) );
	}

	public static function defaults() {
		return array_filter( HTR_RR_Settings::defaults(), function ( $key ) { return 0 === strpos( $key, 'floating_' ); }, ARRAY_FILTER_USE_KEY );
	}

	public static function icons() {
		return array( 'home' => 'Home', 'users' => 'People', 'map' => 'Location', 'heart' => 'Heart', 'phone' => 'Phone', 'calculator' => 'Calculator', 'report' => 'Report', 'shield' => 'Shield' );
	}

	public static function export_document( array $settings ) {
		return array( 'format' => 'htr-floating-tab', 'version' => 1, 'settings' => array_intersect_key( $settings, self::defaults() ) );
	}

	public static function import_document( $document ) {
		if ( ! is_array( $document ) || 'htr-floating-tab' !== ( $document['format'] ?? '' ) || 1 !== ( $document['version'] ?? null ) || ! is_array( $document['settings'] ?? null ) || array_diff_key( self::defaults(), $document['settings'] ) ) {
			return new WP_Error( 'floating_import', 'Upload a complete version 1 floating-tab settings export.' );
		}
		return self::sanitize( $document['settings'] );
	}

	private static function text( $value, $length ) {
		$text = sanitize_text_field( $value );
		preg_match( '/^.{0,' . (int) $length . '}/us', $text, $match );
		return $match[0] ?? '';
	}

	/** Fixed SVG paths, never user-supplied SVG or markup. */
	public static function icon( $name ) {
		$paths = array(
			'home' => '<path d="m3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9"/>',
			'users' => '<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4v3"/>',
			'map' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
			'heart' => '<path d="M20 5c-3-3-7-1-8 1-1-2-5-4-8-1-5 5 2 11 8 16 6-5 13-11 8-16Z"/>',
			'phone' => '<path d="m5 3 4 4-2 3a17 17 0 0 0 7 7l3-2 4 4-2 3C9 22 2 15 2 5Z"/>',
			'calculator' => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M8 6h8M8 10h2m4 0h2M8 14h2m4 0h2M8 18h2m4 0h2"/>',
			'report' => '<path d="M14 2H5v20h14V7ZM14 2v5h5M8 12h8M8 16h8"/>',
			'shield' => '<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6ZM8 12l3 3 5-6"/>',
		);
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? $paths['report'] ) . '</svg>';
	}

	/** Both settings saves and imports use this allowlist; no secrets can enter a tab export. */
	public static function sanitize( array $input ) {
		$out = self::defaults();
		foreach ( array( 'floating_enabled', 'floating_desktop', 'floating_tablet', 'floating_mobile', 'floating_shadow', 'floating_hide_destination' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) && in_array( $input[ $key ], array( 1, '1' ), true ) ? 1 : 0;
		}
		$choices = array(
			'floating_position' => array( 'right', 'left' ), 'floating_vertical' => array( 'center', 'top', 'bottom' ),
			'floating_behavior' => array( 'hover', 'click' ), 'floating_style' => array( 'quote', 'icon_stack', 'icon_label', 'expandable' ),
			'floating_scope' => array( 'all', 'include' ),
			'floating_open_mode' => array( 'page', 'drawer' ), 'floating_drawer_form' => array( 'report', 'calculator' ),
		);
		foreach ( $choices as $key => $allowed ) { if ( isset( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ) { $out[ $key ] = $input[ $key ]; } }
		foreach ( array( 'floating_offset' => array( 0, 100 ), 'floating_radius' => array( 0, 40 ), 'floating_zindex' => array( 1, 99999 ) ) as $key => $bounds ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) && is_numeric( $input[ $key ] ) ) { $out[ $key ] = min( $bounds[1], max( $bounds[0], (int) $input[ $key ] ) ); }
		}
		foreach ( array( 'floating_color', 'floating_hover', 'floating_background' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) && preg_match( '/^#[0-9a-f]{6}$/iD', $input[ $key ] ) ) { $out[ $key ] = $input[ $key ]; }
		}
		foreach ( array( 'floating_label', 'floating_heading', 'floating_message' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) { $out[ $key ] = self::text( $input[ $key ], 160 ); }
		}
		$out['floating_page'] = isset( $input['floating_page'] ) && is_scalar( $input['floating_page'] ) ? absint( $input['floating_page'] ) : 0;
		foreach ( array( 'floating_include_pages', 'floating_exclude_pages' ) as $key ) {
			$out[ $key ] = array_values( array_unique( array_filter( array_map( 'absint', array_filter( (array) ( $input[ $key ] ?? array() ), 'is_scalar' ) ) ) ) );
		}
		if ( isset( $input['floating_items'] ) && ( ! is_array( $input['floating_items'] ) || count( $input['floating_items'] ) > 10 ) ) { return new WP_Error( 'floating', 'Use no more than 10 links.' ); }
		foreach ( ( $input['floating_items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || ! is_string( $item['label'] ?? null ) || ! is_string( $item['url'] ?? null ) ) { return new WP_Error( 'floating', 'Each link needs a label and URL.' ); }
			$label = self::text( $item['label'], 80 );
			if ( '' === trim( $label ) && '' === trim( $item['url'] ) ) { continue; }
			$url = esc_url_raw( $item['url'], array( 'http', 'https', 'tel', 'mailto' ) );
			if ( '' === trim( $label ) || ! $url ) { return new WP_Error( 'floating', 'Each link needs a label and a valid web, telephone or email URL.' ); }
			$icon = is_string( $item['icon'] ?? null ) && isset( self::icons()[ $item['icon'] ] ) ? $item['icon'] : 'report';
			$out['floating_items'][] = array( 'label' => $label, 'url' => $url, 'icon' => $icon, 'new_tab' => ! empty( $item['new_tab'] ) ? 1 : 0 );
		}
		if ( ! empty( $out['floating_enabled'] ) && ! self::items( $out ) ) {
			return new WP_Error( 'floating_destination', 'Choose a published destination page in Items, add a quick link for your selected layout, or choose Slide-out drawer in General before enabling the tab.' );
		}
		return $out;
	}

	public static function items( array $s ) {
		if ( 'drawer' === $s['floating_open_mode'] && ( 'quote' === $s['floating_style'] || empty( $s['floating_items'] ) ) ) {
			return array( array( 'label' => $s['floating_label'] ?: 'Instant Roof Quote', 'url' => self::drawer_url( $s ), 'icon' => 'calculator', 'new_tab' => 0 ) );
		}
		if ( 'quote' !== $s['floating_style'] && ! empty( $s['floating_items'] ) ) { return $s['floating_items']; }
		$page = absint( $s['floating_page'] );
		if ( ! $page || 'publish' !== get_post_status( $page ) || 'page' !== get_post_type( $page ) ) { return array(); }
		return array( array( 'label' => $s['floating_label'] ?: 'Instant Roof Quote', 'url' => get_permalink( $page ), 'icon' => 'calculator', 'new_tab' => 0 ) );
	}

	public static function visible( array $s ) {
		if ( is_admin() || is_feed() || empty( $s['floating_enabled'] ) || ! empty( $_GET['htr_roof_report'] ) || isset( $_GET['htr_rr_drawer'] ) || ! self::items( $s ) ) { return false; } // phpcs:ignore
		$id = get_queried_object_id();
		if ( is_page() && in_array( $id, $s['floating_exclude_pages'], true ) ) { return false; }
		if ( 'include' === $s['floating_scope'] && ( ! is_page() || ! in_array( $id, $s['floating_include_pages'], true ) ) ) { return false; }
		if ( ! empty( $s['floating_hide_destination'] ) && is_page() ) {
			foreach ( self::items( $s ) as $item ) {
				// The drawer endpoint lives at the site root; it is not the homepage destination.
				if ( 'drawer' === $s['floating_open_mode'] && $item['url'] === self::drawer_url( $s ) ) { continue; }
				if ( $id === url_to_postid( $item['url'] ) ) { return false; }
			}
		}
		return true;
	}

	public static function assets() {
		if ( ! self::visible( HTR_RR_Settings::all() ) ) { return; }
		foreach ( array( 'css', 'js' ) as $type ) {
			$file = 'assets/' . $type . '/floating-tab.' . $type;
			$version = HTR_RR_VERSION . '.' . filemtime( HTR_RR_DIR . $file );
			if ( 'css' === $type ) { wp_enqueue_style( 'htr-rr-floating', HTR_RR_URL . $file, array(), $version ); }
			else { wp_enqueue_script( 'htr-rr-floating', HTR_RR_URL . $file, array(), $version, true ); }
		}
	}

	public static function render() {
		$s = HTR_RR_Settings::all();
		if ( self::visible( $s ) ) {
			self::markup( $s );
			if ( 'drawer' === $s['floating_open_mode'] ) { self::drawer_markup( $s ); }
		}
	}

	public static function drawer_url( array $s ) {
		return add_query_arg( 'htr_rr_drawer', $s['floating_drawer_form'], home_url( '/' ) );
	}

	public static function drawer_markup( array $s ) {
		?>
		<dialog class="htr-drawer htr-drawer--<?php echo esc_attr( $s['floating_position'] ); ?>" id="htr-quote-drawer" aria-labelledby="htr-drawer-title" data-htr-drawer>
			<header class="htr-drawer__header"><h2 id="htr-drawer-title"><?php echo 'calculator' === $s['floating_drawer_form'] ? 'Roof Calculator' : 'Instant Roof Report'; ?></h2><button type="button" data-drawer-close aria-label="Close roof quote form" autofocus>&times;</button></header>
			<div class="htr-drawer__body" aria-busy="true">
				<div class="htr-drawer__loading" role="status" aria-live="polite"><span class="htr-drawer__spinner" aria-hidden="true"></span><strong>Loading your roof quote form…</strong><span>This may take a moment.</span></div>
				<iframe title="<?php echo 'calculator' === $s['floating_drawer_form'] ? 'Roof calculator form' : 'Instant roof report form'; ?>" data-src="<?php echo esc_url( self::drawer_url( $s ) ); ?>" hidden></iframe>
			</div>
			<p class="htr-drawer__fallback"><a href="<?php echo esc_url( self::drawer_url( $s ) ); ?>" target="_blank" rel="noopener">Open form in a new tab</a></p>
		</dialog>
		<?php
	}

	/** Isolate the existing shortcode so theme headers/footers are not shown inside the drawer. */
	public static function drawer_page() {
		if ( ! isset( $_GET['htr_rr_drawer'] ) ) { return; } // phpcs:ignore
		$s = HTR_RR_Settings::all();
		$form = $_GET['htr_rr_drawer']; // phpcs:ignore
		if ( empty( $s['floating_enabled'] ) || 'drawer' !== $s['floating_open_mode'] || ! is_string( $form ) || $form !== $s['floating_drawer_form'] ) { wp_die( 'This quote form is not available.', 'Form unavailable', array( 'response' => 404 ) ); }
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		$content = do_shortcode( 'calculator' === $form ? '[htr_roof_calculator]' : '[htr_roof_report]' );
		include HTR_RR_DIR . 'templates/floating-drawer.php';
		exit;
	}

	/** Same markup is used by the frontend and admin live preview. */
	public static function markup( array $s, $preview = false ) {
		$items = self::items( $s );
		if ( ! $items && $preview ) { $items = array( array( 'label' => 'Your quote page', 'url' => '#', 'icon' => 'calculator', 'new_tab' => 0 ) ); }
		$classes = 'htr-floating htr-floating--' . $s['floating_position'] . ' htr-floating--' . $s['floating_vertical'] . ' htr-floating--' . $s['floating_style'];
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) { if ( empty( $s[ 'floating_' . $device ] ) ) { $classes .= ' htr-floating--hide-' . $device; } }
		$style = '--htr-tab-color:' . $s['floating_color'] . ';--htr-tab-hover:' . $s['floating_hover'] . ';--htr-tab-bg:' . $s['floating_background'] . ';--htr-tab-radius:' . (int) $s['floating_radius'] . 'px;--htr-tab-offset:' . (int) $s['floating_offset'] . 'px;--htr-tab-z:' . (int) $s['floating_zindex'] . ';';
		if ( empty( $s['floating_shadow'] ) ) { $style .= '--htr-tab-shadow:none;'; }
		?>
		<aside class="<?php echo esc_attr( $classes ); ?>" style="<?php echo esc_attr( $style ); ?>" aria-label="Quick links" data-htr-floating data-open-mode="<?php echo esc_attr( $s['floating_open_mode'] ); ?>" data-drawer-url="<?php echo esc_url( self::drawer_url( $s ) ); ?>" data-drawer-page-url="<?php echo esc_url( $s['floating_page'] ? get_permalink( $s['floating_page'] ) : '' ); ?>" data-behavior="<?php echo esc_attr( $s['floating_behavior'] ); ?>" <?php echo $preview ? 'data-preview="true"' : ''; ?>>
			<?php if ( 'quote' === $s['floating_style'] ) : ?>
				<?php if ( $s['floating_heading'] || $s['floating_message'] ) : ?>
				<div class="htr-floating__message" data-htr-floating-message><button class="htr-floating__close" type="button" aria-label="Dismiss roof quote message">&times;</button><a href="<?php echo esc_url( $items[0]['url'] ); ?>"><strong><?php echo esc_html( $s['floating_heading'] ); ?></strong><span><?php echo esc_html( $s['floating_message'] ); ?></span></a></div>
				<?php endif; ?>
				<a class="htr-floating__tab" href="<?php echo esc_url( $items[0]['url'] ); ?>"><?php echo esc_html( $items[0]['label'] ); ?></a>
			<?php else : ?>
				<?php if ( 'expandable' === $s['floating_style'] ) : ?><button class="htr-floating__toggle" type="button" aria-expanded="false" aria-controls="<?php echo $preview ? 'htr-floating-preview-links' : 'htr-floating-links'; ?>" aria-label="Toggle quick links"><?php echo self::icon( 'report' ); ?></button><?php endif; ?>
				<nav class="htr-floating__links" id="<?php echo $preview ? 'htr-floating-preview-links' : 'htr-floating-links'; ?>" aria-label="Quick links" <?php echo 'expandable' === $s['floating_style'] ? 'hidden' : ''; ?>>
				<?php foreach ( $items as $item ) : ?>
					<a class="htr-floating__item" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo ! empty( $item['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><span class="htr-floating__icon"><?php echo self::icon( $item['icon'] ); ?></span><span class="htr-floating__label"><?php echo esc_html( $item['label'] ); ?><?php if ( ! empty( $item['new_tab'] ) ) : ?><span class="htr-floating__sr"> (opens in a new tab)</span><?php endif; ?></span></a>
				<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</aside>
		<?php
	}
}
