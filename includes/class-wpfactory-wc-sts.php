<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Main Class
 *
 * @version 2.2.0
 * @since   2.0.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS' ) ) :

	/**
	 * Main WPFactory_WC_STS Class.
	 *
	 * @version 2.2.0
	 * @since   2.0.0
	 */
	final class WPFactory_WC_STS {

		/**
		 * Plugin version.
		 *
		 * @version 2.0.0
		 * @since   2.0.0
		 *
		 * @var string
		 */
		public $version = WPFACTORY_WC_STS_VERSION;

		/**
		 * The single instance of the class.
		 *
		 * @version 2.2.0
		 * @since   2.0.0
		 *
		 * @var WPFactory_WC_STS
		 */
		protected static $instance = null;

		/**
		 * Main WPFactory_WC_STS Instance.
		 *
		 * Ensures only one instance of WPFactory_WC_STS is loaded or can be loaded.
		 *
		 * @version 2.2.0
		 * @since   2.0.0
		 *
		 * @static
		 *
		 * @return WPFactory_WC_STS
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * WPFactory_WC_STS Constructor.
		 *
		 * @version 2.2.0
		 * @since   2.0.0
		 *
		 * @access  public
		 */
		public function __construct() {
			// Check for active WooCommerce plugin.
			if ( ! function_exists( 'WC' ) ) {
				return;
			}

			// Declare compatibility with custom order tables for WooCommerce.
			add_action( 'before_woocommerce_init', array( $this, 'wc_declare_compatibility' ) );

			// Admin.
			if ( is_admin() ) {
				$this->admin();
			}
		}

		/**
		 * WC declare compatibility.
		 *
		 * @version 2.0.0
		 * @since   2.0.0
		 *
		 * @see https://developer.woocommerce.com/docs/hpos-extension-recipe-book/
		 */
		public function wc_declare_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					WPFACTORY_WC_STS_FILE,
					true
				);
			}
		}

		/**
		 * Admin.
		 *
		 * @version 2.0.0
		 * @since   2.0.0
		 */
		public function admin() {
			// Load libs.
			require_once plugin_dir_path( WPFACTORY_WC_STS_FILE ) . 'vendor/autoload.php';

			// Action links.
			add_filter(
				'plugin_action_links_' . plugin_basename( WPFACTORY_WC_STS_FILE ),
				array( $this, 'action_links' )
			);

			// "Recommendations" page.
			add_action( 'init', array( $this, 'add_cross_selling_library' ) );

			// Settings.
			add_action( 'admin_menu', array( $this, 'add_settings' ), 11 );

			// One short, dismissible note about Pro on the plugin's own screens.
			add_action( 'admin_notices', array( $this, 'pro_notice' ) );
			add_action( 'admin_post_wpfactory_wc_sts_dismiss_pro_notice', array( $this, 'dismiss_pro_notice' ) );
		}

		/**
		 * Action links.
		 *
		 * @version 2.2.0
		 * @since   2.0.0
		 *
		 * @param mixed $links Action links for the plugin.
		 *
		 * @return array
		 */
		public function action_links( $links ) {
			$custom_links = array();

			$custom_links[] = '<a href="' . admin_url( 'admin.php?page=support-ticket-system-woocommerce&tab=settings' ) . '">' .
				__( 'Settings', 'support-ticket-system-for-woocommerce' ) .
			'</a>';

			$pro_url        = 'https://wpfactory.com/item/helpdesk-support-ticketing-system-for-woocommerce';
			$custom_links[] = '<a target="_blank" style="font-weight: bold; color: green;" href="' . $pro_url . '">' .
				__( 'Go Pro', 'support-ticket-system-for-woocommerce' ) .
			'</a>';

			return array_merge( $custom_links, $links );
		}

		/**
		 * Add cross selling library.
		 *
		 * @version 2.0.0
		 * @since   2.0.0
		 */
		public function add_cross_selling_library() {
			if ( ! class_exists( '\WPFactory\WPFactory_Cross_Selling\WPFactory_Cross_Selling' ) ) {
				return;
			}

			$cross_selling = new \WPFactory\WPFactory_Cross_Selling\WPFactory_Cross_Selling();
			$cross_selling->setup( array( 'plugin_file_path' => WPFACTORY_WC_STS_FILE ) );
			$cross_selling->init();
		}

		/**
		 * Add settings.
		 *
		 * @version 2.0.0
		 * @since   2.0.0
		 */
		public function add_settings() {
			add_menu_page(
				__( 'Support Tickets', 'support-ticket-system-for-woocommerce' ),
				__( 'Support Tickets', 'support-ticket-system-for-woocommerce' ),
				'stsw_tickets', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- granted to shop staff and the assignment role.
				'support-ticket-system-woocommerce',
				array( $this, 'output_settings' ),
				'dashicons-tag',
				50
			);

			add_submenu_page(
				'support-ticket-system-woocommerce',
				__( 'Inbox', 'support-ticket-system-for-woocommerce' ),
				__( 'Inbox', 'support-ticket-system-for-woocommerce' ),
				'stsw_tickets', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- granted to shop staff and the assignment role.
				'support-ticket-system-woocommerce',
				array( $this, 'output_settings' ),
			);

			if ( ! class_exists( 'WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu' ) ) {
				return;
			}

			$admin_menu = WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu::get_instance();

			add_submenu_page(
				$admin_menu->get_menu_slug(),
				__( 'Support Tickets', 'support-ticket-system-for-woocommerce' ),
				__( 'Support Tickets', 'support-ticket-system-for-woocommerce' ),
				'stsw_tickets', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- granted to shop staff and the assignment role.
				'support-ticket-system-woocommerce',
				array( $this, 'output_settings' ),
				30
			);
		}

		/**
		 * Output settings.
		 *
		 * @version 2.0.0
		 * @since   2.0.0
		 */
		public function output_settings() {
			do_action( 'wpfactory_wc_sts_output_settings' );
		}

		/**
		 * Plugin URL.
		 *
		 * @version 2.0.0
		 * @since   2.0.0
		 *
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit( plugin_dir_url( WPFACTORY_WC_STS_FILE ) );
		}

		/**
		 * Plugin path.
		 *
		 * @version 2.0.0
		 * @since   2.0.0
		 *
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( WPFACTORY_WC_STS_FILE ) );
		}

		/**
		 * Short note about the Pro edition on the plugin's screens, until dismissed.
		 *
		 * @version 2.3.0
		 * @since   2.3.0
		 */
		public function pro_notice() {
			if ( ! current_user_can( 'manage_woocommerce' ) || get_user_meta( get_current_user_id(), 'wpfactory_wc_sts_pro_notice_dismissed', true ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				return;
			}
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( WPFACTORY_WC_STS_PAGE !== $page ) {
				return;
			}
			$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=wpfactory_wc_sts_dismiss_pro_notice' ), 'wpfactory_wc_sts_dismiss_pro_notice' );
			echo '<div class="notice notice-info"><p>' .
				esc_html__( 'Pro adds the order panel and order actions in tickets (refunds, resend emails, status changes), email piping, satisfaction ratings and reports, AI reply drafts with your own key, automation and a REST API.', 'support-ticket-system-for-woocommerce' ) .
				' <a href="https://wpfactory.com/item/helpdesk-support-ticketing-system-for-woocommerce" target="_blank" rel="noopener">' . esc_html__( 'See Pro', 'support-ticket-system-for-woocommerce' ) . '</a>' .
				' | <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'support-ticket-system-for-woocommerce' ) . '</a></p></div>';
		}

		/**
		 * Hides the Pro note for this user.
		 *
		 * @version 2.3.0
		 * @since   2.3.0
		 */
		public function dismiss_pro_notice() {
			check_admin_referer( 'wpfactory_wc_sts_dismiss_pro_notice' );
			if ( current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				update_user_meta( get_current_user_id(), 'wpfactory_wc_sts_pro_notice_dismissed', 1 );
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE ) );
			exit;
		}
	}

endif;
