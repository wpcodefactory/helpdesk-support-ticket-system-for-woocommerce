<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - "Pro features" page (free edition only).
 *
 * A header tab that describes what the Pro plugin adds, with a link to it, and a Companies tab that shows the Pro
 * screen empty and locked. Information only: no Pro code, and every feature of this plugin works without Pro.
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
			add_filter( 'wpfactory_wc_sts_header_tabs', array( $this, 'companies_tab' ), 15 );
			add_action( 'wpfactory_wc_sts_render_tab_companies', array( $this, 'render_companies' ) );
		}

		/**
		 * "Companies" in the plugin header (a Pro feature, shown locked), for people who manage tickets.
		 *
		 * @param array $tabs Tabs.
		 *
		 * @return array
		 */
		public function companies_tab( $tabs ) {
			if ( ! isset( $tabs['companies'] ) && WPFactory_WC_STS_Inc::get_main_instance()->can_manage_tickets() ) {
				$tabs['companies'] = __( 'Companies', 'support-ticket-system-for-woocommerce' );
			}
			return $tabs;
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
			$cta = '<a class="btn btn-primary" href="' . esc_url( self::URL ) . '" target="_blank" rel="noopener noreferrer" data-stsw-pro-cta>' . esc_html__( 'See Helpdesk Pro', 'support-ticket-system-for-woocommerce' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'support-ticket-system-for-woocommerce' ) . '</span></a>';
			echo '<div class="page narrow" data-stsw-pro-page>';
			WPFactory_WC_STS_UI::page_head( __( 'What Helpdesk Pro adds', 'support-ticket-system-for-woocommerce' ), __( 'Everything you use now keeps working, and AI replies are included in this plugin. Helpdesk Pro is a separate plugin that adds the following.', 'support-ticket-system-for-woocommerce' ), $cta, __( 'Pro features', 'support-ticket-system-for-woocommerce' ) );
			echo '<div class="stack"><div class="pro-features">';
			foreach ( $this->features() as $key => $feature ) {
				echo '<section class="card" data-feature="' . esc_attr( $key ) . '" aria-labelledby="hd-pro-' . esc_attr( $key ) . '"><div class="card-head"><h2 class="card-title" id="hd-pro-' . esc_attr( $key ) . '">' . esc_html( $feature[1] ) . '</h2></div><ul class="pro-points">';
				foreach ( $feature[2] as $point ) {
					echo '<li class="row">' . esc_html( $point ) . '</li>';
				}
				echo '</ul></section>';
			}
			echo '</div>';
			echo '<div class="banner banner-info"><span>' . esc_html__( 'Your tickets, replies and settings carry over when you install Pro.', 'support-ticket-system-for-woocommerce' ) . '</span><a class="btn btn-text btn-sm" href="' . esc_url( self::URL ) . '" target="_blank" rel="noopener noreferrer" data-stsw-pro-cta>' . esc_html__( 'See Helpdesk Pro', 'support-ticket-system-for-woocommerce' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'support-ticket-system-for-woocommerce' ) . '</span></a></div>';
			echo '</div></div>';
		}

		/**
		 * Companies (a Pro feature): the Pro screen with no companies, a line pointing to Pro and the form locked.
		 */
		public function render_companies() {
			if ( ! WPFactory_WC_STS_Inc::get_main_instance()->can_manage_tickets() ) {
				return;
			}
			$fields = array(
				'name'    => array( __( 'Name', 'support-ticket-system-for-woocommerce' ), '' ),
				'domains' => array( __( 'Email domains', 'support-ticket-system-for-woocommerce' ), __( 'Customers with these domains join automatically. Comma separated.', 'support-ticket-system-for-woocommerce' ) ),
				'members' => array( __( 'Members (account emails)', 'support-ticket-system-for-woocommerce' ), __( 'Comma separated. Emails without an account are skipped.', 'support-ticket-system-for-woocommerce' ) ),
			);
			echo '<div class="page narrow" data-stsw-companies-locked>';
			WPFactory_WC_STS_UI::page_head( __( 'No companies yet', 'support-ticket-system-for-woocommerce' ), __( 'Group customers of the same business. Their tickets show the company, and colleagues can see each other\'s tickets when sharing is on.', 'support-ticket-system-for-woocommerce' ), '', __( 'Companies', 'support-ticket-system-for-woocommerce' ) );
			echo '<div class="stack"><div class="banner banner-info"><span>' . esc_html__( 'Companies are part of Helpdesk Pro.', 'support-ticket-system-for-woocommerce' ) . '</span><a class="btn btn-text btn-sm" href="' . esc_url( self::URL ) . '" target="_blank" rel="noopener noreferrer" data-stsw-pro-cta>' . esc_html__( 'See Helpdesk Pro', 'support-ticket-system-for-woocommerce' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'support-ticket-system-for-woocommerce' ) . '</span></a></div>';
			echo '<div class="grid"><section class="card" aria-label="' . esc_attr__( 'Companies', 'support-ticket-system-for-woocommerce' ) . '"><div class="empty"><p class="row-title">' . esc_html__( 'No companies yet', 'support-ticket-system-for-woocommerce' ) . '</p>' . esc_html__( 'Companies you add show here, with their domains, members and tickets.', 'support-ticket-system-for-woocommerce' ) . '</div>';
			echo '<div class="card-foot"><span class="small muted">' . esc_html__( 'Customers who joined before a company was added are not grouped automatically.', 'support-ticket-system-for-woocommerce' ) . '</span><button type="button" class="btn btn-text btn-sm" disabled>' . esc_html__( 'Match existing customers', 'support-ticket-system-for-woocommerce' ) . '</button></div></section>';
			echo '<section class="card" aria-labelledby="hd-co-form-title"><div class="card-head"><h2 class="card-title" id="hd-co-form-title">' . esc_html__( 'Add company', 'support-ticket-system-for-woocommerce' ) . '</h2></div><fieldset class="card-body company-form" disabled><legend class="screen-reader-text">' . esc_html__( 'Add company (Helpdesk Pro)', 'support-ticket-system-for-woocommerce' ) . '</legend>';
			foreach ( $fields as $key => $field ) {
				echo '<div class="field"><label class="label" for="hd-co-' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label>';
				echo 'members' === $key ? '<textarea class="input" id="hd-co-' . esc_attr( $key ) . '" rows="3"></textarea>' : '<input type="text" class="input" id="hd-co-' . esc_attr( $key ) . '" />';
				echo '' !== $field[1] ? '<p class="hint">' . esc_html( $field[1] ) . '</p>' : '';
				echo '</div>';
			}
			echo '<div class="company-share"><span class="label" id="hd-co-shared-label">' . esc_html__( 'Members see each other\'s tickets', 'support-ticket-system-for-woocommerce' ) . '</span>' . wp_kses(
				WPFactory_WC_STS_UI::toggle(
					'',
					false,
					array(
						'aria-labelledby' => 'hd-co-shared-label',
						'disabled'        => true,
					)
				),
				WPFactory_WC_STS_UI::toggle_kses()
			) . '</div></fieldset>';
			echo '<div class="card-foot"><span></span><button type="button" class="btn btn-primary" disabled>' . esc_html__( 'Save company', 'support-ticket-system-for-woocommerce' ) . '</button></div></section></div></div></div>';
		}
	}

endif;

return WPFactory_WC_STS_Upgrade::get_instance();
