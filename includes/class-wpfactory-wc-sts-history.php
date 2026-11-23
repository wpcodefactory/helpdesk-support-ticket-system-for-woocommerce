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
			add_action( 'set_object_terms', array( $this, 'log_terms' ), 10, 6 );
			add_filter( 'update_post_metadata', array( $this, 'log_assignee' ), 10, 4 );
			add_filter( 'add_post_metadata', array( $this, 'log_assignee' ), 10, 4 );
			add_filter( 'delete_post_metadata', array( $this, 'log_unassign' ), 10, 3 );
		}

		/**
		 * Meta key of timeline events (one row per change).
		 */
		const META_EVENT = '_stsw_event';

		/**
		 * Adds a timeline event.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $kind      status, priority, assignee, tag_add or tag_remove.
		 * @param string $from      Old value (name).
		 * @param string $to        New value (name).
		 */
		public function add_event( $ticket_id, $kind, $from, $to ) {
			add_post_meta(
				$ticket_id,
				self::META_EVENT,
				array(
					'at'   => current_time( 'mysql', 1 ),
					'by'   => get_current_user_id(),
					'kind' => $kind,
					'from' => (string) $from,
					'to'   => (string) $to,
				)
			);
		}

		/**
		 * Timeline events of a ticket, oldest first.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return array
		 */
		public function get_events( $ticket_id ) {
			return array_values( array_filter( (array) get_post_meta( $ticket_id, self::META_EVENT ), 'is_array' ) );
		}

		/**
		 * One event in plain words.
		 *
		 * @param array $event Event.
		 *
		 * @return string
		 */
		public function event_text( $event ) {
			$who = (int) $event['by'] ? $this->core()->get_user_name( (int) $event['by'] ) : __( 'Automatic', 'support-ticket-system-for-woocommerce' );
			switch ( $event['kind'] ) {
				case 'status':
					/* translators: 1: who, 2: old status, 3: new status. */
					return '' !== $event['from'] ? sprintf( __( '%1$s changed the status from %2$s to %3$s', 'support-ticket-system-for-woocommerce' ), $who, $event['from'], $event['to'] ) : sprintf( /* translators: 1: who, 2: new status. */ __( '%1$s set the status to %2$s', 'support-ticket-system-for-woocommerce' ), $who, $event['to'] );
				case 'priority':
					/* translators: 1: who, 2: priority. */
					return sprintf( __( '%1$s set the priority to %2$s', 'support-ticket-system-for-woocommerce' ), $who, $event['to'] );
				case 'assignee':
					/* translators: 1: who, 2: team member. */
					return '' !== $event['to'] ? sprintf( __( '%1$s assigned the ticket to %2$s', 'support-ticket-system-for-woocommerce' ), $who, $event['to'] ) : sprintf( /* translators: %s: who. */ __( '%s removed the assignee', 'support-ticket-system-for-woocommerce' ), $who );
				case 'tag_add':
					/* translators: 1: who, 2: tag. */
					return sprintf( __( '%1$s added the tag %2$s', 'support-ticket-system-for-woocommerce' ), $who, $event['to'] );
				case 'tag_remove':
					/* translators: 1: who, 2: tag. */
					return sprintf( __( '%1$s removed the tag %2$s', 'support-ticket-system-for-woocommerce' ), $who, $event['from'] );
			}
			return '';
		}

		/**
		 * Status, priority and tag changes (any screen, automation or API).
		 *
		 * @param int    $object_id  Object ID.
		 * @param array  $terms      Terms given.
		 * @param array  $tt_ids     New term taxonomy IDs.
		 * @param string $taxonomy   Taxonomy.
		 * @param bool   $append     Appended.
		 * @param array  $old_tt_ids Old term taxonomy IDs.
		 */
		public function log_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
			unset( $terms, $append );
			if ( ! in_array( $taxonomy, array( 'stsw_tickets_status', 'stsw_tickets_priority', 'stsw_tickets_tag' ), true ) || 'stsw_tickets' !== get_post_type( $object_id ) ) {
				return;
			}
			$new = array_map( 'intval', (array) $tt_ids );
			$old = array_map( 'intval', (array) $old_tt_ids );
			sort( $new );
			sort( $old );
			// The first status and priority of a new ticket are not changes.
			if ( $new === $old || ( ! $old && 'stsw_tickets_tag' !== $taxonomy && time() - (int) get_post_time( 'U', true, $object_id ) < 30 ) ) {
				return;
			}
			$name = function ( $tt_id ) use ( $taxonomy ) {
				$term = get_term_by( 'term_taxonomy_id', (int) $tt_id, $taxonomy );
				return $term ? $term->name : '';
			};
			if ( 'stsw_tickets_tag' === $taxonomy ) {
				foreach ( array_diff( $new, $old ) as $tt_id ) {
					$this->add_event( $object_id, 'tag_add', '', $name( $tt_id ) );
				}
				foreach ( array_diff( $old, $new ) as $tt_id ) {
					$this->add_event( $object_id, 'tag_remove', $name( $tt_id ), '' );
				}
				return;
			}
			$this->add_event( $object_id, 'stsw_tickets_status' === $taxonomy ? 'status' : 'priority', $old ? $name( $old[0] ) : '', $new ? $name( $new[0] ) : '' );
		}

		/**
		 * Assignee changes.
		 *
		 * @param mixed  $check     Short-circuit value (returned unchanged).
		 * @param int    $object_id Post ID.
		 * @param string $meta_key  Meta key.
		 * @param mixed  $value     New value.
		 *
		 * @return mixed
		 */
		public function log_assignee( $check, $object_id, $meta_key, $value ) {
			if ( WPFactory_WC_STS_Inc::META_AGENT !== $meta_key || 'stsw_tickets' !== get_post_type( $object_id ) ) {
				return $check;
			}
			static $seen = array();
			$old         = (int) get_post_meta( $object_id, WPFactory_WC_STS_Inc::META_AGENT, true );
			$new         = (int) $value;
			// update_post_meta() on a ticket without an assignee also runs add_post_meta(): log the change once.
			$key = $object_id . ':' . $old . ':' . $new;
			if ( $old !== $new && ! isset( $seen[ $key ] ) && ( $old || time() - (int) get_post_time( 'U', true, $object_id ) >= 30 ) ) {
				$seen[ $key ] = true;
				$this->add_event( $object_id, 'assignee', $old ? $this->core()->get_user_name( $old ) : '', $new ? $this->core()->get_user_name( $new ) : '' );
			}
			return $check;
		}

		/**
		 * Assignee removed.
		 *
		 * @param mixed  $check     Short-circuit value (returned unchanged).
		 * @param int    $object_id Post ID.
		 * @param string $meta_key  Meta key.
		 *
		 * @return mixed
		 */
		public function log_unassign( $check, $object_id, $meta_key ) {
			if ( WPFactory_WC_STS_Inc::META_AGENT === $meta_key && 'stsw_tickets' === get_post_type( $object_id ) ) {
				$old = (int) get_post_meta( $object_id, WPFactory_WC_STS_Inc::META_AGENT, true );
				if ( $old ) {
					$this->add_event( $object_id, 'assignee', $this->core()->get_user_name( $old ), '' );
				}
			}
			return $check;
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
			$html = '<details class="msg-history"><summary class="hint">' . esc_html(
				/* translators: %d: number of earlier versions. */
				sprintf( _n( 'Edited (%d earlier version)', 'Edited (%d earlier versions)', count( $versions ), 'support-ticket-system-for-woocommerce' ), count( $versions ) )
			) . '</summary><ol class="history-list">';
			foreach ( array_reverse( $versions ) as $version ) {
				$html .= '<li><p class="hint">' . esc_html(
					/* translators: 1: staff name, 2: date. */
					sprintf( __( 'Changed by %1$s on %2$s. Text before:', 'support-ticket-system-for-woocommerce' ), $core->get_user_name( (int) $version['by'] ), $core->format_response_date( (string) $version['at'] ) )
				) . '</p><div class="history-text">' . $core->format_message( (string) $version['content'] ) . '</div></li>';
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
