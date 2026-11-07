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
			add_action( 'wp_ajax_wpfactory_wc_sts_inbox_update', array( $this, 'ajax_update' ) );
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
			if ( WPFACTORY_WC_STS_PAGE !== $page || '' !== $tab ) {
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
					'replyHolder'   => __( 'Write your reply...', 'support-ticket-system-for-woocommerce' ),
					'noteHolder'    => __( 'Only your team can see this note.', 'support-ticket-system-for-woocommerce' ),
					'confirmDelete' => __( 'Move the selected tickets to the trash?', 'support-ticket-system-for-woocommerce' ),
					'selected'      => __( '%d selected', 'support-ticket-system-for-woocommerce' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- number of tickets.
					'selectNone'    => __( 'Select tickets', 'support-ticket-system-for-woocommerce' ),
					'saveError'     => __( 'Not saved. Check your connection and try again.', 'support-ticket-system-for-woocommerce' ),
					'ajaxurl'       => admin_url( 'admin-ajax.php' ),
					'viewing'       => __( '%s is also looking at this ticket.', 'support-ticket-system-for-woocommerce' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- names.
					'replying'      => __( '%s is writing a reply.', 'support-ticket-system-for-woocommerce' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- names.
					'newMessage'    => __( 'A new message arrived on this ticket.', 'support-ticket-system-for-woocommerce' ),
					'reload'        => __( 'Show it', 'support-ticket-system-for-woocommerce' ),
					'nonce'         => wp_create_nonce( self::NONCE ),
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
		 * Search, order and term filters from the request, as WP_Query arguments.
		 *
		 * @param array|null $source Filters (null: the request).
		 *
		 * @return array
		 */
		public function get_filters( $source = null ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
			$source = null === $source ? wp_unslash( $_GET ) : $source;
			$args   = array();
			$search = trim( sanitize_text_field( $source['s'] ?? '' ) );
			$order  = absint( $source['order_id'] ?? 0 );
			$terms  = array(
				'stsw_tickets_priority' => sanitize_title( $source['prio'] ?? '' ),
				'stsw_tickets_subject'  => sanitize_title( $source['subject'] ?? '' ),
				'stsw_tickets_status'   => sanitize_title( $source['status'] ?? '' ),
				'stsw_tickets_tag'      => sanitize_title( $source['tag'] ?? '' ),
			);
			$meta   = array();
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
			$tax = array();
			foreach ( $terms as $taxonomy => $slug ) {
				if ( '' !== $slug ) {
					$tax[] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => array( $slug ),
					);
				}
			}
			if ( $tax ) {
				$args['stsw_extra_tax'] = $tax;
			}
			return $args;
		}

		/**
		 * Merges extra meta and taxonomy conditions into a view's query.
		 *
		 * @param string $view View.
		 * @param array  $args Arguments (with `stsw_extra_meta` and `stsw_extra_tax`).
		 *
		 * @return WP_Query
		 */
		public function query_filtered( $view, $args ) {
			$views = $this->get_views();
			$base  = isset( $views[ $view ] ) ? $views[ $view ]['args'] : array();
			if ( ! empty( $args['stsw_extra_meta'] ) ) {
				$meta               = isset( $base['meta_query'] ) ? array( $base['meta_query'] ) : array();
				$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta, $args['stsw_extra_meta'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}
			if ( ! empty( $args['stsw_extra_tax'] ) ) {
				$tax               = isset( $base['tax_query'] ) ? $base['tax_query'] : array();
				$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax, $args['stsw_extra_tax'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			}
			unset( $args['stsw_extra_meta'], $args['stsw_extra_tax'] );
			return $this->query( $view, $args );
		}

		/*
		|--------------------------------------------------------------------------
		| Screen
		|--------------------------------------------------------------------------
		*/

		/**
		 * Icon of a view.
		 *
		 * @param string $key View.
		 *
		 * @return string
		 */
		protected function view_icon( $key ) {
			$icons = array(
				'needs_reply' => 'inbox',
				'mine'        => 'user',
				'unassigned'  => 'userx',
				'waiting'     => 'clock',
				'closed'      => 'checkc',
				'all'         => 'layers',
				'overdue'     => 'warn',
			);
			return $icons[ $key ] ?? 'lines';
		}

		/**
		 * The inbox.
		 */
		public function render() {
			$core = $this->core();
			if ( ! $core->can_manage_tickets() ) {
				echo '<div class="hd-page">';
				WPFactory_WC_STS_UI::notice( __( 'You are not allowed to see tickets.', 'support-ticket-system-for-woocommerce' ), true );
				echo '</div>';
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
			$prio      = sanitize_title( wp_unslash( $_GET['prio'] ?? '' ) );
			$subject   = sanitize_title( wp_unslash( $_GET['subject'] ?? '' ) );
			$status    = sanitize_title( wp_unslash( $_GET['status'] ?? '' ) );
			$new       = ! empty( $_GET['stsw_new'] );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( $ticket_id && 'stsw_tickets' !== get_post_type( $ticket_id ) ) {
				$ticket_id = 0;
			}

			$filters          = $this->get_filters();
			$filters['paged'] = $paged;
			$list             = $this->query_filtered( $view, $filters );
			$keep             = array_filter(
				array(
					'view'     => $view,
					's'        => $search,
					'order_id' => $order_id,
					'prio'     => $prio,
					'subject'  => $subject,
					'status'   => $status,
					'tag'      => sanitize_title( wp_unslash( $_GET['tag'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
					'paged'    => $paged > 1 ? $paged : 0,
				)
			);
			echo '<div class="hd-inbox-wrap">';
			$this->render_notice( $message );
			$this->render_checklist();
			?>
			<div class="stsw-inbox hd-inbox<?php echo $ticket_id || $new ? ' has-detail' : ''; ?>">
				<nav class="stsw-inbox__rail hd-rail" aria-label="<?php esc_attr_e( 'Ticket views', 'support-ticket-system-for-woocommerce' ); ?>">
					<?php
					/**
					 * Top of the left column (Pro: new ticket button).
					 */
					do_action( 'wpfactory_wc_sts_inbox_rail' );
					?>
					<p class="hd-rail__label" id="hd-rail-views"><?php esc_html_e( 'Views', 'support-ticket-system-for-woocommerce' ); ?></p>
					<ul class="hd-rail__list" aria-labelledby="hd-rail-views">
						<?php foreach ( $views as $key => $item ) : ?>
							<li>
								<a class="hd-rail__item" href="<?php echo esc_url( $this->url( array( 'view' => $key ) ) ); ?>"<?php echo $key === $view && ! $prio ? ' aria-current="page"' : ''; ?>>
									<?php WPFactory_WC_STS_UI::e_icon( $this->view_icon( $key ), 16 ); ?>
									<span class="hd-rail__text"><?php echo esc_html( $item['label'] ); ?></span>
									<span class="hd-count stsw-count"><?php echo (int) $this->count( $key ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php
					/**
					 * Below the views (saved views).
					 */
					do_action( 'wpfactory_wc_sts_inbox_rail_after_views' );
					$priorities = WPFactory_WC_STS_UI::ordered_terms( 'stsw_tickets_priority' );
					?>
					<?php if ( $priorities ) : ?>
						<p class="hd-rail__label" id="hd-rail-prio"><?php esc_html_e( 'Priority', 'support-ticket-system-for-woocommerce' ); ?></p>
						<ul class="hd-rail__list" aria-labelledby="hd-rail-prio">
							<?php
							foreach ( $priorities as $term ) :
								$color = sanitize_hex_color( (string) get_term_meta( $term->term_id, WPFactory_WC_STS_Inc::META_COLOR, true ) );
								?>
								<li>
									<a class="hd-rail__item" href="
									<?php
									echo esc_url(
										$this->url(
											array(
												'view' => 'all',
												'prio' => $term->slug,
											)
										)
									);
									?>
																	"<?php echo $prio === $term->slug ? ' aria-current="page"' : ''; ?>>
										<span class="hd-dot hd-prio--<?php echo esc_attr( $term->slug ); ?>"<?php echo $color ? ' style="--hd-prio-dot:' . esc_attr( $color ) . '"' : ''; ?> aria-hidden="true"></span>
										<span class="hd-rail__text"><?php echo esc_html( $term->name ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="hd-rail__foot"><button type="button" class="hd-linkbtn stsw-shortcuts-open" aria-haspopup="dialog"><?php WPFactory_WC_STS_UI::e_icon( 'keyboard', 16 ); ?> <?php esc_html_e( 'Keyboard shortcuts', 'support-ticket-system-for-woocommerce' ); ?> <kbd>?</kbd></button></p>
				</nav>

				<section class="stsw-inbox__list hd-list" aria-labelledby="hd-list-title">
					<?php
					$this->render_list( $list, $keep, $ticket_id, $views[ $view ]['label'], $order_id, $view );
					?>
				</section>

				<section class="stsw-inbox__detail hd-detail" aria-label="<?php esc_attr_e( 'Ticket', 'support-ticket-system-for-woocommerce' ); ?>">
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
						echo '<div class="hd-blank"><span class="hd-emptystate__icon">' . wp_kses( WPFactory_WC_STS_UI::icon( 'chat', 22 ), WPFactory_WC_STS_UI::svg_kses() ) . '</span><p class="hd-muted">' . esc_html__( 'Select a ticket to read and answer it.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
					}
					?>
				</section>
			</div>
			<div class="hd-toast-region" aria-live="polite" role="status"></div>
			<?php
			echo '</div>';
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
				'deleted' => __( 'Selected tickets moved to the trash.', 'support-ticket-system-for-woocommerce' ),
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
				WPFactory_WC_STS_UI::notice( $texts[ $message ], $error );
			}
		}

		/**
		 * Last message of a ticket (customer or store), for the row preview.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string Plain text.
		 */
		protected function get_preview( $ticket_id ) {
			global $wpdb;
			$table = $wpdb->prefix . $this->core()->table_name;
			$last  = $wpdb->get_var( $wpdb->prepare( 'SELECT content FROM %i WHERE post_id = %d ORDER BY creationdate DESC, id DESC LIMIT 1', $table, $ticket_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$text  = null !== $last ? (string) $last : (string) get_post_field( 'post_content', $ticket_id, 'raw' );
			return wp_html_excerpt( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) ), 110, '...' );
		}

		/**
		 * Ticket list with checkboxes and the bulk bar.
		 *
		 * @param WP_Query $list      Tickets.
		 * @param array    $keep      Current query args.
		 * @param int      $ticket_id Selected ticket.
		 * @param string   $label     View label.
		 * @param int      $order_id  Order filter.
		 * @param string   $view      View key.
		 */
		protected function render_list( $list, $keep, $ticket_id, $label = '', $order_id = 0, $view = '' ) {
			$core = $this->core();
			?>
			<div class="hd-list__head">
				<div class="hd-list__title">
					<h2 class="hd-h2" id="hd-list-title"><?php echo esc_html( $label ); ?> <span class="hd-muted">(<?php echo (int) $list->found_posts; ?>)</span></h2>
					<?php
					/**
					 * Tools above the list (saved views, CSV export).
					 *
					 * @param array  $keep Current filters.
					 * @param string $view View key.
					 */
					do_action( 'wpfactory_wc_sts_inbox_list_head', $keep, $view );
					?>
				</div>
				<?php if ( ! empty( $keep['s'] ) ) : ?>
					<p class="hd-filter">
						<?php
						/* translators: %s: search words. */
						echo esc_html( sprintf( __( 'Results for "%s"', 'support-ticket-system-for-woocommerce' ), $keep['s'] ) );
						?>
						<a href="<?php echo esc_url( $this->url( array( 'view' => $view ) ) ); ?>"><?php esc_html_e( 'Clear', 'support-ticket-system-for-woocommerce' ); ?></a>
					</p>
				<?php endif; ?>
				<?php if ( $order_id ) : ?>
					<p class="hd-filter stsw-filter">
						<?php
						/* translators: %d: order ID. */
						echo esc_html( sprintf( __( 'Tickets of order %d', 'support-ticket-system-for-woocommerce' ), $order_id ) );
						?>
						<a href="<?php echo esc_url( $this->url( array( 'view' => $view ) ) ); ?>"><?php esc_html_e( 'Show all', 'support-ticket-system-for-woocommerce' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
			<?php
			if ( ! $list->have_posts() ) {
				echo '<div class="hd-blank stsw-inbox__empty"><span class="hd-emptystate__icon is-success">' . wp_kses( WPFactory_WC_STS_UI::icon( 'check', 22 ), WPFactory_WC_STS_UI::svg_kses() ) . '</span><p><strong>' . esc_html__( 'Nothing here', 'support-ticket-system-for-woocommerce' ) . '</strong></p><p class="hd-muted">' . esc_html__( 'No tickets in this view.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
				return;
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stsw-bulk">
				<input type="hidden" name="action" value="wpfactory_wc_sts_inbox" />
				<input type="hidden" name="stsw_action" value="bulk" />
				<input type="hidden" name="stsw_back" value="<?php echo esc_attr( wp_json_encode( $keep ) ); ?>" />
				<?php wp_nonce_field( self::NONCE ); ?>
				<div class="hd-bulkbar stsw-bulk__bar">
					<label class="hd-check"><input type="checkbox" class="stsw-check-all" /> <span class="screen-reader-text"><?php esc_html_e( 'Select all', 'support-ticket-system-for-woocommerce' ); ?></span></label>
					<span class="hd-bulkbar__count" aria-live="polite" data-hd-selected><?php esc_html_e( 'Select tickets', 'support-ticket-system-for-woocommerce' ); ?></span>
					<span class="hd-bulkbar__actions">
						<label class="screen-reader-text" for="stsw-bulk-assign"><?php esc_html_e( 'Assign to', 'support-ticket-system-for-woocommerce' ); ?></label>
						<select id="stsw-bulk-assign" name="stsw_bulk_assign" class="hd-pill">
							<option value=""><?php esc_html_e( 'Assign', 'support-ticket-system-for-woocommerce' ); ?></option>
							<option value="0"><?php esc_html_e( 'Nobody', 'support-ticket-system-for-woocommerce' ); ?></option>
							<?php foreach ( WPFactory_WC_STS_Admin::get_instance()->get_assignable_users() as $user ) : ?>
								<option value="<?php echo (int) $user->ID; ?>"><?php echo esc_html( $core->get_user_name( $user->ID ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="hd-btn hd-btn--sm" name="stsw_bulk" value="assign"><?php esc_html_e( 'Assign', 'support-ticket-system-for-woocommerce' ); ?></button>
						<button type="submit" class="hd-btn hd-btn--sm" name="stsw_bulk" value="status:closed"><?php esc_html_e( 'Close', 'support-ticket-system-for-woocommerce' ); ?></button>
						<button type="submit" class="hd-btn hd-btn--sm hd-btn--danger-ghost" name="stsw_bulk" value="delete:1"><?php esc_html_e( 'Delete', 'support-ticket-system-for-woocommerce' ); ?></button>
					</span>
				</div>
				<ul class="stsw-rows hd-rows">
					<?php
					foreach ( $list->posts as $post ) :
						$id       = (int) $post->ID;
						$contact  = $core->get_ticket_contact( $id );
						$activity = $core->get_activity( $id );
						$priority = $core->get_ticket_term( $id, 'stsw_tickets_priority' );
						$waiting  = 'customer' === $activity['by'] && 'closed' !== $core->get_ticket_status_slug( $id );
						$classes  = 'stsw-row hd-row-t' . ( $id === $ticket_id ? ' is-current' : '' ) . ( $waiting ? ' is-waiting' : '' );
						?>
						<li class="<?php echo esc_attr( $classes ); ?>">
							<input type="checkbox" class="hd-row-t__check" name="stsw_ids[]" value="<?php echo (int) $id; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: ticket number. */ __( 'Select ticket #%d', 'support-ticket-system-for-woocommerce' ), $id ) ); ?>" />
							<a class="stsw-row__link hd-row-t__link" href="<?php echo esc_url( $this->url( array_merge( $keep, array( 'ticket' => $id ) ) ) ); ?>"<?php echo $id === $ticket_id ? ' aria-current="true"' : ''; ?>>
								<?php echo WPFactory_WC_STS_UI::avatar( $contact['display_name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar(). ?>
								<span class="hd-row-t__main">
									<span class="hd-row-t__top">
										<span class="hd-row-t__name stsw-row__customer"><?php echo esc_html( $contact['display_name'] ); ?></span>
										<?php if ( $waiting ) : ?>
											<span class="hd-unread" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( '(needs reply)', 'support-ticket-system-for-woocommerce' ); ?></span>
										<?php endif; ?>
										<span class="hd-row-t__time stsw-row__age"><?php echo esc_html( $activity['at'] ? human_time_diff( strtotime( $activity['at'] . ' UTC' ) ) : '' ); ?></span>
									</span>
									<span class="hd-row-t__subject stsw-row__subject"><span class="hd-muted">#<?php echo (int) $id; ?></span> <?php echo esc_html( get_the_title( $id ) ); ?></span>
									<span class="hd-row-t__preview"><?php echo esc_html( $this->get_preview( $id ) ); ?></span>
									<span class="hd-row-t__meta stsw-row__meta">
										<?php echo WPFactory_WC_STS_UI::priority_chip( $priority ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in priority_chip(). ?>
										<?php
										$row_tags = wp_get_object_terms( $id, 'stsw_tickets_tag', array( 'fields' => 'names' ) );
										foreach ( is_wp_error( $row_tags ) ? array() : array_slice( $row_tags, 0, 3 ) as $row_tag ) {
											echo '<span class="hd-tag">' . esc_html( $row_tag ) . '</span>';
										}
										?>
										<?php
										/**
										 * Extra badges on a list row (Pro: due time).
										 *
										 * @param int $id Ticket ID.
										 */
										do_action( 'wpfactory_wc_sts_inbox_row_meta', $id );
										?>
									</span>
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
				echo '<nav class="hd-pages stsw-pages" aria-label="' . esc_attr__( 'Pages', 'support-ticket-system-for-woocommerce' ) . '">';
				if ( $current > 1 ) {
					echo '<a class="hd-btn hd-btn--sm" href="' . esc_url( $this->url( array_merge( $keep, array( 'paged' => $current - 1 ) ) ) ) . '">' . esc_html__( 'Newer', 'support-ticket-system-for-woocommerce' ) . '</a> ';
				}
				/* translators: 1: current page, 2: number of pages. */
				echo '<span class="hd-muted">' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'support-ticket-system-for-woocommerce' ), $current, $pages ) ) . '</span>';
				if ( $current < $pages ) {
					echo ' <a class="hd-btn hd-btn--sm" href="' . esc_url( $this->url( array_merge( $keep, array( 'paged' => $current + 1 ) ) ) ) . '">' . esc_html__( 'Older', 'support-ticket-system-for-woocommerce' ) . '</a>';
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
			return WPFactory_WC_STS_UI::priority_chip( $term );
		}

		/**
		 * Terms of a ticket taxonomy, in the merchant's order.
		 *
		 * @param string $taxonomy Taxonomy.
		 *
		 * @return WP_Term[]
		 */
		protected function get_terms( $taxonomy ) {
			return WPFactory_WC_STS_UI::ordered_terms( $taxonomy );
		}

		/**
		 * Another open ticket of the same customer with the identical subject.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return int Ticket ID or 0.
		 */
		public function find_duplicate( $ticket_id ) {
			$core = $this->core();
			if ( 'closed' === $core->get_ticket_status_slug( $ticket_id ) ) {
				return 0;
			}
			$user_id = $core->get_ticket_user_id( $ticket_id );
			$email   = (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_GUEST_EMAIL, true );
			if ( ! $user_id && '' === $email ) {
				return 0;
			}
			$ids   = get_posts(
				array(
					'post_type'      => 'stsw_tickets',
					'post_status'    => 'publish',
					'posts_per_page' => 10,
					'fields'         => 'ids',
					'post__not_in'   => array( $ticket_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
					'meta_key'       => $user_id ? WPFactory_WC_STS_Inc::META_USER : WPFactory_WC_STS_Inc::META_GUEST_EMAIL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $user_id ? $user_id : $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'stsw_tickets_status',
							'field'    => 'slug',
							'terms'    => array( 'closed' ),
							'operator' => 'NOT IN',
						),
					),
				)
			);
			$title = strtolower( trim( get_the_title( $ticket_id ) ) );
			foreach ( $ids as $id ) {
				if ( strtolower( trim( get_the_title( $id ) ) ) === $title && ! get_post_meta( $id, '_stsw_merged_into', true ) ) {
					return (int) $id;
				}
			}
			return 0;
		}

		/**
		 * The selected ticket: header with auto-saving status, priority and assignee; thread; composer; customer panel.
		 *
		 * @param int   $ticket_id Ticket ID.
		 * @param array $keep      Current query args.
		 */
		protected function render_ticket( $ticket_id, $keep ) {
			$core      = $this->core();
			$status    = $core->get_ticket_status_slug( $ticket_id );
			$priority  = $core->get_ticket_term( $ticket_id, 'stsw_tickets_priority' );
			$back      = wp_json_encode( array_merge( $keep, array( 'ticket' => $ticket_id ) ) );
			$opened    = strtotime( get_post_field( 'post_date_gmt', $ticket_id ) . ' UTC' );
			$duplicate = $this->find_duplicate( $ticket_id );
			ob_start();
			/**
			 * Tools for the selected ticket, shown in the More menu (Pro: merge, AI summary).
			 *
			 * @param int    $ticket_id Ticket ID.
			 * @param string $back      JSON of the inbox query args to return to.
			 */
			do_action( 'wpfactory_wc_sts_inbox_ticket_tools', $ticket_id, $back );
			$tools = trim( (string) ob_get_clean() );
			?>
			<div class="hd-ticket">
				<header class="stsw-ticket__head hd-ticket__head">
					<div class="hd-ticket__titles">
						<a class="hd-back hd-only-narrow" href="<?php echo esc_url( $this->url( $keep ) ); ?>"><?php WPFactory_WC_STS_UI::e_icon( 'back', 16 ); ?> <?php esc_html_e( 'All tickets', 'support-ticket-system-for-woocommerce' ); ?></a>
						<p class="hd-muted hd-ticket__kicker">
							<?php
							/* translators: 1: ticket number, 2: time ago. */
							echo esc_html( sprintf( __( 'Ticket #%1$d · opened %2$s ago', 'support-ticket-system-for-woocommerce' ), $ticket_id, human_time_diff( $opened ) ) );
							?>
						</p>
						<h2 class="hd-h1 stsw-ticket__title" id="hd-ticket-title"><?php echo esc_html( get_the_title( $ticket_id ) ); ?></h2>
					</div>
					<?php if ( '' !== $tools ) : ?>
						<details class="hd-menu">
							<summary class="hd-icon-btn" aria-label="<?php esc_attr_e( 'More actions', 'support-ticket-system-for-woocommerce' ); ?>"><?php WPFactory_WC_STS_UI::e_icon( 'more', 18 ); ?></summary>
							<div class="hd-menu__panel"><?php echo $tools; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the callbacks. ?></div>
						</details>
					<?php endif; ?>
				</header>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stsw-ticket__controls hd-controls" id="stsw-controls" data-hd-autosave data-ticket="<?php echo (int) $ticket_id; ?>">
					<input type="hidden" name="action" value="wpfactory_wc_sts_inbox" />
					<input type="hidden" name="stsw_action" value="update" />
					<input type="hidden" name="ticket" value="<?php echo (int) $ticket_id; ?>" />
					<input type="hidden" name="stsw_back" value="<?php echo esc_attr( $back ); ?>" />
					<?php wp_nonce_field( self::NONCE ); ?>
					<label class="hd-pillfield"><span><?php esc_html_e( 'Status', 'support-ticket-system-for-woocommerce' ); ?></span>
						<select name="stsw_status" id="stsw-status" class="hd-pill">
							<?php foreach ( $this->get_terms( 'stsw_tickets_status' ) as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $status, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="hd-pillfield"><span><?php esc_html_e( 'Priority', 'support-ticket-system-for-woocommerce' ); ?></span>
						<select name="stsw_priority" class="hd-pill">
							<?php foreach ( $this->get_terms( 'stsw_tickets_priority' ) as $term ) : ?>
								<option value="<?php echo (int) $term->term_id; ?>" <?php selected( $priority ? $priority->term_id : 0, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="hd-pillfield"><span><?php esc_html_e( 'Assignee', 'support-ticket-system-for-woocommerce' ); ?></span>
						<?php WPFactory_WC_STS_Admin::get_instance()->render_user_select( 'stsw_agent', $core->get_ticket_agent_id( $ticket_id ), __( 'Nobody', 'support-ticket-system-for-woocommerce' ), 'stsw-agent' ); ?>
					</label>
					<?php $tags = wp_get_object_terms( $ticket_id, 'stsw_tickets_tag', array( 'fields' => 'names' ) ); ?>
					<label class="hd-pillfield"><span><?php esc_html_e( 'Tags', 'support-ticket-system-for-woocommerce' ); ?></span>
						<input type="text" name="stsw_tags" class="hd-pill hd-tags-input" value="<?php echo esc_attr( is_wp_error( $tags ) ? '' : implode( ', ', $tags ) ); ?>" placeholder="<?php esc_attr_e( 'vip, refund', 'support-ticket-system-for-woocommerce' ); ?>" aria-describedby="hd-tags-help" />
					</label>
					<span class="screen-reader-text" id="hd-tags-help"><?php esc_html_e( 'Separate tags with commas.', 'support-ticket-system-for-woocommerce' ); ?></span>
					<span class="hd-help hd-js-only"><?php esc_html_e( 'Changes save automatically', 'support-ticket-system-for-woocommerce' ); ?></span>
					<button type="submit" class="hd-btn hd-btn--sm hd-nojs"><?php esc_html_e( 'Save', 'support-ticket-system-for-woocommerce' ); ?></button>
				</form>
				<?php
				/**
				 * Above the thread (collision detection).
				 *
				 * @param int $ticket_id Ticket ID.
				 */
				do_action( 'wpfactory_wc_sts_inbox_ticket_top', $ticket_id );
				?>
				<?php if ( $duplicate ) : ?>
					<div class="hd-banner hd-banner--warn" role="note">
						<?php WPFactory_WC_STS_UI::e_icon( 'warn', 18 ); ?>
						<span>
							<?php
							/* translators: %d: ticket number. */
							echo esc_html( sprintf( __( 'Possible duplicate: this customer has another open ticket with the same subject (#%d).', 'support-ticket-system-for-woocommerce' ), $duplicate ) );
							?>
						</span>
						<span class="hd-banner__actions">
							<a href="<?php echo esc_url( $this->url( array_merge( $keep, array( 'ticket' => $duplicate ) ) ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: ticket number. */ __( 'Open #%d', 'support-ticket-system-for-woocommerce' ), $duplicate ) ); ?></a>
							<?php
							/**
							 * Actions on the duplicate banner (Pro: Merge tickets).
							 *
							 * @param int $ticket_id Ticket ID.
							 * @param int $duplicate The other ticket.
							 */
							do_action( 'wpfactory_wc_sts_inbox_duplicate', $ticket_id, $duplicate );
							?>
						</span>
					</div>
				<?php endif; ?>
				<div class="stsw-ticket__body hd-ticket__body">
					<div class="stsw-ticket__main hd-ticket__main">
						<?php $this->render_thread( $ticket_id ); ?>
						<?php $this->render_reply_box( $ticket_id, $back ); ?>
					</div>
					<aside class="stsw-ticket__side hd-side" aria-label="<?php esc_attr_e( 'Customer and order', 'support-ticket-system-for-woocommerce' ); ?>">
						<?php $this->render_side( $ticket_id ); ?>
					</aside>
				</div>
			</div>
			<?php
		}

		/**
		 * One message of the thread.
		 *
		 * @param string $type   customer, store or note.
		 * @param string $who    Author.
		 * @param string $when   Formatted date.
		 * @param string $body   Message HTML (escaped).
		 * @param int    $row_id Row ID (0: the ticket itself).
		 * @param string $extra  Extra HTML after the body (escaped).
		 */
		protected function render_message( $type, $who, $when, $body, $row_id = 0, $extra = '' ) {
			$tags = array(
				'store' => __( 'Agent', 'support-ticket-system-for-woocommerce' ),
				'note'  => __( 'Private note', 'support-ticket-system-for-woocommerce' ),
			);
			echo '<li class="stsw-msg stsw-msg--' . esc_attr( $type ) . ' hd-msg hd-msg--' . esc_attr( $type ) . '"' . ( $row_id ? ' id="stsw-' . esc_attr( $type . '-' . $row_id ) . '"' : '' ) . '>';
			echo wp_kses(
				WPFactory_WC_STS_UI::avatar( $who, 'sm' ),
				array(
					'span' => array(
						'class'       => true,
						'aria-hidden' => true,
					),
				)
			);
			echo '<div class="hd-msg__bubble"><p class="stsw-msg__head hd-msg__head"><strong>' . esc_html( $who ) . '</strong>';
			if ( isset( $tags[ $type ] ) ) {
				echo ' <span class="hd-tag hd-tag--' . esc_attr( $type ) . '">' . ( 'note' === $type ? wp_kses( WPFactory_WC_STS_UI::icon( 'lock', 12 ), WPFactory_WC_STS_UI::svg_kses() ) . ' ' : '' ) . esc_html( $tags[ $type ] ) . '</span>';
			}
			echo ' <time class="hd-muted">' . esc_html( $when ) . '</time>';
			if ( $row_id ) {
				echo ' <button type="button" class="hd-msg__delete wpfactory-wc-sts-delete" data-type="' . ( 'note' === $type ? 'note' : 'response' ) . '" data-id="' . (int) $row_id . '">' . esc_html__( 'Delete', 'support-ticket-system-for-woocommerce' ) . '</button>';
			}
			echo '</p><div class="stsw-msg__body hd-msg__body">' . $body . '</div>' . $extra . '</div></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller.
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
			echo '<ol class="stsw-thread hd-thread" aria-labelledby="hd-ticket-title">';
			$started = (int) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_STARTED_BY, true );
			$extra   = '';
			if ( $details ) {
				$extra .= '<dl class="stsw-details hd-details-list">';
				foreach ( $details as $label => $value ) {
					$extra .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
				}
				$extra .= '</dl>';
			}
			$ticket_files = (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FILES, true );
			if ( '' !== $ticket_files && $files ) {
				ob_start();
				foreach ( explode( ',', $ticket_files ) as $url ) {
					$files->print_ticket_file_link( $url );
				}
				$extra .= '<div class="hd-files">' . ob_get_clean() . '</div>';
			}
			$this->render_message(
				$started ? 'store' : 'customer',
				$started ? $core->get_user_name( $started ) : $contact['display_name'],
				$core->format_response_date( get_post_field( 'post_date_gmt', $ticket_id ) ),
				$core->format_message( get_post_field( 'post_content', $ticket_id, 'raw' ) ),
				0,
				$extra
			);
			foreach ( $items as $item ) {
				$row = $item['row'];
				if ( 'note' === $item['type'] ) {
					$who = (int) $row->agent ? $core->get_user_name( (int) $row->agent ) : __( 'Automatic note', 'support-ticket-system-for-woocommerce' );
				} elseif ( 'store' === $item['type'] ) {
					$who = $core->get_user_name( (int) $row->agent );
				} else {
					$who = $contact['display_name'];
				}
				$extra = '';
				if ( 'note' !== $item['type'] && ! empty( $row->attachments ) && $files ) {
					ob_start();
					foreach ( explode( ',', (string) $row->attachments ) as $attachment_id ) {
						$files->print_attachment_link( (int) $attachment_id );
					}
					$extra = '<div class="hd-files">' . ob_get_clean() . '</div>';
				}
				$this->render_message( $item['type'], $who, $core->format_response_date( $row->creationdate ), $core->format_message( $row->content ), (int) $row->id, $extra );
			}
			echo '</ol>';
		}

		/**
		 * Composer: reply or private note tabs, saved replies, files, Ctrl+Enter to send. Drafts stay on this computer.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $back      JSON of the inbox query args.
		 */
		protected function render_reply_box( $ticket_id, $back ) {
			$core = $this->core();
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="stsw-replybox hd-composer" data-ticket="<?php echo (int) $ticket_id; ?>">
				<input type="hidden" name="action" value="wpfactory_wc_sts_inbox" />
				<input type="hidden" name="stsw_action" value="reply" />
				<input type="hidden" name="ticket" value="<?php echo (int) $ticket_id; ?>" />
				<input type="hidden" name="stsw_back" value="<?php echo esc_attr( $back ); ?>" />
				<?php wp_nonce_field( self::NONCE ); ?>
				<fieldset class="stsw-replybox__mode hd-composer__tabs">
					<legend class="screen-reader-text"><?php esc_html_e( 'Message type', 'support-ticket-system-for-woocommerce' ); ?></legend>
					<label class="hd-composer__tab"><input type="radio" name="stsw_mode" value="reply" checked /> <span><?php esc_html_e( 'Reply to customer', 'support-ticket-system-for-woocommerce' ); ?></span></label>
					<label class="hd-composer__tab hd-composer__tab--note"><input type="radio" name="stsw_mode" value="note" /> <span><?php WPFactory_WC_STS_UI::e_icon( 'lock', 14 ); ?> <?php esc_html_e( 'Private note', 'support-ticket-system-for-woocommerce' ); ?></span></label>
				</fieldset>
				<label for="stsw-inbox-reply" class="stsw-replybox__label screen-reader-text"><?php esc_html_e( 'Reply: the customer will see it', 'support-ticket-system-for-woocommerce' ); ?></label>
				<textarea id="stsw-inbox-reply" name="stsw_content" rows="6" placeholder="<?php esc_attr_e( 'Write your reply...', 'support-ticket-system-for-woocommerce' ); ?>" aria-describedby="hd-composer-hint"></textarea>
				<div class="hd-composer__bar">
					<div class="stsw-replybox__tools hd-composer__tools">
						<?php
						if ( class_exists( 'WPFactory_WC_STS_Canned' ) ) {
							WPFactory_WC_STS_Canned::get_instance()->render_select( 'stsw-inbox-reply', $ticket_id );
						}
						if ( $core->attachments_enabled() ) {
							echo '<label class="hd-btn hd-btn--sm hd-btn--ghost hd-attach"><span>' . wp_kses( WPFactory_WC_STS_UI::icon( 'clip', 14 ), WPFactory_WC_STS_UI::svg_kses() ) . ' ' . esc_html__( 'Attach', 'support-ticket-system-for-woocommerce' ) . '</span><input type="file" id="stsw-inbox-files" name="my_file_upload[]" multiple class="hd-attach__input" /></label><span class="hd-help hd-attach__names" aria-live="polite"></span>';
						}
						/**
						 * Composer tools (Pro: AI draft).
						 *
						 * @param int $ticket_id Ticket ID.
						 */
						do_action( 'wpfactory_wc_sts_inbox_reply_tools', $ticket_id );
						?>
					</div>
					<div class="stsw-replybox__actions hd-composer__send">
						<span class="hd-help" id="hd-composer-hint"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> <?php esc_html_e( 'to send', 'support-ticket-system-for-woocommerce' ); ?></span>
						<button type="submit" class="hd-btn hd-btn--sm stsw-reply-close" name="stsw_close" value="1"><?php esc_html_e( 'Send and close', 'support-ticket-system-for-woocommerce' ); ?></button>
						<button type="submit" class="hd-btn hd-btn--primary hd-composer__submit" name="stsw_close" value="0" data-reply="<?php esc_attr_e( 'Send reply', 'support-ticket-system-for-woocommerce' ); ?>" data-note="<?php esc_attr_e( 'Add note', 'support-ticket-system-for-woocommerce' ); ?>"><?php esc_html_e( 'Send reply', 'support-ticket-system-for-woocommerce' ); ?></button>
					</div>
				</div>
				<p class="stsw-replybox__draft hd-help" aria-live="polite"></p>
			</form>
			<?php
		}

		/**
		 * The customer's orders (account, or billing email for guests), newest first.
		 *
		 * @param int    $user_id User ID.
		 * @param string $email   Email.
		 * @param int    $limit   Maximum.
		 *
		 * @return WC_Order[]
		 */
		public function get_customer_orders( $user_id, $email, $limit = 50 ) {
			if ( ! function_exists( 'wc_get_orders' ) || ( ! $user_id && ! is_email( $email ) ) ) {
				return array();
			}
			$args = array(
				'limit'   => $limit,
				'orderby' => 'date',
				'order'   => 'DESC',
				'type'    => 'shop_order',
			);
			if ( $user_id ) {
				$args['customer_id'] = $user_id;
			} else {
				$args['billing_email'] = $email;
			}
			return (array) wc_get_orders( $args );
		}

		/**
		 * Right column: customer card with value tiles, recent orders and other tickets. Pro adds the order panel below.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		protected function render_side( $ticket_id ) {
			$core    = $this->core();
			$contact = $core->get_ticket_contact( $ticket_id );
			$user_id = $core->get_ticket_user_id( $ticket_id );
			$orders  = $this->get_customer_orders( $user_id, $contact['email'] );
			$spent   = 0.0;
			foreach ( $orders as $o ) {
				if ( $o->is_paid() ) {
					$spent += (float) $o->get_total() - (float) $o->get_total_refunded();
				}
			}
			$currency = $orders ? $orders[0]->get_currency() : get_woocommerce_currency();
			$user     = $user_id ? get_userdata( $user_id ) : false;
			?>
			<section class="hd-side__card" aria-labelledby="hd-side-customer">
				<div class="hd-side__who">
					<?php echo WPFactory_WC_STS_UI::avatar( $contact['display_name'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar(). ?>
					<div>
						<h3 class="hd-h2" id="hd-side-customer"><?php echo esc_html( $contact['display_name'] ); ?></h3>
						<?php if ( $contact['email'] ) : ?>
							<a href="mailto:<?php echo esc_attr( $contact['email'] ); ?>" class="hd-side__email"><?php echo esc_html( $contact['email'] ); ?></a>
						<?php endif; ?>
						<?php if ( ! $user_id ) : ?>
							<span class="hd-tag"><?php esc_html_e( 'Guest', 'support-ticket-system-for-woocommerce' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
				<div class="hd-tiles">
					<div class="hd-tile"><span class="hd-help"><?php esc_html_e( 'Lifetime value', 'support-ticket-system-for-woocommerce' ); ?></span><strong><?php echo esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $spent, array( 'currency' => $currency ) ) ), ENT_QUOTES, 'UTF-8' ) ); ?></strong></div>
					<div class="hd-tile"><span class="hd-help"><?php esc_html_e( 'Orders', 'support-ticket-system-for-woocommerce' ); ?></span><strong><?php echo (int) count( $orders ); ?></strong></div>
				</div>
				<?php if ( $user ) : ?>
					<p class="hd-help"><?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'Customer since %s', 'support-ticket-system-for-woocommerce' ), date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ) ); ?></p>
				<?php endif; ?>
			</section>
			<?php if ( $orders ) : ?>
				<section class="hd-side__card" aria-labelledby="hd-side-orders">
					<h3 class="hd-side__title" id="hd-side-orders"><?php esc_html_e( 'Recent orders', 'support-ticket-system-for-woocommerce' ); ?></h3>
					<ul class="hd-side__list">
						<?php foreach ( array_slice( $orders, 0, 5 ) as $o ) : ?>
							<li>
								<a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a>
								<span class="hd-help"><?php echo esc_html( wc_format_datetime( $o->get_date_created() ) ); ?> · <?php echo esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $o->get_total(), array( 'currency' => $o->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ) ); ?></span>
								<span class="hd-status hd-status--<?php echo esc_attr( $o->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
			<?php
			$others = get_posts(
				array(
					'post_type'      => 'stsw_tickets',
					'post_status'    => 'publish',
					'posts_per_page' => 5,
					'post__not_in'   => array( $ticket_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
					'meta_key'       => $user_id ? WPFactory_WC_STS_Inc::META_USER : WPFactory_WC_STS_Inc::META_GUEST_EMAIL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $user_id ? $user_id : ( $contact['email'] ? $contact['email'] : '-' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			if ( $others ) {
				echo '<section class="hd-side__card" aria-labelledby="hd-side-tickets"><h3 class="hd-side__title" id="hd-side-tickets">' . esc_html__( 'Other tickets', 'support-ticket-system-for-woocommerce' ) . '</h3><ul class="hd-side__list">';
				foreach ( $others as $other ) {
					$st = $core->get_ticket_term( $other->ID, 'stsw_tickets_status' );
					echo '<li><a href="' . esc_url( $this->url( array( 'ticket' => $other->ID ) ) ) . '">#' . (int) $other->ID . ' ' . esc_html( get_the_title( $other ) ) . '</a>' . ( $st ? ' <span class="hd-status">' . esc_html( $st->name ) . '</span>' : '' ) . '</li>';
				}
				echo '</ul></section>';
			}
			/**
			 * Below the customer card (Pro: the linked order and order actions).
			 *
			 * @param int $ticket_id Ticket ID.
			 */
			do_action( 'wpfactory_wc_sts_inbox_side', $ticket_id );
		}

		/**
		 * Keyboard shortcuts help.
		 */
		protected function render_shortcuts_dialog() {
			$keys = array(
				'j'            => __( 'Next ticket', 'support-ticket-system-for-woocommerce' ),
				'k'            => __( 'Previous ticket', 'support-ticket-system-for-woocommerce' ),
				'r'            => __( 'Reply', 'support-ticket-system-for-woocommerce' ),
				'n'            => __( 'Private note', 'support-ticket-system-for-woocommerce' ),
				'c'            => __( 'Close the ticket', 'support-ticket-system-for-woocommerce' ),
				'/'            => __( 'Search tickets', 'support-ticket-system-for-woocommerce' ),
				'Ctrl + Enter' => __( 'Send the reply or note', 'support-ticket-system-for-woocommerce' ),
				'?'            => __( 'Show or hide this help', 'support-ticket-system-for-woocommerce' ),
			);
			echo '<dialog class="stsw-shortcuts hd-dialog wpf-hd" aria-labelledby="stsw-shortcuts-title"><h2 class="hd-h2" id="stsw-shortcuts-title">' . esc_html__( 'Keyboard shortcuts', 'support-ticket-system-for-woocommerce' ) . '</h2><dl class="hd-keys">';
			foreach ( $keys as $key => $label ) {
				echo '<dt><kbd>' . esc_html( $key ) . '</kbd></dt><dd>' . esc_html( $label ) . '</dd>';
			}
			echo '</dl><p class="hd-help">' . esc_html__( 'Shortcuts do nothing while you type in a field.', 'support-ticket-system-for-woocommerce' ) . '</p><form method="dialog"><button class="hd-btn">' . esc_html__( 'Close', 'support-ticket-system-for-woocommerce' ) . '</button></form></dialog>';
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
					if ( isset( $_POST['stsw_tags'] ) ) {
						$this->set_tags( $ticket_id, sanitize_text_field( wp_unslash( $_POST['stsw_tags'] ) ) );
					}
					$message = 'updated';
					break;

				case 'bulk':
					$ids  = array_filter( array_map( 'absint', (array) wp_unslash( $_POST['stsw_ids'] ?? array() ) ) );
					$bulk = explode( ':', sanitize_text_field( wp_unslash( $_POST['stsw_bulk'] ?? '' ) ), 2 );
					if ( 'assign' === $bulk[0] ) {
						$assign = sanitize_text_field( wp_unslash( $_POST['stsw_bulk_assign'] ?? '' ) );
						$bulk   = '' === $assign ? array() : array( 'assign', $assign );
					}
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
						} elseif ( 'delete' === $bulk[0] && current_user_can( 'delete_post', $id ) ) {
							wp_trash_post( $id );
						}
					}
					$message = 'delete' === $bulk[0] ? 'deleted' : 'bulk';
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
		 * Auto-save of one header field (status, priority or assignee).
		 */
		public function ajax_update() {
			check_ajax_referer( self::NONCE );
			$ticket_id = absint( $_POST['ticket'] ?? 0 );
			$field     = sanitize_key( wp_unslash( $_POST['field'] ?? '' ) );
			$value     = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
			if ( ! $this->core()->can_manage_tickets() || 'stsw_tickets' !== get_post_type( $ticket_id ) || ! current_user_can( 'edit_post', $ticket_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ) ), 403 );
			}
			switch ( $field ) {
				case 'stsw_status':
					if ( ! get_term_by( 'slug', $value, 'stsw_tickets_status' ) ) {
						wp_send_json_error( array( 'message' => __( 'That did not work. Please try again.', 'support-ticket-system-for-woocommerce' ) ), 400 );
					}
					$this->update_ticket( $ticket_id, sanitize_key( $value ), 0, null );
					$text = __( 'Status saved', 'support-ticket-system-for-woocommerce' );
					break;
				case 'stsw_priority':
					$this->update_ticket( $ticket_id, '', absint( $value ), null );
					$text = __( 'Priority saved', 'support-ticket-system-for-woocommerce' );
					break;
				case 'stsw_agent':
					$this->update_ticket( $ticket_id, '', 0, absint( $value ) );
					$text = __( 'Assignee saved', 'support-ticket-system-for-woocommerce' );
					break;
				case 'stsw_tags':
					$this->set_tags( $ticket_id, $value );
					$text = __( 'Tags saved', 'support-ticket-system-for-woocommerce' );
					break;
				default:
					wp_send_json_error( array( 'message' => __( 'That did not work. Please try again.', 'support-ticket-system-for-woocommerce' ) ), 400 );
			}
			wp_send_json_success( array( 'message' => $text ) );
		}

		/**
		 * Replaces the tags of a ticket.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $list      Comma separated names.
		 */
		public function set_tags( $ticket_id, $list ) {
			$names = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $list ) ) ) ) ) );
			wp_set_object_terms( $ticket_id, array_slice( $names, 0, 20 ), 'stsw_tickets_tag' );
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
