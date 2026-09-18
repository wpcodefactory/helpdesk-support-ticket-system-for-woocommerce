<?php
/**
 * Plugin Name: Helpdesk Support Ticket System for WooCommerce
 * Description: WordPress ticket system - Manage customer queries and issues on your WordPress eShop with helpdesk WooCommerce support ticket system.
 * Plugin URI: https://extend-wp.com/support-ticket-system-for-woocommerce
 * Version: 2.2.0-dev
 * Author: WPFactory
 * Author URI: https://wpfactory.com
 * Text Domain: support-ticket-system-for-woocommerce
 * Domain Path: /langs
 * WC tested up to: 11.1
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Created On: 09-10-2019
 * Updated On: 17-09-2026
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

defined( 'WPFACTORY_WC_STS_VERSION' ) || define( 'WPFACTORY_WC_STS_VERSION', '2.2.0-dev-20260918-1019' );

defined( 'WPFACTORY_WC_STS_FILE' ) || define( 'WPFACTORY_WC_STS_FILE', __FILE__ );

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

add_action( 'plugins_loaded', 'wpfactory_wc_sts' );

/**
 * Includes.
 *
 * @version 2.2.0
 */
require_once plugin_dir_path( __FILE__ ) . '/init.php';
require_once plugin_dir_path( __FILE__ ) . '/includes.php';
require_once plugin_dir_path( __FILE__ ) . '/class-wpfactory-wc-sts-core.php';
