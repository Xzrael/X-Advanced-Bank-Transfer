<?php
/**
 * Main plugin class.
 *
 * @package X_Bank_Transfer
 */

defined( 'ABSPATH' ) || exit;

/**
 * XBT_Plugin - bootstraps and wires every component.
 */
final class XBT_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var XBT_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return XBT_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers all hooks.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function includes() {
		require_once XBT_PLUGIN_DIR . 'includes/class-xbt-gateway.php';
		require_once XBT_PLUGIN_DIR . 'includes/class-xbt-upload-handler.php';
	}

	/**
	 * Register hooks.
	 */
	private function init_hooks() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );

		// Load text domain for translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Register the receipt upload AJAX endpoints (works for guests too).
		add_action( 'wp_ajax_xbt_upload_receipt', array( 'XBT_Upload_Handler', 'handle_upload' ) );
		add_action( 'wp_ajax_nopriv_xbt_upload_receipt', array( 'XBT_Upload_Handler', 'handle_upload' ) );

		// Enqueue frontend assets for the checkout upload field.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Add a settings link on the plugins screen.
		add_filter( 'plugin_action_links_' . XBT_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

		// Display the uploaded receipt on the admin order edit screen.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_receipt_in_admin' ) );
	}

	/**
	 * Add the gateway to the list of available WooCommerce gateways.
	 *
	 * @param array $gateways Existing gateways.
	 * @return array
	 */
	public function add_gateway( $gateways ) {
		$gateways[] = 'XBT_Gateway';
		return $gateways;
	}

	/**
	 * Load the plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'x-bank-transfer', false, dirname( XBT_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register frontend assets.
	 */
	public function register_assets() {
		// Only load on the checkout and order received pages.
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! is_checkout() && ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
			return;
		}

		wp_register_style( 'xbt-checkout', XBT_PLUGIN_URL . 'assets/css/xbt-checkout.css', array(), XBT_VERSION );
		wp_enqueue_style( 'xbt-checkout' );

		// Inject the configured colors as CSS variables so the stylesheet
		// can be re-themed entirely from the gateway settings.
		$styling = self::get_styling_config();
		$inline  = ':root{'
			. '--xbt-primary:#' . esc_attr( ltrim( $styling['primary'], '#' ) ) . ';'
			. '--xbt-accent:#' . esc_attr( ltrim( $styling['accent'], '#' ) ) . ';'
			. '--xbt-primary-soft:' . esc_attr( self::hex_to_rgba( $styling['primary'], 0.06 ) ) . ';'
			. '--xbt-accent-soft:' . esc_attr( self::hex_to_rgba( $styling['accent'], 0.10 ) ) . ';'
			. '--xbt-primary-border:' . esc_attr( self::hex_to_rgba( $styling['primary'], 0.15 ) ) . ';'
			. '--xbt-primary-border-strong:' . esc_attr( self::hex_to_rgba( $styling['primary'], 0.25 ) ) . ';'
			. '--xbt-primary-dashed:' . esc_attr( self::hex_to_rgba( $styling['primary'], 0.35 ) ) . ';'
			. '--xbt-primary-shadow:' . esc_attr( self::hex_to_rgba( $styling['primary'], 0.08 ) ) . ';'
			. '--xbt-primary-shadow-soft:' . esc_attr( self::hex_to_rgba( $styling['primary'], 0.04 ) ) . ';'
			. '--xbt-primary-darker:' . esc_attr( self::darken_hex( $styling['primary'] ) ) . ';'
			. '}';
		wp_add_inline_style( 'xbt-checkout', $inline );

		wp_register_script( 'xbt-checkout-script', XBT_PLUGIN_URL . 'assets/js/xbt-checkout.js', array( 'jquery' ), XBT_VERSION, true );

		wp_localize_script(
			'xbt-checkout-script',
			'xbtCheckout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'xbt_upload_receipt' ),
				'text'    => array(
					'invalidFile' => __( 'Invalid file type. Please upload a JPG, PNG or PDF file.', 'x-bank-transfer' ),
					'defaultError' => __( 'There was an error uploading the file. Please try again.', 'x-bank-transfer' ),
				),
			)
		);

		wp_enqueue_script( 'xbt-checkout-script' );
	}

	/**
	 * Add a "Settings" link to the plugin row on the Plugins screen.
	 *
	 * @param array $links Action links for the plugin.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=xbt_gateway' );

		$plugin_links = array(
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'x-bank-transfer' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Display the uploaded bank receipt on the admin order edit screen.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function display_receipt_in_admin( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $this->gateway_id() !== $order->get_payment_method() ) {
			return;
		}

		$attach_id = $order->get_meta( '_xbt_receipt_attachment_id' );

		if ( empty( $attach_id ) || 'attachment' !== get_post_type( $attach_id ) ) {
			return;
		}

		$src   = wp_get_attachment_url( $attach_id );
		$mime  = get_post_mime_type( $attach_id );
		$title = get_the_title( $attach_id );

		if ( ! $src ) {
			return;
		}

		echo '<div class="xbt-admin-receipt" style="margin-top:12px;">';
		echo '<h3>' . esc_html__( 'Bank Payment Receipt', 'x-bank-transfer' ) . '</h3>';

		// Image files get a preview thumbnail; other files get a download link.
		if ( $mime && 0 === strpos( $mime, 'image/' ) ) {
			echo '<a href="' . esc_url( $src ) . '" target="_blank" rel="noopener">';
			echo '<img src="' . esc_url( $src ) . '" style="max-width:180px;height:auto;border:1px solid #ddd;border-radius:4px;" alt="' . esc_attr( $title ) . '" />';
			echo '</a>';
		} else {
			echo '<a class="button" href="' . esc_url( $src ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download receipt', 'x-bank-transfer' ) . ' (PDF)</a>';
		}

		echo '</div>';
	}

	/**
	 * Get the payment gateway ID used by this plugin.
	 *
	 * @return string
	 */
	private function gateway_id() {
		return 'xbt_gateway';
	}

	/**
	 * Build the styling configuration from saved settings.
	 *
	 * In "theme" mode the primary/accent colors are read from the active
	 * theme (Customizer mods first, then block-theme palette) and fall back
	 * to the manual colors when nothing is found. In "manual" mode the saved
	 * custom colors are used. All return values are validated hex colors.
	 *
	 * @return array{source:string, primary:string, accent:string}
	 */
	public static function get_styling_config() {
		$defaults = array(
			'source'  => 'manual',
			'primary' => '#9804CC',
			'accent'  => '#FFBE56',
		);

		// WooCommerce stores all gateway settings in one option array.
		$all_settings = get_option( 'woocommerce_xbt_gateway_settings', array() );
		$all_settings = is_array( $all_settings ) ? $all_settings : array();
		$settings     = isset( $all_settings['styling'] ) && is_array( $all_settings['styling'] ) ? $all_settings['styling'] : array();
		$config       = wp_parse_args( $settings, $defaults );

		$primary = self::sanitize_hex_color( isset( $config['primary'] ) ? $config['primary'] : '' );
		$accent  = self::sanitize_hex_color( isset( $config['accent'] ) ? $config['accent'] : '' );

		if ( 'theme' === $config['source'] ) {
			$theme_primary = self::theme_color( 'primary' );
			$theme_accent  = self::theme_color( 'accent' );

			if ( $theme_primary ) {
				$primary = $theme_primary;
			}
			if ( $theme_accent ) {
				$accent = $theme_accent;
			}
		}

		return array(
			'source'  => 'theme' === $config['source'] ? 'theme' : 'manual',
			'primary' => $primary ? $primary : $defaults['primary'],
			'accent'  => $accent ? $accent : $defaults['accent'],
		);
	}

	/**
	 * Try to read a color (primary/accent) from the active theme.
	 *
	 * @param string $role 'primary' or 'accent'.
	 * @return string Validated hex color or empty string.
	 */
	public static function theme_color( $role ) {
		// Let other plugins/themes override detection entirely.
		$filtered = apply_filters( "xbt_theme_{$role}_color", '', $role );
		$color    = self::sanitize_hex_color( $filtered );
		if ( $color ) {
			return $color;
		}

		// Common Customizer theme mods.
		$mods = 'primary' === $role
			? array( 'primary_color', 'primary', 'theme_color', 'xbt_primary' )
			: array( 'accent_color', 'accent', 'secondary_color', 'xbt_accent' );

		foreach ( $mods as $mod ) {
			$value = get_theme_mod( $mod, '' );
			$color = self::sanitize_hex_color( $value );
			if ( $color ) {
				return $color;
			}
		}

		// Block themes expose their palette via global styles settings (WP 5.9+).
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette' ) );
			if ( is_array( $palette ) ) {
				// Palette may be grouped per theme: ['theme' => [...], 'default' => [...]].
				$slugs = 'primary' === $role ? array( 'primary' ) : array( 'accent', 'secondary' );
				$listed = self::find_palette_color( $palette, $slugs );
				if ( $listed ) {
					$color = self::sanitize_hex_color( $listed );
					if ( $color ) {
						return $color;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Search a theme.json palette array for the first matching slug color.
	 *
	 * @param mixed  $palette Palette data.
	 * @param array  $slugs   Slugs to look for.
	 * @return string
	 */
	private static function find_palette_color( $palette, $slugs ) {
		if ( ! is_array( $palette ) || empty( $slugs ) ) {
			return '';
		}

		foreach ( $slugs as $slug ) {
			$found = self::search_slug( $palette, $slug );
			if ( $found ) {
				return $found;
			}
		}

		return '';
	}

	/**
	 * Recursively search palette entries for a slug and return its color.
	 *
	 * @param mixed  $items Array to search.
	 * @param string $slug  Slug to find.
	 * @return string
	 */
	private static function search_slug( $items, $slug ) {
		if ( ! is_array( $items ) ) {
			return '';
		}

		foreach ( $items as $key => $item ) {
			if ( 'slug' === $key && $item === $slug && isset( $items['color'] ) ) {
				return $items['color'];
			}
			if ( is_array( $item ) ) {
				$result = self::search_slug( $item, $slug );
				if ( $result ) {
					return $result;
				}
			}
		}

		return '';
	}

	/**
	 * Sanitize a hex color robustly (works even when sanitize_hex_color is
	 * not loaded).
	 *
	 * @param string $color Raw color value.
	 * @return string|false Validated "#rrggbb" or "#rgb" hex, or false.
	 */
	public static function sanitize_hex_color( $color ) {
		if ( is_string( $color ) && preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
			return $color;
		}
		return '';
	}

	/**
	 * Convert a hex color to an rgba() string.
	 *
	 * @param string $hex Hex color.
	 * @param float  $alpha Alpha 0-1.
	 * @return string
	 */
	public static function hex_to_rgba( $hex, $alpha = 1 ) {
		$hex  = ltrim( self::sanitize_hex_color( $hex ), '#' );
		$alpha = max( 0, min( 1, (float) $alpha ) );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			$hex = '9804CC';
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
	}

	/**
	 * Return a slightly darkened version of a hex color (used for hover states).
	 *
	 * @param string $hex Hex color.
	 * @return string
	 */
	public static function darken_hex( $hex, $factor = 0.12 ) {
		$hex = ltrim( self::sanitize_hex_color( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			$hex = '9804CC';
		}

		$r = max( 0, (int) ( hexdec( substr( $hex, 0, 2 ) ) * ( 1 - $factor ) ) );
		$g = max( 0, (int) ( hexdec( substr( $hex, 2, 2 ) ) * ( 1 - $factor ) ) );
		$b = max( 0, (int) ( hexdec( substr( $hex, 4, 2 ) ) * ( 1 - $factor ) ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}
}
