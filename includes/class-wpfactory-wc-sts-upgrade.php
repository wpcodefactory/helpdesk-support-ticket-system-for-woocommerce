<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - "Pro features" page (free edition only).
 *
 * One header tab that describes what the Pro plugin adds, with a link to it. Information only: no Pro code, no
 * locked or disabled controls, and every feature of this plugin works without Pro.
 *
 * @version 3.1.0
 * @since   3.1.0
 *
 * @author  WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Upgrade' ) ) :

	/**
	 * WPFactory_WC_STS_Upgrade class.
	 */
	class WPFactory_WC_STS_Upgrade {

		/**
		 * Pro plugin page.
		 */
		const URL = 'https://wpfactory.com/item/helpdesk-support-ticketing-system-for-woocommerce/';

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Upgrade|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Upgrade
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'wpfactory_wc_sts_header_tabs', array( $this, 'header_tab' ), 50 );
			add_action( 'wpfactory_wc_sts_render_tab_pro', array( $this, 'render' ) );
		}

		/**
		 * "Pro features" in the plugin header, for people who manage the store.
		 *
		 * @param array $tabs Tabs.
		 *
		 * @return array
		 */
		public function header_tab( $tabs ) {
			if ( current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				$tabs['pro'] = __( 'Pro features', 'support-ticket-system-for-woocommerce' );
			}
			return $tabs;
		}

		/**
		 * What Pro adds, by area.
		 *
		 * @return array key => array( icon, title, points )
		 */
		public function features() {
			return array(
				'orders'     => array(
					'coin',
					__( 'Fix it from the ticket', 'support-ticket-system-for-woocommerce' ),
					array(
						__( 'Order panel next to the conversation: items, payment, shipping, tracking.', 'support-ticket-system-for-woocommerce' ),
						__( 'Refunds, store credit, coupons and free replacement orders, each confirmed and logged.', 'support-ticket-system-for-woocommerce' ),
						__( 'Returns and exchanges, payment links for pre-sales questions.', 'support-ticket-system-for-woocommerce' ),
					),
				),
				'automation' => array(
					'zap',
					__( 'Automation', 'support-ticket-system-for-woocommerce' ),
					array(
						__( 'Workflow rules: assign, tag, set priority, send a saved reply or close, on conditions you choose.', 'support-ticket-system-for-woocommerce' ),
						__( 'Reply targets with an Overdue view, working hours and holidays, automatic closing.', 'support-ticket-system-for-woocommerce' ),
						__( 'Tickets opened for you when a payment fails or an order is stuck.', 'support-ticket-system-for-woocommerce' ),
					),
				),
				'email'      => array(
					'mail',
					__( 'Tickets from email', 'support-ticket-system-for-woocommerce' ),
					array(
						__( 'Email piping (IMAP or POP3): emails become tickets and replies, with attachments.', 'support-ticket-system-for-woocommerce' ),
						__( 'Several mailboxes, each with its own address and sender name.', 'support-ticket-system-for-woocommerce' ),
					),
				),
				'team'       => array(
					'users',
					__( 'Team', 'support-ticket-system-for-woocommerce' ),
					array(
						__( 'Support agent role with permissions per role: view, reply, assign, close.', 'support-ticket-system-for-woocommerce' ),
						__( 'Draft then approve, watchers and CC, split and merge tickets.', 'support-ticket-system-for-woocommerce' ),
					),
				),
				'customers'  => array(
					'star',
					__( 'Customers and paid support', 'support-ticket-system-for-woocommerce' ),
					array(
						__( 'Companies: colleagues share their tickets.', 'support-ticket-system-for-woocommerce' ),
						__( 'Sell priority support or ticket credits as WooCommerce products, support period per product.', 'support-ticket-system-for-woocommerce' ),
						__( 'Satisfaction ratings after each ticket.', 'support-ticket-system-for-woocommerce' ),
					),
				),
				'reports'    => array(
					'chart',
					__( 'Reports', 'support-ticket-system-for-woocommerce' ),
					array(
						__( 'Tickets per day, median first reply and time to close, replies by team member, satisfaction results.', 'support-ticket-system-for-woocommerce' ),
					),
				),
				'ai'         => array(
					'sparkle',
					__( 'More AI', 'support-ticket-system-for-woocommerce' ),
					array(
						__( 'Use your own key (Anthropic, OpenAI, Google Gemini) or an AI plugin you already have, with a monthly spending limit.', 'support-ticket-system-for-woocommerce' ),
						__( 'A suggested resolution for tickets with an order: refund, replacement or store credit, checked against the order.', 'support-ticket-system-for-woocommerce' ),
					),
				),
				'developers' => array(
					'code',
					__( 'For developers', 'support-ticket-system-for-woocommerce' ),
					array(
						__( 'REST API to create and answer tickets, signed webhooks, and an MCP server for AI agents.', 'support-ticket-system-for-woocommerce' ),
					),
				),
			);
		}

		/**
		 * The page.
		 */
		public function render() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				return;
			}
			$cta = '<a class="hd-btn hd-btn--primary" href="' . esc_url( self::URL ) . '" target="_blank" rel="noopener noreferrer" data-stsw-pro-cta>' . esc_html__( 'See Helpdesk Pro', 'support-ticket-system-for-woocommerce' ) . '</a>';
			echo '<div class="hd-page hd-pro" data-stsw-pro-page>';
			WPFactory_WC_STS_UI::page_head( __( 'Pro features', 'support-ticket-system-for-woocommerce' ), __( 'Everything you use now keeps working, and AI replies are included in this plugin. Helpdesk Pro is a separate plugin that adds the following.', 'support-ticket-system-for-woocommerce' ), $cta );
			echo '<ul class="hd-pro__grid">';
			foreach ( $this->features() as $key => $feature ) {
				echo '<li class="hd-pro__card" data-feature="' . esc_attr( $key ) . '"><h3>' . wp_kses( WPFactory_WC_STS_UI::icon( $feature[0], 18 ), WPFactory_WC_STS_UI::svg_kses() ) . ' ' . esc_html( $feature[1] ) . '</h3><ul>';
				foreach ( $feature[2] as $point ) {
					echo '<li>' . esc_html( $point ) . '</li>';
				}
				echo '</ul></li>';
			}
			echo '</ul>';
			echo '<p class="hd-pro__cta">' . $cta . ' <span class="hd-help">' . esc_html__( 'Your tickets, replies and settings carry over when you install Pro.', 'support-ticket-system-for-woocommerce' ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $cta escaped above.
			echo '</div>';
		}
	}

endif;

return WPFactory_WC_STS_Upgrade::get_instance();
