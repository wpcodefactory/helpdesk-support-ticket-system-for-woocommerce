<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - REST API, read only
 *
 * GET wpfactory-wc-sts/v1/tickets and /tickets/<id> for signed-in users (cookie with the REST nonce, or an application
 * password). Customers read their own tickets; store staff read every ticket they may edit. Writing (new tickets,
 * replies, notes, changes) is part of the Pro edition.
 *
 * @version 3.0.0
 * @since   3.0.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_REST' ) ) :

	/**
	 * Read-only REST routes.
	 */
	class WPFactory_WC_STS_REST {

		/**
		 * Namespace (shared with the Pro write routes).
		 */
		const NS = 'wpfactory-wc-sts/v1';

		/**
		 * Instance.
		 *
		 * @var WPFactory_WC_STS_REST|null
		 */
		protected static $instance = null;

		/**
		 * Instance.
		 *
		 * @return WPFactory_WC_STS_REST
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
			add_action( 'rest_api_init', array( $this, 'routes' ) );
		}

		/**
		 * Core.
		 *
		 * @return WPFactory_WC_STS_Inc
		 */
		protected function core() {
			return WPFactory_WC_STS_Inc::get_main_instance();
		}

		/**
		 * Routes.
		 */
		public function routes() {
			register_rest_route(
				self::NS,
				'/tickets',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_tickets' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => array(
						'status'   => array( 'type' => 'string' ),
						'customer' => array( 'type' => 'string' ),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
					),
				)
			);
			register_rest_route(
				self::NS,
				'/tickets/(?P<id>\d+)',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ticket' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => array(
						'id'            => array(
							'type'              => 'integer',
							'required'          => true,
							'validate_callback' => array( $this, 'is_ticket' ),
						),
						'include_notes' => array( 'type' => 'boolean' ),
					),
				)
			);
		}

		/**
		 * Signed-in users only; a ticket must be one the user may see.
		 *
		 * @param WP_REST_Request $request Request.
		 *
		 * @return bool|WP_Error
		 */
		public function can_read( $request ) {
			$id   = (int) $request->get_param( 'id' );
			$core = $this->core();
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'rest_forbidden', __( 'Sign in to read tickets.', 'support-ticket-system-for-woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
			}
			if ( $id && ! ( $core->can_manage_tickets() ? current_user_can( 'edit_post', $id ) : $core->user_can_access_ticket( $id ) ) ) {
				return new WP_Error( 'rest_forbidden', __( 'You cannot read this ticket.', 'support-ticket-system-for-woocommerce' ), array( 'status' => 403 ) );
			}
			return true;
		}

		/**
		 * Validates a ticket ID.
		 *
		 * @param mixed $value Value.
		 *
		 * @return bool
		 */
		public function is_ticket( $value ) {
			return 'stsw_tickets' === get_post_type( (int) $value );
		}

		/**
		 * Ticket as data.
		 *
		 * @param int  $ticket_id Ticket ID.
		 * @param bool $messages  Include messages.
		 * @param bool $notes     Include private notes (staff only).
		 * @param bool $staff     Staff view (assignee, inbox link).
		 *
		 * @return array
		 */
		public function ticket_data( $ticket_id, $messages = false, $notes = false, $staff = true ) {
			$core     = $this->core();
			$contact  = $core->get_ticket_contact( $ticket_id );
			$activity = $core->get_activity( $ticket_id );
			$priority = $core->get_ticket_term( $ticket_id, 'stsw_tickets_priority' );
			$data     = array(
				'id'         => (int) $ticket_id,
				'number'     => $core->get_ticket_number( $ticket_id ),
				'title'      => get_the_title( $ticket_id ),
				'status'     => $core->get_ticket_status_slug( $ticket_id ),
				'priority'   => $priority ? $priority->slug : '',
				'customer'   => array(
					'id'    => $core->get_ticket_user_id( $ticket_id ),
					'email' => $contact['email'],
					'name'  => $contact['display_name'],
				),
				'order_id'   => $core->get_ticket_order_id( $ticket_id ),
				'last_by'    => $activity['by'],
				'last_at'    => $activity['at'] ? mysql_to_rfc3339( $activity['at'] ) : null,
				'created_at' => mysql_to_rfc3339( get_post_field( 'post_date_gmt', $ticket_id ) ),
			);
			if ( $staff ) {
				$data['assignee'] = $core->get_ticket_agent_id( $ticket_id );
				$data['link']     = admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&ticket=' . (int) $ticket_id );
			} else {
				$data['link'] = $core->get_ticket_customer_url( $ticket_id );
			}
			if ( $messages ) {
				$data['messages'] = array(
					array(
						'id'      => 0,
						'by'      => get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_STARTED_BY, true ) ? 'store' : 'customer',
						'message' => get_post_field( 'post_content', $ticket_id, 'raw' ),
						'date'    => mysql_to_rfc3339( get_post_field( 'post_date_gmt', $ticket_id ) ),
					),
				);
				foreach ( $core->get_replies( $ticket_id ) as $row ) {
					$message = array(
						'id'      => (int) $row->id,
						'by'      => (int) $row->agent > 0 ? 'store' : 'customer',
						'message' => $row->content,
						'date'    => mysql_to_rfc3339( $row->creationdate ),
					);
					if ( $staff ) {
						$message['author'] = (int) $row->agent;
					}
					$data['messages'][] = $message;
				}
			}
			if ( $notes && $staff ) {
				$data['notes'] = array();
				foreach ( $core->get_notes( $ticket_id ) as $row ) {
					$data['notes'][] = array(
						'id'      => (int) $row->id,
						'author'  => (int) $row->agent,
						'message' => $row->content,
						'date'    => mysql_to_rfc3339( $row->creationdate ),
					);
				}
			}
			/**
			 * Ticket data in the REST API and the MCP server.
			 *
			 * @param array $data      Data.
			 * @param int   $ticket_id Ticket ID.
			 * @param bool  $staff     Staff view.
			 */
			return (array) apply_filters( 'wpfactory_wc_sts_rest_ticket', $data, (int) $ticket_id, (bool) $staff );
		}

		/**
		 * GET /tickets. Staff: all tickets (filters: status, customer email). Customers: their own tickets.
		 *
		 * @param WP_REST_Request $request Request.
		 *
		 * @return WP_REST_Response
		 */
		public function list_tickets( $request ) {
			$core  = $this->core();
			$staff = $core->can_manage_tickets();
			$args  = array(
				'post_type'      => 'stsw_tickets',
				'post_status'    => 'publish',
				'posts_per_page' => (int) $request['per_page'],
				'paged'          => (int) $request['page'],
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			);
			if ( $request['status'] ) {
				$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'stsw_tickets_status',
						'field'    => 'slug',
						'terms'    => sanitize_key( $request['status'] ),
					),
				);
			}
			if ( ! $staff ) {
				$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => WPFactory_WC_STS_Inc::META_USER,
						'value' => get_current_user_id(),
					),
					array(
						'key'     => '_stsw_internal',
						'compare' => 'NOT EXISTS',
					),
				);
			} elseif ( $request['customer'] && is_email( $request['customer'] ) ) {
				$user               = get_user_by( 'email', $request['customer'] );
				$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'   => WPFactory_WC_STS_Inc::META_GUEST_EMAIL,
						'value' => $request['customer'],
					),
					array(
						'key'   => WPFactory_WC_STS_Inc::META_USER,
						'value' => $user ? (int) $user->ID : -1,
					),
				);
			}
			/**
			 * Query of GET /tickets (Pro: permission scopes).
			 *
			 * @param array $args    WP_Query arguments.
			 * @param bool  $staff   Staff request.
			 * @param int   $user_id User ID.
			 */
			$query = new WP_Query( (array) apply_filters( 'wpfactory_wc_sts_rest_query', $args, $staff, get_current_user_id() ) );
			$data  = array();
			foreach ( $query->posts as $id ) {
				if ( $staff && ! current_user_can( 'edit_post', $id ) ) {
					continue;
				}
				$data[] = $this->ticket_data( (int) $id, false, false, $staff );
			}
			$response = rest_ensure_response( $data );
			$response->header( 'X-WP-Total', (string) $query->found_posts );
			$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
			return $response;
		}

		/**
		 * GET /tickets/<id>.
		 *
		 * @param WP_REST_Request $request Request.
		 *
		 * @return array
		 */
		public function get_ticket( $request ) {
			$staff = $this->core()->can_manage_tickets();
			return $this->ticket_data( (int) $request['id'], true, $staff && (bool) $request['include_notes'], $staff );
		}
	}

endif;

return WPFactory_WC_STS_REST::get_instance();
