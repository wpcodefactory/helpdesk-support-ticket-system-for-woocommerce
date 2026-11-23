<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: "Where is my order?" step.
 *
 * When a customer picks "Where is my order?", the message step first shows what the store already knows: tracking
 * from WooCommerce Shipment Tracking or Advanced Shipment Tracking (`_wc_shipment_tracking_items`), anything another
 * plugin adds with the `wpfactory_wc_sts_order_tracking` filter, or otherwise a plain order status timeline.
 * The team sees the same tracking next to the ticket. Shared by both editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Tracking' ) ) :

	/**
	 * WPFactory_WC_STS_Tracking class.
	 */
	class WPFactory_WC_STS_Tracking {

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Tracking|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Tracking
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
			self::$instance = $this;
			// Right under the reason, so it shows as soon as "Where is my order?" is chosen.
			add_action( 'wpfactory_wc_sts_ask_after_reason', array( $this, 'render_step' ) );
			add_action( 'wpfactory_wc_sts_inbox_side', array( $this, 'render_side' ), 4 );
		}

		/**
		 * Shipments of an order.
		 *
		 * @param WC_Order $order Order.
		 *
		 * @return array[] { provider, number, url, date }
		 */
		public function get_tracking( $order ) {
			$items = array();
			foreach ( (array) $order->get_meta( '_wc_shipment_tracking_items' ) as $row ) {
				if ( ! is_array( $row ) || '' === (string) ( $row['tracking_number'] ?? '' ) ) {
					continue;
				}
				$provider = (string) ( $row['formatted_tracking_provider'] ?? '' );
				$provider = '' !== $provider ? $provider : (string) ( '' !== (string) ( $row['custom_tracking_provider'] ?? '' ) ? $row['custom_tracking_provider'] : ( $row['tracking_provider'] ?? '' ) );
				$url      = (string) ( $row['formatted_tracking_link'] ?? '' );
				$url      = '' !== $url ? $url : (string) ( $row['custom_tracking_link'] ?? '' );
				$date     = $row['date_shipped'] ?? '';
				$items[]  = array(
					'provider' => $provider,
					'number'   => (string) $row['tracking_number'],
					'url'      => $url,
					'date'     => is_numeric( $date ) ? wp_date( get_option( 'date_format' ), (int) $date ) : (string) $date,
				);
			}
			/**
			 * Shipments of an order, for tracking plugins that store them elsewhere.
			 *
			 * @param array[]  $items { provider, number, url, date }.
			 * @param WC_Order $order Order.
			 */
			return (array) apply_filters( 'wpfactory_wc_sts_order_tracking', $items, $order );
		}

		/**
		 * Status timeline: placed, paid, shipped or completed, with dates when known.
		 *
		 * @param WC_Order $order Order.
		 *
		 * @return array[] { label, date, done }
		 */
		public function get_timeline( $order ) {
			$status = $order->get_status();
			$format = get_option( 'date_format' );
			$date   = function ( $d ) use ( $format ) {
				return $d ? wc_format_datetime( $d, $format ) : '';
			};
			$steps  = array(
				array(
					'label' => __( 'Order placed', 'support-ticket-system-for-woocommerce' ),
					'date'  => $date( $order->get_date_created() ),
					'done'  => true,
				),
				array(
					'label' => __( 'Payment received', 'support-ticket-system-for-woocommerce' ),
					'date'  => $date( $order->get_date_paid() ),
					'done'  => (bool) $order->get_date_paid() || $order->is_paid(),
				),
				array(
					'label' => __( 'Being prepared', 'support-ticket-system-for-woocommerce' ),
					'date'  => '',
					'done'  => in_array( $status, array( 'processing', 'completed' ), true ),
				),
				array(
					'label' => __( 'Completed', 'support-ticket-system-for-woocommerce' ),
					'date'  => $date( $order->get_date_completed() ),
					'done'  => 'completed' === $status,
				),
			);
			if ( in_array( $status, array( 'cancelled', 'refunded', 'failed', 'on-hold' ), true ) ) {
				$steps[] = array(
					'label' => wc_get_order_status_name( $status ),
					'date'  => $date( $order->get_date_modified() ),
					'done'  => true,
				);
			}
			return $steps;
		}

		/**
		 * Tracking or timeline for one order.
		 *
		 * @param WC_Order $order Order.
		 */
		public function render_order( $order ) {
			$tracking = $this->get_tracking( $order );
			WPFactory_WC_STS_Templates::render(
				'order-tracking',
				array(
					'order'    => $order,
					'tracking' => $tracking,
					'timeline' => $tracking ? array() : $this->get_timeline( $order ),
				)
			);
		}

		/**
		 * One panel per order at the top of the message step; the script shows the chosen order's panel when the
		 * reason is "Where is my order?".
		 *
		 * @param WC_Order[]|WC_Order $orders Orders offered in the form (one order arrives as the order).
		 */
		public function render_step( $orders ) {
			// do_action() passes a list holding one order as the order itself (guests always have one order).
			$orders = $orders instanceof WC_Order ? array( $orders ) : (array) $orders;
			foreach ( $orders as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				echo '<section class="stsw-track" data-stsw-track="' . (int) $order->get_id() . '" aria-labelledby="stsw-track-' . (int) $order->get_id() . '" hidden>';
				echo '<h3 class="stsw-track__title" id="stsw-track-' . (int) $order->get_id() . '">' . esc_html__( 'Here is what we know about your order', 'support-ticket-system-for-woocommerce' ) . '</h3>';
				$this->render_order( $order );
				echo '<p class="stsw-help">' . esc_html__( 'Still need help? Write to us below.', 'support-ticket-system-for-woocommerce' ) . '</p></section>';
			}
		}

		/**
		 * Tracking next to the ticket in the inbox.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function render_side( $ticket_id ) {
			$core  = WPFactory_WC_STS_Inc::get_main_instance();
			$order = $core->get_ticket_order_id( $ticket_id ) ? wc_get_order( $core->get_ticket_order_id( $ticket_id ) ) : false;
			if ( ! $order ) {
				return;
			}
			$tracking = $this->get_tracking( $order );
			if ( ! $tracking ) {
				return;
			}
			echo '<section class="card" aria-labelledby="hd-side-tracking"><div class="card-head"><h3 class="card-title" id="hd-side-tracking">' . esc_html__( 'Tracking', 'support-ticket-system-for-woocommerce' ) . '</h3></div>';
			foreach ( $tracking as $item ) {
				$label = trim( $item['provider'] . ' ' . $item['number'] );
				echo '<div class="row"><div><p class="row-title small">' . ( '' !== $item['url'] ? '<a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>' : esc_html( $label ) ) . '</p>' . ( '' !== $item['date'] ? '<div class="row-meta">' . esc_html( $item['date'] ) . '</div>' : '' ) . '</div></div>';
			}
			echo '</section>';
		}
	}

endif;

return WPFactory_WC_STS_Tracking::get_instance();
