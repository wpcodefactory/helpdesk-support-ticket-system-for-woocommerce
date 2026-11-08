<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Ticket lifecycle
 *
 * First reply and close times stored in seconds (reports read them), files removed when a ticket closes, and data
 * retention: closed tickets deleted or anonymised after a number of days. Both privacy options are off by default.
 *
 * @version 3.0.0
 * @since   3.0.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Lifecycle' ) ) :

	/**
	 * Lifecycle.
	 */
	class WPFactory_WC_STS_Lifecycle {

		/**
		 * Seconds from opening to the first store reply.
		 */
		const META_FIRST_SECONDS = '_stsw_first_reply_seconds';

		/**
		 * Seconds from opening to the last close.
		 */
		const META_CLOSE_SECONDS = '_stsw_close_seconds';

		/**
		 * When the ticket was last closed (GMT).
		 */
		const META_CLOSED_AT = '_stsw_closed_at';

		/**
		 * The ticket's files were removed.
		 */
		const META_FILES_DELETED = '_stsw_files_deleted';

		/**
		 * Settings (shared by both editions).
		 */
		const OPTION_DELETE_FILES = 'wpfactory_wc_sts_delete_files_on_close';
		const OPTION_DAYS         = 'wpfactory_wc_sts_retention_days';
		const OPTION_MODE         = 'wpfactory_wc_sts_retention_mode';
		const OPTION_BACKFILL     = 'wpfactory_wc_sts_seconds_backfill';

		/**
		 * Daily event.
		 */
		const CRON = 'wpfactory_wc_sts_retention';

		/**
		 * Tickets handled per run.
		 */
		const BATCH = 50;

		/**
		 * Instance.
		 *
		 * @var WPFactory_WC_STS_Lifecycle|null
		 */
		protected static $instance = null;

		/**
		 * Instance.
		 *
		 * @return WPFactory_WC_STS_Lifecycle
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
			add_action( 'wpfactory_wc_sts_reply_added', array( $this, 'on_reply' ), 5, 3 );
			add_action( 'wpfactory_wc_sts_status_changed', array( $this, 'on_status' ), 20, 3 );
			add_action( 'admin_init', array( $this, 'maybe_backfill' ) );
			add_action( 'init', array( $this, 'schedule' ) );
			add_action( self::CRON, array( $this, 'run_retention' ) );
			add_action( 'admin_post_wpfactory_wc_sts_delete_files', array( $this, 'handle_delete_files' ) );
			add_action( 'wpfactory_wc_sts_inbox_side', array( $this, 'render_side' ), 90 );
			add_filter( 'wpfactory_wc_sts_settings_general', array( $this, 'settings' ), 30 );
			add_filter( 'wpfactory_wc_sts_inbox_messages', array( $this, 'messages' ) );
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
		 * Settings rows (General > Data).
		 *
		 * @param array $fields Fields.
		 *
		 * @return array
		 */
		public function settings( $fields ) {
			$fields[ '@' . self::OPTION_DELETE_FILES ] = array(
				'type'        => 'checkbox',
				'card'        => 'data',
				'label'       => __( 'Delete files when a ticket closes', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'Removes every file attached to the ticket when it is closed. The messages stay. Off by default.', 'support-ticket-system-for-woocommerce' ),
			);
			$fields[ '@' . self::OPTION_DAYS ]         = array(
				'type'        => 'number',
				'card'        => 'data',
				'label'       => __( 'Remove closed tickets after (days)', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'Empty or 0: keep them. Counted from the last message. Runs once a day.', 'support-ticket-system-for-woocommerce' ),
			);
			$fields[ '@' . self::OPTION_MODE ]         = array(
				'type'    => 'select',
				'card'    => 'data',
				'label'   => __( 'How to remove them', 'support-ticket-system-for-woocommerce' ),
				'options' => array(
					'anonymise' => __( 'Anonymise: keep the ticket for reports, remove personal data, messages and files', 'support-ticket-system-for-woocommerce' ),
					'delete'    => __( 'Delete the ticket completely', 'support-ticket-system-for-woocommerce' ),
				),
			);
			return $fields;
		}

		/*
		|--------------------------------------------------------------------------
		| Seconds
		|--------------------------------------------------------------------------
		*/

		/**
		 * Seconds between the ticket's creation and a GMT date.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $gmt       GMT date.
		 *
		 * @return int
		 */
		protected function since_created( $ticket_id, $gmt ) {
			$created = strtotime( (string) get_post_field( 'post_date_gmt', $ticket_id ) . ' UTC' );
			return max( 0, (int) strtotime( $gmt . ' UTC' ) - (int) $created );
		}

		/**
		 * First store reply: stores the seconds once.
		 *
		 * @param int  $ticket_id Ticket ID.
		 * @param int  $reply_id  Reply ID.
		 * @param bool $by_staff  Whether the store replied.
		 */
		public function on_reply( $ticket_id, $reply_id, $by_staff ) {
			if ( $by_staff ) {
				$this->store_first_reply( $ticket_id );
			}
		}

		/**
		 * Stores the first reply seconds from the first reply date, unless already stored.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function store_first_reply( $ticket_id ) {
			if ( '' !== (string) get_post_meta( $ticket_id, self::META_FIRST_SECONDS, true ) || get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_STARTED_BY, true ) ) {
				return;
			}
			$first = (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FIRST_REPLY, true );
			if ( '' !== $first ) {
				update_post_meta( $ticket_id, self::META_FIRST_SECONDS, $this->since_created( $ticket_id, $first ) );
			}
		}

		/**
		 * Status change: close time, and files removed on close when the setting is on.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $slug      New status.
		 * @param string $old       Old status.
		 */
		public function on_status( $ticket_id, $slug, $old ) {
			if ( 'closed' !== $slug ) {
				return;
			}
			$now = current_time( 'mysql', 1 );
			update_post_meta( $ticket_id, self::META_CLOSED_AT, $now );
			update_post_meta( $ticket_id, self::META_CLOSE_SECONDS, $this->since_created( $ticket_id, $now ) );
			if ( '1' === (string) get_option( self::OPTION_DELETE_FILES ) ) {
				$this->delete_files( $ticket_id, 0 );
			}
		}

		/**
		 * Fills the seconds of older tickets in batches (admin requests, until done).
		 */
		public function maybe_backfill() {
			if ( 'done' === get_option( self::OPTION_BACKFILL ) ) {
				return;
			}
			$ids = get_posts(
				array(
					'post_type'      => 'stsw_tickets',
					'post_status'    => 'any',
					'posts_per_page' => 200,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_stsw_seconds_done',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
			foreach ( $ids as $id ) {
				$this->backfill( (int) $id );
			}
			if ( count( $ids ) < 200 ) {
				update_option( self::OPTION_BACKFILL, 'done', false );
			}
		}

		/**
		 * Seconds of one older ticket: first reply from its replies, close time from its last message when closed.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function backfill( $ticket_id ) {
			$core = $this->core();
			if ( '' === (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FIRST_REPLY, true ) ) {
				foreach ( $core->get_replies( $ticket_id ) as $reply ) {
					if ( (int) $reply->agent > 0 ) {
						update_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FIRST_REPLY, $reply->creationdate );
						break;
					}
				}
			}
			$this->store_first_reply( $ticket_id );
			if ( 'closed' === $core->get_ticket_status_slug( $ticket_id ) && '' === (string) get_post_meta( $ticket_id, self::META_CLOSE_SECONDS, true ) ) {
				$activity = $core->get_activity( $ticket_id );
				$at       = $activity['at'] ? $activity['at'] : (string) get_post_field( 'post_modified_gmt', $ticket_id );
				update_post_meta( $ticket_id, self::META_CLOSED_AT, $at );
				update_post_meta( $ticket_id, self::META_CLOSE_SECONDS, $this->since_created( $ticket_id, $at ) );
			}
			update_post_meta( $ticket_id, '_stsw_seconds_done', 1 );
		}

		/*
		|--------------------------------------------------------------------------
		| Files
		|--------------------------------------------------------------------------
		*/

		/**
		 * Removes every file of a ticket (ticket files and reply files) and leaves a private note.
		 *
		 * @param int $ticket_id Ticket ID.
		 * @param int $by        Staff user who asked (0: automatic).
		 *
		 * @return int Files removed.
		 */
		public function delete_files( $ticket_id, $by ) {
			global $wpdb;
			$core  = $this->core();
			$count = 0;
			foreach ( get_attached_media( '', $ticket_id ) as $attachment ) {
				if ( wp_delete_attachment( $attachment->ID, true ) ) {
					++$count;
				}
			}
			delete_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FILES );
			$wpdb->update( $wpdb->prefix . $core->table_name, array( 'attachments' => '' ), array( 'post_id' => (int) $ticket_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $count ) {
				update_post_meta( $ticket_id, self::META_FILES_DELETED, current_time( 'mysql', 1 ) );
				$core->add_note(
					$ticket_id,
					/* translators: %d: number of files. */
					sprintf( _n( '%d file of this ticket was deleted.', '%d files of this ticket were deleted.', $count, 'support-ticket-system-for-woocommerce' ), $count ),
					(int) $by
				);
			}
			return $count;
		}

		/**
		 * "Delete files" in the inbox side panel (tickets with files).
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function render_side( $ticket_id ) {
			if ( ! get_attached_media( '', $ticket_id ) || ! current_user_can( 'edit_post', $ticket_id ) ) {
				return;
			}
			echo '<section class="hd-side__card" aria-labelledby="hd-side-files"><h3 class="hd-side__title" id="hd-side-files">' . esc_html__( 'Files', 'support-ticket-system-for-woocommerce' ) . '</h3>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-hd-confirm="' . esc_attr__( 'Delete every file of this ticket? This cannot be undone.', 'support-ticket-system-for-woocommerce' ) . '">';
			echo '<input type="hidden" name="action" value="wpfactory_wc_sts_delete_files" /><input type="hidden" name="ticket" value="' . (int) $ticket_id . '" />';
			wp_nonce_field( 'wpfactory_wc_sts_delete_files_' . (int) $ticket_id );
			echo '<button type="submit" class="hd-btn hd-btn--sm hd-btn--danger">' . esc_html__( 'Delete files', 'support-ticket-system-for-woocommerce' ) . '</button></form></section>';
		}

		/**
		 * Deletes a ticket's files on a staff request.
		 */
		public function handle_delete_files() {
			$ticket_id = absint( $_POST['ticket'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified next.
			check_admin_referer( 'wpfactory_wc_sts_delete_files_' . $ticket_id );
			if ( 'stsw_tickets' !== get_post_type( $ticket_id ) || ! $this->core()->can_manage_tickets() || ! current_user_can( 'edit_post', $ticket_id ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}
			$this->delete_files( $ticket_id, get_current_user_id() );
			wp_safe_redirect( admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&ticket=' . $ticket_id . '&stsw_msg=files' ) );
			exit;
		}

		/**
		 * Inbox message after deleting files.
		 *
		 * @param array $texts Messages.
		 *
		 * @return array
		 */
		public function messages( $texts ) {
			$texts['files'] = __( 'The files of this ticket were deleted.', 'support-ticket-system-for-woocommerce' );
			return $texts;
		}

		/*
		|--------------------------------------------------------------------------
		| Retention
		|--------------------------------------------------------------------------
		*/

		/**
		 * Daily event while retention is on.
		 */
		public function schedule() {
			$on = absint( get_option( self::OPTION_DAYS, 0 ) ) > 0;
			if ( $on && ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
			} elseif ( ! $on && wp_next_scheduled( self::CRON ) ) {
				wp_clear_scheduled_hook( self::CRON );
			}
		}

		/**
		 * Closed tickets past the retention period.
		 *
		 * @param int $days Days.
		 *
		 * @return int[]
		 */
		public function get_expired( $days ) {
			$before = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
			return array_map(
				'intval',
				get_posts(
					array(
						'post_type'      => 'stsw_tickets',
						'post_status'    => 'any',
						'posts_per_page' => self::BATCH,
						'fields'         => 'ids',
						'orderby'        => 'modified',
						'order'          => 'ASC',
						'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => 'stsw_tickets_status',
								'field'    => 'slug',
								'terms'    => 'closed',
							),
						),
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'     => WPFactory_WC_STS_Inc::META_LAST_AT,
								'value'   => $before,
								'compare' => '<',
								'type'    => 'DATETIME',
							),
							array(
								'key'     => '_stsw_anonymised',
								'compare' => 'NOT EXISTS',
							),
						),
					)
				)
			);
		}

		/**
		 * Deletes or anonymises closed tickets past the retention period (one batch per run).
		 *
		 * @return int Tickets handled.
		 */
		public function run_retention() {
			$days = absint( get_option( self::OPTION_DAYS, 0 ) );
			if ( ! $days ) {
				return 0;
			}
			$mode = 'delete' === get_option( self::OPTION_MODE, 'anonymise' ) ? 'delete' : 'anonymise';
			$ids  = $this->get_expired( $days );
			foreach ( $ids as $id ) {
				if ( 'delete' === $mode ) {
					wp_delete_post( $id, true );
				} else {
					$this->anonymise( $id );
				}
			}
			if ( self::BATCH === count( $ids ) ) {
				// More to do: run again soon instead of waiting a day.
				wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::CRON );
			}
			return count( $ids );
		}

		/**
		 * Removes personal data from a ticket: messages, notes, files, form answers, customer and order links.
		 * Status, subject, priority, dates and the stored seconds stay for reports.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function anonymise( $ticket_id ) {
			global $wpdb;
			$core = $this->core();
			$this->delete_files( $ticket_id, 0 );
			$wpdb->update( $wpdb->prefix . $core->table_name, array( 'content' => '' ), array( 'post_id' => (int) $ticket_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->prefix . $core->notes_table_name, array( 'post_id' => (int) $ticket_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			wp_update_post(
				array(
					'ID'           => (int) $ticket_id,
					/* translators: %s: ticket number. */
					'post_title'   => sprintf( __( 'Removed ticket %s', 'support-ticket-system-for-woocommerce' ), $core->get_ticket_number( $ticket_id ) ),
					'post_content' => '',
				)
			);
			if ( class_exists( 'WPFactory_WC_STS_Fields' ) ) {
				WPFactory_WC_STS_Fields::get_instance()->delete_values( $ticket_id );
			}
			foreach ( array( WPFactory_WC_STS_Inc::META_GUEST_EMAIL, '_stsw_guest_name', WPFactory_WC_STS_Inc::META_ORDER, WPFactory_WC_STS_Inc::META_ITEMS, '_stsw_chat_key', '_stsw_edit_history', '_stsw_watchers' ) as $key ) {
				delete_post_meta( $ticket_id, $key );
			}
			$core->set_ticket_user_id( $ticket_id, 0 );
			update_post_meta( $ticket_id, '_stsw_anonymised', current_time( 'mysql', 1 ) );
			/**
			 * A ticket was anonymised by data retention (add-ons remove their own data).
			 *
			 * @param int $ticket_id Ticket ID.
			 */
			do_action( 'wpfactory_wc_sts_ticket_anonymised', (int) $ticket_id );
		}
	}

endif;

return WPFactory_WC_STS_Lifecycle::get_instance();
