<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: the staff inbox.
 *
 * One screen in wp-admin: views with live counts (Needs reply, Mine, Unassigned, Waiting on customer, Closed), the
 * ticket list, and the selected ticket with its conversation, private notes, reply box, status, priority and
 * assignee. Bulk actions, keyboard shortcuts, a setup checklist, and tickets on the WooCommerce order screens.
 * The old ticket list and edit screens redirect here. Shared by the free and Pro editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Inbox' ) ) :

	/**
	 * WPFactory_WC_STS_Inbox class.
	 *
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	class WPFactory_WC_STS_Inbox {

		/**
		 * Tickets per page.
		 */
		const PER_PAGE = 30;

		/**
		 * Nonce action.
		 */
		const NONCE = 'wpfactory_wc_sts_inbox';

		/**
		 * Ticket counts per order, for the orders list column.
		 *
		 * @var array|null
		 */
		protected $order_counts = null;

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Inbox|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Inbox
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
			add_action( 'admin_post_wpfactory_wc_sts_inbox', array( $this, 'handle_action' ) );
			add_action( 'load-edit.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-post.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-post-new.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

			// WooCommerce order screens (HPOS and posts storage).
			add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'order_columns' ) );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'order_column_content' ), 10, 2 );
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'order_columns' ) );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'order_column_content' ), 10, 2 );
		}

		/**
		 * Core instance.
		 *
		 * @return WPFactory_WC_STS_Inc
		 */
		protected function core() {
			return WPFactory_WC_STS_Inc::get_main_instance();
		}

		/**
		 * Inbox URL.
		 *
		 * @param array $args Query args.
		 *
		 * @return string
		 */
		public function url( $args = array() ) {
			return add_query_arg( $args, admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE ) );
		}

		/**
		 * Old ticket screens (list, edit, add) open the inbox instead.
		 */
		public function redirect_old_screens() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
			global $pagenow;
			$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) );
			$post_id   = absint( $_GET['post'] ?? 0 );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( 'post.php' === $pagenow && $post_id && 'stsw_tickets' === get_post_type( $post_id ) ) {
				wp_safe_redirect( $this->url( array( 'ticket' => $post_id ) ) );
				exit;
			}
			if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) && 'stsw_tickets' === $post_type ) {
				wp_safe_redirect( $this->url( 'post-new.php' === $pagenow ? array( 'stsw_new' => 1 ) : array() ) );
				exit;
			}
		}

		/**
		 * Inbox script and styles.
		 */
		public function enqueue() {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( WPFACTORY_WC_STS_PAGE !== $page || 'settings' === $tab ) {
				return;
			}
			wp_enqueue_script( 'wpfactory-wc-sts-inbox', plugins_url( 'assets/js/inbox.js', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION, true );
			wp_localize_script(
				'wpfactory-wc-sts-inbox',
				'WPFactory_WC_STS_Inbox',
				array(
					'confirmClose'  => __( 'Close this ticket?', 'support-ticket-system-for-woocommerce' ),
					'confirmBulk'   => __( 'Apply this change to the selected tickets?', 'support-ticket-system-for-woocommerce' ),
					'confirmCanned' => __( 'Replace the text you already typed?', 'support-ticket-system-for-woocommerce' ),
					'draftSaved'    => __( 'Draft saved on this computer', 'support-ticket-system-for-woocommerce' ),
					'noteMode'      => __( 'Private note: only staff will see it', 'support-ticket-system-for-woocommerce' ),
					'replyMode'     => __( 'Reply: the customer will see it', 'support-ticket-system-for-woocommerce' ),
				)
			);
		}

		/*
		|--------------------------------------------------------------------------
		| Views and queries
		|--------------------------------------------------------------------------
		*/

		/**
		 * Views of the left rail.
		 *
		 * @return array key => { label, query args }
		 */
		public function get_views() {
			$open  = array(
				'taxonomy' => 'stsw_tickets_status',
				'field'    => 'slug',
				'terms'    => array( 'closed' ),
				'operator' => 'NOT IN',
			);
			$views = array(
				'needs_reply' => array(
					'label' => __( 'Needs reply', 'support-ticket-system-for-woocommerce' ),
					'args'  => array(
						'tax_query'  => array( $open ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'   => WPFactory_WC_STS_Inc::META_LAST_BY,
								'value' => 'customer',
							),
						),
						'orderby'    => 'modified',
						'order'      => 'ASC',
					),
				),
				'mine'        => array(
					'label' => __( 'Mine', 'support-ticket-system-for-woocommerce' ),
					'args'  => array(
						'tax_query'  => array( $open ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'   => WPFactory_WC_STS_Inc::META_AGENT,
								'value' => get_current_user_id(),
							),
						),
					),
				),
				'unassigned'  => array(
					'label' => __( 'Unassigned', 'support-ticket-system-for-woocommerce' ),
					'args'  => array(
						'tax_query'  => array( $open ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'OR',
							array(
								'key'     => WPFactory_WC_STS_Inc::META_AGENT,
								'compare' => 'NOT EXISTS',
							),
							array(
								'key'     => WPFactory_WC_STS_Inc::META_AGENT,
								'value'   => array( '', '0' ),
								'compare' => 'IN',
							),
						),
					),
				),
				'waiting'     => array(
					'label' => __( 'Waiting on customer', 'support-ticket-system-for-woocommerce' ),
					'args'  => array(
						'tax_query'  => array( $open ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'   => WPFactory_WC_STS_Inc::META_LAST_BY,
								'value' => 'store',
							),
						),
					),
				),
				'closed'      => array(
					'label' => __( 'Closed', 'support-ticket-system-for-woocommerce' ),
					'args'  => array(
						'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => 'stsw_tickets_status',
								'field'    => 'slug',
								'terms'    => array( 'closed' ),
							),
						),
					),
				),
				'all'         => array(
					'label' => __( 'All', 'support-ticket-system-for-woocommerce' ),
					'args'  => array(),
				),
			);
			/**
			 * Views of the inbox (Pro adds "Overdue").
			 *
			 * @param array $views key => { label, args (WP_Query arguments) }.
			 */
			return (array) apply_filters( 'wpfactory_wc_sts_inbox_views', $views );
		}

		/**
		 * Tickets of a view.
		 *
		 * @param string $view  View key.
		 * @param array  $extra Extra WP_Query arguments.
		 *
		 * @return WP_Query
		 */
		public function query( $view, $extra = array() ) {
			$views = $this->get_views();
			$args  = isset( $views[ $view ] ) ? $views[ $view ]['args'] : array();
			return new WP_Query(
				array_merge(
					array(
						'post_type'      => 'stsw_tickets',
						'post_status'    => 'publish',
						'posts_per_page' => self::PER_PAGE,
						'orderby'        => 'modified',
						'order'          => 'DESC',
						'no_found_rows'  => false,
					),
					$args,
					$extra
				)
			);
		}

		/**
		 * Number of tickets in a view.
		 *
		 * @param string $view View key.
		 *
		 * @return int
		 */
		public function count( $view ) {
			$query = $this->query(
				$view,
				array(
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			return (int) $query->found_posts;
		}

		/**
		 * Search and order filter from the request, as WP_Query arguments.
		 *
		 * @return array
		 */
		protected function get_filters() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
			$args   = array();
			$search = trim( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) );
			$order  = absint( $_GET['order_id'] ?? 0 );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$meta = array();
			if ( '' !== $search ) {
				if ( ctype_digit( ltrim( $search, '#' ) ) ) {
					$args['post__in'] = array( absint( ltrim( $search, '#' ) ) );
				} elseif ( is_email( $search ) ) {
					$user   = get_user_by( 'email', $search );
					$meta[] = array(
						'relation' => 'OR',
						array(
							'key'   => WPFactory_WC_STS_Inc::META_GUEST_EMAIL,
							'value' => $search,
						),
						array(
							'key'   => WPFactory_WC_STS_Inc::META_USER,
							'value' => $user ? (int) $user->ID : -1,
						),
					);
				} else {
					$args['s'] = $search;
				}
			}
			if ( $order ) {
				$meta[] = array(
					'key'   => WPFactory_WC_STS_Inc::META_ORDER,
					'value' => $order,
				);
			}
			if ( $meta ) {
				$args['stsw_extra_meta'] = $meta;
			}
			return $args;
		}

		/**
		 * Merges extra meta conditions into a view's query.
		 *
		 * @param string $view View.
		 * @param array  $args Arguments (with `stsw_extra_meta`).
		 *
		 * @return WP_Query
		 */
		protected function query_filtered( $view, $args ) {
			$views = $this->get_views();
			$base  = isset( $views[ $view ] ) ? $views[ $view ]['args'] : array();
			if ( ! empty( $args['stsw_extra_meta'] ) ) {
				$meta               = isset( $base['meta_query'] ) ? array( $base['meta_query'] ) : array();
				$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta, $args['stsw_extra_meta'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}
			unset( $args['stsw_extra_meta'] );
			return $this->query( $view, $args );
		}

		/*
		|--------------------------------------------------------------------------
		| Screen
		|--------------------------------------------------------------------------
		*/

		/**
		 * The inbox.
		 */
		public function render() {
			$core = $this->core();
			if ( ! $core->can_manage_tickets() ) {
				echo '<p>' . esc_html__( 'You are not allowed to see tickets.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				return;
			}
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
			$views     = $this->get_views();
			$view      = sanitize_key( wp_unslash( $_GET['view'] ?? 'needs_reply' ) );
			$view      = isset( $views[ $view ] ) ? $view : 'needs_reply';
			$ticket_id = absint( $_GET['ticket'] ?? 0 );
			$paged     = max( 1, absint( $_GET['paged'] ?? 1 ) );
			$message   = sanitize_key( wp_unslash( $_GET['stsw_msg'] ?? '' ) );
			$search    = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
			$order_id  = absint( $_GET['order_id'] ?? 0 );
			$new       = ! empty( $_GET['stsw_new'] );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( $ticket_id && 'stsw_tickets' !== get_post_type( $ticket_id ) ) {
				$ticket_id = 0;
			}

			$this->render_notice( $message );
			$this->render_checklist();

			$filters          = $this->get_filters();
			$filters['paged'] = $paged;
			$list             = $this->query_filtered( $view, $filters );
			$keep             = array_filter(
				array(
					'view'     => $view,
					's'        => $search,
					'order_id' => $order_id,
					'paged'    => $paged > 1 ? $paged : 0,
				)
			);
			?>
			<div class="stsw-inbox<?php echo $ticket_id || $new ? ' has-detail' : ''; ?>">
				<nav class="stsw-inbox__rail" aria-label="<?php esc_attr_e( 'Ticket views', 'support-ticket-system-for-woocommerce' ); ?>">
					<ul>
						<?php foreach ( $views as $key => $item ) : ?>
							<li>
								<a href="<?php echo esc_url( $this->url( array( 'view' => $key ) ) ); ?>"<?php echo $key === $view ? ' class="is-current" aria-current="page"' : ''; ?>>
									<span><?php echo esc_html( $item['label'] ); ?></span>
									<span class="stsw-count"><?php echo (int) $this->count( $key ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php
					/**
					 * Below the views (Pro: new ticket button).
					 */
					do_action( 'wpfactory_wc_sts_inbox_rail' );
					?>
					<p><button type="button" class="button-link stsw-shortcuts-open" aria-haspopup="dialog"><?php esc_html_e( 'Keyboard shortcuts (?)', 'support-ticket-system-for-woocommerce' ); ?></button></p>
				</nav>

				<section class="stsw-inbox__list" aria-label="<?php echo esc_attr( $views[ $view ]['label'] ); ?>">
					<form method="get" class="stsw-search" role="search">
						<input type="hidden" name="page" value="<?php echo esc_attr( WPFACTORY_WC_STS_PAGE ); ?>" />
						<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>" />
						<label class="screen-reader-text" for="stsw-search"><?php esc_html_e( 'Search tickets', 'support-ticket-system-for-woocommerce' ); ?></label>
						<input type="search" id="stsw-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by words, #number or email', 'support-ticket-system-for-woocommerce' ); ?>" />
						<button type="submit" class="button"><?php esc_html_e( 'Search', 'support-ticket-system-for-woocommerce' ); ?></button>
					</form>
					<?php if ( $order_id ) : ?>
						<p class="stsw-filter">
							<?php
							/* translators: %d: order ID. */
							echo esc_html( sprintf( __( 'Tickets of order %d', 'support-ticket-system-for-woocommerce' ), $order_id ) );
							?>
							<a href="<?php echo esc_url( $this->url( array( 'view' => $view ) ) ); ?>"><?php esc_html_e( 'Show all', 'support-ticket-system-for-woocommerce' ); ?></a>
						</p>
					<?php endif; ?>
					<?php $this->render_list( $list, $keep, $ticket_id ); ?>
				</section>

				<section class="stsw-inbox__detail" aria-label="<?php esc_attr_e( 'Ticket', 'support-ticket-system-for-woocommerce' ); ?>">
					<?php
					if ( $new ) {
						/**
						 * New ticket form started by staff (Pro).
						 *
						 * @param array $keep Current inbox query args.
						 */
						do_action( 'wpfactory_wc_sts_inbox_new_ticket', $keep );
					} elseif ( $ticket_id ) {
						$this->render_ticket( $ticket_id, $keep );
					} else {
						echo '<p class="stsw-inbox__empty">' . esc_html__( 'Select a ticket to read and answer it.', 'support-ticket-system-for-woocommerce' ) . '</p>';
					}
					?>
				</section>
			</div>
			<?php
			$this->render_shortcuts_dialog();
		}

		/**
		 * Result notice of the last action.
		 *
		 * @param string $message Message key.
		 */
		protected function render_notice( $message ) {
			$texts = array(
				'sent'    => __( 'Reply sent.', 'support-ticket-system-for-woocommerce' ),
				'noted'   => __( 'Private note added.', 'support-ticket-system-for-woocommerce' ),
				'updated' => __( 'Ticket updated.', 'support-ticket-system-for-woocommerce' ),
				'bulk'    => __( 'Selected tickets updated.', 'support-ticket-system-for-woocommerce' ),
				'test'    => __( 'Test ticket created. Open it below, reply, and check that the emails arrive.', 'support-ticket-system-for-woocommerce' ),
				'empty'   => __( 'Write a message first.', 'support-ticket-system-for-woocommerce' ),
				'none'    => __( 'Select at least one ticket.', 'support-ticket-system-for-woocommerce' ),
				'error'   => __( 'That did not work. Please try again.', 'support-ticket-system-for-woocommerce' ),
			);
			/**
			 * Inbox result messages (Pro adds its own).
			 *
			 * @param array $texts key => message.
			 */
			$texts = (array) apply_filters( 'wpfactory_wc_sts_inbox_messages', $texts );
			if ( isset( $texts[ $message ] ) ) {
				$error = in_array( $message, array( 'empty', 'none', 'error' ), true ) || 0 === strpos( $message, 'error_' );
				echo '<div class="notice ' . ( $error ? 'notice-error' : 'notice-success' ) . ' inline" role="' . ( $error ? 'alert' : 'status' ) . '"><p>' . esc_html( $texts[ $message ] ) . '</p></div>';
			}
		}

		/**
		 * Ticket list with checkboxes and bulk actions.
		 *
		 * @param WP_Query $list      Tickets.
		 * @param array    $keep      Current query args.
		 * @param int      $ticket_id Selected ticket.
		 */
		protected function render_list( $list, $keep, $ticket_id ) {
			$core = $this->core();
			if ( ! $list->have_posts() ) {
				echo '<p class="stsw-inbox__empty">' . esc_html__( 'No tickets here.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				return;
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stsw-bulk">
				<input type="hidden" name="action" value="wpfactory_wc_sts_inbox" />
				<input type="hidden" name="stsw_action" value="bulk" />
				<input type="hidden" name="stsw_back" value="<?php echo esc_attr( wp_json_encode( $keep ) ); ?>" />
				<?php wp_nonce_field( self::NONCE ); ?>
				<div class="stsw-bulk__bar">
					<label><input type="checkbox" class="stsw-check-all" /> <span class="screen-reader-text"><?php esc_html_e( 'Select all', 'support-ticket-system-for-woocommerce' ); ?></span></label>
					<label class="screen-reader-text" for="stsw-bulk-action"><?php esc_html_e( 'Bulk action', 'support-ticket-system-for-woocommerce' ); ?></label>
					<select id="stsw-bulk-action" name="stsw_bulk">
						<option value=""><?php esc_html_e( 'Bulk action', 'support-ticket-system-for-woocommerce' ); ?></option>
						<optgroup label="<?php esc_attr_e( 'Status', 'support-ticket-system-for-woocommerce' ); ?>">
							<?php foreach ( $this->get_terms( 'stsw_tickets_status' ) as $term ) : ?>
								<option value="status:<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Priority', 'support-ticket-system-for-woocommerce' ); ?>">
							<?php foreach ( $this->get_terms( 'stsw_tickets_priority' ) as $term ) : ?>
								<option value="priority:<?php echo (int) $term->term_id; ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Assign to', 'support-ticket-system-for-woocommerce' ); ?>">
							<option value="assign:0"><?php esc_html_e( 'Nobody', 'support-ticket-system-for-woocommerce' ); ?></option>
							<?php foreach ( WPFactory_WC_STS_Admin::get_instance()->get_assignable_users() as $user ) : ?>
								<option value="assign:<?php echo (int) $user->ID; ?>"><?php echo esc_html( $core->get_user_name( $user->ID ) ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					</select>
					<button type="submit" class="button"><?php esc_html_e( 'Apply', 'support-ticket-system-for-woocommerce' ); ?></button>
				</div>
				<ul class="stsw-rows">
					<?php
					foreach ( $list->posts as $post ) :
						$id       = (int) $post->ID;
						$contact  = $core->get_ticket_contact( $id );
						$activity = $core->get_activity( $id );
						$order_id = $core->get_ticket_order_id( $id );
						$order    = $order_id ? wc_get_order( $order_id ) : false;
						$priority = $core->get_ticket_term( $id, 'stsw_tickets_priority' );
						$waiting  = 'customer' === $activity['by'] && 'closed' !== $core->get_ticket_status_slug( $id );
						$classes  = 'stsw-row' . ( $id === $ticket_id ? ' is-current' : '' ) . ( $waiting ? ' is-waiting' : '' );
						?>
						<li class="<?php echo esc_attr( $classes ); ?>">
							<input type="checkbox" name="stsw_ids[]" value="<?php echo (int) $id; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: ticket number. */ __( 'Select ticket #%d', 'support-ticket-system-for-woocommerce' ), $id ) ); ?>" />
							<a class="stsw-row__link" href="<?php echo esc_url( $this->url( array_merge( $keep, array( 'ticket' => $id ) ) ) ); ?>"<?php echo $id === $ticket_id ? ' aria-current="true"' : ''; ?>>
								<span class="stsw-row__top">
									<strong class="stsw-row__customer"><?php echo esc_html( $contact['display_name'] ); ?></strong>
									<span class="stsw-row__age"><?php echo esc_html( $activity['at'] ? human_time_diff( strtotime( $activity['at'] . ' UTC' ) ) : '' ); ?></span>
								</span>
								<span class="stsw-row__subject">#<?php echo (int) $id; ?> <?php echo esc_html( get_the_title( $id ) ); ?></span>
								<span class="stsw-row__meta">
									<?php if ( $order ) : ?>
										<span>
											<?php
											/* translators: %s: order number. */
											echo esc_html( sprintf( __( 'Order #%s', 'support-ticket-system-for-woocommerce' ), $order->get_order_number() ) );
											?>
										</span>
									<?php endif; ?>
									<?php if ( $priority ) : ?>
										<?php echo $this->priority_chip( $priority ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in priority_chip(). ?>
									<?php endif; ?>
									<?php
									/**
									 * Extra badges on a list row (Pro: due time).
									 *
									 * @param int $id Ticket ID.
									 */
									do_action( 'wpfactory_wc_sts_inbox_row_meta', $id );
									?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</form>
			<?php
			$pages = (int) $list->max_num_pages;
			if ( $pages > 1 ) {
				$current = max( 1, (int) ( $keep['paged'] ?? 1 ) );
				echo '<nav class="stsw-pages" aria-label="' . esc_attr__( 'Pages', 'support-ticket-system-for-woocommerce' ) . '">';
				if ( $current > 1 ) {
					echo '<a class="button" href="' . esc_url( $this->url( array_merge( $keep, array( 'paged' => $current - 1 ) ) ) ) . '">' . esc_html__( 'Newer', 'support-ticket-system-for-woocommerce' ) . '</a> ';
				}
				/* translators: 1: current page, 2: number of pages. */
				echo '<span>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'support-ticket-system-for-woocommerce' ), $current, $pages ) ) . '</span>';
				if ( $current < $pages ) {
					echo ' <a class="button" href="' . esc_url( $this->url( array_merge( $keep, array( 'paged' => $current + 1 ) ) ) ) . '">' . esc_html__( 'Older', 'support-ticket-system-for-woocommerce' ) . '</a>';
				}
				echo '</nav>';
			}
		}

		/**
		 * Priority chip with the merchant's colour.
		 *
		 * @param WP_Term $term Priority.
		 *
		 * @return string
		 */
		public function priority_chip( $term ) {
			$color = sanitize_hex_color( (string) get_term_meta( $term->term_id, WPFactory_WC_STS_Inc::META_COLOR, true ) );
			return '<span class="stsw-priority"' . ( $color ? ' style="--stsw-priority:' . esc_attr( $color ) . '"' : '' ) . '>' . esc_html( $term->name ) . '</span>';
		}

		/**
		 * Terms of a ticket taxonomy.
		 *
		 * @param string $taxonomy Taxonomy.
		 *
		 * @return WP_Term[]
		 */
		protected function get_terms( $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			return is_wp_error( $terms ) ? array() : $terms;
		}

		/**
		 * The selected ticket: header with status, priority and assignee; conversation with notes; reply box.
		 *
		 * @param int   $ticket_id Ticket ID.
		 * @param array $keep      Current query args.
		 */
		protected function render_ticket( $ticket_id, $keep ) {
			$core     = $this->core();
			$contact  = $core->get_ticket_contact( $ticket_id );
			$order_id = $core->get_ticket_order_id( $ticket_id );
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			$status   = $core->get_ticket_status_slug( $ticket_id );
			$priority = $core->get_ticket_term( $ticket_id, 'stsw_tickets_priority' );
			$back     = wp_json_encode( array_merge( $keep, array( 'ticket' => $ticket_id ) ) );
			?>
			<header class="stsw-ticket__head">
				<h2 class="stsw-ticket__title">#<?php echo (int) $ticket_id; ?> <?php echo esc_html( get_the_title( $ticket_id ) ); ?></h2>
				<p class="stsw-ticket__who">
					<?php
					echo esc_html( $contact['display_name'] );
					if ( $contact['email'] ) {
						echo ' <a href="mailto:' . esc_attr( $contact['email'] ) . '">' . esc_html( $contact['email'] ) . '</a>';
					}
					if ( ! $core->get_ticket_user_id( $ticket_id ) ) {
						echo ' <span class="stsw-priority">' . esc_html__( 'Guest', 'support-ticket-system-for-woocommerce' ) . '</span>';
					}
					if ( $order ) {
						/* translators: %s: order number. */
						echo ' <a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( sprintf( __( 'Order #%s', 'support-ticket-system-for-woocommerce' ), $order->get_order_number() ) ) . '</a>';
					}
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stsw-ticket__controls" id="stsw-controls">
					<input type="hidden" name="action" value="wpfactory_wc_sts_inbox" />
					<input type="hidden" name="stsw_action" value="update" />
					<input type="hidden" name="ticket" value="<?php echo (int) $ticket_id; ?>" />
					<input type="hidden" name="stsw_back" value="<?php echo esc_attr( $back ); ?>" />
					<?php wp_nonce_field( self::NONCE ); ?>
					<label><span><?php esc_html_e( 'Status', 'support-ticket-system-for-woocommerce' ); ?></span>
						<select name="stsw_status" id="stsw-status">
							<?php foreach ( $this->get_terms( 'stsw_tickets_status' ) as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $status, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><span><?php esc_html_e( 'Priority', 'support-ticket-system-for-woocommerce' ); ?></span>
						<select name="stsw_priority">
							<?php foreach ( $this->get_terms( 'stsw_tickets_priority' ) as $term ) : ?>
								<option value="<?php echo (int) $term->term_id; ?>" <?php selected( $priority ? $priority->term_id : 0, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><span><?php esc_html_e( 'Assignee', 'support-ticket-system-for-woocommerce' ); ?></span>
						<?php WPFactory_WC_STS_Admin::get_instance()->render_user_select( 'stsw_agent', $core->get_ticket_agent_id( $ticket_id ), __( 'Nobody', 'support-ticket-system-for-woocommerce' ) ); ?>
					</label>
					<button type="submit" class="button"><?php esc_html_e( 'Save', 'support-ticket-system-for-woocommerce' ); ?></button>
				</form>
				<?php
				/**
				 * Tools for the selected ticket (Pro: merge, AI summary).
				 *
				 * @param int    $ticket_id Ticket ID.
				 * @param string $back      JSON of the inbox query args to return to.
				 */
				do_action( 'wpfactory_wc_sts_inbox_ticket_tools', $ticket_id, $back );
				?>
			</header>

			<div class="stsw-ticket__body">
				<div class="stsw-ticket__main">
					<?php $this->render_thread( $ticket_id ); ?>
					<?php $this->render_reply_box( $ticket_id, $back ); ?>
				</div>
				<aside class="stsw-ticket__side" aria-label="<?php esc_attr_e( 'Customer and order', 'support-ticket-system-for-woocommerce' ); ?>">
					<?php $this->render_side( $ticket_id ); ?>
				</aside>
			</div>
			<?php
		}

		/**
		 * Conversation with private notes, oldest first.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		protected function render_thread( $ticket_id ) {
			$core    = $this->core();
			$files   = class_exists( 'WPFactory_WC_STS_Files' ) ? WPFactory_WC_STS_Files::get_instance() : null;
			$contact = $core->get_ticket_contact( $ticket_id );
			$items   = array();
			foreach ( $core->get_replies( $ticket_id ) as $row ) {
				$items[] = array(
					'type' => (int) $row->agent > 0 ? 'store' : 'customer',
					'row'  => $row,
				);
			}
			foreach ( $core->get_notes( $ticket_id ) as $row ) {
				$items[] = array(
					'type' => 'note',
					'row'  => $row,
				);
			}
			usort(
				$items,
				function ( $a, $b ) {
					return strcmp( $a['row']->creationdate . sprintf( '%010d', $a['row']->id ), $b['row']->creationdate . sprintf( '%010d', $b['row']->id ) );
				}
			);
			$details = WPFactory_WC_STS_Customer::get_instance()->get_ticket_details( $ticket_id );
			echo '<ol class="stsw-thread">';
			$started = (int) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_STARTED_BY, true );
			echo '<li class="stsw-msg stsw-msg--' . ( $started ? 'store' : 'customer' ) . '"><p class="stsw-msg__head"><strong>' . esc_html( $started ? $core->get_user_name( $started ) : $contact['display_name'] ) . '</strong> <time>' . esc_html( $core->format_response_date( get_post_field( 'post_date_gmt', $ticket_id ) ) ) . '</time></p>';
			echo '<div class="stsw-msg__body">' . $core->format_message( get_post_field( 'post_content', $ticket_id, 'raw' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
			if ( $details ) {
				echo '<dl class="stsw-details">';
				foreach ( $details as $label => $value ) {
					echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
				}
				echo '</dl>';
			}
			$ticket_files = (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FILES, true );
			if ( '' !== $ticket_files && $files ) {
				foreach ( explode( ',', $ticket_files ) as $url ) {
					$files->print_ticket_file_link( $url );
				}
			}
			echo '</li>';
			foreach ( $items as $item ) {
				$row = $item['row'];
				if ( 'note' === $item['type'] ) {
					/* translators: %s: staff name. */
					$who = (int) $row->agent ? sprintf( __( 'Private note by %s', 'support-ticket-system-for-woocommerce' ), $core->get_user_name( (int) $row->agent ) ) : __( 'Automatic note', 'support-ticket-system-for-woocommerce' );
				} elseif ( 'store' === $item['type'] ) {
					$who = $core->get_user_name( (int) $row->agent );
				} else {
					$who = $contact['display_name'];
				}
				echo '<li class="stsw-msg stsw-msg--' . esc_attr( $item['type'] ) . '" id="stsw-' . esc_attr( $item['type'] . '-' . (int) $row->id ) . '"><p class="stsw-msg__head"><strong>' . esc_html( $who ) . '</strong> <time>' . esc_html( $core->format_response_date( $row->creationdate ) ) . '</time>';
				echo ' <button type="button" class="button-link wpfactory-wc-sts-delete" data-type="' . ( 'note' === $item['type'] ? 'note' : 'response' ) . '" data-id="' . (int) $row->id . '">' . esc_html__( 'Delete', 'support-ticket-system-for-woocommerce' ) . '</button></p>';
				echo '<div class="stsw-msg__body">' . $core->format_message( $row->content ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
				if ( 'note' !== $item['type'] && ! empty( $row->attachments ) && $files ) {
					foreach ( explode( ',', (string) $row->attachments ) as $attachment_id ) {
						$files->print_attachment_link( (int) $attachment_id );
					}
				}
				echo '</li>';
			}
			echo '</ol>';
		}

		/**
		 * Reply box: reply or private note, saved replies, files, send or send and close. Drafts are kept on this computer.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $back      JSON of the inbox query args.
		 */
		protected function render_reply_box( $ticket_id, $back ) {
			$core = $this->core();
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="stsw-replybox" data-ticket="<?php echo (int) $ticket_id; ?>">
				<input type="hidden" name="action" value="wpfactory_wc_sts_inbox" />
				<input type="hidden" name="stsw_action" value="reply" />
				<input type="hidden" name="ticket" value="<?php echo (int) $ticket_id; ?>" />
				<input type="hidden" name="stsw_back" value="<?php echo esc_attr( $back ); ?>" />
				<?php wp_nonce_field( self::NONCE ); ?>
				<fieldset class="stsw-replybox__mode">
					<legend class="screen-reader-text"><?php esc_html_e( 'Message type', 'support-ticket-system-for-woocommerce' ); ?></legend>
					<label><input type="radio" name="stsw_mode" value="reply" checked /> <?php esc_html_e( 'Reply', 'support-ticket-system-for-woocommerce' ); ?></label>
					<label><input type="radio" name="stsw_mode" value="note" /> <?php esc_html_e( 'Private note', 'support-ticket-system-for-woocommerce' ); ?></label>
				</fieldset>
				<div class="stsw-replybox__tools">
					<?php
					if ( class_exists( 'WPFactory_WC_STS_Canned' ) ) {
						WPFactory_WC_STS_Canned::get_instance()->render_select( 'stsw-inbox-reply' );
					}
					/**
					 * Reply box tools (Pro: AI draft).
					 *
					 * @param int $ticket_id Ticket ID.
					 */
					do_action( 'wpfactory_wc_sts_inbox_reply_tools', $ticket_id );
					?>
				</div>
				<label for="stsw-inbox-reply" class="stsw-replybox__label"><?php esc_html_e( 'Reply: the customer will see it', 'support-ticket-system-for-woocommerce' ); ?></label>
				<textarea id="stsw-inbox-reply" name="stsw_content" rows="7"></textarea>
				<p class="stsw-replybox__draft" aria-live="polite"></p>
				<?php if ( $core->attachments_enabled() ) : ?>
					<p class="stsw-replybox__files">
						<label for="stsw-inbox-files"><?php esc_html_e( 'Attach files', 'support-ticket-system-for-woocommerce' ); ?></label>
						<input type="file" id="stsw-inbox-files" name="my_file_upload[]" multiple />
					</p>
				<?php endif; ?>
				<p class="stsw-replybox__actions">
					<button type="submit" class="button button-primary" name="stsw_close" value="0"><?php esc_html_e( 'Send', 'support-ticket-system-for-woocommerce' ); ?></button>
					<button type="submit" class="button stsw-reply-close" name="stsw_close" value="1"><?php esc_html_e( 'Send and close', 'support-ticket-system-for-woocommerce' ); ?></button>
				</p>
			</form>
			<?php
		}

		/**
		 * Right column: customer, order and other tickets (Pro adds the full order panel and actions).
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		protected function render_side( $ticket_id ) {
			$core    = $this->core();
			$contact = $core->get_ticket_contact( $ticket_id );
			$user_id = $core->get_ticket_user_id( $ticket_id );
			ob_start();
			/**
			 * Side panel of the selected ticket (Pro: order panel and order actions). Printing anything replaces the
			 * basic customer box.
			 *
			 * @param int $ticket_id Ticket ID.
			 */
			do_action( 'wpfactory_wc_sts_inbox_side', $ticket_id );
			$extra = ob_get_clean();
			if ( '' !== trim( $extra ) ) {
				echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the callbacks.
				return;
			}
			echo '<h3>' . esc_html__( 'Customer', 'support-ticket-system-for-woocommerce' ) . '</h3><p>' . esc_html( $contact['display_name'] ) . '<br/>' . esc_html( $contact['email'] ) . '</p>';
			$order_id = $core->get_ticket_order_id( $ticket_id );
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			if ( $order ) {
				/* translators: %s: order number. */
				echo '<h3>' . esc_html( sprintf( __( 'Order #%s', 'support-ticket-system-for-woocommerce' ), $order->get_order_number() ) ) . '</h3>';
				echo '<p>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . ', ' . wp_kses_post( $order->get_formatted_order_total() ) . '</p>';
			}
			$others = $user_id ? get_posts(
				array(
					'post_type'      => 'stsw_tickets',
					'post_status'    => 'publish',
					'posts_per_page' => 5,
					'post__not_in'   => array( $ticket_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
					'meta_key'       => WPFactory_WC_STS_Inc::META_USER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			) : array();
			if ( $others ) {
				echo '<h3>' . esc_html__( 'Other tickets', 'support-ticket-system-for-woocommerce' ) . '</h3><ul>';
				foreach ( $others as $other ) {
					echo '<li><a href="' . esc_url( $this->url( array( 'ticket' => $other->ID ) ) ) . '">#' . (int) $other->ID . ' ' . esc_html( get_the_title( $other ) ) . '</a></li>';
				}
				echo '</ul>';
			}
		}

		/**
		 * Keyboard shortcuts help.
		 */
		protected function render_shortcuts_dialog() {
			$keys = array(
				'j' => __( 'Next ticket', 'support-ticket-system-for-woocommerce' ),
				'k' => __( 'Previous ticket', 'support-ticket-system-for-woocommerce' ),
				'r' => __( 'Reply', 'support-ticket-system-for-woocommerce' ),
				'n' => __( 'Private note', 'support-ticket-system-for-woocommerce' ),
				'c' => __( 'Close the ticket', 'support-ticket-system-for-woocommerce' ),
				'?' => __( 'Show or hide this help', 'support-ticket-system-for-woocommerce' ),
			);
			echo '<dialog class="stsw-shortcuts" aria-labelledby="stsw-shortcuts-title"><h2 id="stsw-shortcuts-title">' . esc_html__( 'Keyboard shortcuts', 'support-ticket-system-for-woocommerce' ) . '</h2><dl>';
			foreach ( $keys as $key => $label ) {
				echo '<dt><kbd>' . esc_html( $key ) . '</kbd></dt><dd>' . esc_html( $label ) . '</dd>';
			}
			echo '</dl><p>' . esc_html__( 'Shortcuts do nothing while you type in a field.', 'support-ticket-system-for-woocommerce' ) . '</p><form method="dialog"><button class="button">' . esc_html__( 'Close', 'support-ticket-system-for-woocommerce' ) . '</button></form></dialog>';
		}

		/*
		|--------------------------------------------------------------------------
		| Setup checklist
		|--------------------------------------------------------------------------
		*/

		/**
		 * Setup steps and whether each is done.
		 *
		 * @return array key => { label, done, link, link_label }
		 */
		public function get_checklist() {
			$core   = $this->core();
			$page   = wc_get_page_id( 'myaccount' );
			$email  = sanitize_email( (string) get_option( $core->plugin . 'AdminEmailAddress' ) );
			$alerts = '1' === (string) get_option( $core->plugin . 'mailToADmin' );
			return array(
				'page'   => array(
					'label'      => __( 'Customers can reach the Help page in My Account', 'support-ticket-system-for-woocommerce' ),
					'done'       => $page > 0 && 'publish' === get_post_status( $page ),
					'link'       => $core->get_customer_tickets_url(),
					'link_label' => __( 'View the customer page', 'support-ticket-system-for-woocommerce' ),
				),
				'sender' => array(
					'label'      => __( 'Store email address set and email alerts on', 'support-ticket-system-for-woocommerce' ),
					'done'       => (bool) $email && $alerts,
					'link'       => admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&tab=settings#notifications' ),
					'link_label' => __( 'Open Notifications', 'support-ticket-system-for-woocommerce' ),
				),
				'test'   => array(
					'label'      => __( 'Send yourself a test ticket', 'support-ticket-system-for-woocommerce' ),
					'done'       => $core->count_tickets( '' ) > 0,
					'link'       => '',
					'link_label' => '',
				),
			);
		}

		/**
		 * Checklist at the top of the inbox until every step is done or it is hidden.
		 */
		protected function render_checklist() {
			if ( get_user_meta( get_current_user_id(), 'wpfactory_wc_sts_hide_checklist', true ) ) {
				return;
			}
			$steps = $this->get_checklist();
			$done  = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );
			if ( count( $steps ) === $done ) {
				return;
			}
			?>
			<section class="stsw-checklist" aria-labelledby="stsw-checklist-title">
				<h2 id="stsw-checklist-title">
					<?php
					/* translators: 1: steps done, 2: number of steps. */
					echo esc_html( sprintf( __( 'Get set up (%1$d of %2$d done)', 'support-ticket-system-for-woocommerce' ), $done, count( $steps ) ) );
					?>
				</h2>
				<ol>
					<?php foreach ( $steps as $key => $step ) : ?>
						<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
							<span class="dashicons <?php echo $step['done'] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span>
							<?php echo esc_html( $step['label'] ); ?>
							<span class="screen-reader-text"><?php echo $step['done'] ? esc_html__( '(done)', 'support-ticket-system-for-woocommerce' ) : esc_html__( '(to do)', 'support-ticket-system-for-woocommerce' ); ?></span>
							<?php if ( 'test' === $key && ! $step['done'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stsw-inline-form">
									<input type="hidden" name="action" value="wpfactory_wc_sts_inbox" />
									<input type="hidden" name="stsw_action" value="test_ticket" />
									<?php wp_nonce_field( self::NONCE ); ?>
									<button type="submit" class="button"><?php esc_html_e( 'Create a test ticket', 'support-ticket-system-for-woocommerce' ); ?></button>
								</form>
							<?php elseif ( $step['link'] ) : ?>
								<a href="<?php echo esc_url( $step['link'] ); ?>"><?php echo esc_html( $step['link_label'] ); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpfactory_wc_sts_inbox" />
					<input type="hidden" name="stsw_action" value="hide_checklist" />
					<?php wp_nonce_field( self::NONCE ); ?>
					<button type="submit" class="button-link"><?php esc_html_e( 'Hide this checklist', 'support-ticket-system-for-woocommerce' ); ?></button>
				</form>
			</section>
			<?php
		}

		/*
		|--------------------------------------------------------------------------
		| Actions
		|--------------------------------------------------------------------------
		*/

		/**
		 * Handles inbox forms (reply, note, update, bulk, test ticket, hide checklist), then returns to the inbox.
		 */
		public function handle_action() {
			check_admin_referer( self::NONCE );
			$core = $this->core();
			if ( ! $core->can_manage_tickets() ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}
			$action    = sanitize_key( wp_unslash( $_POST['stsw_action'] ?? '' ) );
			$ticket_id = absint( $_POST['ticket'] ?? 0 );
			$back      = json_decode( wp_unslash( (string) ( $_POST['stsw_back'] ?? '' ) ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded, then each value sanitized below.
			$back      = is_array( $back ) ? array_map( 'sanitize_text_field', array_map( 'strval', $back ) ) : array();
			$back      = array_intersect_key( $back, array_flip( array( 'view', 's', 'order_id', 'paged', 'ticket' ) ) );

			if ( $ticket_id && ( 'stsw_tickets' !== get_post_type( $ticket_id ) || ! current_user_can( 'edit_post', $ticket_id ) ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}

			$message = 'error';
			switch ( $action ) {
				case 'reply':
					$content = sanitize_textarea_field( wp_unslash( $_POST['stsw_content'] ?? '' ) );
					if ( '' === trim( $content ) ) {
						$message = 'empty';
						break;
					}
					if ( 'note' === sanitize_key( wp_unslash( $_POST['stsw_mode'] ?? 'reply' ) ) ) {
						$core->add_note( $ticket_id, $content, get_current_user_id() );
						$message = 'noted';
						break;
					}
					$core->add_reply(
						$ticket_id,
						$content,
						get_current_user_id(),
						true,
						array(
							'close'       => ! empty( $_POST['stsw_close'] ),
							'files_field' => $core->attachments_enabled() ? 'my_file_upload' : '',
						)
					);
					$message = 'sent';
					break;

				case 'update':
					$this->update_ticket(
						$ticket_id,
						sanitize_key( wp_unslash( $_POST['stsw_status'] ?? '' ) ),
						absint( $_POST['stsw_priority'] ?? 0 ),
						isset( $_POST['stsw_agent'] ) ? absint( $_POST['stsw_agent'] ) : null
					);
					$message = 'updated';
					break;

				case 'bulk':
					$ids  = array_filter( array_map( 'absint', (array) wp_unslash( $_POST['stsw_ids'] ?? array() ) ) );
					$bulk = explode( ':', sanitize_text_field( wp_unslash( $_POST['stsw_bulk'] ?? '' ) ), 2 );
					if ( ! $ids || 2 !== count( $bulk ) ) {
						$message = 'none';
						break;
					}
					foreach ( $ids as $id ) {
						if ( 'stsw_tickets' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
							continue;
						}
						if ( 'status' === $bulk[0] ) {
							$this->update_ticket( $id, sanitize_key( $bulk[1] ), 0, null );
						} elseif ( 'priority' === $bulk[0] ) {
							$this->update_ticket( $id, '', absint( $bulk[1] ), null );
						} elseif ( 'assign' === $bulk[0] ) {
							$this->update_ticket( $id, '', 0, absint( $bulk[1] ) );
						}
					}
					$message = 'bulk';
					break;

				case 'test_ticket':
					$user      = wp_get_current_user();
					$ticket_id = $core->create_ticket(
						array(
							'title'   => __( 'Test ticket', 'support-ticket-system-for-woocommerce' ),
							'content' => __( 'This is a test ticket. Reply to it to see what customers receive, then close it.', 'support-ticket-system-for-woocommerce' ),
							'user_id' => (int) $user->ID,
							'reason'  => 'other',
						)
					);
					$back      = is_wp_error( $ticket_id ) ? $back : array( 'ticket' => $ticket_id );
					$message   = is_wp_error( $ticket_id ) ? 'error' : 'test';
					break;

				case 'hide_checklist':
					update_user_meta( get_current_user_id(), 'wpfactory_wc_sts_hide_checklist', 1 );
					$message = '';
					break;
			}
			wp_safe_redirect( $this->url( array_filter( array_merge( $back, array( 'stsw_msg' => $message ) ) ) ) );
			exit;
		}

		/**
		 * Changes status, priority and assignee of a ticket (empty or null values are left as they are).
		 *
		 * @param int      $ticket_id Ticket ID.
		 * @param string   $status    Status slug.
		 * @param int      $priority  Priority term ID.
		 * @param int|null $agent     Assignee user ID (0: nobody, null: unchanged).
		 */
		public function update_ticket( $ticket_id, $status, $priority, $agent ) {
			$core = $this->core();
			if ( '' !== $status && get_term_by( 'slug', $status, 'stsw_tickets_status' ) ) {
				$core->set_ticket_status( $ticket_id, $status );
			}
			if ( $priority && get_term( $priority, 'stsw_tickets_priority' ) ) {
				wp_set_object_terms( $ticket_id, (int) $priority, 'stsw_tickets_priority' );
			}
			if ( null !== $agent ) {
				$core->assign_ticket( $ticket_id, ( $agent && get_user_by( 'id', $agent ) ) ? $agent : 0 );
			}
		}

		/*
		|--------------------------------------------------------------------------
		| WooCommerce order screens
		|--------------------------------------------------------------------------
		*/

		/**
		 * Tickets box on the order edit screen (HPOS and posts storage).
		 */
		public function add_order_meta_box() {
			if ( ! $this->core()->can_manage_tickets() ) {
				return;
			}
			$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
			foreach ( array_unique( array( $screen, 'shop_order' ) ) as $id ) {
				add_meta_box( 'wpfactory-wc-sts-order-tickets', __( 'Support tickets', 'support-ticket-system-for-woocommerce' ), array( $this, 'render_order_meta_box' ), $id, 'side', 'default' );
			}
		}

		/**
		 * Tickets box content.
		 *
		 * @param WP_Post|WC_Order $post_or_order Post (posts storage) or order (HPOS).
		 */
		public function render_order_meta_box( $post_or_order ) {
			$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
			if ( ! $order ) {
				return;
			}
			$ids = $this->core()->get_order_ticket_ids( $order->get_id() );
			if ( ! $ids ) {
				echo '<p>' . esc_html__( 'No tickets for this order.', 'support-ticket-system-for-woocommerce' ) . '</p>';
			} else {
				echo '<ul>';
				foreach ( $ids as $id ) {
					$status = $this->core()->get_ticket_term( $id, 'stsw_tickets_status' );
					echo '<li><a href="' . esc_url( $this->url( array( 'ticket' => $id ) ) ) . '">#' . (int) $id . ' ' . esc_html( get_the_title( $id ) ) . '</a>' . ( $status ? ' <span class="description">(' . esc_html( $status->name ) . ')</span>' : '' ) . '</li>';
				}
				echo '</ul>';
			}
			/**
			 * Below the order's tickets (Pro: open a ticket for this order).
			 *
			 * @param WC_Order $order Order.
			 */
			do_action( 'wpfactory_wc_sts_order_meta_box', $order );
		}

		/**
		 * Tickets column on the orders list.
		 *
		 * @param array $columns Columns.
		 *
		 * @return array
		 */
		public function order_columns( $columns ) {
			if ( ! $this->core()->can_manage_tickets() ) {
				return $columns;
			}
			$new = array();
			foreach ( $columns as $key => $label ) {
				$new[ $key ] = $label;
				if ( 'order_status' === $key ) {
					$new['stsw_tickets'] = __( 'Tickets', 'support-ticket-system-for-woocommerce' );
				}
			}
			if ( ! isset( $new['stsw_tickets'] ) ) {
				$new['stsw_tickets'] = __( 'Tickets', 'support-ticket-system-for-woocommerce' );
			}
			return $new;
		}

		/**
		 * Tickets column content (counts for all orders read in one query).
		 *
		 * @param string       $column        Column.
		 * @param int|WC_Order $order_or_post Order (HPOS) or post ID (posts storage).
		 */
		public function order_column_content( $column, $order_or_post ) {
			if ( 'stsw_tickets' !== $column ) {
				return;
			}
			if ( null === $this->order_counts ) {
				global $wpdb;
				$rows               = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT pm.meta_value AS order_id, COUNT(*) AS n FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'stsw_tickets' AND p.post_status = 'publish' WHERE pm.meta_key = %s GROUP BY pm.meta_value",
						WPFactory_WC_STS_Inc::META_ORDER
					)
				);
				$this->order_counts = wp_list_pluck( $rows, 'n', 'order_id' );
			}
			$order_id = $order_or_post instanceof WC_Order ? $order_or_post->get_id() : (int) $order_or_post;
			$count    = (int) ( $this->order_counts[ $order_id ] ?? 0 );
			if ( $count ) {
				echo '<a href="' . esc_url(
					$this->url(
						array(
							'view'     => 'all',
							'order_id' => $order_id,
						)
					)
				) . '">' . (int) $count . '</a>';
			} else {
				echo '<span aria-hidden="true">0</span><span class="screen-reader-text">' . esc_html__( 'No tickets', 'support-ticket-system-for-woocommerce' ) . '</span>';
			}
		}
	}

endif;

return WPFactory_WC_STS_Inbox::get_instance();
