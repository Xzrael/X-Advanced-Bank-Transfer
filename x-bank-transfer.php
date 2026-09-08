<?php
/**
 * Plugin Name: X Bank Transfer
 * Plugin URI:  https://github.com/Xzrael/X-Advanced-Bank-Transfer
 * Description: Accept direct bank transfer payments. Customers upload their bank payment receipt (JPG, PNG or PDF) during checkout, and admins can view or download it from the order.
 * Version:     1.1.0
 * Author:      Xrupt
 * Author URI:  https://www.linkedin.com/in/johnson-josephx/
 * License:     GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: x-bank-transfer
 * Domain Path: /languages
 * Requires at least: 5.4
 * Requires PHP: 7.0
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'XBT_VERSION', '1.1.0' );
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

/**
 * Activation routine.
 */
function xbt_activate() {
	// No custom database tables or rewrite rules are used, so nothing heavy is required.
	// Store a version marker for future upgrade routines.
	if ( false === get_option( 'xbt_version' ) ) {
		add_option( 'xbt_version', XBT_VERSION );
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
	echo '<div class="notice notice-error"><p><strong>X Bank Transfer</strong> requires WooCommerce to be installed and active. Please activate WooCommerce.</p></div>';
}
