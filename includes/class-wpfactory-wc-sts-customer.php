<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: customer screens.
 *
 * My Account "Help" tab, the [stsw_user_tickets] shortcode and the blocks: the customer's requests as cards, a
 * three step "Ask for help" flow (order, items and reason, message), a confirmation screen and the conversation
 * view with reply, close and reopen. Also used for guests (signed link). Shared by the free and Pro editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Customer' ) ) :

	/**
	 * WPFactory_WC_STS_Customer class.
	 *
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	class WPFactory_WC_STS_Customer {

		/**
		 * Orders offered in the first step.
		 */
		const ORDER_LIMIT = 10;

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Customer|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Customer
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
			add_action( 'init', array( $this, 'register_blocks' ) );
			add_action( 'woocommerce_before_customer_login_form', array( $this, 'login_back_notice' ) );
			add_action( 'woocommerce_login_form_end', array( $this, 'login_back_field' ) );
		}

		/**
		 * Ticket a logged-out customer tried to open from an email link (it belongs to a registered customer).
		 *
		 * @return int Ticket ID or 0.
		 */
		protected function ticket_after_login() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
			$ticket_id = absint( $_GET['ticket'] ?? 0 );
			$view      = sanitize_key( wp_unslash( $_GET['stsw_view'] ?? '' ) );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( is_user_logged_in() || ! $ticket_id || 'ticket' !== $view || 'stsw_tickets' !== get_post_type( $ticket_id ) || ! $this->core()->get_ticket_user_id( $ticket_id ) ) {
				return 0;
			}
			return $ticket_id;
		}

		/**
		 * "Log in to see your request" above the login form.
		 */
		public function login_back_notice() {
			$ticket_id = $this->ticket_after_login();
			if ( $ticket_id ) {
				/* translators: %s: ticket number. */
				echo '<p class="stsw-notice stsw-login-back" role="status">' . esc_html( sprintf( __( 'Log in to see your request #%s. You will go straight back to it.', 'support-ticket-system-for-woocommerce' ), $this->core()->get_ticket_number( $ticket_id ) ) ) . '</p>';
			}
		}

		/**
		 * After logging in, back to the ticket the email linked to.
		 */
		public function login_back_field() {
			$ticket_id = $this->ticket_after_login();
			if ( $ticket_id ) {
				echo '<input type="hidden" name="redirect" value="' . esc_url( $this->core()->get_ticket_customer_url( $ticket_id ) ) . '" />';
			}
		}

		/**
		 * Core instance.
		 *
		 * @return WPFactory_WC_STS_Inc
		 */
		protected function core() {
			return WPFactory_WC_STS_Inc::get_main_instance();
		}

		/*
		|--------------------------------------------------------------------------
		| Blocks
		|--------------------------------------------------------------------------
		*/

		/**
		 * Dynamic blocks: help center (list, ask flow, conversation), ask form only, and one request.
		 */
		public function register_blocks() {
			if ( ! function_exists( 'register_block_type' ) ) {
				return;
			}
			wp_register_script(
				'wpfactory-wc-sts-blocks',
				plugins_url( 'assets/js/blocks.js', WPFACTORY_WC_STS_FILE ),
				array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
				WPFACTORY_WC_STS_VERSION,
				true
			);
			wp_localize_script(
				'wpfactory-wc-sts-blocks',
				'WPFactory_WC_STS_Blocks',
				array(
					'help'    => __( 'Help center', 'support-ticket-system-for-woocommerce' ),
					'helpD'   => __( 'Customers see their requests, ask for help and follow the conversation.', 'support-ticket-system-for-woocommerce' ),
					'ask'     => __( 'Ask for help form', 'support-ticket-system-for-woocommerce' ),
					'askD'    => __( 'The three step form to open a support request.', 'support-ticket-system-for-woocommerce' ),
					'ticket'  => __( 'Support request', 'support-ticket-system-for-woocommerce' ),
					'ticketD' => __( 'One request with its conversation (from the link customers receive).', 'support-ticket-system-for-woocommerce' ),
					'note'    => __( 'Shown to customers on the site. Logged-out visitors see the login form, or the guest form when guest tickets are on.', 'support-ticket-system-for-woocommerce' ),
				)
			);
			$blocks = array(
				'help-center' => 'all',
				'ask-form'    => 'new',
				'ticket'      => 'ticket',
			);
			foreach ( $blocks as $name => $view ) {
				register_block_type(
					'wpfactory-wc-sts/' . $name,
					array(
						'api_version'     => 2,
						'editor_script'   => 'wpfactory-wc-sts-blocks',
						'render_callback' => function () use ( $view ) {
							return $this->render_block( $view );
						},
						'supports'        => array( 'html' => false ),
					)
				);
			}
		}

		/**
		 * Block output.
		 *
		 * @param string $view `all`, `new` or `ticket`.
		 *
		 * @return string
		 */
		public function render_block( $view ) {
			if ( class_exists( 'WPFactory_WC_STS_Init' ) ) {
				WPFactory_WC_STS_Init::enqueue_frontend_assets();
			}
			ob_start();
			$this->render_page( 'all' === $view ? '' : $view );
			return '<div class="wp-block-wpfactory-wc-sts">' . ob_get_clean() . '</div>';
		}

		/*
		|--------------------------------------------------------------------------
		| URLs and routing
		|--------------------------------------------------------------------------
		*/

		/**
		 * Page the screens are on: My Account > Help, or the page with the shortcode or block.
		 *
		 * @return string
		 */
		public function get_base_url() {
			if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'tickets' ) ) {
				return $this->core()->get_customer_tickets_url();
			}
			if ( ! is_admin() && is_singular() && get_permalink() ) {
				return (string) get_permalink();
			}
			return $this->core()->get_customer_tickets_url();
		}

		/**
		 * URL of a view.
		 *
		 * @param string $view `new`, `sent`, `ticket` or empty for the list.
		 * @param array  $args Extra query args.
		 * @param string $base Base URL (current page when empty).
		 *
		 * @return string
		 */
		public function get_view_url( $view = '', $args = array(), $base = '' ) {
			$base = $base ? $base : $this->get_base_url();
			$base = remove_query_arg( array( 'stsw_view', 'ticket', 'order' ), $base );
			if ( '' !== $view ) {
				$args = array_merge( array( 'stsw_view' => $view ), $args );
			}
			return add_query_arg( $args, $base );
		}

		/**
		 * Current view from the URL. A `?order=` link (Get help on an order) opens the ask flow.
		 *
		 * @param string $forced View forced by a block.
		 *
		 * @return string
		 */
		protected function get_current_view( $forced = '' ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$view = $forced ? $forced : sanitize_key( wp_unslash( $_GET['stsw_view'] ?? '' ) );
			if ( '' === $view && ! empty( $_GET['order'] ) ) {
				$view = 'new';
			}
			if ( 'ticket' === $forced && empty( $_GET['ticket'] ) ) {
				$view = '';
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return in_array( $view, array( 'new', 'sent', 'ticket' ), true ) ? $view : '';
		}

		/**
		 * Prints the customer screen for the current request.
		 *
		 * @param string $forced View forced by a block (`new` or `ticket`).
		 */
		public function render_page( $forced = '' ) {
			if ( ! is_user_logged_in() ) {
				if ( class_exists( 'WPFactory_WC_STS_Guest' ) && WPFactory_WC_STS_Guest::get_instance()->is_enabled() ) {
					WPFactory_WC_STS_Guest::get_instance()->render();
				} else {
					echo do_shortcode( '[woocommerce_my_account]' );
				}
				return;
			}
			// My Account prints notices itself; other pages (shortcode, blocks) do it here.
			if ( function_exists( 'wc_print_notices' ) && ! is_account_page() && function_exists( 'WC' ) && WC()->session ) {
				wc_print_notices();
			}
			$ticket_id = absint( $_GET['ticket'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- access is checked below.
			echo '<div class="stsw">';
			switch ( $this->get_current_view( $forced ) ) {
				case 'new':
					$this->render_ask( $this->core()->get_requested_order_id() );
					break;
				case 'sent':
					if ( $this->core()->user_can_access_ticket( $ticket_id ) ) {
						$this->render_sent( $ticket_id );
					} else {
						$this->render_list();
					}
					break;
				case 'ticket':
					if ( $this->core()->user_can_access_ticket( $ticket_id ) ) {
						$this->render_ticket( $ticket_id );
					} else {
						echo '<p class="stsw-notice stsw-notice--error" role="alert">' . esc_html__( 'This request was not found.', 'support-ticket-system-for-woocommerce' ) . '</p>';
						$this->render_list();
					}
					break;
				default:
					$this->render_list();
			}
			echo '</div>';
		}

		/*
		|--------------------------------------------------------------------------
		| Requests list
		|--------------------------------------------------------------------------
		*/

		/**
		 * Customer state of a ticket: `closed`, `reply_needed` (the store wrote last) or `waiting` (with the store).
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string
		 */
		public function get_state( $ticket_id ) {
			if ( 'closed' === $this->core()->get_ticket_status_slug( $ticket_id ) ) {
				return 'closed';
			}
			$activity = $this->core()->get_activity( $ticket_id );
			return 'store' === $activity['by'] ? 'reply_needed' : 'waiting';
		}

		/**
		 * Customer-friendly status badge: "New reply" when the store wrote last, "Waiting for us" when the customer
		 * did, or the status name when closed.
		 *
		 * @param int  $ticket_id Ticket ID.
		 * @param bool $for_staff The viewer works on the ticket (shows the plain status).
		 *
		 * @return string HTML.
		 */
		public function status_chip( $ticket_id, $for_staff = false ) {
			$state  = $this->get_state( $ticket_id );
			$status = $this->core()->get_ticket_term( $ticket_id, 'stsw_tickets_status' );
			$labels = array(
				'reply_needed' => __( 'New reply', 'support-ticket-system-for-woocommerce' ),
				'waiting'      => __( 'Waiting for us', 'support-ticket-system-for-woocommerce' ),
				'closed'       => $status ? $status->name : __( 'Closed', 'support-ticket-system-for-woocommerce' ),
			);
			$text   = $for_staff && $status ? $status->name : $labels[ $state ];
			return '<span class="stsw-chip stsw-chip--' . esc_attr( $state ) . '">' . esc_html( $text ) . '</span>';
		}

		/**
		 * "2 hours ago" for a GMT date.
		 *
		 * @param string $gmt GMT date.
		 *
		 * @return string
		 */
		public function ago( $gmt ) {
			if ( empty( $gmt ) ) {
				return '';
			}
			/* translators: %s: human readable time difference, e.g. "2 hours". */
			return sprintf( __( '%s ago', 'support-ticket-system-for-woocommerce' ), human_time_diff( strtotime( $gmt . ' UTC' ) ) );
		}

		/**
		 * The customer's tickets, with the "Open a ticket" button.
		 */
		public function render_list() {
			$core  = $this->core();
			$page  = max( 1, absint( $_GET['stsw_page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
			$query = $core->query_customer_tickets( 0, $page );
			WPFactory_WC_STS_Templates::render(
				'ticket-list',
				array(
					'view'          => $this,
					'core'          => $core,
					'query'         => $query,
					'user'          => get_current_user_id(),
					'page'          => $page,
					'pages'         => (int) $query->max_num_pages,
					'closed_hidden' => '1' === (string) get_option( $core->plugin . 'hideClosed' ),
				)
			);
		}

		/*
		|--------------------------------------------------------------------------
		| Ask for help (three steps)
		|--------------------------------------------------------------------------
		*/

		/**
		 * Orders the customer can pick, newest first.
		 *
		 * @param int $user_id User ID.
		 *
		 * @return WC_Order[]
		 */
		protected function get_orders( $user_id ) {
			return wc_get_orders(
				array(
					'customer_id' => $user_id,
					'limit'       => self::ORDER_LIMIT,
					'orderby'     => 'date',
					'order'       => 'DESC',
					'type'        => 'shop_order',
				)
			);
		}

		/**
		 * Short label of an order: number, date, item count, total.
		 *
		 * @param WC_Order $order Order.
		 *
		 * @return string
		 */
		public function order_label( $order ) {
			return sprintf(
				/* translators: 1: order number, 2: date, 3: number of items, 4: order total. */
				_n( 'Order #%1$s, %2$s, %3$d item, %4$s', 'Order #%1$s, %2$s, %3$d items, %4$s', $order->get_item_count(), 'support-ticket-system-for-woocommerce' ),
				$order->get_order_number(),
				wc_format_datetime( $order->get_date_created() ),
				$order->get_item_count(),
				wp_strip_all_tags( html_entity_decode( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ), ENT_QUOTES, 'UTF-8' ) )
			);
		}

		/**
		 * Three step form.
		 *
		 * @param int   $order_id Pre-selected order (0: none).
		 * @param array $guest    Guest context: fixed order and hidden fields (logged-in customers: empty).
		 */
		public function render_ask( $order_id = 0, $guest = array() ) {
			$core   = $this->core();
			$draft  = $guest ? array() : (array) get_transient( 'wpfactory_wc_sts_draft_' . get_current_user_id() );
			$orders = $guest ? array_filter( array( wc_get_order( $order_id ) ) ) : $this->get_orders( get_current_user_id() );
			if ( ! $guest ) {
				delete_transient( 'wpfactory_wc_sts_draft_' . get_current_user_id() );
				$order_id = ! empty( $draft['order'] ) ? (int) $draft['order'] : $order_id;
			}
			WPFactory_WC_STS_Templates::render(
				'ticket-new',
				array(
					'view'     => $this,
					'core'     => $core,
					'order_id' => (int) $order_id,
					'guest'    => $guest,
					'draft'    => $draft,
					'orders'   => $orders,
					'steps'    => $guest ? 2 : 3,
				)
			);
		}

		/**
		 * Items of each order, as checkboxes (only the chosen order's items are shown when scripts run).
		 *
		 * @param WC_Order[] $orders Orders.
		 */
		public function render_items( $orders ) {
			foreach ( $orders as $order ) {
				$items = $order->get_items();
				if ( ! $items ) {
					continue;
				}
				echo '<fieldset class="stsw-items" data-order="' . (int) $order->get_id() . '"><legend>';
				/* translators: %s: order number. */
				echo esc_html( sprintf( __( 'Which items of order #%s? (optional)', 'support-ticket-system-for-woocommerce' ), $order->get_order_number() ) );
				echo '</legend><div class="stsw-choices">';
				foreach ( $items as $item_id => $item ) {
					$label = $item->get_name() . ' x ' . $item->get_quantity();
					/**
					 * Label of an order item in the ask flow (Pro adds the end of the support period).
					 *
					 * @param string        $label Label.
					 * @param WC_Order_Item $item  Item.
					 * @param WC_Order      $order Order.
					 */
					$label = (string) apply_filters( 'wpfactory_wc_sts_item_label', $label, $item, $order );
					$match = class_exists( 'WPFactory_WC_STS_Fields' ) ? WPFactory_WC_STS_Fields::get_instance()->item_match( $item ) : array();
					echo '<label class="stsw-choice"><input type="checkbox" name="stsw_items[]" value="' . (int) $item_id . '" data-stsw-match="' . esc_attr( implode( ' ', $match ) ) . '" /> <span>' . esc_html( $label ) . '</span></label>';
				}
				echo '</div></fieldset>';
			}
		}

		/**
		 * Select of a ticket taxonomy (only when it has terms).
		 *
		 * @param string $taxonomy Taxonomy.
		 * @param string $label    Label.
		 * @param string $value_by `term_id` or `name`.
		 */
		public function render_term_select( $taxonomy, $label, $value_by, $args = array() ) {
			$args  = wp_parse_args(
				$args,
				array(
					'placeholder' => '',
					'when'        => '',
					'required'    => false,
				)
			);
			$terms = WPFactory_WC_STS_UI::ordered_terms( $taxonomy );
			if ( empty( $terms ) ) {
				return;
			}
			$id = 'stsw-' . $taxonomy;
			echo '<div class="stsw-field"' . ( '' !== $args['when'] ? ' data-stsw-when="' . esc_attr( $args['when'] ) . '"' : '' ) . '><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $args['required'] ? ' <span aria-hidden="true">*</span>' : '' ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $taxonomy ) . '"' . ( $args['required'] ? ' required' : '' ) . '>';
			if ( '' !== $args['placeholder'] ) {
				echo '<option value="">' . esc_html( $args['placeholder'] ) . '</option>';
			}
			foreach ( $terms as $term ) {
				$value = 'name' === $value_by ? $term->name : (string) $term->term_id;
				echo '<option value="' . esc_attr( $value ) . '"' . selected( '' === $args['placeholder'] && 'name' === $value_by && 'normal' === $term->slug, true, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
			echo '</select></div>';
		}

		/**
		 * File input, when attachments are on.
		 *
		 * @param string $id Input ID.
		 */
		public function render_file_input( $id ) {
			if ( ! $this->core()->attachments_enabled() || ! class_exists( 'WPFactory_WC_STS_Files' ) ) {
				return;
			}
			$files = WPFactory_WC_STS_Files::get_instance();
			?>
			<div class="stsw-field stsw-drop">
				<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Attach files (optional)', 'support-ticket-system-for-woocommerce' ); ?></label>
				<div class="stsw-drop__zone">
					<input type="file" id="<?php echo esc_attr( $id ); ?>" name="my_file_upload[]" multiple accept="<?php echo esc_attr( $files->get_accept_attribute() ); ?>" aria-describedby="<?php echo esc_attr( $id ); ?>-help" />
					<span class="stsw-drop__text" aria-hidden="true"><?php esc_html_e( 'Drop files here or choose files', 'support-ticket-system-for-woocommerce' ); ?></span>
				</div>
				<small id="<?php echo esc_attr( $id ); ?>-help" class="stsw-help"><?php echo esc_html( $files->get_rules_text() ); ?></small>
			</div>
			<?php
		}

		/**
		 * Confirmation after a new request.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function render_sent( $ticket_id ) {
			WPFactory_WC_STS_Templates::render(
				'ticket-sent',
				array(
					'view'      => $this,
					'core'      => $this->core(),
					'ticket_id' => (int) $ticket_id,
					'message'   => $this->core()->get_saved_message( 'textforTicketSave' ),
				)
			);
			$this->render_list();
		}

		/*
		|--------------------------------------------------------------------------
		| Conversation
		|--------------------------------------------------------------------------
		*/

		/**
		 * One request: details, conversation, and reply, close or reopen.
		 *
		 * @param int   $ticket_id Ticket ID.
		 * @param array $guest     Guest context (hidden fields, nonce, file link args); empty for logged-in users.
		 */
		public function render_ticket( $ticket_id, $guest = array() ) {
			$core     = $this->core();
			$order_id = $core->get_ticket_order_id( $ticket_id );
			WPFactory_WC_STS_Templates::render(
				'ticket-view',
				array(
					'view'      => $this,
					'core'      => $core,
					'ticket_id' => (int) $ticket_id,
					'guest'     => $guest,
					'viewer'    => $guest ? 0 : get_current_user_id(),
					'order'     => $order_id ? wc_get_order( $order_id ) : false,
					'closed'    => 'closed' === $core->get_ticket_status_slug( $ticket_id ),
				)
			);
		}

		/**
		 * Hidden inputs.
		 *
		 * @param array $fields name => value.
		 */
		public function hidden_fields( $fields ) {
			foreach ( $fields as $name => $value ) {
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
			}
		}

		/**
		 * The conversation: the first message and every reply, oldest first.
		 *
		 * @param int   $ticket_id Ticket ID.
		 * @param int   $viewer_id Viewer (0 for a guest).
		 * @param array $file_args Extra query args for file links (guest access).
		 */
		public function render_thread( $ticket_id, $viewer_id, $file_args = array() ) {
			$core = $this->core();
			WPFactory_WC_STS_Templates::render(
				'ticket-thread',
				array(
					'view'      => $this,
					'core'      => $core,
					'ticket_id' => (int) $ticket_id,
					'viewer_id' => (int) $viewer_id,
					'owner'     => $core->get_ticket_user_id( $ticket_id ),
					'staff'     => $viewer_id && $core->can_manage_tickets( $viewer_id ),
					'you'       => __( 'You', 'support-ticket-system-for-woocommerce' ),
					/* translators: %s: store name. */
					'store'     => sprintf( __( '%s Support', 'support-ticket-system-for-woocommerce' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
					'files'     => class_exists( 'WPFactory_WC_STS_Files' ) ? WPFactory_WC_STS_Files::get_instance() : null,
					'file_args' => $file_args,
				)
			);
		}

		/**
		 * Reason, items, topic and form answers of a ticket, for display.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return array label => value
		 */
		public function get_ticket_details( $ticket_id ) {
			$core    = $this->core();
			$details = array();
			$reasons = $core->get_reasons();
			$reason  = (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_REASON, true );
			if ( isset( $reasons[ $reason ] ) ) {
				$details[ __( 'Reason', 'support-ticket-system-for-woocommerce' ) ] = $reasons[ $reason ];
			}
			$items = $core->get_ticket_item_names( $ticket_id );
			if ( $items ) {
				$details[ __( 'Items', 'support-ticket-system-for-woocommerce' ) ] = implode( ', ', $items );
			}
			$subject = $core->get_ticket_term( $ticket_id, 'stsw_tickets_subject' );
			if ( $subject ) {
				$details[ __( 'Topic', 'support-ticket-system-for-woocommerce' ) ] = $subject->name;
			}
			if ( class_exists( 'WPFactory_WC_STS_Fields' ) ) {
				$details = array_merge( $details, WPFactory_WC_STS_Fields::get_instance()->get_display_values( $ticket_id ) );
			}
			return $details;
		}
	}

endif;

return WPFactory_WC_STS_Customer::get_instance();
