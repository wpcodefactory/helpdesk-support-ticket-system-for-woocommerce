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
 * Edition settings used by the shared core (Pro defines its own values).
 *
 * @version 2.3.0
 * @since   2.3.0
 */
defined( 'WPFACTORY_WC_STS_PAGE' ) || define( 'WPFACTORY_WC_STS_PAGE', 'support-ticket-system-woocommerce' );
defined( 'WPFACTORY_WC_STS_VERSION_OPTION' ) || define( 'WPFACTORY_WC_STS_VERSION_OPTION', 'wpfactory_wc_sts_version' );
defined( 'WPFACTORY_WC_STS_NAME' ) || define( 'WPFACTORY_WC_STS_NAME', 'Helpdesk Support Ticket System for WooCommerce' );

if ( ! function_exists( 'wpfactory_wc_sts_is_pro_active' ) ) {
	/**
	 * Whether the Pro plugin is active.
	 *
	 * Pro is a full standalone plugin sharing the same tickets, table, shortcode and endpoint.
	 * Running both creates every ticket, response and email twice, so the free plugin stands down.
	 *
	 * @version 2.2.1
	 * @since   2.2.1
	 *
	 * @return bool
	 */
	function wpfactory_wc_sts_is_pro_active() {
		$plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $plugins as $plugin ) {
			if ( '/support-ticket-system-woocommerce-pro.php' === substr( (string) $plugin, -strlen( '/support-ticket-system-woocommerce-pro.php' ) ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( wpfactory_wc_sts_is_pro_active() ) {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-info"><p>' .
				esc_html__( 'Helpdesk Support Ticket System for WooCommerce Pro is active, so the free version is paused. You can deactivate the free version; your tickets stay in place.', 'support-ticket-system-for-woocommerce' ) .
			'</p></div>';
		}
	);
	return;
}

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
foreach ( array( 'init', 'inc', 'customer', 'inbox', 'admin', 'emails', 'files', 'guest', 'canned', 'fields', 'privacy', 'ui', 'kb', 'chat', 'teamwork' ) as $wpfactory_wc_sts_part ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpfactory-wc-sts-' . $wpfactory_wc_sts_part . '.php';
}
