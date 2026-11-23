<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: importers.
 *
 * Copies tickets, replies and notes from SupportCandy, Fluent Support, Awesome Support and JS Help Desk. A dry run
 * shows the counts first. The source data is only read, never changed. Each imported ticket remembers its source,
 * so running the import again skips what is already here. No emails are sent for imported tickets.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Import' ) ) :

	/**
	 * WPFactory_WC_STS_Import class.
	 */
	class WPFactory_WC_STS_Import {

		/**
		 * Ticket meta: source plugin.
		 */
		const META_SOURCE = '_stsw_import_source';

		/**
		 * Ticket meta: ticket ID in the source.
		 */
		const META_ID = '_stsw_import_id';

		/**
		 * Tickets per batch.
		 */
		const BATCH = 25;

		/**
		 * Nonce action.
		 */
		const NONCE = 'wpfactory_wc_sts_import';

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Import|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Import
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
			add_filter( 'wpfactory_wc_sts_header_tabs', array( $this, 'header_tab' ), 20 );
			add_action( 'wpfactory_wc_sts_render_tab_tools', array( $this, 'render' ) );
			add_action( 'wp_ajax_wpfactory_wc_sts_import', array( $this, 'ajax' ) );
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
		 * Header tab.
		 *
		 * @param array $tabs Tabs.
		 *
		 * @return array
		 */
		public function header_tab( $tabs ) {
			$tabs['tools'] = __( 'Import and export', 'support-ticket-system-for-woocommerce' );
			return $tabs;
		}

		/**
		 * Whether a table exists.
		 *
		 * @param string $name Table name without prefix.
		 *
		 * @return bool
		 */
		protected function table( $name ) {
			global $wpdb;
			$table = $wpdb->prefix . $name;
			return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		/**
		 * Supported sources.
		 *
		 * @return array key => label
		 */
		public function sources() {
			return array(
				'supportcandy'   => 'SupportCandy',
				'fluent_support' => 'Fluent Support',
				'awesome'        => 'Awesome Support',
				'jshelpdesk'     => 'JS Help Desk',
			);
		}

		/**
		 * Whether a source has data on this site (the plugin itself may be inactive).
		 *
		 * @param string $source Source.
		 *
		 * @return bool
		 */
		public function detect( $source ) {
			global $wpdb;
			switch ( $source ) {
				case 'supportcandy':
					return $this->table( 'psmsc_tickets' ) && $this->table( 'psmsc_threads' );
				case 'fluent_support':
					return $this->table( 'fs_tickets' ) && $this->table( 'fs_conversations' );
				case 'awesome':
					return (bool) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ticket' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				case 'jshelpdesk':
					return $this->table( 'js_ticket_tickets' ) && $this->table( 'js_ticket_replies' );
			}
			return false;
		}

		/**
		 * Source ticket IDs, oldest first.
		 *
		 * @param string $source Source.
		 *
		 * @return int[]
		 */
		public function source_ids( $source ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			switch ( $source ) {
				case 'supportcandy':
					return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE is_active = 1 ORDER BY id', $wpdb->prefix . 'psmsc_tickets' ) ) );
				case 'fluent_support':
					return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ORDER BY id', $wpdb->prefix . 'fs_tickets' ) ) );
				case 'awesome':
					return array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ticket' AND post_status NOT IN ('trash','auto-draft') ORDER BY ID" ) );
				case 'jshelpdesk':
					return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ORDER BY id', $wpdb->prefix . 'js_ticket_tickets' ) ) );
			}
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return array();
		}

		/**
		 * Source IDs already imported.
		 *
		 * @param string $source Source.
		 *
		 * @return int[]
		 */
		public function imported_ids( $source ) {
			global $wpdb;
			return array_map(
				'intval',
				$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT id.meta_value FROM {$wpdb->postmeta} id JOIN {$wpdb->postmeta} src ON src.post_id = id.post_id AND src.meta_key = %s AND src.meta_value = %s WHERE id.meta_key = %s",
						self::META_SOURCE,
						$source,
						self::META_ID
					)
				)
			);
		}

		/**
		 * One source ticket in a common shape.
		 *
		 * @param string $source Source.
		 * @param int    $id     Source ticket ID.
		 *
		 * @return array|null { title, content, created, user_id, email, name, status (open|closed), priority, subject,
		 *                    agent, messages: [ { type reply|note, staff, author, content, created } ], files }
		 */
		public function read( $source, $id ) {
			$method = 'read_' . $source;
			return method_exists( $this, $method ) ? $this->$method( $id ) : null;
		}

		/**
		 * WordPress user ID of an email, or 0.
		 *
		 * @param string $email Email.
		 *
		 * @return int
		 */
		protected function user_by_email( $email ) {
			$user = is_email( $email ) ? get_user_by( 'email', $email ) : false;
			return $user ? (int) $user->ID : 0;
		}

		/**
		 * Plain text of source HTML.
		 *
		 * @param string $html HTML.
		 *
		 * @return string
		 */
		protected function text( $html ) {
			$html = preg_replace( '#<br\s*/?>|</p>|</div>|</li>#i', "\n", (string) $html );
			return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
		}

		/**
		 * SupportCandy ticket.
		 *
		 * @param int $id Ticket ID.
		 *
		 * @return array|null
		 */
		protected function read_supportcandy( $id ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$p      = $wpdb->prefix;
			$ticket = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $p . 'psmsc_tickets', $id ) );
			if ( ! $ticket ) {
				return null;
			}
			$customer = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $p . 'psmsc_customers', $ticket->customer ) );
			$status   = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM %i WHERE id = %d', $p . 'psmsc_statuses', $ticket->status ) );
			$priority = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM %i WHERE id = %d', $p . 'psmsc_priorities', $ticket->priority ) );
			$category = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM %i WHERE id = %d', $p . 'psmsc_categories', $ticket->category ) );
			$closed   = array_map( 'intval', (array) get_option( 'wpsc-tl-ms-advanced', array() )['closed-ticket-statuses'] ?? array( 4 ) );
			$closed   = $closed ? $closed : array( 4 );
			$agents   = array_filter( explode( '|', (string) $ticket->assigned_agent ) );
			$agent    = $agents ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user FROM %i WHERE id = %d AND is_agentgroup = 0', $p . 'psmsc_agents', (int) reset( $agents ) ) ) : 0;
			$threads  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ticket = %d AND is_active = 1 ORDER BY id', $p . 'psmsc_threads', $id ) );
			$staff    = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT customer FROM %i WHERE is_active = 1', $p . 'psmsc_agents' ) ) );
			$files    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ticket_id = %d AND is_active = 1', $p . 'psmsc_attachments', $id ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$content  = '';
			$messages = array();
			foreach ( $threads as $thread ) {
				if ( 'report' === $thread->type && '' === $content ) {
					$content = $this->text( $thread->body );
					continue;
				}
				if ( ! in_array( $thread->type, array( 'reply', 'note' ), true ) ) {
					continue;
				}
				$author_user = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user FROM %i WHERE id = %d', $p . 'psmsc_customers', $thread->customer ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$messages[]  = array(
					'type'    => $thread->type,
					'staff'   => in_array( (int) $thread->customer, $staff, true ),
					'author'  => $author_user,
					'content' => $this->text( $thread->body ),
					'created' => $thread->date_created,
				);
			}
			$email = $customer ? (string) $customer->email : '';
			return array(
				'title'    => (string) $ticket->subject,
				'content'  => $content,
				'created'  => (string) $ticket->date_created,
				'user_id'  => $customer && (int) $customer->user && get_userdata( (int) $customer->user ) ? (int) $customer->user : $this->user_by_email( $email ),
				'email'    => $email,
				'name'     => $customer ? (string) $customer->name : '',
				'status'   => in_array( (int) $ticket->status, $closed, true ) ? 'closed' : 'open',
				'priority' => (string) $priority,
				'subject'  => (string) $category,
				'agent'    => $agent,
				'messages' => $messages,
				'files'    => $files,
				'label'    => (string) $status,
			);
		}

		/**
		 * Fluent Support ticket.
		 *
		 * @param int $id Ticket ID.
		 *
		 * @return array|null
		 */
		protected function read_fluent_support( $id ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$p      = $wpdb->prefix;
			$ticket = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $p . 'fs_tickets', $id ) );
			if ( ! $ticket ) {
				return null;
			}
			$person   = function ( $pid ) use ( $wpdb, $p ) {
				return $pid ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $p . 'fs_persons', $pid ) ) : null;
			};
			$customer = $person( (int) $ticket->customer_id );
			$agent    = $person( (int) $ticket->agent_id );
			$product  = ( $ticket->product_id && $this->table( 'fs_products' ) ) ? $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM %i WHERE id = %d', $p . 'fs_products', $ticket->product_id ) ) : '';
			$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE ticket_id = %d AND conversation_type IN ('response','note') ORDER BY id", $p . 'fs_conversations', $id ) );
			$files    = $this->table( 'fs_attachments' ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ticket_id = %d', $p . 'fs_attachments', $id ) ) : 0;
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$messages = array();
			foreach ( $rows as $row ) {
				$author     = $person( (int) $row->person_id );
				$messages[] = array(
					'type'    => 'note' === $row->conversation_type ? 'note' : 'reply',
					'staff'   => $author && 'agent' === $author->person_type,
					'author'  => $author ? (int) $author->user_id : 0,
					'content' => $this->text( $row->content ),
					'created' => (string) $row->created_at,
				);
			}
			$email    = $customer ? (string) $customer->email : '';
			$priority = '' !== (string) $ticket->priority ? $ticket->priority : $ticket->client_priority;
			return array(
				'title'    => (string) $ticket->title,
				'content'  => $this->text( $ticket->content ),
				'created'  => (string) $ticket->created_at,
				'user_id'  => $customer && (int) $customer->user_id ? (int) $customer->user_id : $this->user_by_email( $email ),
				'email'    => $email,
				'name'     => $customer ? trim( $customer->first_name . ' ' . $customer->last_name ) : '',
				'status'   => 'closed' === $ticket->status ? 'closed' : 'open',
				'priority' => (string) $priority,
				'subject'  => (string) $product,
				'agent'    => $agent ? (int) $agent->user_id : 0,
				'messages' => $messages,
				'files'    => $files,
				'label'    => (string) $ticket->status,
			);
		}

		/**
		 * Awesome Support ticket.
		 *
		 * @param int $id Ticket post ID.
		 *
		 * @return array|null
		 */
		protected function read_awesome( $id ) {
			$post = get_post( $id );
			if ( ! $post || 'ticket' !== $post->post_type ) {
				return null;
			}
			$author   = get_userdata( (int) $post->post_author );
			$priority = taxonomy_exists( 'ticket_priority' ) ? wp_get_post_terms( $id, 'ticket_priority', array( 'fields' => 'names' ) ) : $this->raw_terms( $id, 'ticket_priority' );
			$dept     = taxonomy_exists( 'department' ) ? wp_get_post_terms( $id, 'department', array( 'fields' => 'names' ) ) : $this->raw_terms( $id, 'department' );
			$messages = array();
			$children = get_posts(
				array(
					'post_type'      => array( 'ticket_reply', 'ticket_note' ),
					'post_parent'    => $id,
					'post_status'    => 'any',
					'posts_per_page' => 500,
					'orderby'        => 'date',
					'order'          => 'ASC',
				)
			);
			foreach ( $children as $child ) {
				$messages[] = array(
					'type'    => 'ticket_note' === $child->post_type ? 'note' : 'reply',
					'staff'   => (int) $child->post_author !== (int) $post->post_author,
					'author'  => (int) $child->post_author,
					'content' => $this->text( $child->post_content ),
					'created' => $child->post_date_gmt,
				);
			}
			$files = count(
				get_posts(
					array(
						'post_type'       => 'attachment',
						'post_parent__in' => array_merge( array( $id ), wp_list_pluck( $children, 'ID' ) ),
						'post_status'     => 'any',
						'fields'          => 'ids',
						'posts_per_page'  => 500,
					)
				)
			);
			return array(
				'title'    => $post->post_title,
				'content'  => $this->text( $post->post_content ),
				'created'  => $post->post_date_gmt,
				'user_id'  => $author ? (int) $author->ID : 0,
				'email'    => $author ? $author->user_email : '',
				'name'     => $author ? $author->display_name : '',
				'status'   => 'closed' === get_post_meta( $id, '_wpas_status', true ) ? 'closed' : 'open',
				'priority' => $priority && ! is_wp_error( $priority ) ? (string) reset( $priority ) : '',
				'subject'  => $dept && ! is_wp_error( $dept ) ? (string) reset( $dept ) : '',
				'agent'    => (int) get_post_meta( $id, '_wpas_assignee', true ),
				'messages' => $messages,
				'files'    => $files,
				'label'    => (string) $post->post_status,
			);
		}

		/**
		 * Term names of a taxonomy that is not registered (the source plugin is inactive).
		 *
		 * @param int    $id       Object ID.
		 * @param string $taxonomy Taxonomy.
		 *
		 * @return string[]
		 */
		protected function raw_terms( $id, $taxonomy ) {
			global $wpdb;
			return $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT t.name FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = %s",
					$id,
					$taxonomy
				)
			);
		}

		/**
		 * JS Help Desk ticket.
		 *
		 * @param int $id Ticket ID.
		 *
		 * @return array|null
		 */
		protected function read_jshelpdesk( $id ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$p      = $wpdb->prefix;
			$ticket = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $p . 'js_ticket_tickets', $id ) );
			if ( ! $ticket ) {
				return null;
			}
			$priority = $this->table( 'js_ticket_priorities' ) ? $wpdb->get_var( $wpdb->prepare( 'SELECT priority FROM %i WHERE id = %d', $p . 'js_ticket_priorities', $ticket->priorityid ) ) : '';
			$dept     = $this->table( 'js_ticket_departments' ) ? $wpdb->get_var( $wpdb->prepare( 'SELECT departmentname FROM %i WHERE id = %d', $p . 'js_ticket_departments', $ticket->departmentid ) ) : '';
			$replies  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ticketid = %d ORDER BY id', $p . 'js_ticket_replies', $id ) );
			$notes    = $this->table( 'js_ticket_notes' ) ? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ticketid = %d ORDER BY id', $p . 'js_ticket_notes', $id ) ) : array();
			$files    = $this->table( 'js_ticket_attachments' ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ticketid = %d', $p . 'js_ticket_attachments', $id ) ) : 0;
			$staff    = $this->table( 'js_ticket_staff' ) ? $wpdb->get_results( $wpdb->prepare( 'SELECT id, uid FROM %i', $p . 'js_ticket_staff' ), OBJECT_K ) : array();
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$messages = array();
			foreach ( $replies as $row ) {
				if ( ! empty( $row->is_ai_draft ) ) {
					continue;
				}
				$is_staff   = (int) $row->staffid > 0;
				$messages[] = array(
					'type'    => 'reply',
					'staff'   => $is_staff,
					'author'  => $is_staff ? (int) ( $staff[ $row->staffid ]->uid ?? 0 ) : (int) $row->uid,
					'content' => $this->text( $row->message ),
					'created' => (string) $row->created,
				);
			}
			foreach ( $notes as $row ) {
				$messages[] = array(
					'type'    => 'note',
					'staff'   => true,
					'author'  => (int) ( $staff[ $row->staffid ]->uid ?? 0 ),
					'content' => trim( ( '' !== (string) $row->title ? $row->title . "\n" : '' ) . $this->text( $row->note ) ),
					'created' => (string) $row->created,
				);
			}
			usort(
				$messages,
				function ( $a, $b ) {
					return strcmp( $a['created'], $b['created'] );
				}
			);
			$email = (string) $ticket->email;
			return array(
				'title'    => (string) $ticket->subject,
				'content'  => $this->text( $ticket->message ),
				'created'  => (string) $ticket->created,
				'user_id'  => (int) $ticket->uid && get_userdata( (int) $ticket->uid ) ? (int) $ticket->uid : $this->user_by_email( $email ),
				'email'    => $email,
				'name'     => (string) $ticket->name,
				'status'   => in_array( (int) $ticket->status, array( 4, 5 ), true ) ? 'closed' : 'open',
				'priority' => (string) $priority,
				'subject'  => (string) $dept,
				'agent'    => (int) ( $staff[ $ticket->staffid ]->uid ?? 0 ),
				'messages' => $messages,
				'files'    => $files,
				'label'    => (string) $ticket->status,
				'order_id' => (int) ( $ticket->wcorderid ?? 0 ),
			);
		}

		/**
		 * Counts for the dry run.
		 *
		 * @param string $source Source.
		 *
		 * @return array { tickets, new, done, replies, notes, files, guests }
		 */
		public function dry_run( $source ) {
			$ids    = $this->source_ids( $source );
			$done   = array_intersect( $ids, $this->imported_ids( $source ) );
			$counts = array(
				'tickets' => count( $ids ),
				'new'     => count( $ids ) - count( $done ),
				'done'    => count( $done ),
				'replies' => 0,
				'notes'   => 0,
				'files'   => 0,
				'guests'  => 0,
			);
			foreach ( $ids as $id ) {
				$t = $this->read( $source, $id );
				if ( ! $t ) {
					continue;
				}
				foreach ( $t['messages'] as $m ) {
					++$counts[ 'note' === $m['type'] ? 'notes' : 'replies' ];
				}
				$counts['files']  += (int) $t['files'];
				$counts['guests'] += $t['user_id'] ? 0 : 1;
			}
			return $counts;
		}

		/**
		 * Term ID by name, created when missing.
		 *
		 * @param string $name     Name.
		 * @param string $taxonomy Taxonomy.
		 *
		 * @return int
		 */
		protected function term( $name, $taxonomy ) {
			$name = trim( (string) $name );
			if ( '' === $name ) {
				return 0;
			}
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( ! $term ) {
				$term = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
			}
			if ( $term ) {
				return (int) $term->term_id;
			}
			$new = wp_insert_term( $name, $taxonomy );
			return is_wp_error( $new ) ? 0 : (int) $new['term_id'];
		}

		/**
		 * Our priority for a source priority name.
		 *
		 * @param string $name Source name.
		 *
		 * @return int Term ID.
		 */
		protected function priority( $name ) {
			$key  = strtolower( trim( $name ) );
			$slug = in_array( $key, array( 'high', 'urgent', 'critical' ), true ) ? 'high' : ( 'low' === $key ? 'low' : 'normal' );
			$term = get_term_by( 'slug', $slug, 'stsw_tickets_priority' );
			return $term ? (int) $term->term_id : 0;
		}

		/**
		 * GMT date of a source date ('' when unreadable).
		 *
		 * @param string $date Date (GMT).
		 *
		 * @return string
		 */
		protected function date( $date ) {
			$time = strtotime( (string) $date . ' UTC' );
			return $time && $time > 0 ? gmdate( 'Y-m-d H:i:s', $time ) : current_time( 'mysql', 1 );
		}

		/**
		 * Imports one ticket.
		 *
		 * @param string $source Source.
		 * @param int    $id     Source ticket ID.
		 *
		 * @return int New ticket ID (0: skipped).
		 */
		public function import_one( $source, $id ) {
			global $wpdb;
			$core = $this->core();
			$t    = $this->read( $source, $id );
			if ( ! $t || '' === trim( $t['title'] . $t['content'] ) ) {
				return 0;
			}
			$created   = $this->date( $t['created'] );
			$ticket_id = wp_insert_post(
				array(
					'post_type'     => 'stsw_tickets',
					'post_status'   => 'publish',
					'post_title'    => sanitize_text_field( '' !== $t['title'] ? $t['title'] : wp_html_excerpt( $t['content'], 60, '...' ) ),
					'post_content'  => sanitize_textarea_field( $t['content'] ),
					'post_date_gmt' => $created,
					'post_date'     => get_date_from_gmt( $created ),
				),
				true
			);
			if ( is_wp_error( $ticket_id ) ) {
				return 0;
			}
			update_post_meta( $ticket_id, self::META_SOURCE, $source );
			update_post_meta( $ticket_id, self::META_ID, (int) $id );
			$core->set_ticket_user_id( $ticket_id, (int) $t['user_id'] );
			if ( ! $t['user_id'] && is_email( $t['email'] ) ) {
				update_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_GUEST_EMAIL, sanitize_email( $t['email'] ) );
				if ( '' !== $t['name'] ) {
					update_post_meta( $ticket_id, '_stsw_guest_name', sanitize_text_field( $t['name'] ) );
				}
			}
			if ( $t['agent'] && get_userdata( $t['agent'] ) ) {
				update_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_AGENT, (int) $t['agent'] );
			}
			if ( ! empty( $t['order_id'] ) && function_exists( 'wc_get_order' ) && wc_get_order( $t['order_id'] ) ) {
				update_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_ORDER, (int) $t['order_id'] );
			}
			$status = get_term_by( 'slug', 'closed' === $t['status'] ? 'closed' : 'open', 'stsw_tickets_status' );
			if ( $status ) {
				wp_set_object_terms( $ticket_id, (int) $status->term_id, 'stsw_tickets_status' );
			}
			$priority = $this->priority( $t['priority'] );
			if ( $priority ) {
				wp_set_object_terms( $ticket_id, $priority, 'stsw_tickets_priority' );
			}
			$subject = $this->term( $t['subject'], 'stsw_tickets_subject' );
			if ( $subject ) {
				wp_set_object_terms( $ticket_id, $subject, 'stsw_tickets_subject' );
			}
			$last_by    = 'customer';
			$last_at    = $created;
			$first_seen = '';
			foreach ( $t['messages'] as $m ) {
				if ( '' === trim( $m['content'] ) ) {
					continue;
				}
				$at    = $this->date( $m['created'] );
				$agent = $m['staff'] ? ( $m['author'] && get_userdata( $m['author'] ) ? (int) $m['author'] : get_current_user_id() ) : 0;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$wpdb->prefix . ( 'note' === $m['type'] ? $core->notes_table_name : $core->table_name ),
					array_merge(
						array(
							'user'         => (int) $t['user_id'],
							'post_id'      => (int) $ticket_id,
							'creationdate' => $at,
							'agent'        => 'note' === $m['type'] ? max( 1, $agent ) : $agent,
							'content'      => sanitize_textarea_field( $m['content'] ),
						),
						'note' === $m['type'] ? array() : array( 'attachments' => '' )
					)
				);
				if ( 'reply' === $m['type'] ) {
					$last_by = $m['staff'] ? 'store' : 'customer';
					$last_at = $at;
					if ( $m['staff'] && '' === $first_seen ) {
						$first_seen = $at;
					}
				}
			}
			if ( '' !== $first_seen ) {
				update_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FIRST_REPLY, $first_seen );
			}
			$core->touch_activity( $ticket_id, $last_by, $last_at );
			$sources = $this->sources();
			$note    = sprintf(
				/* translators: 1: plugin name, 2: ticket number there, 3: status there. */
				__( 'Imported from %1$s (ticket %2$d, status "%3$s").', 'support-ticket-system-for-woocommerce' ),
				$sources[ $source ] ?? $source,
				$id,
				$t['label']
			);
			if ( $t['files'] ) {
				/* translators: %d: number of files. */
				$note .= ' ' . sprintf( _n( '%d attached file stays in the old plugin and was not copied.', '%d attached files stay in the old plugin and were not copied.', (int) $t['files'], 'support-ticket-system-for-woocommerce' ), (int) $t['files'] );
			}
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prefix . $core->notes_table_name,
				array(
					'user'         => (int) $t['user_id'],
					'post_id'      => (int) $ticket_id,
					'creationdate' => current_time( 'mysql', 1 ),
					'agent'        => 0,
					'content'      => $note,
				)
			);
			return (int) $ticket_id;
		}

		/**
		 * Imports the next batch.
		 *
		 * @param string $source Source.
		 *
		 * @return array { imported, skipped, left }
		 */
		public function import_batch( $source ) {
			$done     = $this->imported_ids( $source );
			$todo     = array_values( array_diff( $this->source_ids( $source ), $done ) );
			$imported = 0;
			$skipped  = 0;
			foreach ( array_slice( $todo, 0, self::BATCH ) as $id ) {
				if ( $this->import_one( $source, $id ) ) {
					++$imported;
				} else {
					++$skipped;
				}
			}
			return array(
				'imported' => $imported,
				'skipped'  => $skipped,
				'left'     => max( 0, count( $todo ) - self::BATCH ),
			);
		}

		/**
		 * Dry run or import batch (the screen calls it until nothing is left).
		 */
		public function ajax() {
			check_ajax_referer( self::NONCE );
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				wp_send_json_error( null, 403 );
			}
			$source = sanitize_key( wp_unslash( $_POST['source'] ?? '' ) );
			if ( ! isset( $this->sources()[ $source ] ) || ! $this->detect( $source ) ) {
				wp_send_json_error( array( 'message' => __( 'Nothing to import from this plugin.', 'support-ticket-system-for-woocommerce' ) ), 400 );
			}
			if ( 'dry' === sanitize_key( wp_unslash( $_POST['step'] ?? '' ) ) ) {
				wp_send_json_success( $this->dry_run( $source ) );
			}
			wp_send_json_success( $this->import_batch( $source ) );
		}

		/**
		 * Address of the CSV export of every ticket.
		 *
		 * @return string
		 */
		public function export_url() {
			return wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'wpfactory_wc_sts_export',
						'view'   => 'all',
					),
					admin_url( 'admin-post.php' )
				),
				WPFactory_WC_STS_Inbox::NONCE
			);
		}

		/**
		 * Tools screen (Settings > Advanced > Tools): import from another helpdesk plugin, export every ticket.
		 */
		public function render() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				return;
			}
			$back = '<a class="btn btn-text" href="' . esc_url( WPFactory_WC_STS_UI::url( 'settings', array( 'section' => 'advanced' ) ) ) . '">' . esc_html__( 'Back to Advanced settings', 'support-ticket-system-for-woocommerce' ) . '</a>';
			echo '<div class="page narrow" data-hd-import data-nonce="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '">';
			WPFactory_WC_STS_UI::page_head( __( 'Import and export', 'support-ticket-system-for-woocommerce' ), __( 'Bring tickets over from another helpdesk plugin, or download your tickets as a spreadsheet.', 'support-ticket-system-for-woocommerce' ), $back, __( 'Settings', 'support-ticket-system-for-woocommerce' ) );
			echo '<div class="stack">';
			echo '<section class="card" aria-labelledby="hd-import-h"><div class="card-head"><div><h2 class="card-title" id="hd-import-h">' . esc_html__( 'Import tickets', 'support-ticket-system-for-woocommerce' ) . '</h2><div class="row-meta">' . esc_html__( 'Start with a check: it only counts. The other plugin\'s data is never changed, and tickets already imported are skipped. No emails are sent.', 'support-ticket-system-for-woocommerce' ) . '</div></div></div>';
			foreach ( $this->sources() as $key => $label ) {
				$found = $this->detect( $key );
				echo '<div class="row" data-hd-source="' . esc_attr( $key ) . '"><div><p class="row-title">' . esc_html( $label ) . '</p><div class="row-meta">' . ( $found ? esc_html__( 'Tickets found on this site.', 'support-ticket-system-for-woocommerce' ) : esc_html__( 'No tickets found. The plugin\'s tables are not on this site.', 'support-ticket-system-for-woocommerce' ) ) . '</div><div class="row-meta" data-hd-result aria-live="polite"></div></div><div class="row-actions">';
				if ( $found ) {
					echo '<button type="button" class="btn btn-secondary btn-sm" data-hd-dry>' . esc_html__( 'Check what will be imported', 'support-ticket-system-for-woocommerce' ) . '</button><button type="button" class="btn btn-primary btn-sm" data-hd-run hidden>' . esc_html__( 'Import now', 'support-ticket-system-for-woocommerce' ) . '</button>';
				} else {
					echo WPFactory_WC_STS_UI::chip( __( 'Not found', 'support-ticket-system-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in chip().
				}
				echo '</div></div>';
			}
			echo '</section>';
			echo '<section class="card" aria-labelledby="hd-export-h"><div class="card-head"><div><h2 class="card-title" id="hd-export-h">' . esc_html__( 'Export tickets', 'support-ticket-system-for-woocommerce' ) . '</h2><div class="row-meta">' . esc_html__( 'A CSV file with every ticket: number, subject, status, priority, topic, customer, order, assignee and dates. To export only some tickets, filter the inbox and use Export CSV there.', 'support-ticket-system-for-woocommerce' ) . '</div></div><a class="btn btn-secondary btn-sm" href="' . esc_url( $this->export_url() ) . '">' . esc_html__( 'Download all tickets (CSV)', 'support-ticket-system-for-woocommerce' ) . '</a></div></section>';
			echo '</div></div>';
		}
	}

endif;

return WPFactory_WC_STS_Import::get_instance();
