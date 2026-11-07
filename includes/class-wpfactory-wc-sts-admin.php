<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: ticket screens in wp-admin.
 *
 * Ticket edit screen (conversation, reply, private notes, assignment, order), tickets list columns and filters,
 * priority colours and subject assignees. Shared by the free and Pro editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Admin' ) ) :

	/**
	 * WPFactory_WC_STS_Admin class.
	 *
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	class WPFactory_WC_STS_Admin {

		/**
		 * Core instance.
		 *
		 * @var WPFactory_WC_STS_Inc
		 */
		protected $core;

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Admin|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Admin
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self( WPFactory_WC_STS_Inc::get_main_instance() );
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @param WPFactory_WC_STS_Inc $core Core instance.
		 */
		public function __construct( $core ) {
			$this->core     = $core;
			self::$instance = $this;

			add_action( 'add_meta_boxes_stsw_tickets', array( $this, 'add_ticket_meta_boxes' ) );
			add_action( 'save_post_stsw_tickets', array( $this, 'save_fields' ) );
			add_action( 'post_edit_form_tag', array( $this, 'form_enctype' ) );

			add_action( 'wp_ajax_wpfactory_wc_sts_response_delete', array( $this, 'response_delete' ) );
			add_action( 'wp_ajax_wpfactory_wc_sts_note_delete', array( $this, 'note_delete' ) );

			add_filter( 'manage_stsw_tickets_posts_columns', array( $this, 'columns' ) );
			add_action( 'manage_stsw_tickets_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
			add_action( 'restrict_manage_posts', array( $this, 'filter_tickets' ) );

			add_action( 'stsw_tickets_priority_add_form_fields', array( $this, 'priority_add_field' ) );
			add_action( 'stsw_tickets_priority_edit_form_fields', array( $this, 'priority_edit_field' ) );
			add_action( 'created_stsw_tickets_priority', array( $this, 'save_priority_color' ) );
			add_action( 'edited_stsw_tickets_priority', array( $this, 'save_priority_color' ) );
			add_filter( 'manage_edit-stsw_tickets_priority_columns', array( $this, 'priority_columns' ) );
			add_filter( 'manage_stsw_tickets_priority_custom_column', array( $this, 'priority_column_content' ), 10, 3 );

			add_action( 'stsw_tickets_subject_add_form_fields', array( $this, 'subject_add_field' ) );
			add_action( 'stsw_tickets_subject_edit_form_fields', array( $this, 'subject_edit_field' ) );
			add_action( 'created_stsw_tickets_subject', array( $this, 'save_subject_assignee' ) );
			add_action( 'edited_stsw_tickets_subject', array( $this, 'save_subject_assignee' ) );
			add_filter( 'manage_edit-stsw_tickets_subject_columns', array( $this, 'subject_columns' ) );
			add_filter( 'manage_stsw_tickets_subject_custom_column', array( $this, 'subject_column_content' ), 10, 3 );
		}

		/**
		 * Staff users tickets can be assigned to: users with the assignment role, plus shop staff.
		 *
		 * Before 2.3.0 the subject assignee list loaded every user on the site, customers included.
		 *
		 * @return WP_User[]
		 */
		public function get_assignable_users() {
			$roles = array( 'administrator', 'shop_manager' );
			$role  = (string) get_option( $this->core->plugin . 'assignToRole' );
			if ( '' !== $role ) {
				$roles[] = $role;
			}
			return get_users(
				array(
					'role__in' => array_unique( $roles ),
					'orderby'  => 'display_name',
					'number'   => 500,
				)
			);
		}

		/**
		 * Select of assignable users.
		 *
		 * @param string $name     Field name.
		 * @param int    $selected Selected user ID.
		 * @param string $none     Label of the empty option.
		 * @param string $id       Field ID.
		 */
		public function render_user_select( $name, $selected, $none, $id = '' ) {
			$users = $this->get_assignable_users();
			$ids   = wp_list_pluck( $users, 'ID' );
			if ( $selected && ! in_array( (int) $selected, array_map( 'intval', $ids ), true ) ) {
				$extra = get_user_by( 'id', (int) $selected );
				if ( $extra ) {
					array_unshift( $users, $extra );
				}
			}
			echo '<select name="' . esc_attr( $name ) . '"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
			echo '<option value="">' . esc_html( $none ) . '</option>';
			foreach ( $users as $user ) {
				echo '<option value="' . (int) $user->ID . '"' . selected( (int) $selected, (int) $user->ID, false ) . '>' . esc_html( $this->core->get_user_name( $user->ID ) ) . '</option>';
			}
			echo '</select>';
		}

		/*
		|--------------------------------------------------------------------------
		| Ticket edit screen
		|--------------------------------------------------------------------------
		*/

		/**
		 * Meta boxes on the ticket screen.
		 */
		public function add_ticket_meta_boxes() {
			add_meta_box( 'stswpro_ticketContent', __( 'Ticket', 'support-ticket-system-for-woocommerce' ), array( $this, 'render_ticket_content_meta_box' ), 'stsw_tickets', 'normal', 'high' );
			add_meta_box( 'stswpro_ticketResponded', __( 'Conversation', 'support-ticket-system-for-woocommerce' ), array( $this, 'render_conversation_meta_box' ), 'stsw_tickets', 'normal', 'high' );
			add_meta_box( 'stswpro_ticketResponses', __( 'Reply to the customer', 'support-ticket-system-for-woocommerce' ), array( $this, 'render_new_response_meta_box' ), 'stsw_tickets', 'normal', 'high' );
			add_meta_box( 'stswpro_ticketNoted', __( 'Private notes', 'support-ticket-system-for-woocommerce' ), array( $this, 'render_notes_meta_box' ), 'stsw_tickets', 'normal', 'low' );
			add_meta_box( 'appInfo', __( 'Ticket info', 'support-ticket-system-for-woocommerce' ), array( $this, 'render_ticket_info_meta_box' ), 'stsw_tickets', 'side', 'high' );
		}

		/**
		 * The ticket form uploads files with the reply.
		 *
		 * @param WP_Post $post Post being edited.
		 */
		public function form_enctype( $post ) {
			if ( $post && 'stsw_tickets' === $post->post_type ) {
				echo ' enctype="multipart/form-data"';
			}
		}

		/**
		 * Ticket message, custom fields and files.
		 *
		 * @param WP_Post $post Ticket.
		 */
		public function render_ticket_content_meta_box( $post ) {
			echo '<div class="stsw-ticket-message">' . $this->core->format_message( $post->post_content ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
			if ( class_exists( 'WPFactory_WC_STS_Fields' ) ) {
				$values = WPFactory_WC_STS_Fields::get_instance()->get_display_values( $post->ID );
				if ( $values ) {
					echo '<table class="widefat striped" style="margin-top:12px"><tbody>';
					foreach ( $values as $label => $value ) {
						echo '<tr><th style="width:30%">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
					}
					echo '</tbody></table>';
				}
			}
			$files = (string) get_post_meta( $post->ID, WPFactory_WC_STS_Inc::META_FILES, true );
			if ( '' !== $files && class_exists( 'WPFactory_WC_STS_Files' ) ) {
				echo '<h4>' . esc_html__( 'Attachments', 'support-ticket-system-for-woocommerce' ) . '</h4>';
				foreach ( explode( ',', $files ) as $url ) {
					WPFactory_WC_STS_Files::get_instance()->print_ticket_file_link( $url );
				}
			}
		}

		/**
		 * Conversation, oldest first.
		 *
		 * @param WP_Post $post Ticket.
		 */
		public function render_conversation_meta_box( $post ) {
			$replies = $this->core->get_replies( $post->ID );
			if ( empty( $replies ) ) {
				echo '<p>' . esc_html__( 'No replies yet', 'support-ticket-system-for-woocommerce' ) . '</p>';
				return;
			}
			echo '<table class="wp-list-table widefat fixed striped posts"><thead><tr>' .
				'<th style="width:18%">' . esc_html__( 'Date', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th style="width:18%">' . esc_html__( 'From', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th>' . esc_html__( 'Message', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th style="width:15%">' . esc_html__( 'Attachments', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th style="width:8%">' . esc_html__( 'Action', 'support-ticket-system-for-woocommerce' ) . '</th></tr></thead><tbody>';
			foreach ( $replies as $res ) {
				echo '<tr class="' . (int) $res->id . '"><td>' . esc_html( $this->core->format_response_date( $res->creationdate ) ) . '</td><td>' . esc_html( $this->core->get_response_author_label( $res ) ) . '</td><td>' . $this->core->format_message( $res->content ) . '</td><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
				if ( ! empty( $res->attachments ) && class_exists( 'WPFactory_WC_STS_Files' ) ) {
					foreach ( explode( ',', (string) $res->attachments ) as $attachment_id ) {
						WPFactory_WC_STS_Files::get_instance()->print_attachment_link( (int) $attachment_id );
					}
				}
				echo '</td><td><button type="button" class="button-link wpfactory-wc-sts-delete" data-type="response" data-id="' . (int) $res->id . '">' . esc_html__( 'Delete', 'support-ticket-system-for-woocommerce' ) . '</button></td></tr>';
			}
			echo '</tbody></table>';
		}

		/**
		 * Reply box (plain text; field name unchanged since 1.x).
		 *
		 * @param WP_Post $post Ticket.
		 */
		public function render_new_response_meta_box( $post ) {
			$field = sanitize_key( $this->core->plugin . 'response' );
			if ( class_exists( 'WPFactory_WC_STS_Canned' ) ) {
				WPFactory_WC_STS_Canned::get_instance()->render_select( $field );
			}
			?>
			<label class="screen-reader-text" for="<?php echo esc_attr( $field ); ?>"><?php esc_html_e( 'Reply to the customer', 'support-ticket-system-for-woocommerce' ); ?></label>
			<textarea name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" rows="6" style="width:100%" placeholder="<?php esc_attr_e( 'Write your reply to the customer', 'support-ticket-system-for-woocommerce' ); ?>"></textarea>
			<?php if ( $this->core->attachments_enabled() && class_exists( 'WPFactory_WC_STS_Files' ) ) : ?>
				<p>
					<label for="stsw-admin-files"><?php esc_html_e( 'Attachments', 'support-ticket-system-for-woocommerce' ); ?></label>
					<input type="file" id="stsw-admin-files" name="my_file_upload[]" multiple />
				</p>
			<?php endif; ?>
			<p>
				<label><input type="checkbox" name="wpfactory_wc_sts_close_ticket" value="1" /> <?php esc_html_e( 'Close the ticket with this reply', 'support-ticket-system-for-woocommerce' ); ?></label>
			</p>
			<p class="description"><?php esc_html_e( 'The reply is saved when you click Update. The customer gets an email if customer emails are on.', 'support-ticket-system-for-woocommerce' ); ?></p>
			<?php
			wp_nonce_field( 'wpfactory_wc_sts_save', 'wpfactory_wc_sts_nonce' );
		}

		/**
		 * Private notes and the new note field.
		 *
		 * @param WP_Post $post Ticket.
		 */
		public function render_notes_meta_box( $post ) {
			$notes = $this->core->get_notes( $post->ID );
			if ( $notes ) {
				echo '<table class="wp-list-table widefat fixed striped posts"><thead><tr>' .
					'<th style="width:18%">' . esc_html__( 'Date', 'support-ticket-system-for-woocommerce' ) . '</th>' .
					'<th style="width:18%">' . esc_html__( 'Written by', 'support-ticket-system-for-woocommerce' ) . '</th>' .
					'<th>' . esc_html__( 'Note', 'support-ticket-system-for-woocommerce' ) . '</th>' .
					'<th style="width:8%">' . esc_html__( 'Action', 'support-ticket-system-for-woocommerce' ) . '</th></tr></thead><tbody>';
				foreach ( $notes as $note ) {
					echo '<tr class="note-' . (int) $note->id . '"><td>' . esc_html( $this->core->format_response_date( $note->creationdate ) ) . '</td><td>' . esc_html( $this->core->get_user_name( (int) $note->agent ) ) . '</td><td>' . $this->core->format_message( $note->content ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
					echo '<td><button type="button" class="button-link wpfactory-wc-sts-delete" data-type="note" data-id="' . (int) $note->id . '">' . esc_html__( 'Delete', 'support-ticket-system-for-woocommerce' ) . '</button></td></tr>';
				}
				echo '</tbody></table>';
			}
			$field = sanitize_key( $this->core->plugin . 'note' );
			?>
			<p>
				<label for="<?php echo esc_attr( $field ); ?>"><?php esc_html_e( 'New private note', 'support-ticket-system-for-woocommerce' ); ?></label>
				<textarea name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" rows="3" style="width:100%" placeholder="<?php esc_attr_e( 'Only store staff can see private notes', 'support-ticket-system-for-woocommerce' ); ?>"></textarea>
			</p>
			<?php
		}

		/**
		 * Customer, order and assignee.
		 *
		 * @param WP_Post $post Ticket.
		 */
		public function render_ticket_info_meta_box( $post ) {
			$contact  = $this->core->get_ticket_contact( $post->ID );
			$user_id  = $this->core->get_ticket_user_id( $post->ID );
			$order_id = $this->core->get_ticket_order_id( $post->ID );

			echo '<p><strong>' . esc_html__( 'Customer', 'support-ticket-system-for-woocommerce' ) . ':</strong> ';
			if ( $user_id ) {
				echo '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $user_id ) ) . '">' . esc_html( $contact['display_name'] ) . '</a>';
			} else {
				echo esc_html( $contact['display_name'] ) . ' (' . esc_html__( 'guest', 'support-ticket-system-for-woocommerce' ) . ')';
			}
			if ( $contact['email'] ) {
				echo '<br/><a href="mailto:' . esc_attr( $contact['email'] ) . '">' . esc_html( $contact['email'] ) . '</a>';
			}
			echo '</p>';

			// Order: only this customer's orders (any order of the store would let a typo link someone else's order).
			$orders = $user_id ? wc_get_orders(
				array(
					'customer_id' => $user_id,
					'return'      => 'ids',
					'limit'       => 100,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			) : array();
			if ( $order_id && ! in_array( $order_id, array_map( 'absint', $orders ), true ) ) {
				array_unshift( $orders, $order_id );
			}
			$linked = $order_id ? wc_get_order( $order_id ) : false;
			echo '<p><label for="stsw-ticket-order"><strong>' . esc_html__( 'Order', 'support-ticket-system-for-woocommerce' ) . '</strong></label>';
			if ( $linked ) {
				echo ' <a href="' . esc_url( $linked->get_edit_order_url() ) . '">#' . esc_html( $linked->get_order_number() ) . '</a>';
			}
			echo '<br/><select id="stsw-ticket-order" name="' . esc_attr( $this->core->plugin . 'ticketorder' ) . '"><option value="">' . esc_html__( 'No order', 'support-ticket-system-for-woocommerce' ) . '</option>';
			foreach ( $orders as $id ) {
				$order = wc_get_order( $id );
				$label = $order ? '#' . $order->get_order_number() . ' (' . wc_format_datetime( $order->get_date_created() ) . ')' : '#' . $id;
				echo '<option value="' . (int) $id . '"' . selected( $order_id, absint( $id ), false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></p>';

			echo '<p><label for="stsw-ticket-agent"><strong>' . esc_html__( 'Assignee', 'support-ticket-system-for-woocommerce' ) . '</strong></label><br/>';
			$default = (int) get_option( $this->core->plugin . 'assignToUser' );
			$none    = $default ? sprintf(
				/* translators: %s: name of the default assignee. */
				__( 'Default assignee (%s)', 'support-ticket-system-for-woocommerce' ),
				$this->core->get_user_name( $default )
			) : __( 'Nobody', 'support-ticket-system-for-woocommerce' );
			$this->render_user_select( $this->core->plugin . 'ticketagent', $this->core->get_ticket_agent_id( $post->ID ), $none, 'stsw-ticket-agent' );
			echo '</p>';
		}

		/**
		 * Saves the ticket screen: order, assignee, reply and private note.
		 *
		 * @version 2.3.0
		 *
		 * @param int $post_id Ticket ID.
		 */
		public function save_fields( $post_id ) {
			if (
				wp_is_post_revision( $post_id ) ||
				( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
				! isset( $_POST['wpfactory_wc_sts_nonce'] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpfactory_wc_sts_nonce'] ) ), 'wpfactory_wc_sts_save' ) ||
				! current_user_can( 'edit_post', $post_id ) ||
				! $this->core->can_manage_tickets()
			) {
				return;
			}
			$prefix = $this->core->plugin;

			if ( isset( $_POST[ $prefix . 'ticketorder' ] ) ) {
				$order_id = absint( $_POST[ $prefix . 'ticketorder' ] );
				if ( $order_id && wc_get_order( $order_id ) ) {
					update_post_meta( $post_id, WPFactory_WC_STS_Inc::META_ORDER, $order_id );
				} elseif ( ! $order_id ) {
					delete_post_meta( $post_id, WPFactory_WC_STS_Inc::META_ORDER );
				}
			}

			if ( isset( $_POST[ $prefix . 'ticketagent' ] ) ) {
				$agent = absint( $_POST[ $prefix . 'ticketagent' ] );
				$this->core->assign_ticket( $post_id, ( $agent && get_user_by( 'id', $agent ) ) ? $agent : 0 );
			}

			$note = sanitize_textarea_field( wp_unslash( $_POST[ $prefix . 'note' ] ?? '' ) );
			if ( '' !== trim( $note ) ) {
				$this->core->add_note( $post_id, $note, get_current_user_id() );
			}

			$reply = sanitize_textarea_field( wp_unslash( $_POST[ $prefix . 'response' ] ?? '' ) );
			if ( '' !== trim( $reply ) ) {
				// `save_post` runs again inside `wp_insert_attachment()`; the reply is added once.
				remove_action( 'save_post_stsw_tickets', array( $this, 'save_fields' ) );
				$this->core->add_reply(
					$post_id,
					$reply,
					get_current_user_id(),
					true,
					array(
						'close'       => ! empty( $_POST['wpfactory_wc_sts_close_ticket'] ),
						'files_field' => $this->core->attachments_enabled() ? 'my_file_upload' : '',
					)
				);
			}
		}

		/**
		 * Deletes a reply (AJAX, staff only).
		 */
		public function response_delete() {
			check_ajax_referer( 'wpfactory_wc_sts_response_delete', 'nonce' );
			$this->delete_row( $this->core->table_name );
		}

		/**
		 * Deletes a private note (AJAX, staff only).
		 */
		public function note_delete() {
			check_ajax_referer( 'wpfactory_wc_sts_response_delete', 'nonce' );
			$this->delete_row( $this->core->notes_table_name );
		}

		/**
		 * Deletes a reply or note row after checking the user may manage tickets.
		 *
		 * @param string $table Table name without prefix.
		 */
		protected function delete_row( $table ) {
			if ( ! $this->core->can_manage_tickets() ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}
			global $wpdb;
			$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the callers.
			$wpdb->delete( $wpdb->prefix . $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			wp_send_json_success( array( 'id' => $id ) );
		}

		/*
		|--------------------------------------------------------------------------
		| Tickets list
		|--------------------------------------------------------------------------
		*/

		/**
		 * Columns of the tickets list.
		 *
		 * @param array $columns Columns.
		 *
		 * @return array
		 */
		public function columns( $columns ) {
			$checkbox = isset( $columns['cb'] ) ? array( 'cb' => $columns['cb'] ) : array();
			return $checkbox + array(
				'title'                         => __( 'Title', 'support-ticket-system-for-woocommerce' ),
				'taxonomy-stsw_tickets_status'  => __( 'Status', 'support-ticket-system-for-woocommerce' ),
				'taxonomy-stsw_tickets_subject' => __( 'Subject', 'support-ticket-system-for-woocommerce' ),
				'priority'                      => __( 'Priority', 'support-ticket-system-for-woocommerce' ),
				'User'                          => __( 'Customer', 'support-ticket-system-for-woocommerce' ),
				'Order'                         => __( 'Order', 'support-ticket-system-for-woocommerce' ),
				'Assignee'                      => __( 'Assignee', 'support-ticket-system-for-woocommerce' ),
				'Last Response'                 => __( 'Last reply', 'support-ticket-system-for-woocommerce' ),
				'date'                          => __( 'Date', 'support-ticket-system-for-woocommerce' ),
			);
		}

		/**
		 * Column content.
		 *
		 * @param string $column_name Column.
		 * @param int    $post_id     Ticket ID.
		 */
		public function render_columns( $column_name, $post_id ) {
			switch ( $column_name ) {
				case 'priority':
					$term = $this->core->get_ticket_term( $post_id, 'stsw_tickets_priority' );
					if ( $term ) {
						$color = sanitize_hex_color( (string) get_term_meta( $term->term_id, WPFactory_WC_STS_Inc::META_COLOR, true ) );
						echo '<span class="stsw-priority" style="padding:3px 6px;border-radius:3px;' . ( $color ? 'background:' . esc_attr( $color ) . ';color:#fff' : '' ) . '">' . esc_html( $term->name ) . '</span>';
					}
					break;
				case 'Order':
					$order = $this->core->get_ticket_order_id( $post_id ) ? wc_get_order( $this->core->get_ticket_order_id( $post_id ) ) : false;
					if ( $order ) {
						echo '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
					}
					break;
				case 'User':
					$contact = $this->core->get_ticket_contact( $post_id );
					echo esc_html( $contact['display_name'] );
					break;
				case 'Assignee':
					echo esc_html( $this->core->get_user_name( $this->core->get_ticket_assignee_id( $post_id ) ) );
					break;
				case 'Last Response':
					$this->render_last_reply( $post_id );
					break;
			}
		}

		/**
		 * Who wrote last and when.
		 *
		 * @param int $post_id Ticket ID.
		 */
		protected function render_last_reply( $post_id ) {
			global $wpdb;
			$result = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT agent, creationdate FROM %i WHERE post_id = %d ORDER BY creationdate DESC, id DESC LIMIT 1',
					$wpdb->prefix . $this->core->table_name,
					(int) $post_id
				)
			);
			if ( $result ) {
				/* translators: %s: human readable time difference, e.g. "2 hours". */
				$ago = sprintf( __( '%s ago', 'support-ticket-system-for-woocommerce' ), human_time_diff( strtotime( $result->creationdate . ' UTC' ) ) );
				if ( (int) $result->agent > 0 ) {
					/* translators: %s: time since the reply, e.g. "2 hours ago". */
					echo esc_html( sprintf( __( 'Store replied %s', 'support-ticket-system-for-woocommerce' ), $ago ) );
				} else {
					/* translators: %s: time since the message, e.g. "2 hours ago". */
					echo '<strong>' . esc_html( sprintf( __( 'Customer wrote %s, awaiting reply', 'support-ticket-system-for-woocommerce' ), $ago ) ) . '</strong>';
				}
			} elseif ( 'closed' !== $this->core->get_ticket_status_slug( $post_id ) ) {
				echo '<strong>' . esc_html__( 'No reply yet', 'support-ticket-system-for-woocommerce' ) . '</strong>';
			}
		}

		/**
		 * Status, priority and subject filters above the tickets list.
		 *
		 * @param string $post_type Post type.
		 */
		public function filter_tickets( $post_type ) {
			if ( 'stsw_tickets' !== $post_type ) {
				return;
			}
			foreach ( array( 'stsw_tickets_status', 'stsw_tickets_priority', 'stsw_tickets_subject' ) as $taxonomy ) {
				$object = get_taxonomy( $taxonomy );
				$terms  = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
					)
				);
				if ( ! $object || is_wp_error( $terms ) || empty( $terms ) ) {
					continue;
				}
				$current = isset( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo '<label class="screen-reader-text" for="' . esc_attr( $taxonomy ) . '">' . esc_html( $object->labels->name ) . '</label>';
				echo '<select name="' . esc_attr( $taxonomy ) . '" id="' . esc_attr( $taxonomy ) . '" class="postform">';
				/* translators: %s: taxonomy name, e.g. "Status". */
				echo '<option value="">' . esc_html( sprintf( __( 'All: %s', 'support-ticket-system-for-woocommerce' ), $object->labels->name ) ) . '</option>';
				foreach ( $terms as $term ) {
					printf( '<option value="%1$s"%2$s>%3$s (%4$d)</option>', esc_attr( $term->slug ), selected( $current, $term->slug, false ), esc_html( $term->name ), (int) $term->count );
				}
				echo '</select>';
			}
		}

		/*
		|--------------------------------------------------------------------------
		| Priority colours and subject assignees (term meta names unchanged)
		|--------------------------------------------------------------------------
		*/

		/**
		 * Colour field on the "add priority" form.
		 */
		public function priority_add_field() {
			wp_nonce_field( 'wpfactory_wc_sts_term', 'wpfactory_wc_sts_term_nonce' );
			echo '<div class="form-field"><label for="stsw-priority-color">' . esc_html__( 'Color', 'support-ticket-system-for-woocommerce' ) . '</label><input type="text" class="stsw-color-field" id="stsw-priority-color" name="stsw_priority_color" value="" /></div>';
		}

		/**
		 * Colour field on the "edit priority" form.
		 *
		 * @param WP_Term $term Term.
		 */
		public function priority_edit_field( $term ) {
			wp_nonce_field( 'wpfactory_wc_sts_term', 'wpfactory_wc_sts_term_nonce' );
			$color = (string) get_term_meta( $term->term_id, WPFactory_WC_STS_Inc::META_COLOR, true );
			echo '<tr class="form-field"><th scope="row"><label for="stsw-priority-color">' . esc_html__( 'Color', 'support-ticket-system-for-woocommerce' ) . '</label></th><td><input type="text" class="stsw-color-field" id="stsw-priority-color" name="stsw_priority_color" value="' . esc_attr( $color ) . '" /></td></tr>';
		}

		/**
		 * Saves the priority colour.
		 *
		 * @param int $term_id Term ID.
		 */
		public function save_priority_color( $term_id ) {
			if ( ! $this->term_form_is_valid() || ! isset( $_POST['stsw_priority_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in term_form_is_valid().
				return;
			}
			$color = sanitize_hex_color( wp_unslash( $_POST['stsw_priority_color'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, WPFactory_WC_STS_Inc::META_COLOR, $color ? $color : '' );
		}

		/**
		 * Assignee field on the "add subject" form.
		 */
		public function subject_add_field() {
			wp_nonce_field( 'wpfactory_wc_sts_term', 'wpfactory_wc_sts_term_nonce' );
			echo '<div class="form-field"><label for="stsw-subject-assignee">' . esc_html__( 'Assign tickets with this subject to', 'support-ticket-system-for-woocommerce' ) . '</label>';
			$this->render_user_select( 'stsw_subject_assignee', 0, __( 'Nobody', 'support-ticket-system-for-woocommerce' ), 'stsw-subject-assignee' );
			echo '</div>';
		}

		/**
		 * Assignee field on the "edit subject" form.
		 *
		 * @param WP_Term $term Term.
		 */
		public function subject_edit_field( $term ) {
			wp_nonce_field( 'wpfactory_wc_sts_term', 'wpfactory_wc_sts_term_nonce' );
			echo '<tr class="form-field"><th scope="row"><label for="stsw-subject-assignee">' . esc_html__( 'Assign tickets with this subject to', 'support-ticket-system-for-woocommerce' ) . '</label></th><td>';
			$this->render_user_select( 'stsw_subject_assignee', (int) get_term_meta( $term->term_id, WPFactory_WC_STS_Inc::META_SUBJECT_ASSIGNEE, true ), __( 'Nobody', 'support-ticket-system-for-woocommerce' ), 'stsw-subject-assignee' );
			echo '</td></tr>';
		}

		/**
		 * Saves the subject assignee.
		 *
		 * @param int $term_id Term ID.
		 */
		public function save_subject_assignee( $term_id ) {
			if ( ! $this->term_form_is_valid() || ! isset( $_POST['stsw_subject_assignee'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in term_form_is_valid().
				return;
			}
			$user = absint( $_POST['stsw_subject_assignee'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $user && get_user_by( 'id', $user ) ) {
				update_term_meta( $term_id, WPFactory_WC_STS_Inc::META_SUBJECT_ASSIGNEE, $user );
			} else {
				delete_term_meta( $term_id, WPFactory_WC_STS_Inc::META_SUBJECT_ASSIGNEE );
			}
		}

		/**
		 * Nonce and capability for the term forms.
		 *
		 * @return bool
		 */
		protected function term_form_is_valid() {
			return isset( $_POST['wpfactory_wc_sts_term_nonce'] ) &&
				wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpfactory_wc_sts_term_nonce'] ) ), 'wpfactory_wc_sts_term' ) &&
				$this->core->can_manage_tickets();
		}

		/**
		 * Colour column on the priorities screen.
		 *
		 * @param array $columns Columns.
		 *
		 * @return array
		 */
		public function priority_columns( $columns ) {
			$columns['colors'] = __( 'Color', 'support-ticket-system-for-woocommerce' );
			return $columns;
		}

		/**
		 * Colour column content.
		 *
		 * @param string $content     Content.
		 * @param string $column_name Column.
		 * @param int    $term_id     Term ID.
		 *
		 * @return string
		 */
		public function priority_column_content( $content, $column_name, $term_id ) {
			if ( 'colors' !== $column_name ) {
				return $content;
			}
			$color = sanitize_hex_color( (string) get_term_meta( $term_id, WPFactory_WC_STS_Inc::META_COLOR, true ) );
			return $color ? '<span style="display:inline-block;width:1.5em;height:1.5em;border-radius:3px;vertical-align:middle;background:' . esc_attr( $color ) . '"></span> ' . esc_html( $color ) : '';
		}

		/**
		 * Assignee column on the subjects screen.
		 *
		 * @param array $columns Columns.
		 *
		 * @return array
		 */
		public function subject_columns( $columns ) {
			$columns['assignee'] = __( 'Assignee', 'support-ticket-system-for-woocommerce' );
			return $columns;
		}

		/**
		 * Assignee column content.
		 *
		 * @param string $content     Content.
		 * @param string $column_name Column.
		 * @param int    $term_id     Term ID.
		 *
		 * @return string
		 */
		public function subject_column_content( $content, $column_name, $term_id ) {
			if ( 'assignee' !== $column_name ) {
				return $content;
			}
			return esc_html( $this->core->get_user_name( (int) get_term_meta( $term_id, WPFactory_WC_STS_Inc::META_SUBJECT_ASSIGNEE, true ) ) );
		}
	}

endif;

return WPFactory_WC_STS_Admin::get_instance();
