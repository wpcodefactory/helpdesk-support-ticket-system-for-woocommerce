<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: privacy (personal data export and erase, policy text).
 *
 * Adds the customer's tickets (account and guest tickets), replies, order, form answers and files to Tools > Export
 * Personal Data and Tools > Erase Personal Data. Shared by the free and Pro editions.
 *
 * @version 2.3.0
 * @since   2.2.1
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Privacy' ) ) :

	/**
	 * WPFactory_WC_STS_Privacy class.
	 *
	 * @version 2.3.0
	 * @since   2.2.1
	 */
	class WPFactory_WC_STS_Privacy {

		/**
		 * Tickets handled per exporter or eraser call.
		 *
		 * @var int
		 */
		const BATCH = 20;

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
			add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		}

		/**
		 * Registers the exporter.
		 *
		 * @param array $exporters Exporters.
		 *
		 * @return array
		 */
		public function register_exporter( $exporters ) {
			$exporters['wpfactory-wc-sts-tickets'] = array(
				'exporter_friendly_name' => __( 'Support tickets', 'support-ticket-system-for-woocommerce' ),
				'callback'               => array( $this, 'export' ),
			);
			return $exporters;
		}

		/**
		 * Registers the eraser.
		 *
		 * @param array $erasers Erasers.
		 *
		 * @return array
		 */
		public function register_eraser( $erasers ) {
			$erasers['wpfactory-wc-sts-tickets'] = array(
				'eraser_friendly_name' => __( 'Support tickets', 'support-ticket-system-for-woocommerce' ),
				'callback'             => array( $this, 'erase' ),
			);
			return $erasers;
		}

		/**
		 * Tickets opened by the account with this email address, or as a guest with it.
		 *
		 * @param string $email Email address.
		 * @param int    $page  Page (1-based).
		 *
		 * @return int[]
		 */
		protected function get_ticket_ids( $email, $page ) {
			$meta = array(
				'relation' => 'OR',
				array(
					'key'   => WPFactory_WC_STS_Inc::META_GUEST_EMAIL,
					'value' => strtolower( (string) $email ),
				),
			);
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$meta[] = array(
					'key'   => WPFactory_WC_STS_Inc::META_USER,
					'value' => (int) $user->ID,
				);
			}
			return array_map(
				'intval',
				get_posts(
					array(
						'post_type'      => 'stsw_tickets',
						'post_status'    => 'any',
						'fields'         => 'ids',
						'posts_per_page' => self::BATCH,
						'paged'          => max( 1, (int) $page ),
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					)
				)
			);
		}

		/**
		 * Exporter callback.
		 *
		 * @param string $email Email address.
		 * @param int    $page  Page.
		 *
		 * @return array
		 */
		public function export( $email, $page = 1 ) {
			$core  = WPFactory_WC_STS_Inc::get_main_instance();
			$ids   = $this->get_ticket_ids( $email, $page );
			$items = array();
			foreach ( $ids as $id ) {
				$status = $core->get_ticket_term( $id, 'stsw_tickets_status' );
				$lines  = array();
				foreach ( $core->get_replies( $id ) as $row ) {
					$lines[] = $row->creationdate . ' UTC, ' .
						( (int) $row->agent > 0 ? __( 'Store', 'support-ticket-system-for-woocommerce' ) : __( 'Customer', 'support-ticket-system-for-woocommerce' ) ) .
						': ' . wp_specialchars_decode( $row->content, ENT_QUOTES );
				}
				$data  = array(
					array(
						'name'  => __( 'Ticket number', 'support-ticket-system-for-woocommerce' ),
						'value' => (string) $id,
					),
					array(
						'name'  => __( 'Title', 'support-ticket-system-for-woocommerce' ),
						'value' => get_the_title( $id ),
					),
					array(
						'name'  => __( 'Opened', 'support-ticket-system-for-woocommerce' ),
						'value' => get_post_field( 'post_date', $id ),
					),
					array(
						'name'  => __( 'Status', 'support-ticket-system-for-woocommerce' ),
						'value' => $status ? $status->name : '',
					),
					array(
						'name'  => __( 'Message', 'support-ticket-system-for-woocommerce' ),
						'value' => get_post_field( 'post_content', $id, 'raw' ),
					),
					array(
						'name'  => __( 'Conversation', 'support-ticket-system-for-woocommerce' ),
						'value' => implode( "\n", $lines ),
					),
				);
				$guest = (string) get_post_meta( $id, WPFactory_WC_STS_Inc::META_GUEST_EMAIL, true );
				if ( '' !== $guest ) {
					$data[] = array(
						'name'  => __( 'Email', 'support-ticket-system-for-woocommerce' ),
						'value' => $guest,
					);
				}
				$order = $core->get_ticket_order_id( $id ) ? wc_get_order( $core->get_ticket_order_id( $id ) ) : false;
				if ( $order ) {
					$data[] = array(
						'name'  => __( 'Order', 'support-ticket-system-for-woocommerce' ),
						'value' => $order->get_order_number(),
					);
				}
				if ( class_exists( 'WPFactory_WC_STS_Fields' ) ) {
					foreach ( WPFactory_WC_STS_Fields::get_instance()->get_display_values( $id ) as $label => $value ) {
						$data[] = array(
							'name'  => $label,
							'value' => $value,
						);
					}
				}
				$files = array();
				foreach ( get_attached_media( '', $id ) as $attachment ) {
					$name    = (string) get_post_meta( $attachment->ID, '_stsw_original_name', true );
					$files[] = '' !== $name ? $name : get_the_title( $attachment );
				}
				if ( $files ) {
					$data[] = array(
						'name'  => __( 'Attachments', 'support-ticket-system-for-woocommerce' ),
						'value' => implode( "\n", $files ),
					);
				}

				$items[] = array(
					'group_id'          => 'wpfactory-wc-sts-tickets',
					'group_label'       => __( 'Support tickets', 'support-ticket-system-for-woocommerce' ),
					'group_description' => __( 'Support tickets you opened and the replies in them.', 'support-ticket-system-for-woocommerce' ),
					'item_id'           => 'stsw-ticket-' . $id,
					'data'              => $data,
				);
			}

			return array(
				'data' => $items,
				'done' => count( $ids ) < self::BATCH,
			);
		}

		/**
		 * Eraser callback: deletes the tickets the person opened, with replies, private notes, answers and files.
		 *
		 * Tickets are deleted rather than anonymised: their text is the customer's own words.
		 * Replies the user wrote as store staff on other customers' tickets are kept.
		 *
		 * @param string $email Email address.
		 * @param int    $page  Page (ignored: deleted tickets leave the result set).
		 *
		 * @return array
		 */
		public function erase( $email, $page = 1 ) {
			$ids = $this->get_ticket_ids( $email, 1 );
			foreach ( $ids as $id ) {
				// Replies, notes and files are removed by the core on `before_delete_post`.
				wp_delete_post( $id, true );
			}
			return array(
				'items_removed'  => count( $ids ) > 0,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => count( $ids ) < self::BATCH,
			);
		}

		/**
		 * Suggested text for the site's privacy policy (Settings > Privacy).
		 */
		public function add_privacy_policy_content() {
			if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
				return;
			}
			$content = '<p>' . esc_html__( 'When you open a support ticket we store the title, your message, your answers to the form, your replies and the store\'s replies, linked to your account or, without an account, to your email address and order. We use them to answer your request and keep a record of it. Files you attach to a ticket are stored on this site with the ticket. We email you about your tickets if the store has turned on email notifications.', 'support-ticket-system-for-woocommerce' ) . '</p>' .
				'<p>' . esc_html__( 'You can ask for an export or for the deletion of your tickets; deleting them removes the tickets, every reply in them and their files.', 'support-ticket-system-for-woocommerce' ) . '</p>';
			wp_add_privacy_policy_content( WPFACTORY_WC_STS_NAME, wp_kses_post( $content ) );
		}
	}

endif;

return new WPFactory_WC_STS_Privacy();
