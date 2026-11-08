<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Edit history
 *
 * Staff can correct a reply or a private note. Every earlier text is kept with who changed it and when, and shown to
 * staff under the message. Customers see the current text.
 *
 * @version 3.0.0
 * @since   3.0.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_History' ) ) :

	/**
	 * Edit history.
	 */
	class WPFactory_WC_STS_History {

		/**
		 * Ticket meta: row key (`reply-12`, `note-3`) => list of { content, by, at }.
		 */
		const META = '_stsw_edit_history';

		/**
		 * Instance.
		 *
		 * @var WPFactory_WC_STS_History|null
		 */
		protected static $instance = null;

		/**
		 * Instance.
		 *
		 * @return WPFactory_WC_STS_History
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
			add_action( 'wp_ajax_wpfactory_wc_sts_message_edit', array( $this, 'ajax_edit' ) );
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
		 * Table of a row type.
		 *
		 * @param string $type `reply` or `note`.
		 *
		 * @return string
		 */
		protected function table( $type ) {
			global $wpdb;
			return $wpdb->prefix . ( 'note' === $type ? $this->core()->notes_table_name : $this->core()->table_name );
		}

		/**
		 * One reply or note row.
		 *
		 * @param string $type `reply` or `note`.
		 * @param int    $id   Row ID.
		 *
		 * @return object|null
		 */
		public function get_row( $type, $id ) {
			global $wpdb;
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table( $type ), (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		/**
		 * Earlier versions of a row, oldest first.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $type      `reply` or `note`.
		 * @param int    $id        Row ID.
		 *
		 * @return array
		 */
		public function get_versions( $ticket_id, $type, $id ) {
			$all = get_post_meta( $ticket_id, self::META, true );
			return is_array( $all ) && isset( $all[ $type . '-' . (int) $id ] ) ? (array) $all[ $type . '-' . (int) $id ] : array();
		}

		/**
		 * Changes the text of a reply or note, keeping the previous text.
		 *
		 * @param string $type    `reply` or `note`.
		 * @param int    $id      Row ID.
		 * @param string $content New text.
		 * @param int    $user_id Staff user.
		 *
		 * @return bool
		 */
		public function edit( $type, $id, $content, $user_id ) {
			global $wpdb;
			$type    = 'note' === $type ? 'note' : 'reply';
			$row     = $this->get_row( $type, $id );
			$content = sanitize_textarea_field( $content );
			if ( ! $row || '' === trim( $content ) ) {
				return false;
			}
			if ( (string) $row->content === $content ) {
				return true;
			}
			$ticket_id     = (int) $row->post_id;
			$all           = get_post_meta( $ticket_id, self::META, true );
			$all           = is_array( $all ) ? $all : array();
			$key           = $type . '-' . (int) $id;
			$all[ $key ]   = isset( $all[ $key ] ) ? (array) $all[ $key ] : array();
			$all[ $key ][] = array(
				'content' => (string) $row->content,
				'by'      => (int) $user_id,
				'at'      => current_time( 'mysql', 1 ),
			);
			update_post_meta( $ticket_id, self::META, $all );
			$wpdb->update( $this->table( $type ), array( 'content' => $content ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			/**
			 * A reply or note was edited.
			 *
			 * @param int    $ticket_id Ticket ID.
			 * @param string $type      `reply` or `note`.
			 * @param int    $id        Row ID.
			 */
			do_action( 'wpfactory_wc_sts_message_edited', $ticket_id, $type, (int) $id );
			return true;
		}

		/**
		 * Earlier versions as HTML for staff (empty when never edited).
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $type      `reply` or `note`.
		 * @param int    $id        Row ID.
		 *
		 * @return string
		 */
		public function render_versions( $ticket_id, $type, $id ) {
			$versions = $this->get_versions( $ticket_id, $type, $id );
			if ( ! $versions ) {
				return '';
			}
			$core = $this->core();
			$html = '<details class="hd-history"><summary>' . esc_html(
				/* translators: %d: number of earlier versions. */
				sprintf( _n( 'Edited (%d earlier version)', 'Edited (%d earlier versions)', count( $versions ), 'support-ticket-system-for-woocommerce' ), count( $versions ) )
			) . '</summary><ol class="hd-history__list">';
			foreach ( array_reverse( $versions ) as $version ) {
				$html .= '<li><p class="hd-help">' . esc_html(
					/* translators: 1: staff name, 2: date. */
					sprintf( __( 'Changed by %1$s on %2$s. Text before:', 'support-ticket-system-for-woocommerce' ), $core->get_user_name( (int) $version['by'] ), $core->format_response_date( (string) $version['at'] ) )
				) . '</p><div class="hd-history__text">' . $core->format_message( (string) $version['content'] ) . '</div></li>';
			}
			return $html . '</ol></details>';
		}

		/**
		 * Saves an edit from the inbox.
		 */
		public function ajax_edit() {
			check_ajax_referer( 'wpfactory_wc_sts_inbox' );
			$type = 'note' === sanitize_key( wp_unslash( $_POST['type'] ?? '' ) ) ? 'note' : 'reply';
			$id   = absint( $_POST['id'] ?? 0 );
			$row  = $this->get_row( $type, $id );
			if ( ! $row || ! $this->core()->can_manage_tickets() || ! current_user_can( 'edit_post', (int) $row->post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ) ), 403 );
			}
			if ( ! $this->edit( $type, $id, wp_unslash( (string) ( $_POST['content'] ?? '' ) ), get_current_user_id() ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in edit().
				wp_send_json_error( array( 'message' => __( 'Write the new text first.', 'support-ticket-system-for-woocommerce' ) ), 400 );
			}
			$row = $this->get_row( $type, $id );
			wp_send_json_success(
				array(
					'message' => __( 'Changes saved', 'support-ticket-system-for-woocommerce' ),
					'html'    => $this->core()->format_message( (string) $row->content ),
					'text'    => (string) $row->content,
					'history' => $this->render_versions( (int) $row->post_id, $type, $id ),
				)
			);
		}
	}

endif;

return WPFactory_WC_STS_History::get_instance();
