<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HTR_RR_Floating_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'save', 'export', 'import' ) as $action ) { add_action( 'admin_post_htr_rr_floating_' . $action, array( __CLASS__, $action . '_settings' ) ); }
	}
	public static function menu() {
		add_submenu_page( 'htr-rr', 'Floating Side Tab', 'Floating Side Tab', 'manage_options', 'htr-rr-floating', array( __CLASS__, 'render' ) );
	}
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'htr-rr-floating' ) ) { return; }
		foreach ( array( 'floating-tab', 'floating-admin' ) as $asset ) {
			$deps = 'floating-admin' === $asset ? array( 'htr-rr-floating-tab' ) : array();
			wp_enqueue_style( 'htr-rr-' . $asset, HTR_RR_URL . 'assets/css/' . $asset . '.css', $deps, HTR_RR_VERSION . '.' . filemtime( HTR_RR_DIR . 'assets/css/' . $asset . '.css' ) );
			wp_enqueue_script( 'htr-rr-' . $asset, HTR_RR_URL . 'assets/js/' . $asset . '.js', $deps, HTR_RR_VERSION . '.' . filemtime( HTR_RR_DIR . 'assets/js/' . $asset . '.js' ), true );
		}
	}
	private static function authorize( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'htr_rr_floating_' . $action );
	}
	private static function fail( $message ) { wp_die( esc_html( $message ), 'Floating tab settings', array( 'back_link' => true, 'response' => 400 ) ); }
	private static function save_values( array $input ) {
		$clean = HTR_RR_Floating_Tab::sanitize( $input );
		if ( is_wp_error( $clean ) ) { self::fail( $clean->get_error_message() ); }
		HTR_RR_Settings::save( $clean );
		wp_safe_redirect( admin_url( 'admin.php?page=htr-rr-floating&saved=1' ) );
		exit;
	}
	public static function save_settings() {
		self::authorize( 'save' );
		self::save_values( wp_unslash( $_POST ) ); // phpcs:ignore
	}
	public static function export_settings() {
		self::authorize( 'export' );
		$json = wp_json_encode( HTR_RR_Floating_Tab::export_document( HTR_RR_Settings::all() ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) { self::fail( 'Could not encode the settings.' ); }
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="htr-floating-tab-settings.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $json; // phpcs:ignore -- JSON download.
		exit;
	}
	public static function import_settings() {
		self::authorize( 'import' );
		$file = $_FILES['floating_file'] ?? array(); // phpcs:ignore
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_string( $file['tmp_name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) ) { self::fail( 'Choose a settings JSON file up to 1 MB.' ); }
		$json = file_get_contents( $file['tmp_name'], false, null, 0, 1048577 ); // phpcs:ignore
		if ( false === $json || strlen( $json ) > 1048576 ) { self::fail( 'Choose a settings JSON file up to 1 MB.' ); }
		$document = json_decode( preg_replace( '/^\xEF\xBB\xBF/', '', $json ), true, 32 );
		if ( JSON_ERROR_NONE !== json_last_error() ) { self::fail( 'The settings file is not valid JSON.' ); }
		$settings = HTR_RR_Floating_Tab::import_document( $document );
		if ( is_wp_error( $settings ) ) { self::fail( $settings->get_error_message() ); }
		self::save_values( $settings );
	}
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = HTR_RR_Settings::all();
		$pages = get_pages( array( 'post_status' => 'publish' ) );
		include HTR_RR_DIR . 'templates/floating-settings.php';
	}
	public static function field( array $s, $key, $label, $type = 'text', $options = array() ) {
		echo '<label class="htr-ft-field"><span>' . esc_html( $label ) . '</span>';
		if ( 'select' === $type ) {
			echo '<select name="' . esc_attr( $key ) . '">';
			foreach ( $options as $value => $text ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $s[ $key ], $value, false ) . '>' . esc_html( $text ) . '</option>'; }
			echo '</select>';
		} else {
			$bounds = array( 'floating_offset' => array( 0, 100 ), 'floating_radius' => array( 0, 40 ), 'floating_zindex' => array( 1, 99999 ) );
			$range = $bounds[ $key ] ?? array( 0, 99999 );
			echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . '"' . ( 'checkbox' === $type ? ' value="1"' . checked( $s[ $key ], 1, false ) : ' value="' . esc_attr( $s[ $key ] ) . '"' ) . ( 'number' === $type ? ' min="' . (int) $range[0] . '" max="' . (int) $range[1] . '" step="1"' : '' ) . '>';
		}
		echo '</label>';
	}
	public static function item( $index, array $item = array() ) {
		$prefix = 'floating_items[' . $index . ']';
		?>
		<div class="htr-ft-item" data-item>
			<label>Label<input name="<?php echo esc_attr( $prefix ); ?>[label]" value="<?php echo esc_attr( $item['label'] ?? '' ); ?>" maxlength="80" placeholder="Instant roof report"></label>
			<label>Link URL<input name="<?php echo esc_attr( $prefix ); ?>[url]" value="<?php echo esc_attr( $item['url'] ?? '' ); ?>" placeholder="https://… or tel:…"></label>
			<label>Icon<select name="<?php echo esc_attr( $prefix ); ?>[icon]">
			<?php foreach ( HTR_RR_Floating_Tab::icons() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $item['icon'] ?? 'report', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
			</select></label>
			<label class="htr-ft-check"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[new_tab]" value="1" <?php checked( $item['new_tab'] ?? 0, 1 ); ?>> Open in a new tab</label>
			<div class="htr-ft-item-actions"><button type="button" class="button" data-move="up" aria-label="Move link up">↑</button><button type="button" class="button" data-move="down" aria-label="Move link down">↓</button><button type="button" class="button" data-remove>Remove</button></div>
		</div>
		<?php
	}
}
