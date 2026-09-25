<?php
/**
 * Plugin Name: Helpdesk Support Ticket System for WooCommerce
 * Description: WordPress ticket system - Manage customer queries and issues on your WordPress eShop with helpdesk WooCommerce support ticket system.
 * Plugin URI: https://wpfactory.com/item/helpdesk-support-ticketing-system-for-woocommerce
 * Version: 2.2.0
 * Author: WPFactory
 * Author URI: https://wpfactory.com
 * Text Domain: support-ticket-system-for-woocommerce
 * Domain Path: /langs
 * WC tested up to: 11.1
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Created on: 09-10-2019
 * Updated on: 25-09-2026
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version.
 *
 * @version 2.0.0
 * @since   2.0.0
 */
defined( 'WPFACTORY_WC_STS_VERSION' ) || define( 'WPFACTORY_WC_STS_VERSION', '2.2.0' );

/**
 * Plugin file.
 *
 * @version 2.0.0
 * @since   2.0.0
 */
defined( 'WPFACTORY_WC_STS_FILE' ) || define( 'WPFACTORY_WC_STS_FILE', __FILE__ );

/**
 * Plugin main class.
 *
 * @version 2.0.0
 * @since   2.0.0
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpfactory-wc-sts.php';

if ( ! function_exists( 'wpfactory_wc_sts' ) ) {
	/**
	 * Returns the main instance of WPFactory_WC_STS to prevent the need to use globals.
	 *
	 * @version 2.0.0
	 * @since   2.0.0
	 */
	function wpfactory_wc_sts() {
		return WPFactory_WC_STS::instance();
	}
}

/**
 * Initialize the plugin.
 *
 * @version 2.0.0
 * @since   2.0.0
 */
add_action( 'plugins_loaded', 'wpfactory_wc_sts' );

/**
 * Includes.
 *
 * @version 2.2.0
 *
 * @todo (v2.2.0) Move everything to the `plugins_loaded` action.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpfactory-wc-sts-init.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpfactory-wc-sts-inc.php';
