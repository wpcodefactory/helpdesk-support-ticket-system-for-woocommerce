<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Privacy (personal data export and erase, policy text).
 *
 * Adds the customer's tickets and replies to Tools > Export Personal Data and Tools > Erase Personal Data.
 *
 * @version 2.2.1
 * @since   2.2.1
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Privacy' ) ) :

	/**
	 * WPFactory_WC_STS_Privacy class.
	 *
	 * @version 2.2.1
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
		 *
		 * @version 2.2.1
		 * @since   2.2.1
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
		 * Ticket IDs opened by the user with this email address.
		 *
		 * @param string $email Email address.
		 * @param int    $page  Page (1-based).
		 *
		 * @return int[]
		 */
		protected function get_ticket_ids( $email, $page ) {
			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				return array();
			}
			return get_posts(
				array(
					'post_type'      => 'stsw_tickets',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => self::BATCH,
					'paged'          => max( 1, (int) $page ),
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => 'STSWooCommerceProticketuser',
							'value' => (int) $user->ID,
						),
					),
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
			global $wpdb;

			$ids   = $this->get_ticket_ids( $email, $page );
			$items = array();
			foreach ( $ids as $id ) {
				$status = wp_get_object_terms( $id, 'stsw_tickets_status', array( 'fields' => 'names' ) );
				$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare( 'SELECT creationdate, agent, content FROM %i WHERE post_id = %d ORDER BY creationdate ASC, id ASC', $wpdb->prefix . 'stsw_responses', $id )
				);
				$lines = array();
				foreach ( (array) $rows as $row ) {
					$lines[] = $row->creationdate . ' UTC, ' .
						( (int) $row->agent > 0 ? __( 'Store', 'support-ticket-system-for-woocommerce' ) : __( 'Customer', 'support-ticket-system-for-woocommerce' ) ) .
						': ' . wp_specialchars_decode( $row->content, ENT_QUOTES );
				}
				$data = array(
					array(
						'name'  => __( 'Ticket number', 'support-ticket-system-for-woocommerce' ),
						'value' => (string) $id,
					),
					array(
						'name'  => __( 'Subject', 'support-ticket-system-for-woocommerce' ),
						'value' => get_the_title( $id ),
					),
					array(
						'name'  => __( 'Opened', 'support-ticket-system-for-woocommerce' ),
						'value' => get_post_field( 'post_date', $id ),
					),
					array(
						'name'  => __( 'Status', 'support-ticket-system-for-woocommerce' ),
						'value' => is_wp_error( $status ) ? '' : implode( ', ', $status ),
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
				$data = array_merge( $data, $this->extra_export_data( $id ) );

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
		 * Extra export fields (Pro adds the linked order and attachments).
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return array
		 */
		protected function extra_export_data( $ticket_id ) {
			return array();
		}

		/**
		 * Eraser callback: deletes the tickets the user opened, with their replies.
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
			global $wpdb;

			$ids = $this->get_ticket_ids( $email, 1 );
			foreach ( $ids as $id ) {
				$wpdb->delete( $wpdb->prefix . 'stsw_responses', array( 'post_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$this->erase_extra( $id );
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
		 * Extra data removed with a ticket (Pro: private notes and attachments).
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		protected function erase_extra( $ticket_id ) {
		}

		/**
		 * Suggested text for the site's privacy policy (Settings > Privacy).
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 */
		public function add_privacy_policy_content() {
			if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
				return;
			}
			$content = '<p>' . esc_html__( 'When you open a support ticket we store the subject, your message, your replies and the store\'s replies, linked to your account. We use them to answer your request and keep a record of it. We email you about your tickets if the store has turned on email notifications.', 'support-ticket-system-for-woocommerce' ) . '</p>' .
				'<p>' . esc_html__( 'You can ask for an export or for the deletion of your tickets; deleting them removes the tickets and every reply in them.', 'support-ticket-system-for-woocommerce' ) . '</p>';
			wp_add_privacy_policy_content( $this->policy_name(), wp_kses_post( $content ) );
		}

		/**
		 * Name shown in the privacy policy guide.
		 *
		 * @return string
		 */
		protected function policy_name() {
			return __( 'Helpdesk Support Ticket System for WooCommerce', 'support-ticket-system-for-woocommerce' );
		}
	}

endif;

return new WPFactory_WC_STS_Privacy();
