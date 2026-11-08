<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: team features of the inbox.
 *
 * Collision detection (who else is viewing or replying, and new messages while a ticket is open), saved views with
 * counts, and CSV export. Shared by both editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Teamwork' ) ) :

	/**
	 * WPFactory_WC_STS_Teamwork class.
	 */
	class WPFactory_WC_STS_Teamwork {

		/**
		 * User meta: saved views.
		 */
		const META_VIEWS = '_stsw_saved_views';

		/**
		 * Seconds a viewer counts as present.
		 */
		const PRESENCE_TTL = 40;

		/**
		 * Most tickets in one export.
		 */
		const EXPORT_LIMIT = 5000;

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Teamwork|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Teamwork
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
			add_action( 'wp_ajax_wpfactory_wc_sts_presence', array( $this, 'ajax_presence' ) );
			add_action( 'wpfactory_wc_sts_inbox_ticket_top', array( $this, 'render_presence' ) );
			add_action( 'wpfactory_wc_sts_inbox_rail_after_views', array( $this, 'render_saved_views' ) );
			add_action( 'wpfactory_wc_sts_inbox_list_head', array( $this, 'render_list_tools' ), 10, 2 );
			add_action( 'admin_post_wpfactory_wc_sts_view', array( $this, 'handle_view' ) );
			add_action( 'admin_post_wpfactory_wc_sts_export', array( $this, 'handle_export' ) );
			add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 80 );
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
		| Collision detection
		|--------------------------------------------------------------------------
		*/

		/**
		 * Tickets waiting for a reply, in the admin bar (team members, setting on by default).
		 *
		 * @param WP_Admin_Bar $bar Admin bar.
		 */
		public function admin_bar( $bar ) {
			if ( '1' !== (string) get_option( 'wpfactory_wc_sts_admin_bar', '1' ) || ! is_user_logged_in() || ! $this->core()->can_manage_tickets() ) {
				return;
			}
			$key   = 'wpfactory_wc_sts_bar_' . get_current_user_id();
			$count = get_transient( $key );
			if ( false === $count ) {
				$count = WPFactory_WC_STS_Inbox::get_instance()->count( 'needs_reply' );
				set_transient( $key, (int) $count, MINUTE_IN_SECONDS );
			}
			$count = (int) $count;
			/* translators: %d: number of tickets. */
			$label = sprintf( _n( '%d ticket waiting for a reply', '%d tickets waiting for a reply', $count, 'support-ticket-system-for-woocommerce' ), $count );
			$bar->add_node(
				array(
					'id'    => 'stsw-tickets',
					'title' => '<span class="ab-icon dashicons dashicons-format-chat" aria-hidden="true"></span><span class="ab-label" aria-hidden="true">' . (int) $count . '</span><span class="screen-reader-text">' . esc_html( $label ) . '</span>',
					'href'  => admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&view=needs_reply' ),
					'meta'  => array( 'title' => $label ),
				)
			);
		}

		/**
		 * Who is on a ticket now.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return array user ID => { at, typing }
		 */
		public function get_presence( $ticket_id ) {
			$list = get_transient( 'stsw_presence_' . (int) $ticket_id );
			$list = is_array( $list ) ? $list : array();
			foreach ( $list as $user => $row ) {
				if ( (int) $row['at'] < time() - self::PRESENCE_TTL ) {
					unset( $list[ $user ] );
				}
			}
			return $list;
		}

		/**
		 * Records that a user is on a ticket.
		 *
		 * @param int  $ticket_id Ticket ID.
		 * @param int  $user_id   User ID.
		 * @param bool $typing    Writing a reply.
		 */
		public function mark_present( $ticket_id, $user_id, $typing ) {
			$list             = $this->get_presence( $ticket_id );
			$list[ $user_id ] = array(
				'at'     => time(),
				'typing' => (bool) $typing,
			);
			set_transient( 'stsw_presence_' . (int) $ticket_id, $list, 2 * MINUTE_IN_SECONDS );
		}

		/**
		 * Banner slot above the thread (filled by the script).
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function render_presence( $ticket_id ) {
			$this->mark_present( $ticket_id, get_current_user_id(), false );
			$activity = $this->core()->get_activity( $ticket_id );
			echo '<div class="hd-banner hd-banner--info" data-hd-presence data-ticket="' . (int) $ticket_id . '" data-since="' . esc_attr( (string) $activity['at'] ) . '" role="status" hidden></div>';
		}

		/**
		 * Heartbeat of an open ticket: other viewers, and whether a new message arrived.
		 */
		public function ajax_presence() {
			check_ajax_referer( WPFactory_WC_STS_Inbox::NONCE );
			$ticket_id = absint( $_POST['ticket'] ?? 0 );
			if ( ! $this->core()->can_manage_tickets() || 'stsw_tickets' !== get_post_type( $ticket_id ) || ! current_user_can( 'edit_post', $ticket_id ) ) {
				wp_send_json_error( null, 403 );
			}
			$me = get_current_user_id();
			$this->mark_present( $ticket_id, $me, ! empty( $_POST['typing'] ) );
			update_user_meta( $me, '_stsw_last_seen', time() );
			$others = array();
			foreach ( $this->get_presence( $ticket_id ) as $user => $row ) {
				if ( (int) $user !== $me ) {
					$others[] = array(
						'name'   => $this->core()->get_user_name( (int) $user ),
						'typing' => ! empty( $row['typing'] ),
					);
				}
			}
			$since    = sanitize_text_field( wp_unslash( $_POST['since'] ?? '' ) );
			$activity = $this->core()->get_activity( $ticket_id );
			wp_send_json_success(
				array(
					'others'  => $others,
					'changed' => '' !== $since && (string) $activity['at'] > $since,
					'by'      => $activity['by'],
				)
			);
		}

		/*
		|--------------------------------------------------------------------------
		| Saved views
		|--------------------------------------------------------------------------
		*/

		/**
		 * Filters a saved view may keep.
		 *
		 * @return string[]
		 */
		protected function keys() {
			return array( 'view', 's', 'order_id', 'prio', 'subject', 'status', 'tag' );
		}

		/**
		 * The user's saved views.
		 *
		 * @param int $user_id User ID (0: current).
		 *
		 * @return array id => { name, args }
		 */
		public function get_views( $user_id = 0 ) {
			$views = get_user_meta( $user_id ? $user_id : get_current_user_id(), self::META_VIEWS, true );
			return is_array( $views ) ? $views : array();
		}

		/**
		 * Number of tickets of a saved view.
		 *
		 * @param array $args Filters.
		 *
		 * @return int
		 */
		public function count( $args ) {
			$inbox   = WPFactory_WC_STS_Inbox::get_instance();
			$filters = $inbox->get_filters( $args );
			$query   = $inbox->query_filtered(
				$args['view'] ?? 'all',
				array_merge(
					$filters,
					array(
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				)
			);
			return (int) $query->found_posts;
		}

		/**
		 * Saved views in the left column.
		 */
		public function render_saved_views() {
			$views = $this->get_views();
			if ( ! $views ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
			$current = sanitize_key( wp_unslash( $_GET['saved'] ?? '' ) );
			echo '<p class="hd-rail__label" id="hd-rail-saved">' . esc_html__( 'Saved views', 'support-ticket-system-for-woocommerce' ) . '</p><ul class="hd-rail__list" aria-labelledby="hd-rail-saved">';
			foreach ( $views as $id => $view ) {
				$url = add_query_arg( array_merge( $view['args'], array( 'saved' => $id ) ), WPFactory_WC_STS_UI::url() );
				echo '<li class="hd-rail__saved"><a class="hd-rail__item" href="' . esc_url( $url ) . '"' . ( $current === $id ? ' aria-current="page"' : '' ) . '>' . wp_kses( WPFactory_WC_STS_UI::icon( 'search', 16 ), WPFactory_WC_STS_UI::svg_kses() ) . '<span class="hd-rail__text">' . esc_html( $view['name'] ) . '</span><span class="hd-count">' . (int) $this->count( $view['args'] ) . '</span></a>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hd-inline"><input type="hidden" name="action" value="wpfactory_wc_sts_view" /><input type="hidden" name="delete" value="' . esc_attr( $id ) . '" />';
				wp_nonce_field( WPFactory_WC_STS_Inbox::NONCE );
				/* translators: %s: view name. */
				echo '<button type="submit" class="hd-icon-btn hd-rail__remove" aria-label="' . esc_attr( sprintf( __( 'Remove the saved view %s', 'support-ticket-system-for-woocommerce' ), $view['name'] ) ) . '">' . wp_kses( WPFactory_WC_STS_UI::icon( 'trash', 14 ), WPFactory_WC_STS_UI::svg_kses() ) . '</button></form></li>';
			}
			echo '</ul>';
		}

		/**
		 * Above the list: save these filters as a view, and export them as CSV.
		 *
		 * @param array  $keep Current filters.
		 * @param string $view View key.
		 */
		public function render_list_tools( $keep, $view ) {
			$args = array_intersect_key( $keep, array_flip( $this->keys() ) );
			echo '<div class="hd-listtools">';
			echo '<a class="hd-btn hd-btn--sm hd-btn--ghost" href="' . esc_url( wp_nonce_url( add_query_arg( array_merge( $args, array( 'action' => 'wpfactory_wc_sts_export' ) ), admin_url( 'admin-post.php' ) ), WPFactory_WC_STS_Inbox::NONCE ) ) . '">' . esc_html__( 'Export CSV', 'support-ticket-system-for-woocommerce' ) . '</a>';
			if ( count( $args ) > 1 || 'all' !== $view ) {
				echo '<details class="hd-menu hd-saveview"><summary class="hd-btn hd-btn--sm hd-btn--ghost">' . esc_html__( 'Save view', 'support-ticket-system-for-woocommerce' ) . '</summary><div class="hd-menu__panel"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hd-form">';
				echo '<input type="hidden" name="action" value="wpfactory_wc_sts_view" /><input type="hidden" name="args" value="' . esc_attr( wp_json_encode( $args ) ) . '" />';
				wp_nonce_field( WPFactory_WC_STS_Inbox::NONCE );
				echo '<div class="hd-field"><label for="hd-view-name">' . esc_html__( 'Name of the view', 'support-ticket-system-for-woocommerce' ) . '</label><input type="text" id="hd-view-name" name="name" required maxlength="60" /></div>';
				echo '<p class="hd-help">' . esc_html__( 'Keeps the current view, filters and search. Only you see your saved views.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				echo '<button type="submit" class="hd-btn hd-btn--primary hd-btn--sm">' . esc_html__( 'Save', 'support-ticket-system-for-woocommerce' ) . '</button></form></div></details>';
			}
			echo '</div>';
		}

		/**
		 * Saves or removes a view.
		 */
		public function handle_view() {
			check_admin_referer( WPFactory_WC_STS_Inbox::NONCE );
			if ( ! $this->core()->can_manage_tickets() ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}
			$user  = get_current_user_id();
			$views = $this->get_views( $user );
			$del   = sanitize_key( wp_unslash( $_POST['delete'] ?? '' ) );
			if ( '' !== $del ) {
				unset( $views[ $del ] );
				update_user_meta( $user, self::META_VIEWS, $views );
				wp_safe_redirect( WPFactory_WC_STS_UI::url() );
				exit;
			}
			$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
			$args = json_decode( wp_unslash( (string) ( $_POST['args'] ?? '' ) ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded, then each value sanitized.
			$args = is_array( $args ) ? array_map( 'sanitize_text_field', array_map( 'strval', array_intersect_key( $args, array_flip( $this->keys() ) ) ) ) : array();
			if ( '' === $name || count( $views ) >= 20 ) {
				wp_safe_redirect( WPFactory_WC_STS_UI::url( '', array_merge( $args, array( 'stsw_msg' => 'error' ) ) ) );
				exit;
			}
			$id           = 'v' . substr( md5( $name . microtime() ), 0, 8 );
			$views[ $id ] = array(
				'name' => $name,
				'args' => $args,
			);
			update_user_meta( $user, self::META_VIEWS, $views );
			wp_safe_redirect( WPFactory_WC_STS_UI::url( '', array_merge( $args, array( 'saved' => $id ) ) ) );
			exit;
		}

		/*
		|--------------------------------------------------------------------------
		| CSV export
		|--------------------------------------------------------------------------
		*/

		/**
		 * A CSV cell that spreadsheets will not run as a formula.
		 *
		 * @param mixed $value Value.
		 *
		 * @return string
		 */
		public static function cell( $value ) {
			$value = (string) $value;
			return ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $value : $value;
		}

		/**
		 * Rows of tickets for the export.
		 *
		 * @param int[] $ids Ticket IDs.
		 *
		 * @return array[]
		 */
		public function rows( $ids ) {
			$core = $this->core();
			$rows = array(
				array(
					__( 'Ticket', 'support-ticket-system-for-woocommerce' ),
					__( 'Subject', 'support-ticket-system-for-woocommerce' ),
					__( 'Status', 'support-ticket-system-for-woocommerce' ),
					__( 'Priority', 'support-ticket-system-for-woocommerce' ),
					__( 'Topic', 'support-ticket-system-for-woocommerce' ),
					__( 'Customer', 'support-ticket-system-for-woocommerce' ),
					__( 'Email', 'support-ticket-system-for-woocommerce' ),
					__( 'Order', 'support-ticket-system-for-woocommerce' ),
					__( 'Assignee', 'support-ticket-system-for-woocommerce' ),
					__( 'Created (UTC)', 'support-ticket-system-for-woocommerce' ),
					__( 'Last message (UTC)', 'support-ticket-system-for-woocommerce' ),
					__( 'Last message by', 'support-ticket-system-for-woocommerce' ),
					__( 'Replies', 'support-ticket-system-for-woocommerce' ),
				),
			);
			foreach ( $ids as $id ) {
				$contact  = $core->get_ticket_contact( $id );
				$status   = $core->get_ticket_term( $id, 'stsw_tickets_status' );
				$priority = $core->get_ticket_term( $id, 'stsw_tickets_priority' );
				$subject  = $core->get_ticket_term( $id, 'stsw_tickets_subject' );
				$agent    = $core->get_ticket_agent_id( $id );
				$order_id = $core->get_ticket_order_id( $id );
				$order    = $order_id ? wc_get_order( $order_id ) : false;
				$activity = $core->get_activity( $id );
				$rows[]   = array_map(
					array( __CLASS__, 'cell' ),
					array(
						$id,
						html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
						$status ? $status->name : '',
						$priority ? $priority->name : '',
						$subject ? $subject->name : '',
						$contact['display_name'],
						$contact['email'],
						$order ? $order->get_order_number() : '',
						$agent ? $core->get_user_name( $agent ) : '',
						get_post_field( 'post_date_gmt', $id ),
						$activity['at'],
						$activity['by'],
						count( $core->get_replies( $id ) ),
					)
				);
			}
			return $rows;
		}

		/**
		 * Downloads the tickets of the current filters as CSV.
		 */
		public function handle_export() {
			check_admin_referer( WPFactory_WC_STS_Inbox::NONCE );
			if ( ! $this->core()->can_manage_tickets() ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}
			$args  = array_map( 'sanitize_text_field', array_map( 'wp_unslash', array_intersect_key( $_GET, array_flip( $this->keys() ) ) ) );
			$inbox = WPFactory_WC_STS_Inbox::get_instance();
			$query = $inbox->query_filtered(
				$args['view'] ?? 'all',
				array_merge(
					$inbox->get_filters( $args ),
					array(
						'posts_per_page' => self::EXPORT_LIMIT,
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				)
			);
			$ids   = array_values( array_filter( array_map( 'intval', $query->posts ), fn( $id ) => current_user_can( 'edit_post', $id ) ) );
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="tickets-' . gmdate( 'Y-m-d' ) . '.csv"' );
			$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 mark for spreadsheets.
			foreach ( $this->rows( $ids ) as $row ) {
				fputcsv( $out, $row, ',', '"', '\\' );
			}
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			exit;
		}
	}

endif;

return WPFactory_WC_STS_Teamwork::get_instance();
