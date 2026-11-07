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
		 * Status chip.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string HTML.
		 */
		public function status_chip( $ticket_id ) {
			$status = $this->core()->get_ticket_term( $ticket_id, 'stsw_tickets_status' );
			$slug   = $status ? $status->slug : '';
			$name   = $status ? $status->name : __( 'Open', 'support-ticket-system-for-woocommerce' );
			return '<span class="stsw-chip stsw-chip--' . esc_attr( sanitize_html_class( $slug ) ) . '">' . esc_html( $name ) . '</span>';
		}

		/**
		 * "2 hours ago" for a GMT date.
		 *
		 * @param string $gmt GMT date.
		 *
		 * @return string
		 */
		protected function ago( $gmt ) {
			if ( empty( $gmt ) ) {
				return '';
			}
			/* translators: %s: human readable time difference, e.g. "2 hours". */
			return sprintf( __( '%s ago', 'support-ticket-system-for-woocommerce' ), human_time_diff( strtotime( $gmt . ' UTC' ) ) );
		}

		/**
		 * The customer's requests as cards, with the "Ask for help" button.
		 */
		public function render_list() {
			$query = $this->core()->query_customer_tickets( 0 );
			$user  = get_current_user_id();
			?>
			<div class="stsw-head">
				<h2 class="stsw-title"><?php esc_html_e( 'Your requests', 'support-ticket-system-for-woocommerce' ); ?></h2>
				<a class="stsw-button stsw-button--primary button wp-element-button" href="<?php echo esc_url( $this->get_view_url( 'new' ) ); ?>"><?php esc_html_e( 'Ask for help', 'support-ticket-system-for-woocommerce' ); ?></a>
			</div>
			<?php
			if ( ! $query->have_posts() ) {
				echo '<div class="stsw-empty"><p>' . esc_html__( 'You have no support requests yet.', 'support-ticket-system-for-woocommerce' ) . '</p><p>' . esc_html__( 'A question about an order or a product? Choose "Ask for help" and we will reply by email.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
				return;
			}
			echo '<ul class="stsw-cards">';
			foreach ( $query->posts as $post ) {
				$id       = (int) $post->ID;
				$state    = $this->get_state( $id );
				$activity = $this->core()->get_activity( $id );
				$order    = $this->core()->get_ticket_order_id( $id ) ? wc_get_order( $this->core()->get_ticket_order_id( $id ) ) : false;
				$is_agent = $this->core()->get_ticket_user_id( $id ) !== $user;
				echo '<li class="stsw-card stsw-card--' . esc_attr( $state ) . '">';
				echo '<a class="stsw-card__link" href="' . esc_url( $this->get_view_url( 'ticket', array( 'ticket' => $id ) ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
				echo '<p class="stsw-card__meta">';
				/* translators: %d: request number. */
				echo '<span>' . esc_html( sprintf( __( 'Request #%d', 'support-ticket-system-for-woocommerce' ), $id ) ) . '</span>';
				if ( $order ) {
					/* translators: %s: order number. */
					echo '<span>' . esc_html( sprintf( __( 'Order #%s', 'support-ticket-system-for-woocommerce' ), $order->get_order_number() ) ) . '</span>';
				}
				/* translators: %s: time since the last message, e.g. "2 hours ago". */
				echo '<span>' . esc_html( sprintf( __( 'Last message %s', 'support-ticket-system-for-woocommerce' ), $this->ago( $activity['at'] ) ) ) . '</span>';
				echo '</p><p class="stsw-card__chips">' . $this->status_chip( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in status_chip().
				if ( 'reply_needed' === $state && ! $is_agent ) {
					echo ' <span class="stsw-chip stsw-chip--attention">' . esc_html__( 'Your reply needed', 'support-ticket-system-for-woocommerce' ) . '</span>';
				}
				if ( $is_agent ) {
					echo ' <span class="stsw-chip">' . esc_html__( 'Assigned to you', 'support-ticket-system-for-woocommerce' ) . '</span>';
				}
				echo '</p></li>';
			}
			echo '</ul>';
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
		protected function order_label( $order ) {
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
			$steps = $guest ? 2 : 3;
			$step  = 0;
			?>
			<div class="stsw-head">
				<h2 class="stsw-title" id="stsw-ask-title"><?php esc_html_e( 'Ask for help', 'support-ticket-system-for-woocommerce' ); ?></h2>
				<?php if ( ! $guest ) : ?>
					<a class="stsw-link" href="<?php echo esc_url( $this->get_view_url() ); ?>"><?php esc_html_e( 'Back to your requests', 'support-ticket-system-for-woocommerce' ); ?></a>
				<?php endif; ?>
			</div>
			<form class="stsw-ask <?php echo esc_attr( $core->plugin ); ?>new_ticket" method="post" enctype="multipart/form-data" aria-labelledby="stsw-ask-title" data-stsw-steps="<?php echo (int) $steps; ?>">
				<p class="screen-reader-text stsw-live" aria-live="polite"></p>

				<?php if ( ! $guest ) : ?>
					<fieldset class="stsw-step" data-step="<?php echo (int) ++$step; ?>">
						<legend class="stsw-step__title">
							<?php
							/* translators: 1: step number, 2: number of steps. */
							echo esc_html( sprintf( __( 'Step %1$d of %2$d: which order is it about?', 'support-ticket-system-for-woocommerce' ), $step, $steps ) );
							?>
						</legend>
						<div class="stsw-choices">
							<?php foreach ( $orders as $order ) : ?>
								<label class="stsw-choice">
									<input type="radio" name="ticketorder" value="<?php echo (int) $order->get_id(); ?>" <?php checked( (int) $order_id, (int) $order->get_id() ); ?> />
									<span><?php echo esc_html( $this->order_label( $order ) ); ?></span>
								</label>
							<?php endforeach; ?>
							<label class="stsw-choice">
								<input type="radio" name="ticketorder" value="" <?php checked( ! $order_id ); ?> />
								<span><?php esc_html_e( 'Not about an order', 'support-ticket-system-for-woocommerce' ); ?></span>
							</label>
						</div>
					</fieldset>
				<?php else : ?>
					<input type="hidden" name="ticketorder" value="<?php echo (int) $order_id; ?>" />
				<?php endif; ?>

				<fieldset class="stsw-step" data-step="<?php echo (int) ++$step; ?>">
					<legend class="stsw-step__title">
						<?php
						/* translators: 1: step number, 2: number of steps. */
						echo esc_html( sprintf( __( 'Step %1$d of %2$d: what is it about?', 'support-ticket-system-for-woocommerce' ), $step, $steps ) );
						?>
					</legend>
					<?php $this->render_items( $orders ); ?>
					<div class="stsw-field">
						<label for="stsw-reason"><?php esc_html_e( 'Reason', 'support-ticket-system-for-woocommerce' ); ?></label>
						<select id="stsw-reason" name="stsw_reason">
							<?php foreach ( $core->get_reasons() as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $draft['reason'] ?? '', $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php
					$this->render_term_select( 'stsw_tickets_subject', __( 'Topic', 'support-ticket-system-for-woocommerce' ), 'term_id' );
					if ( '1' === (string) get_option( $core->plugin . 'enablePriority' ) ) {
						$this->render_term_select( 'stsw_tickets_priority', __( 'How urgent is it?', 'support-ticket-system-for-woocommerce' ), 'name' );
					}
					?>
				</fieldset>

				<fieldset class="stsw-step" data-step="<?php echo (int) ++$step; ?>">
					<legend class="stsw-step__title">
						<?php
						/* translators: 1: step number, 2: number of steps. */
						echo esc_html( sprintf( __( 'Step %1$d of %2$d: tell us more', 'support-ticket-system-for-woocommerce' ), $step, $steps ) );
						?>
					</legend>
					<div class="stsw-field">
						<label for="stsw-ticket-title"><?php esc_html_e( 'Short summary (optional)', 'support-ticket-system-for-woocommerce' ); ?></label>
						<input type="text" id="stsw-ticket-title" name="title" maxlength="150" value="<?php echo esc_attr( $draft['title'] ?? '' ); ?>" />
					</div>
					<?php
					if ( class_exists( 'WPFactory_WC_STS_Fields' ) ) {
						WPFactory_WC_STS_Fields::get_instance()->render_inputs();
					}
					?>
					<div class="stsw-field">
						<label for="stsw-ticket-content"><?php esc_html_e( 'Message', 'support-ticket-system-for-woocommerce' ); ?> <span aria-hidden="true">*</span></label>
						<textarea id="stsw-ticket-content" name="content" rows="6" required><?php echo esc_textarea( $draft['content'] ?? '' ); ?></textarea>
					</div>
					<?php $this->render_file_input( 'stsw-ticket-files' ); ?>
				</fieldset>

				<?php
				foreach ( ( $guest['hidden'] ?? array() ) as $name => $value ) {
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
				}
				if ( $guest ) {
					wp_nonce_field( $guest['nonce'], $guest['nonce'] );
				} else {
					wp_nonce_field( 'stswticketfrontend', 'stswticketfrontend' );
					echo '<input type="hidden" name="stsw_return" value="' . esc_attr( $this->get_view_url() ) . '" />';
				}
				?>
				<div class="stsw-actions">
					<button type="button" class="stsw-button stsw-js-only" data-stsw-back hidden><?php esc_html_e( 'Back', 'support-ticket-system-for-woocommerce' ); ?></button>
					<button type="button" class="stsw-button stsw-button--primary button wp-element-button stsw-js-only" data-stsw-next hidden><?php esc_html_e( 'Next', 'support-ticket-system-for-woocommerce' ); ?></button>
					<button type="submit" class="stsw-button stsw-button--primary button wp-element-button" data-stsw-submit><?php esc_html_e( 'Send', 'support-ticket-system-for-woocommerce' ); ?></button>
				</div>
			</form>
			<?php
		}

		/**
		 * Items of each order, as checkboxes (only the chosen order's items are shown when scripts run).
		 *
		 * @param WC_Order[] $orders Orders.
		 */
		protected function render_items( $orders ) {
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
					echo '<label class="stsw-choice"><input type="checkbox" name="stsw_items[]" value="' . (int) $item_id . '" /> <span>' . esc_html( $label ) . '</span></label>';
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
		public function render_term_select( $taxonomy, $label, $value_by ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return;
			}
			$id = 'stsw-' . $taxonomy;
			echo '<div class="stsw-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $taxonomy ) . '">';
			foreach ( $terms as $term ) {
				$value = 'name' === $value_by ? $term->name : (string) $term->term_id;
				echo '<option value="' . esc_attr( $value ) . '"' . selected( 'name' === $value_by && 'normal' === $term->slug, true, false ) . '>' . esc_html( $term->name ) . '</option>';
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
			<div class="stsw-field">
				<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Attach files (optional)', 'support-ticket-system-for-woocommerce' ); ?></label>
				<input type="file" id="<?php echo esc_attr( $id ); ?>" name="my_file_upload[]" multiple accept="<?php echo esc_attr( $files->get_accept_attribute() ); ?>" aria-describedby="<?php echo esc_attr( $id ); ?>-help" />
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
			$message = $this->core()->get_saved_message( 'textforTicketSave' );
			?>
			<div class="stsw-sent" role="status" tabindex="-1">
				<h2 class="stsw-title"><?php esc_html_e( 'Thanks, we received your request', 'support-ticket-system-for-woocommerce' ); ?></h2>
				<p>
					<?php
					/* translators: %d: request number. */
					echo esc_html( sprintf( __( 'Your request number is #%d. We will reply by email, and you can follow it here.', 'support-ticket-system-for-woocommerce' ), $ticket_id ) );
					?>
				</p>
				<?php echo wp_kses_post( $message ); ?>
				<p class="stsw-actions">
					<a class="stsw-button stsw-button--primary button wp-element-button" href="<?php echo esc_url( $this->get_view_url( 'ticket', array( 'ticket' => $ticket_id ) ) ); ?>"><?php esc_html_e( 'View your request', 'support-ticket-system-for-woocommerce' ); ?></a>
					<a class="stsw-button" href="<?php echo esc_url( $this->get_view_url() ); ?>"><?php esc_html_e( 'All your requests', 'support-ticket-system-for-woocommerce' ); ?></a>
				</p>
			</div>
			<?php
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
			$viewer   = $guest ? 0 : get_current_user_id();
			$order_id = $core->get_ticket_order_id( $ticket_id );
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			$closed   = 'closed' === $core->get_ticket_status_slug( $ticket_id );
			?>
			<div class="stsw-head">
				<h2 class="stsw-title"><?php echo esc_html( get_the_title( $ticket_id ) ); ?></h2>
				<?php if ( ! $guest ) : ?>
					<a class="stsw-link" href="<?php echo esc_url( $this->get_view_url() ); ?>"><?php esc_html_e( 'All your requests', 'support-ticket-system-for-woocommerce' ); ?></a>
				<?php endif; ?>
			</div>
			<p class="stsw-card__meta">
				<?php
				/* translators: %d: request number. */
				echo '<span>' . esc_html( sprintf( __( 'Request #%d', 'support-ticket-system-for-woocommerce' ), $ticket_id ) ) . '</span>';
				if ( $order ) {
					$url = ( $viewer && (int) $order->get_customer_id() === $viewer ) ? $order->get_view_order_url() : '';
					/* translators: %s: order number. */
					$text = sprintf( __( 'Order #%s', 'support-ticket-system-for-woocommerce' ), $order->get_order_number() );
					echo '<span>' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>' : esc_html( $text ) ) . '</span>';
				}
				echo '<span>' . $this->status_chip( $ticket_id ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in status_chip().
				?>
			</p>
			<?php $this->render_thread( $ticket_id, $viewer, $guest['file_args'] ?? array() ); ?>
			<?php
			/**
			 * After the conversation on the customer screen (Pro: satisfaction rating).
			 *
			 * @param int   $ticket_id Ticket ID.
			 * @param array $guest     Guest context.
			 */
			do_action( 'wpfactory_wc_sts_customer_ticket_after_thread', $ticket_id, $guest );
			$hidden = array_merge( array( 'post_id' => $ticket_id ), $guest['hidden'] ?? array() );
			$nonce  = $guest['nonce'] ?? 'stswresponsefrontend';
			if ( ! $guest ) {
				$hidden['stsw_return'] = $this->get_view_url( 'ticket', array( 'ticket' => $ticket_id ) );
			}
			if ( $closed ) {
				?>
				<form class="stsw-reopen" method="post">
					<p><?php esc_html_e( 'This request is closed. Reopen it if you still need help.', 'support-ticket-system-for-woocommerce' ); ?></p>
					<?php $this->hidden_fields( $hidden ); ?>
					<?php wp_nonce_field( $nonce, $nonce ); ?>
					<button type="submit" class="stsw-button" name="stsw_ticket_action" value="reopen"><?php esc_html_e( 'Reopen request', 'support-ticket-system-for-woocommerce' ); ?></button>
				</form>
				<?php
				return;
			}
			?>
			<form class="stsw-reply <?php echo esc_attr( $core->plugin ); ?>new_response" method="post" enctype="multipart/form-data">
				<div class="stsw-field">
					<label for="stsw-reply-<?php echo (int) $ticket_id; ?>"><?php esc_html_e( 'Your reply', 'support-ticket-system-for-woocommerce' ); ?></label>
					<textarea id="stsw-reply-<?php echo (int) $ticket_id; ?>" name="response_content" rows="5" required></textarea>
				</div>
				<?php $this->render_file_input( 'stsw-reply-files-' . (int) $ticket_id ); ?>
				<?php $this->hidden_fields( $hidden ); ?>
				<?php wp_nonce_field( $nonce, $nonce ); ?>
				<div class="stsw-actions">
					<button type="submit" class="stsw-button stsw-button--primary button wp-element-button" name="stsw_ticket_action" value="reply"><?php esc_html_e( 'Send reply', 'support-ticket-system-for-woocommerce' ); ?></button>
					<button type="submit" class="stsw-button" name="stsw_ticket_action" value="close" formnovalidate><?php esc_html_e( 'Close request', 'support-ticket-system-for-woocommerce' ); ?></button>
				</div>
			</form>
			<?php
		}

		/**
		 * Hidden inputs.
		 *
		 * @param array $fields name => value.
		 */
		protected function hidden_fields( $fields ) {
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
			$core  = $this->core();
			$owner = $core->get_ticket_user_id( $ticket_id );
			$staff = $viewer_id && $core->can_manage_tickets( $viewer_id );
			$you   = __( 'You', 'support-ticket-system-for-woocommerce' );
			$store = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$files = class_exists( 'WPFactory_WC_STS_Files' ) ? WPFactory_WC_STS_Files::get_instance() : null;

			echo '<ol class="stsw-thread">';
			// First message.
			$first_by = ( ! $viewer_id || $viewer_id === $owner ) ? $you : $core->get_ticket_contact( $ticket_id )['display_name'];
			echo '<li class="stsw-msg stsw-msg--customer"><p class="stsw-msg__head"><strong>' . esc_html( $first_by ) . '</strong> <time datetime="' . esc_attr( get_post_time( 'c', true, $ticket_id ) ) . '">' . esc_html( $core->format_response_date( get_post_field( 'post_date_gmt', $ticket_id ) ) ) . '</time></p>';
			echo '<div class="stsw-msg__body">' . $core->format_message( get_post_field( 'post_content', $ticket_id, 'raw' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
			$details = $this->get_ticket_details( $ticket_id );
			if ( $details ) {
				echo '<dl class="stsw-details">';
				foreach ( $details as $label => $value ) {
					echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
				}
				echo '</dl>';
			}
			$ticket_files = (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FILES, true );
			if ( '' !== $ticket_files && $files ) {
				echo '<div class="stsw-files">';
				foreach ( explode( ',', $ticket_files ) as $url ) {
					$files->print_ticket_file_link( $url, $file_args );
				}
				echo '</div>';
			}
			echo '</li>';

			foreach ( $core->get_replies( $ticket_id ) as $res ) {
				$by_store = (int) $res->agent > 0;
				if ( $by_store ) {
					$who = $staff ? $core->get_response_author_label( $res ) : $store;
				} else {
					$who = ( ! $viewer_id || $viewer_id === $owner ) ? $you : $core->get_ticket_contact( $ticket_id )['display_name'];
				}
				echo '<li class="stsw-msg stsw-msg--' . ( $by_store ? 'store' : 'customer' ) . '"><p class="stsw-msg__head"><strong>' . esc_html( $who ) . '</strong> <time datetime="' . esc_attr( mysql2date( 'c', $res->creationdate . ' +0000' ) ) . '">' . esc_html( $core->format_response_date( $res->creationdate ) ) . '</time></p>';
				echo '<div class="stsw-msg__body">' . $core->format_message( $res->content ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
				if ( ! empty( $res->attachments ) && $files ) {
					echo '<div class="stsw-files">';
					foreach ( explode( ',', (string) $res->attachments ) as $attachment_id ) {
						$files->print_attachment_link( (int) $attachment_id, $file_args );
					}
					echo '</div>';
				}
				echo '</li>';
			}
			echo '</ol>';
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
