<?php
/**
 * Plugin Name: Xrupt Bank Transfer Receipts for WooCommerce
 * Plugin URI:  https://github.com/Xzrael/X-Advanced-Bank-Transfer
 * Description: WooCommerce bank transfer gateway with mandatory receipt upload, styled themed receipt cards and lightbox preview, plus brandable CSS-variable checkout theming with auto theme-color detection and live preview.
 * Version:     1.2.1
 * Author:      Xrupt
 * Author URI:  https://www.linkedin.com/in/johnson-josephx/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xrupt-bank-transfer
 * Domain Path: /languages
 * Requires at least: 5.4
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 9.4
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declare HPOS compatibility for WooCommerce.
 *
 * @see https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Plugin constants.
 */
define( 'XBT_VERSION', '1.2.1' );
define( 'XBT_PLUGIN_FILE', __FILE__ );
define( 'XBT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'XBT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'XBT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register activation & deactivation hooks.
 *
 * These MUST be registered in the main plugin file scope and directly callable.
 */
register_activation_hook( __FILE__, 'xbt_activate' );
register_deactivation_hook( __FILE__, 'xbt_deactivate' );

// Keep version option in sync for updates (so future migrations can run).
add_action(
	'plugins_loaded',
	function () {
		$stored = get_option( 'xbt_version' );
		if ( $stored !== XBT_VERSION ) {
			update_option( 'xbt_version', XBT_VERSION );
		}
	},
	20
);

/**
 * Activation routine.
 */
function xbt_activate() {
	// No custom database tables or rewrite rules are used, so nothing heavy is required.
	// Store a version marker for future upgrade routines.
	if ( false === get_option( 'xbt_version' ) ) {
		add_option( 'xbt_version', XBT_VERSION, '', false );
	}
}

/**
 * Deactivation routine.
 *
 * We intentionally keep uploaded receipts on disk and only remove the transient
 * caches set by this plugin. User data is never deleted on deactivation.
 */
function xbt_deactivate() {
	delete_transient( 'xbt_gateway_loaded' );
}

/**
 * Run the plugin once all plugins are loaded.
 */
add_action( 'plugins_loaded', 'xbt_init' );

/**
 * Bootstrap the plugin.
 */
function xbt_init() {
	// Only run if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'xbt_missing_woocommerce_notice' );
		return;
	}

	require_once XBT_PLUGIN_DIR . 'includes/class-xbt-plugin.php';
	XBT_Plugin::instance();
}

/**
 * Admin notice shown when WooCommerce is not active.
 */
function xbt_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Xrupt Bank Transfer Receipts for WooCommerce', 'xrupt-bank-transfer' ) . '</strong> ' . esc_html__( 'requires WooCommerce to be installed and active. Please activate WooCommerce.', 'xrupt-bank-transfer' ) . '</p></div>';
}
