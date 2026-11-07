<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - WPFactory_WC_STS_Inc Class
 *
 * @version 2.2.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Inc' ) ) :

	/**
	 * WPFactory_WC_STS_Inc class.
	 *
	 * @version 2.2.0
	 */
	class WPFactory_WC_STS_Inc {

		/**
		 * Plugin identifier.
		 *
		 * @var string
		 */
		public $plugin = 'STSWooCommerce';

		/**
		 * Plugin name.
		 *
		 * @var string
		 */
		public $name = 'Support Ticket System for WooCommerce';

		/**
		 * Table name for storing responses.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $table_name = 'stsw_responses';

		/**
		 * Database version for the table.
		 *
		 * @var string
		 */
		public $stswpro_table_db_version = '1.4';

		/**
		 * Whether the last front-end form submission saved something.
		 *
		 * @since 2.2.1
		 *
		 * @var bool
		 */
		public $last_saved = false;

		/**
		 * The instance created when the plugin loads (set at the bottom of this file).
		 *
		 * @since 2.2.1
		 *
		 * @var WPFactory_WC_STS_Inc|null
		 */
		public static $main_instance = null;

		/**
		 * Returns the loaded instance. Creating another one would register every hook a second time.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @return WPFactory_WC_STS_Inc
		 */
		public static function get_main_instance() {
			if ( null === self::$main_instance ) {
				self::$main_instance = new self();
			}
			return self::$main_instance;
		}

		/**
		 * Constructor.
		 *
		 * @version 2.2.0
		 *
		 * @todo (v2.2.0) What is `hook` in `add_filter( 'hook', array( $this, 'send_with_placeholders' ), 10, 2 )`?
		 */
		public function __construct() {

			add_action( 'init', array( $this, 'register_ticket_post_type_and_taxonomy' ) );

			add_action( 'admin_init', array( $this, 'add_ticket_meta_boxes' ) );

			add_action( 'save_post', array( $this, 'save_fields' ) );
			add_action( 'post_updated', array( $this, 'notify_user_on_wp_edit' ) );
			add_action( 'admin_menu', array( $this, 'menu_page' ) );
			add_action( 'wp_ajax_wpfactory_wc_sts_response_delete', array( $this, 'response_delete' ) );
			add_action( 'before_delete_post', array( $this, 'delete_relevant_responses' ) );

			add_filter( 'woocommerce_account_menu_items', array( $this, 'tickets_link' ) );
			add_action( 'init', array( $this, 'stswpro_add_endpoint' ) );
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'stswpro_add_my_account_order_actions' ), 10, 2 );
			add_action( 'woocommerce_account_tickets_endpoint', array( $this, 'stswpro_my_account_endpoint_content' ) );
			add_shortcode( 'stsw_user_tickets', array( $this, 'shortcode_output' ) );

			// My Account endpoint registered with WooCommerce (title, `is_wc_endpoint_url()`).
			add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
			add_filter( 'woocommerce_endpoint_tickets_title', array( $this, 'endpoint_title' ) );

			// One-time steps after install or update (rewrite flush, default statuses).
			add_action( 'init', array( $this, 'maybe_run_version_update' ), 20 );
			register_activation_hook( WPFACTORY_WC_STS_FILE, array( $this, 'on_activation' ) );

			// Front-end forms are handled before output, then redirected (no double submit on refresh).
			add_action( 'template_redirect', array( $this, 'handle_frontend_forms' ) );

			// "Send test email" button in Settings > Notifications.
			add_action( 'admin_post_wpfactory_wc_sts_test_email', array( $this, 'send_test_email' ) );

			add_filter( 'manage_stsw_tickets_posts_columns', array( $this, 'add_column_header' ) );
			add_action( 'manage_stsw_tickets_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
			add_filter( 'manage_edit-stsw_tickets_sortable_columns', array( $this, 'add_column_header' ) );
			add_filter( 'manage_stsw_tickets_posts_columns', array( $this, 'column_order' ) );

			add_action( 'restrict_manage_posts', array( $this, 'stswpro_filter_tickets' ) );
			register_activation_hook( WPFACTORY_WC_STS_FILE, array( $this, 'ticket_response_table_install' ) );
			add_action( 'plugins_loaded', array( $this, 'stswpro_tickets_table_update_db_check' ) );
			add_action( 'woocommerce_view_order', array( $this, 'stswpro_view_order' ), 20 );

			add_filter( 'hook', array( $this, 'send_with_placeholders' ), 10, 2 );
		}

		/**
		 * Ticket response table install.
		 *
		 * @version 2.2.0
		 */
		public function ticket_response_table_install() {
			global $wpdb;

			$table_name = $wpdb->prefix . $this->table_name;

			$sql = 'CREATE TABLE ' . sanitize_text_field( $table_name ) . ' (
				id int(11) NOT NULL AUTO_INCREMENT,
				user int(11) NOT NULL,
				post_id int(11) NOT NULL,
				creationdate DATETIME NULL,
				agent int(11) NOT NULL,
				content longtext NOT NULL,
				attachments longtext NOT NULL,
				PRIMARY KEY  (id)
			);';

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			// Save current database version for later use (on upgrade).
			add_option( 'stswpro_tickets_table_db_version', sanitize_text_field( $this->stswpro_table_db_version ) );

			/**
			 * New version of table.
			 */
			$installed_ver = get_option( 'stswpro_tickets_table_db_version' );
			if ( $installed_ver !== $this->stswpro_table_db_version ) {
				$sql = 'CREATE TABLE ' . sanitize_text_field( $table_name ) . ' (
					id int(11) NOT NULL AUTO_INCREMENT,
					user int(11) NOT NULL,
					post_id int(11) NOT NULL,
					creationdate DATETIME NULL,
					agent int(11) NOT NULL,
					content longtext NOT NULL,
					attachments longtext NOT NULL,
					PRIMARY KEY  (id)
				);';

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );

				// Notice that we are updating option, rather than adding it.
				update_option( 'stswpro_tickets_table_db_version', sanitize_text_field( $this->stswpro_table_db_version ) );
			}
		}

		/**
		 * Trick to update plugin database, see docs.
		 *
		 * @version 2.2.0
		 */
		public function stswpro_tickets_table_update_db_check() {
			// `get_option` (not `get_site_option`): the version is stored per site, so on multisite the
			// old check never matched and `dbDelta` ran on every request.
			if ( get_option( 'stswpro_tickets_table_db_version' ) !== $this->stswpro_table_db_version ) {
				$this->ticket_response_table_install();
			}
		}

		/**
		 * On activation: make the next request run the one-time update steps (it flushes rewrite rules
		 * after the `tickets` endpoint is registered, which cannot be done inside the activation request).
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 */
		public function on_activation() {
			delete_option( 'wpfactory_wc_sts_version' );
		}

		/**
		 * One-time steps when the plugin version changes (new install, update, reactivation).
		 *
		 * Safe for existing stores: only adds what is missing, never changes settings or existing terms.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 */
		public function maybe_run_version_update() {
			if ( get_option( 'wpfactory_wc_sts_version' ) === WPFACTORY_WC_STS_VERSION ) {
				return;
			}

			// Statuses used by the plugin and its dashboard. Existing terms (matched by name or slug) are left as they are.
			$statuses = array(
				'open'        => __( 'Open', 'support-ticket-system-for-woocommerce' ),
				'in-progress' => __( 'In Progress', 'support-ticket-system-for-woocommerce' ),
				'closed'      => __( 'Closed', 'support-ticket-system-for-woocommerce' ),
			);
			foreach ( $statuses as $slug => $name ) {
				if ( ! term_exists( $slug, 'stsw_tickets_status' ) && ! term_exists( $name, 'stsw_tickets_status' ) ) {
					wp_insert_term( $name, 'stsw_tickets_status', array( 'slug' => $slug ) );
				}
			}

			// The `tickets` endpoint is registered by now, so customers do not get a 404 on My Account > Tickets.
			flush_rewrite_rules( false );

			update_option( 'wpfactory_wc_sts_version', WPFACTORY_WC_STS_VERSION, false );
		}

		/**
		 * Registers `tickets` as a WooCommerce My Account endpoint.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param array $vars WooCommerce query vars.
		 *
		 * @return array
		 */
		public function add_query_var( $vars ) {
			if ( ! isset( $vars['tickets'] ) ) {
				$vars['tickets'] = 'tickets';
			}
			return $vars;
		}

		/**
		 * My Account endpoint title.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @return string
		 */
		public function endpoint_title() {
			$label = get_option( $this->plugin . 'renameAccountTabLink' );
			return ! empty( $label ) ? $label : __( 'Tickets', 'support-ticket-system-for-woocommerce' );
		}

		/**
		 * Shortcode `[stsw_user_tickets]`: returns the ticket screen instead of printing it.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @return string
		 */
		public function shortcode_output() {
			ob_start();
			$this->stswpro_my_account_endpoint_content();
			return ob_get_clean();
		}

		/**
		 * Handles the front-end "new ticket" and "new response" forms before any output,
		 * shows the result as a WooCommerce notice and redirects back (Post/Redirect/Get).
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 */
		public function handle_frontend_forms() {
			if (
				! isset( $_SERVER['REQUEST_METHOD'] ) ||
				'POST' !== $_SERVER['REQUEST_METHOD'] ||
				! is_user_logged_in() ||
				( ! isset( $_POST['stswticketfrontend'] ) && ! isset( $_POST['stswresponsefrontend'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in save_ticket() / save_response().
			) {
				return;
			}

			$nonce_field = isset( $_POST['stswticketfrontend'] ) ? 'stswticketfrontend' : 'stswresponsefrontend'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_field ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$this->add_frontend_notice( __( 'This form expired. Please refresh the page and send your message again.', 'support-ticket-system-for-woocommerce' ), 'error' );
				$this->redirect_back();
			}

			ob_start();
			if ( 'stswticketfrontend' === $nonce_field ) {
				$this->save_ticket();
			} else {
				$this->save_response();
			}
			$message = trim( (string) ob_get_clean() );

			if ( $this->last_saved ) {
				$this->add_frontend_notice(
					'' !== $message ? $message : __( 'Thanks, your message was sent. We will reply by email.', 'support-ticket-system-for-woocommerce' ),
					'success'
				);
			} else {
				$this->add_frontend_notice(
					'' !== $message ? $message : __( 'Your message could not be sent. Please check the form and try again.', 'support-ticket-system-for-woocommerce' ),
					'error'
				);
			}
			$this->redirect_back();
		}

		/**
		 * Adds a notice shown on the next page view.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param string $message Message (HTML allowed, filtered on output).
		 * @param string $type    Notice type: success, error or notice.
		 */
		public function add_frontend_notice( $message, $type = 'success' ) {
			if ( function_exists( 'wc_add_notice' ) && function_exists( 'WC' ) && WC()->session ) {
				wc_add_notice( wp_kses_post( $message ), $type );
			}
		}

		/**
		 * Redirects back to the page the form was posted from.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 */
		public function redirect_back() {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			$url  = set_url_scheme( 'http://' . $host . $uri );
			wp_safe_redirect( $url ? $url : wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		/**
		 * Register ticket post type and status taxonomy.
		 *
		 * @version 2.2.0
		 */
		public function register_ticket_post_type_and_taxonomy() {
			// Tickets post type.
			register_post_type(
				'stsw_tickets',
				array(
					'labels'              => array(
						'name'              => esc_html__( 'Tickets', 'support-ticket-system-for-woocommerce' ),
						'singular_name'     => esc_html__( 'Ticket', 'support-ticket-system-for-woocommerce' ),
						'search_items'      => esc_html__( 'Search Tickets', 'support-ticket-system-for-woocommerce' ),
						'all_items'         => esc_html__( 'All Tickets', 'support-ticket-system-for-woocommerce' ),
						'parent_item'       => esc_html__( 'Parent Ticket', 'support-ticket-system-for-woocommerce' ),
						'parent_item_colon' => esc_html__( 'Parent Ticket:', 'support-ticket-system-for-woocommerce' ),
						'edit_item'         => esc_html__( 'Edit Ticket', 'support-ticket-system-for-woocommerce' ),
						'update_item'       => esc_html__( 'Update Ticket', 'support-ticket-system-for-woocommerce' ),
						'add_new_item'      => esc_html__( 'Add New Ticket', 'support-ticket-system-for-woocommerce' ),
						'add_new'           => esc_html__( 'New Ticket', 'support-ticket-system-for-woocommerce' ),
						'new_item_name'     => esc_html__( 'New Ticket Name', 'support-ticket-system-for-woocommerce' ),
						'new_item'          => esc_html__( 'New Ticket', 'support-ticket-system-for-woocommerce' ),
						'menu_name'         => esc_html__( 'Tickets', 'support-ticket-system-for-woocommerce' ),
						'not_found'         => esc_html__( 'No Tickets found', 'support-ticket-system-for-woocommerce' ),
					),
					'description'         => esc_html__( 'Adding and editing my Tickets', 'support-ticket-system-for-woocommerce' ),
					'menu_icon'           => 'dashicons-calendar',
					'supports'            => array( 'title' ),
					'show_in_rest'        => false,
					'capability_type'     => 'page',
					// Tickets hold customer personal data: only store staff may read or edit them (not every role with `edit_pages`).
					'capabilities'        => $this->get_ticket_capabilities( 'manage_woocommerce' ),
					'map_meta_cap'        => true,
					'hierarchical'        => false,
					'menu_position'       => null,
					'public'              => false, // it's not public, it shouldn't have it's own permalink, and so on.
					'publicly_queryable'  => false,
					'show_ui'             => true,  // you should be able to edit it in wp-admin.
					'show_in_menu'        => false,
					'exclude_from_search' => true,  // you should exclude it from search results.
					'show_in_nav_menus'   => false, // you shouldn't be able to add it to menus.
					'has_archive'         => false, // it shouldn't have archive page.
					'rewrite'             => false, // it shouldn't have rewrite rules.
				)
			);

			// Status taxonomy.
			$labels = array(
				'name'              => _x( 'Status', 'taxonomy general name', 'support-ticket-system-for-woocommerce' ),
				'singular_name'     => _x( 'Status', 'taxonomy singular name', 'support-ticket-system-for-woocommerce' ),
				'search_items'      => esc_html__( 'Search Status', 'support-ticket-system-for-woocommerce' ),
				'all_items'         => esc_html__( 'All Status', 'support-ticket-system-for-woocommerce' ),
				'parent_item'       => esc_html__( 'Parent Status', 'support-ticket-system-for-woocommerce' ),
				'parent_item_colon' => esc_html__( 'Parent Status:', 'support-ticket-system-for-woocommerce' ),
				'edit_item'         => esc_html__( 'Edit Status', 'support-ticket-system-for-woocommerce' ),
				'update_item'       => esc_html__( 'Update Status', 'support-ticket-system-for-woocommerce' ),
				'add_new_item'      => esc_html__( 'Add New Status', 'support-ticket-system-for-woocommerce' ),
				'new_item_name'     => esc_html__( 'New Status Name', 'support-ticket-system-for-woocommerce' ),
				'not_found'         => esc_html__( 'No Status found.', 'support-ticket-system-for-woocommerce' ),
				'menu_name'         => esc_html__( 'Status', 'support-ticket-system-for-woocommerce' ),
			);

			register_taxonomy(
				'stsw_tickets_status',
				array( 'stsw_tickets' ),
				array(
					'hierarchical'      => true,
					'labels'            => $labels,
					'show_ui'           => true,
					'show_admin_column' => true,
					'query_var'         => true,
					'rewrite'           => array( 'slug' => 'stsw_tickets_status' ),
				)
			);
		}

		/**
		 * Primitive capabilities for the `stsw_tickets` post type, all mapped to one capability.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param string $cap Capability required for every ticket action.
		 *
		 * @return array
		 */
		public function get_ticket_capabilities( $cap ) {
			return array(
				'edit_posts'             => $cap,
				'edit_others_posts'      => $cap,
				'edit_private_posts'     => $cap,
				'edit_published_posts'   => $cap,
				'publish_posts'          => $cap,
				'read_private_posts'     => $cap,
				'delete_posts'           => $cap,
				'delete_others_posts'    => $cap,
				'delete_private_posts'   => $cap,
				'delete_published_posts' => $cap,
				'create_posts'           => $cap,
				'read'                   => 'read',
			);
		}

		/**
		 * Add meta boxes to tickets newly created post type.
		 *
		 * @version 2.2.0
		 */
		public function add_ticket_meta_boxes() {
			add_meta_box(
				'stswpro_ticketContent',
				esc_html__( 'Ticket Content', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'render_ticket_content_meta_box' ),
				'stsw_tickets',
				'normal',
				'high'
			);

			add_meta_box(
				'stswpro_ticketResponded',
				esc_html__( 'Responses', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'responses' ),
				'stsw_tickets',
				'normal',
				'high'
			);

			add_meta_box(
				'appInfo',
				esc_html__( 'Ticket Info', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'render_ticket_info_meta_box' ),
				'stsw_tickets',
				'side',
				'high'
			);

			add_meta_box(
				'assignTouser',
				esc_html__( 'Assign to User', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'render_assign_to_user_meta_box' ),
				'stsw_tickets',
				'side',
				'high'
			);

			add_meta_box(
				'stswpro_ticketResponses',
				esc_html__( 'New Response', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'render_new_response_meta_box' ),
				'stsw_tickets',
				'normal',
				'high'
			);
		}

		/**
		 * Get ticket user ID.
		 *
		 * @version 2.1.4
		 * @since   2.1.0
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return int User ID.
		 */
		public function get_ticket_user_id( $ticket_id ) {
			return (int) get_post_meta( $ticket_id, 'STSWooCommerceProticketuser', true );
		}

		/**
		 * Set ticket user ID.
		 *
		 * @version 2.1.0
		 * @since   2.1.0
		 *
		 * @param int $ticket_id Ticket ID.
		 * @param int $user_id   User ID.
		 *
		 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure or if the value passed to the function is the same as the one that is already in the database.
		 *
		 * @todo (v2.1.0) why `STSWooCommerceProticketuser` (and not `STSWooCommerceticketuser`)?
		 */
		public function set_ticket_user_id( $ticket_id, $user_id ) {
			return update_post_meta( $ticket_id, 'STSWooCommerceProticketuser', $user_id );
		}

		/**
		 * Render ticket info meta box.
		 *
		 * @version 2.2.0
		 *
		 * @param WP_Post $post The current post object.
		 */
		public function render_ticket_info_meta_box( $post ) {
			global $post;

			?>
			<b><?php esc_html_e( 'Order', 'support-ticket-system-for-woocommerce' ); ?></b>:
			<span class="proVersion"><?php esc_html_e( 'Pro Version', 'support-ticket-system-for-woocommerce' ); ?></span>
			<br/>

			<?php $user = $this->get_ticket_user_id( $post->ID ); ?>
			<b><?php esc_html_e( 'User', 'support-ticket-system-for-woocommerce' ); ?></b>:
			<?php
			if ( ! empty( $user ) ) {
				printf(
					'<a href="%1$s" target="_blank">%2$s</a>',
					esc_url( admin_url( 'user-edit.php?user_id=' . $user ) ),
					esc_html( $this->get_user_name( $user ) )
				);
			}
			?>
			<br/>

			<b><?php esc_html_e( 'Ticket Assignee', 'support-ticket-system-for-woocommerce' ); ?></b>:
			<span class="proVersion"><?php esc_html_e( 'Pro Version', 'support-ticket-system-for-woocommerce' ); ?></span>
			<?php
		}

		/**
		 * Return the name of a user based on user ID.
		 *
		 * @version 2.2.0
		 *
		 * @param int $id User ID.
		 *
		 * @return string User's full name.
		 */
		public function get_user_name( $id ) {
			$user = get_user_by( 'id', $id );
			return $user->first_name . ' ' . $user->last_name;
		}

		/**
		 * Render ticket content meta box.
		 *
		 * @version 2.2.0
		 *
		 * @param WP_Post $post The current post object.
		 */
		public function render_ticket_content_meta_box( $post ) {
			?>
			<table class="wp-list-table widefat fixed striped posts">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Issue', 'support-ticket-system-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Attachments', 'support-ticket-system-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<th><?php echo $this->format_message( $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message(). ?></th>
						<th>
							<span class="proVersion"><?php esc_html_e( 'Pro Version', 'support-ticket-system-for-woocommerce' ); ?></span>
						</th>
					</tr>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Query & display responses in ticket edit screen post box.
		 *
		 * @version 2.2.0
		 *
		 * @param WP_Post $post The current ticket post object.
		 */
		public function responses( $post ) {
			global $post;
			global $wpdb;
			$table_name = $wpdb->prefix . $this->table_name;

			// Oldest first, so the conversation reads top to bottom.
			$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT * FROM %i WHERE post_id = %d ORDER BY creationdate ASC, id ASC',
					$table_name,
					$post->ID
				)
			);
			if ( ! empty( $result ) ) {
				print "<table class='wp-list-table widefat fixed striped posts'>";
				print '<thead><tr>
					<th style="width:20%">' . esc_html__( 'Date', 'support-ticket-system-for-woocommerce' ) . '</th>
					<th style="width:20%">' . esc_html__( 'From', 'support-ticket-system-for-woocommerce' ) . '</th>
					<th>' . esc_html__( 'Message', 'support-ticket-system-for-woocommerce' ) . '</th>
					<th style="width:12%">' . esc_html__( 'Attachments', 'support-ticket-system-for-woocommerce' ) . '</th>
					<th style="width:8%">' . esc_html__( 'Action', 'support-ticket-system-for-woocommerce' ) . '</th></tr></thead><tbody>';

				foreach ( $result as $res ) {
					print "<tr class='" . (int) $res->id . "'><td>" . esc_html( $this->format_response_date( $res->creationdate ) ) . '</td><td>' . esc_html( $this->get_response_author_label( $res ) ) . '</td><td>' . $this->format_message( $res->content ) . '</td><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
					?>
					<span class='proVersion' ><?php print esc_html__( 'Pro Version', 'support-ticket-system-for-woocommerce' ); ?></span>
					<?php
					print "</td>
						<td><p id='deleteResponse'><a href='#' id='" . esc_attr( $res->id ) . "'>" . esc_html__( 'Delete', 'support-ticket-system-for-woocommerce' ) . '</a></p></td>
						</tr>';
				}
				print '</tbody></table>';
			} else {
				print esc_html__( 'No responses yet', 'support-ticket-system-for-woocommerce' );
			}
		}

		/**
		 * "Store: Name" or "Customer: Name" for a response row.
		 *
		 * Responses written by staff have the staff user in `agent`; customer responses have `agent` 0.
		 * (Before 2.2.1 the label compared `user` with 1, so store replies showed as the customer's own.)
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param object $res Response row.
		 *
		 * @return string
		 */
		public function get_response_author_label( $res ) {
			if ( (int) $res->agent > 0 ) {
				$agent = get_user_by( 'id', (int) $res->agent );
				/* translators: %s: name of the store staff member who replied. */
				return sprintf( __( 'Store: %s', 'support-ticket-system-for-woocommerce' ), $agent ? $agent->display_name : get_bloginfo( 'name' ) );
			}
			$customer = get_user_by( 'id', (int) $res->user );
			/* translators: %s: customer name. */
			return sprintf( __( 'Customer: %s', 'support-ticket-system-for-woocommerce' ), $customer ? $customer->display_name : __( 'unknown', 'support-ticket-system-for-woocommerce' ) );
		}

		/**
		 * Formats a stored GMT date in the site's date and time format.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param string $gmt_date Date in GMT, `Y-m-d H:i:s`.
		 *
		 * @return string
		 */
		public function format_response_date( $gmt_date ) {
			if ( empty( $gmt_date ) ) {
				return '';
			}
			return get_date_from_gmt( $gmt_date, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		}

		/**
		 * Escapes a ticket or response text for display, keeping line breaks.
		 *
		 * Older responses were stored HTML encoded; `esc_html()` does not double encode them.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param string $text Stored text.
		 *
		 * @return string Safe HTML.
		 */
		public function format_message( $text ) {
			return nl2br( esc_html( (string) $text ) );
		}

		/**
		 * Sets a ticket status by slug, so statuses the merchant renamed keep working.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $slug      Status slug: open, in-progress or closed.
		 */
		public function set_ticket_status( $ticket_id, $slug ) {
			$term = get_term_by( 'slug', $slug, 'stsw_tickets_status' );
			if ( $term ) {
				wp_set_object_terms( $ticket_id, (int) $term->term_id, 'stsw_tickets_status' );
				return;
			}
			$names = array(
				'open'        => 'Open',
				'in-progress' => 'In Progress',
				'closed'      => 'Closed',
			);
			wp_set_object_terms( $ticket_id, $names[ $slug ] ?? $slug, 'stsw_tickets_status' );
		}

		/**
		 * Current status slug of a ticket.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string
		 */
		public function get_ticket_status_slug( $ticket_id ) {
			$terms = wp_get_object_terms( $ticket_id, 'stsw_tickets_status', array( 'fields' => 'slugs' ) );
			return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';
		}

		/**
		 * Response create.
		 *
		 * @version 2.2.0
		 *
		 * @param WP_Post $post The current ticket post object.
		 */
		public function render_new_response_meta_box( $post ) {
			// Plain text reply (the rich editor's formatting was stripped on save anyway). Field name unchanged.
			$editor_id = sanitize_key( $this->plugin . 'response' );
			?>
			<textarea name="<?php echo esc_attr( $editor_id ); ?>" id="<?php echo esc_attr( $editor_id ); ?>" rows="6" style="width:100%" placeholder="<?php esc_attr_e( 'Write your reply to the customer', 'support-ticket-system-for-woocommerce' ); ?>"></textarea>
			<p>
				<label><input type="checkbox" name="wpfactory_wc_sts_close_ticket" value="1" /> <?php esc_html_e( 'Close the ticket with this reply', 'support-ticket-system-for-woocommerce' ); ?></label>
			</p>
			<p class="description"><?php esc_html_e( 'The reply is saved when you click Update. The customer gets an email if "Notify Customer by Email" is on.', 'support-ticket-system-for-woocommerce' ); ?></p>
			<?php

			wp_nonce_field(
				'wpfactory_wc_sts_save',
				'wpfactory_wc_sts_nonce'
			);
		}

		/**
		 * Assign to user.
		 *
		 * @version 2.2.0
		 */
		public function render_assign_to_user_meta_box() {
			?>
			<span class="proVersion"><?php esc_html_e( 'Pro Version', 'support-ticket-system-for-woocommerce' ); ?></span>
			<?php
		}

		/**
		 * Function to save any custom meta fields for tickets created.
		 *
		 * @version 2.2.0
		 */
		public function save_fields( $post_id = 0 ) {
			if (
				empty( $_POST[ $this->plugin . 'response' ] ) ||
				! $post_id ||
				'stsw_tickets' !== get_post_type( $post_id ) ||
				wp_is_post_revision( $post_id ) ||
				! current_user_can( 'edit_post', $post_id )
			) {
				return;
			}

			// Verify the nonce.
			if (
				! isset( $_POST['wpfactory_wc_sts_nonce'] ) ||
				! wp_verify_nonce(
					sanitize_text_field(
						wp_unslash(
							$_POST['wpfactory_wc_sts_nonce']
						)
					),
					'wpfactory_wc_sts_save'
				)
			) {
				return;
			}

			global $wpdb;
			$post = get_post( $post_id );

			$user         = $this->get_ticket_user_id( $post->ID );
			$current_user = wp_get_current_user();
			// Stored as plain text and escaped on output (before 2.2.1 it was HTML encoded here, so "don't" showed as "don&#039;t" in some places).
			$response   = sanitize_textarea_field( wp_unslash( $_POST[ $this->plugin . 'response' ] ) );
			$table_name = $wpdb->prefix . $this->table_name;

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table_name,
				array(
					'user'         => $user,
					'creationdate' => current_time( 'mysql', 1 ),
					'content'      => $response,
					'agent'        => (int) $current_user->ID,
					'post_id'      => (int) $post->ID,
					'attachments'  => '',
				)
			);

			// Status after a store reply: closed if asked, otherwise "in progress" when it was open (merchant-set statuses are kept).
			if ( ! empty( $_POST['wpfactory_wc_sts_close_ticket'] ) ) {
				$this->set_ticket_status( $post->ID, 'closed' );
			} elseif ( in_array( $this->get_ticket_status_slug( $post->ID ), array( '', 'open' ), true ) ) {
				$this->set_ticket_status( $post->ID, 'in-progress' );
			}
		}

		/**
		 * Function to delete the response.
		 *
		 * @version 2.2.0
		 */
		public function response_delete() {
			if ( ! isset( $_POST['id'] ) ) {
				return;
			}

			check_ajax_referer( 'wpfactory_wc_sts_response_delete', 'nonce' );

			$id = (int) $_POST['id'];

			global $wpdb;
			$table_name = $wpdb->prefix . $this->table_name;

			$ticket_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT post_id FROM %i WHERE id = %d',
					$table_name,
					$id
				)
			);

			if (
				! $ticket_id ||
				! $this->verify_ticket_current_user_id( (int) $ticket_id )
			) {
				wp_die( esc_html__( 'Wrong user.', 'support-ticket-system-for-woocommerce' ) );
			}

			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_name,
				array( 'id' => $id )
			);

			echo esc_html( (string) $id );
			die();
		}

		/**
		 * On ticket delete, clear all responses from table.
		 *
		 * @version 2.2.0
		 *
		 * @param int $post_id The ID of the ticket post being deleted.
		 */
		public function delete_relevant_responses( $post_id ) {
			// Do not run on other post types.
			$post_type = get_post_type( $post_id );
			if ( 'stsw_tickets' !== $post_type ) {
				return true;
			}

			global $wpdb;

			$table_name = $wpdb->prefix . $this->table_name;
			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_name,
				array( 'post_id' => (int) $post_id )
			);
		}

		/**
		 * Add extra columns to tickets list table for better management.
		 *
		 * @version 2.2.0
		 *
		 * @param array $columns The existing columns in the tickets list table.
		 *
		 * @return array The modified columns array.
		 */
		public function add_column_header( $columns ) {
			$columns['Order']         = esc_html__( 'Order', 'support-ticket-system-for-woocommerce' );
			$columns['User']          = esc_html__( 'User', 'support-ticket-system-for-woocommerce' );
			$columns['Assignee']      = esc_html__( 'Assignee', 'support-ticket-system-for-woocommerce' );
			$columns['Last Response'] = esc_html__( 'Last Response', 'support-ticket-system-for-woocommerce' );
			$columns['priority']      = esc_html__( 'Priority', 'support-ticket-system-for-woocommerce' );
			return $columns;
		}

		/**
		 * Populate the new columns added with relevant content.
		 *
		 * @version 2.2.0
		 *
		 * @param string $column_name The name of the column being rendered.
		 * @param int    $post_id     The ID of the ticket post.
		 */
		public function render_columns( $column_name, $post_id ) {
			if ( in_array( $column_name, array( 'priority', 'Order', 'subject', 'Assignee' ), true ) ) {
				?>
				<span class="proVersion"><?php esc_html_e( 'Pro', 'support-ticket-system-for-woocommerce' ); ?></span>
				<?php
			}

			if ( 'User' === $column_name ) {
				$user = $this->get_ticket_user_id( $post_id );
				if ( $user ) {
					echo esc_html( $this->get_user_name( $user ) );
				}
			}

			if ( 'Last Response' === $column_name ) {
				global $wpdb;
				$table_name = $wpdb->prefix . $this->table_name;
				$result     = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						'SELECT * FROM %i WHERE post_id = %d ORDER BY creationdate DESC, id DESC LIMIT 1',
						$table_name,
						(int) $post_id
					)
				);
				if ( ! empty( $result ) ) {
					$ago = sprintf(
						/* translators: %s: human readable time difference, e.g. "2 hours". */
						__( '%s ago', 'support-ticket-system-for-woocommerce' ),
						human_time_diff( strtotime( $result->creationdate . ' UTC' ) )
					);
					if ( (int) $result->agent > 0 ) {
						/* translators: %s: time since the reply, e.g. "2 hours ago". */
						echo esc_html( sprintf( __( 'Store replied %s', 'support-ticket-system-for-woocommerce' ), $ago ) );
					} else {
						/* translators: %s: time since the message, e.g. "2 hours ago". */
						echo '<strong>' . esc_html( sprintf( __( 'Customer wrote %s, awaiting reply', 'support-ticket-system-for-woocommerce' ), $ago ) ) . '</strong>';
					}
				} elseif ( 'closed' !== $this->get_ticket_status_slug( $post_id ) ) {
					echo '<strong>' . esc_html__( 'No reply yet', 'support-ticket-system-for-woocommerce' ) . '</strong>';
				}
			}
		}

		/**
		 * Column order.
		 *
		 * @param array $columns The existing columns in the tickets list table.
		 *
		 * @return array The reordered columns array.
		 */
		public function column_order( $columns ) {
			// Reorder columns.
			unset( $columns['title'] );
			unset( $columns['date'] );
			unset( $columns['Assignee'] );
			unset( $columns['Last Response'] );
			unset( $columns['Order'] );
			unset( $columns['User'] );
			unset( $columns['subject'] );
			unset( $columns['title'] );
			unset( $columns['taxonomy-stsw_tickets_status'] );
			unset( $columns['priority'] );

			return array_merge(
				$columns,
				array(
					'title'                        => esc_html__( 'Title', 'support-ticket-system-for-woocommerce' ),
					'taxonomy-stsw_tickets_status' => esc_html__( 'Status', 'support-ticket-system-for-woocommerce' ),
					'User'                         => esc_html__( 'User', 'support-ticket-system-for-woocommerce' ),
					'Last Response'                => esc_html__( 'Last Response', 'support-ticket-system-for-woocommerce' ),
					'date'                         => esc_html__( 'Date', 'support-ticket-system-for-woocommerce' ),
					'subject'                      => esc_html__( 'Subject', 'support-ticket-system-for-woocommerce' ),
					'priority'                     => esc_html__( 'Priority', 'support-ticket-system-for-woocommerce' ),
					'Order'                        => esc_html__( 'Order', 'support-ticket-system-for-woocommerce' ),
					'Assignee'                     => esc_html__( 'Assignee', 'support-ticket-system-for-woocommerce' ),
				)
			);
		}

		/**
		 * Add submenu pages to supports tickets link.
		 *
		 * @version 2.0.0
		 */
		public function menu_page() {
			add_submenu_page(
				'support-ticket-system-woocommerce',
				__( 'Tickets', 'support-ticket-system-for-woocommerce' ),
				__( 'Tickets', 'support-ticket-system-for-woocommerce' ),
				'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown
				'edit.php?post_type=stsw_tickets',
				null
			);

			add_submenu_page(
				'support-ticket-system-woocommerce',
				__( 'Priorities', 'support-ticket-system-for-woocommerce' ),
				__( 'Priorities', 'support-ticket-system-for-woocommerce' ),
				'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown
				'#',
				null
			);

			add_submenu_page(
				'support-ticket-system-woocommerce',
				__( 'Subject', 'support-ticket-system-for-woocommerce' ),
				__( 'Subject', 'support-ticket-system-for-woocommerce' ),
				'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown
				'#',
				null
			);

			add_submenu_page(
				'support-ticket-system-woocommerce',
				__( 'Settings', 'support-ticket-system-for-woocommerce' ),
				__( 'Settings', 'support-ticket-system-for-woocommerce' ),
				'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown
				admin_url( 'admin.php?page=support-ticket-system-woocommerce&tab=settings' ),
				array( $this, 'init' )
			);
		}

		/**
		 * Tickets dashboard.
		 *
		 * @version 2.2.0
		 */
		public function tickets_dashboard() {
			// Content for dashboard tab  - the default in support tickets.
			?>
			<div class='clearfix'>
				<div class='report_widget <?php print esc_attr( $this->plugin ); ?>columns3 em'>
					<b>
						<a href='<?php print esc_url( admin_url() ); ?>edit.php?post_type=stsw_tickets'>
							<?php esc_html_e( 'ALL', 'support-ticket-system-for-woocommerce' ); ?><br/>
							<?php print esc_html( $this->get_all_tickets() ); ?>
						</a>
					</b>
					</a>
				</div>
				<div class='report_widget <?php print esc_attr( $this->plugin ); ?>columns3 em'>
					<b>
						<a href='<?php print esc_url( admin_url() ); ?>edit.php?s&post_status=all&post_type=stsw_tickets&m=0&stsw_tickets_status=open'>
							<?php esc_html_e( 'OPEN', 'support-ticket-system-for-woocommerce' ); ?><br/>
							<?php print esc_html( $this->get_open_tickets() ); ?>
						</a>
					</b>
					</a>
				</div>
				<div class='report_widget <?php print esc_attr( $this->plugin ); ?>columns3 em'>
					<b>
						<a href='<?php print esc_url( admin_url() ); ?>edit.php?s&post_status=all&post_type=stsw_tickets&m=0&stsw_tickets_status=in-progress'>
							<?php esc_html_e( 'IN PROGRESS', 'support-ticket-system-for-woocommerce' ); ?><br/>
							<?php print esc_html( $this->get_in_progress_tickets() ); ?>
						</a>
					</b>
					</a>
				</div>
			</div>
			<?php
			$this->dashboard_overview();
		}

		/**
		 * Dashboard overview: setup checks, quick links and the tickets waiting for a store reply.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 */
		public function dashboard_overview() {
			$customer_url = wc_get_endpoint_url( 'tickets', '', wc_get_page_permalink( 'myaccount' ) );
			$settings_url = admin_url( 'admin.php?page=support-ticket-system-woocommerce&tab=settings' );

			if ( '1' !== (string) get_option( $this->plugin . 'mailToADmin' ) ) {
				echo '<div class="notice notice-warning inline"><p>' .
					esc_html__( 'Email alerts are off: you will not be told when a customer opens or replies to a ticket.', 'support-ticket-system-for-woocommerce' ) .
					' <a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Turn them on in Settings > Notifications', 'support-ticket-system-for-woocommerce' ) . '</a></p></div>';
			}

			echo '<p>' .
				'<a class="button" target="_blank" href="' . esc_url( $customer_url ) . '">' . esc_html__( 'View the customer page', 'support-ticket-system-for-woocommerce' ) . '</a> ' .
				'<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=stsw_tickets' ) ) . '">' . esc_html__( 'All tickets', 'support-ticket-system-for-woocommerce' ) . '</a> ' .
				'<a class="button" href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'support-ticket-system-for-woocommerce' ) . '</a>' .
			'</p>';

			echo '<h2>' . esc_html__( 'Waiting for your reply', 'support-ticket-system-for-woocommerce' ) . '</h2>';
			$waiting = $this->get_tickets_waiting_for_reply( 10 );
			if ( empty( $waiting ) ) {
				echo '<p>' . esc_html__( 'No customer is waiting for a reply. Customers open tickets from My Account > Tickets, from the "Get Help" button on their orders, or on any page with the [stsw_user_tickets] shortcode.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				return;
			}
			echo '<table class="widefat striped"><thead><tr>' .
				'<th>' . esc_html__( 'Ticket', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th>' . esc_html__( 'Customer', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th>' . esc_html__( 'Last message', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'</tr></thead><tbody>';
			foreach ( $waiting as $row ) {
				$user = get_user_by( 'id', $this->get_ticket_user_id( $row['id'] ) );
				echo '<tr>' .
					'<td><a href="' . esc_url( get_edit_post_link( $row['id'] ) ) . '">#' . (int) $row['id'] . ' ' . esc_html( get_the_title( $row['id'] ) ) . '</a></td>' .
					'<td>' . esc_html( $user ? $user->display_name : '' ) . '</td>' .
					'<td>' . esc_html(
						sprintf(
							/* translators: %s: human readable time difference, e.g. "2 hours". */
							__( '%s ago', 'support-ticket-system-for-woocommerce' ),
							human_time_diff( strtotime( $row['last'] . ' UTC' ) )
						)
					) . '</td>' .
				'</tr>';
			}
			echo '</tbody></table>';
		}

		/**
		 * Open tickets whose last message is from the customer (or that have no reply yet), oldest first.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param int $limit Maximum number of tickets.
		 *
		 * @return array List of array( 'id' => int, 'last' => 'Y-m-d H:i:s' GMT ).
		 */
		public function get_tickets_waiting_for_reply( $limit = 10 ) {
			global $wpdb;

			$ids = get_posts(
				array(
					'post_type'      => 'stsw_tickets',
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'fields'         => 'ids',
					'orderby'        => 'date',
					'order'          => 'DESC',
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
			if ( empty( $ids ) ) {
				return array();
			}

			// Latest response per ticket in one query.
			$table        = $wpdb->prefix . $this->table_name;
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$rows         = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT r.post_id, r.agent, r.creationdate FROM %i r
					INNER JOIN ( SELECT post_id, MAX(id) AS max_id FROM %i WHERE post_id IN ($placeholders) GROUP BY post_id ) m ON r.id = m.max_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					array_merge( array( $table, $table ), $ids )
				),
				OBJECT_K
			);

			$waiting = array();
			foreach ( $ids as $id ) {
				if ( isset( $rows[ $id ] ) ) {
					if ( (int) $rows[ $id ]->agent > 0 ) {
						continue; // The store replied last.
					}
					$last = $rows[ $id ]->creationdate;
				} else {
					$last = get_post_field( 'post_date_gmt', $id );
				}
				$waiting[] = array(
					'id'   => (int) $id,
					'last' => $last,
				);
			}

			usort(
				$waiting,
				function ( $a, $b ) {
					return strcmp( $a['last'], $b['last'] );
				}
			);
			return array_slice( $waiting, 0, $limit );
		}

		/**
		 * Get all tickets.
		 *
		 * @version 2.2.0
		 */
		public function get_all_tickets() {
			// Function to populate the dashboard screen.
			$args       = array(
				'post_type' => 'stsw_tickets',
			);
			$the_query  = new WP_Query( $args );
			$total_post = $the_query->found_posts;
			return esc_html( $total_post );
		}

		/**
		 * Get open tickets.
		 *
		 * @version 2.2.0
		 */
		public function get_open_tickets() {
			// Function to populate the dashboard screen.
			$the_query  = new WP_Query(
				array(
					'post_type' => 'stsw_tickets',
					'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'stsw_tickets_status',
							'field'    => 'slug',
							'terms'    => 'open',
						),
					),
				)
			);
			$total_post = $the_query->found_posts;
			return esc_html( $total_post );
		}

		/**
		 * Get in progress tickets.
		 *
		 * @version 2.2.0
		 */
		public function get_in_progress_tickets() {
			// Function to populate the dashboard screen.
			$the_query  = new WP_Query(
				array(
					'post_type' => 'stsw_tickets',
					'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'stsw_tickets_status',
							'field'    => 'slug',
							'terms'    => 'in-progress',
						),
					),
				)
			);
			$total_post = $the_query->found_posts;
			return esc_html( $total_post );
		}

		/**
		 * Add my account order actions.
		 *
		 * @version 2.2.0
		 *
		 * @param array $actions The current order actions.
		 */
		public function stswpro_add_my_account_order_actions( $actions, $order = null ) {
			// Add a button to actions column of my account orders page.
			if (
				get_option( $this->plugin . 'renameOrderButtonLink' ) &&
				! empty( get_option( $this->plugin . 'renameOrderButtonLink' ) )
			) {
				$button_title = get_option( $this->plugin . 'renameOrderButtonLink' );
			} else {
				$button_title = esc_html__( 'Get Help', 'support-ticket-system-for-woocommerce' );
			}
			$actions['help'] = array(
				// Adjust URL as needed.
				'url'  => add_query_arg( 'order', $order ? $order->get_order_number() : '', $this->get_customer_tickets_url() ), // Works with plain permalinks; the order number pre-fills the title.
				'name' => esc_html( $button_title ),
			);

			return $actions;
		}

		/**
		 * Add ticketing functionality to users account page.
		 *
		 * @version 2.2.0
		 *
		 * @param array $menu_links The current menu links.
		 */
		public function tickets_link( $menu_links ) {
			// Add tab to my account page to ticketing system.
			if (
				get_option( $this->plugin . 'renameAccountTabLink' ) &&
				! empty( get_option( $this->plugin . 'renameAccountTabLink' ) )
			) {
				$new = array( 'tickets' => esc_html( get_option( $this->plugin . 'renameAccountTabLink' ) ) );
			} else {
				$new = array( 'tickets' => esc_html__( 'Tickets', 'support-ticket-system-for-woocommerce' ) );
			}

			$menu_links = array_slice( $menu_links, 0, 5, true ) + $new + array_slice( $menu_links, 1, null, true );
			return $menu_links;
		}

		/**
		 * Register permalink endpoint.
		 */
		public function stswpro_add_endpoint() {
			add_rewrite_endpoint( 'tickets', EP_PAGES );
		}

		/**
		 * My account endpoint content.
		 *
		 * Add content to support ticketing system.
		 *
		 * @version 2.2.0
		 *
		 * @todo (v2.2.0) Fix: when this function is used for the `[stsw_user_tickets]` shortcode, it has to `return` the content instead of directly printing it.
		 */
		public function stswpro_my_account_endpoint_content() {
			// User needs to be logged in.
			if ( is_user_logged_in() ) {
				// Forms are saved in `handle_frontend_forms()`; My Account prints notices itself, other pages (shortcode) do it here.
				if ( function_exists( 'wc_print_notices' ) && ! is_account_page() && function_exists( 'WC' ) && WC()->session ) {
					wc_print_notices();
				}
				?>
				<div class='stswproaccordion'>
					<?php
					$customer = wp_get_current_user();

					$cat_query = '';
					// Hide closed tickets setting.
					if ( get_option( $this->plugin . 'hideClosed' ) && get_option( $this->plugin . 'hideClosed' ) === '1' ) {
						$category = 'stsw_tickets_status';
						$term     = 'closed';

						$cat_query = array(
							array(
								'taxonomy' => sanitize_text_field( $category ),
								'field'    => 'slug',
								'terms'    => sanitize_text_field( $term ),
								'operator' => 'NOT IN',
							),
						);

					}

					$meta_query = array();
					// Administrators see all tickets; everyone else sees the tickets they opened, whatever their role
					// (before 2.2.1 roles other than `customer`, e.g. subscribers or wholesale roles, saw none of their tickets).
					if ( ! in_array( 'administrator', $customer->roles, true ) ) {
						$meta_query[] = array(
							'key'     => 'STSWooCommerceProticketuser',
							'value'   => (int) $customer->ID,
							'compare' => '=',
						);
					}

					$args = array(
						'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'tax_query'      => $cat_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'post_type'      => 'stsw_tickets',
						'posts_per_page' => -1,
					);

					$query = new WP_Query( $args );
					if ( $query->have_posts() ) {
						?>
						<h3>
							<?php esc_html_e( 'TICKETS', 'support-ticket-system-for-woocommerce' ); ?> <i class='fa fa-angle-down'></i>
						</h3>
						<div class="postbox">
							<div class='stswproaccordion2'>
							<?php
							while ( $query->have_posts() ) {
								$query->the_post();

								$status = wp_get_post_terms( get_the_ID(), 'stsw_tickets_status', 'name' );
								?>

								<h3>
									<?php the_title(); ?> - <?php echo esc_html( get_the_date() ); ?> -
									<?php
									if ( ! empty( $status ) ) {
										print esc_html( $status[0]->name );}
									?>
										<i class='fa fa-angle-down'></i>
								</h3>
								<div class='post-content'>

								<table class='wp-list-table widefat fixed striped posts'>
									<thead>
										<tr>
											<th><?php esc_html_e( 'Title', 'support-ticket-system-for-woocommerce' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<th><?php echo wp_kses_post( wpautop( esc_html( get_post_field( 'post_content', get_the_ID(), 'raw' ) ) ) ); // Customer text: escaped, shortcodes never run. ?></th>
										</tr>
									</tbody>
								</table>

								<?php

								global $wpdb;
								$table_name = $wpdb->prefix . $this->table_name;
								$result     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										'SELECT * FROM %i WHERE post_id = %d ORDER BY creationdate ASC, id ASC',
										$table_name,
										(int) get_the_ID()
									)
								);
								if ( ! empty( $result ) ) {
									print '<h4>' . esc_html__( 'Responses', 'support-ticket-system-for-woocommerce' ) . '</h4>';
									print "<table class='wp-list-table widefat fixed striped posts'>";
									print '<tr>
										<th>' . esc_html__( 'Date', 'support-ticket-system-for-woocommerce' ) . '</th>
										<th>' . esc_html__( 'From', 'support-ticket-system-for-woocommerce' ) . '</th>
										<th>' . esc_html__( 'Content', 'support-ticket-system-for-woocommerce' ) . '</th>';

									foreach ( $result as $res ) {
										// Store replies carry the staff user in `agent`; the customer sees the store name, not staff names.
										if ( (int) $res->agent > 0 ) {
											$who = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
										} elseif ( (int) $res->user === (int) $customer->ID ) {
											$who = __( 'You', 'support-ticket-system-for-woocommerce' );
										} else {
											$who = $this->get_response_author_label( $res );
										}

										print '<tr>
											<td>' . esc_html( $this->format_response_date( $res->creationdate ) ) . '</td>
											<td>' . esc_html( $who ) . '</td>
											<td>' . $this->format_message( $res->content ) . '</td>
										</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
									}
									print '</table>';
								}
								?>
								<?php if ( ! empty( $status ) ) { ?>
									<div class='stswproaccordion3'>
										<h4><?php esc_html_e( 'Add a response', 'support-ticket-system-for-woocommerce' ); ?> <i class='fa fa-plus'></i></h4>
										<div>
											<form class="<?php print esc_attr( $this->plugin ); ?>new_response" name="<?php print esc_attr( $this->plugin ); ?>new_response" method="post" >
												<textarea id="content" tabindex="3" class='tinymce-enabled' name="response_content" cols="50" rows="3"></textarea>
												<input type="hidden" value="<?php print esc_attr( $customer->ID ); ?>"  name="customer_id" />
												<input type="hidden" value="<?php print esc_attr( get_the_ID() ); ?>"  name="post_id" />

												<label for='closeTicket'><?php esc_html_e( 'Close Ticket', 'support-ticket-system-for-woocommerce' ); ?></label> <input type='checkbox' name='closeTicket' value='1' />
												<p></p>
												<?php wp_nonce_field( 'stswresponsefrontend', 'stswresponsefrontend' ); ?>
												<input type="submit" value="<?php esc_html_e( 'Send', 'support-ticket-system-for-woocommerce' ); ?>" tabindex="6" id="submit" name="submit" />

											</form>
										</div>
									</div>
								<?php } ?>
								</div>
								<?php
							}
							?>
							</div>
						</div>
						<?php
					} else {
						esc_html_e( ' No Tickets found', 'support-ticket-system-for-woocommerce' );
					}
					?>

					<h3><?php esc_html_e( 'ADD NEW TICKET', 'support-ticket-system-for-woocommerce' ); ?> <i class='fa fa-plus'></i></h3>

					<div class="postbox">
						<form  class="<?php print esc_html( $this->plugin ); ?>new_ticket" name="<?php print esc_html( $this->plugin ); ?>new_ticket" method="post">

						<p>
							<label for="title">
								<?php esc_html_e( 'Title', 'support-ticket-system-for-woocommerce' ); ?>
							</label><br />
							<?php
							// "Get Help" on an order links here with ?order=<number>: pre-fill the title when the order is the customer's.
							$prefill = '';
							if ( ! empty( $_GET['order'] ) && function_exists( 'wc_get_orders' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pre-fill.
								$order_number = sanitize_text_field( wp_unslash( $_GET['order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								$order_obj    = wc_get_order( absint( $order_number ) );
								if ( $order_obj && (int) $order_obj->get_customer_id() === (int) get_current_user_id() ) {
									/* translators: %s: order number. */
									$prefill = sprintf( __( 'Order #%s: ', 'support-ticket-system-for-woocommerce' ), $order_obj->get_order_number() );
								}
							}
							?>
							<input type="text" id="title" value="<?php echo esc_attr( $prefill ); ?>" tabindex="1" size="20" name="title" />
						</p>

						<p>
							<label for="content">
								<?php esc_html_e( 'Message', 'support-ticket-system-for-woocommerce' ); ?>
							</label><br />
							<textarea id="content" tabindex="3" name="content" class='tinymce-enabled' cols="50" rows="3"></textarea>
						</p>

						<input type="hidden" id="ticketuser" value="<?php print (int) get_current_user_id(); ?>" tabindex="1" size="20" name="ticketuser" />

						<?php wp_nonce_field( 'stswticketfrontend', 'stswticketfrontend' ); ?>

						<p align="right"><input type="submit" value="<?php esc_html_e( 'Send', 'support-ticket-system-for-woocommerce' ); ?>" tabindex="6" id="submit" name="submit" /></p>

						</form>
					</div>
				</div>
				<?php
			} else { // Check if user is logged in, if not display login form.
				echo do_shortcode( '[woocommerce_my_account]' );
			}
		}

		/**
		 * Verify user.
		 *
		 * @version 2.2.0
		 * @since   2.1.0
		 *
		 * @param int $user_id   User ID to verify.
		 * @param int $ticket_id Ticket ID to verify.
		 *
		 * @return bool True if the user is verified, false otherwise.
		 */
		public function verify_user( $user_id, $ticket_id ) {
			if ( ! function_exists( 'wp_get_current_user' ) ) {
				return false;
			}
			$current_user = wp_get_current_user();
			if (
				! $current_user ||
				(int) $user_id !== (int) $current_user->ID
			) {
				return false;
			}

			return $this->verify_ticket_current_user_id( $ticket_id );
		}

		/**
		 * Verify ticket current user ID.
		 *
		 * @version 2.2.0
		 * @since   2.1.2
		 *
		 * @param int $ticket_id Ticket ID to verify.
		 *
		 * @return bool True if the current user is verified, false otherwise.
		 */
		public function verify_ticket_current_user_id( $ticket_id ) {
			if ( current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				return true;
			}

			$user_id = get_current_user_id();
			if ( $user_id ) {
				$ticket_user_id = $this->get_ticket_user_id( $ticket_id );
				if (
					$ticket_user_id &&
					$user_id === $ticket_user_id
				) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Function to save a response from frontend.
		 *
		 * @version 2.2.0
		 */
		public function save_response() {
			if (
				isset( $_POST['stswresponsefrontend'], $_SERVER['REQUEST_METHOD'] ) &&
				'POST' === $_SERVER['REQUEST_METHOD']
			) {
				// Form is submitted via ajax.
				check_ajax_referer(
					'stswresponsefrontend',
					'stswresponsefrontend'
				);

				// Stop running function if form wasn't submitted.
				if (
					! isset( $_POST['response_content'] ) ||
					strlen( sanitize_textarea_field( wp_unslash( $_POST['response_content'] ) ) ) <= 1
				) {
					return;
				}

				if (
					! wp_verify_nonce(
						sanitize_text_field( wp_unslash( $_POST['stswresponsefrontend'] ) ),
						'stswresponsefrontend'
					)
				) {
					esc_html_e( 'Did not save because your submission is invalid...', 'support-ticket-system-for-woocommerce' );
					return;
				}

				$response    = sanitize_textarea_field( wp_unslash( $_POST['response_content'] ) );
				$post_id     = intval( $_POST['post_id'] ?? 0 );
				$customer_id = intval( $_POST['customer_id'] ?? 0 );

				// Verify user.
				if ( ! $this->verify_user( $customer_id, $post_id ) ) {
					esc_html_e( 'Wrong user.', 'support-ticket-system-for-woocommerce' );
					return;
				}

				global $wpdb;

				$table_name = $wpdb->prefix . $this->table_name;
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$table_name,
					array(
						'user'         => $customer_id,
						'creationdate' => current_time( 'mysql', 1 ),
						'content'      => $response,
						'post_id'      => $post_id,
					)
				);

				if (
					get_option( $this->plugin . 'textforResponseSave' ) &&
					! empty( get_option( $this->plugin . 'textforResponseSave' ) )
				) {
					echo wp_kses(
						wpautop( (string) get_option( $this->plugin . 'textforResponseSave' ) ), // Editor stores paragraphs as blank lines.
						WPFactory_WC_STS_Init::$allowed_html
					);
				}

				$last_id = (int) $wpdb->insert_id;
				$this->last_saved = $last_id > 0;

				if ( ! empty( $_POST['closeTicket'] ) ) {
					$this->set_ticket_status( $post_id, 'closed' );
				} elseif ( 'closed' === $this->get_ticket_status_slug( $post_id ) ) {
					// A customer reply to a closed ticket reopens it, so it is not missed.
					$this->set_ticket_status( $post_id, 'open' );
				}

				$user = get_user_by( 'id', $customer_id );

				// Send with placeholders.
				$ticket_id        = $post_id;
				$response_id      = $last_id;
				$title            = '';
				$response_content = $response;
				$to_email         = sanitize_email( $user->user_email );
				$to_first_name    = esc_html( $user->first_name );
				$to_last_name     = esc_html( $user->last_name );

				$this->send_with_placeholders(
					$ticket_id,
					$response_id,
					$title,
					$response_content,
					$to_email,
					$to_first_name,
					$to_last_name,
					$user
				);

			}
		}

		/**
		 * Save ticket submitted from frontend.
		 *
		 * @version 2.2.0
		 */
		public function save_ticket() {
			if (
				isset( $_POST['stswticketfrontend'], $_SERVER['REQUEST_METHOD'] ) &&
				'POST' === $_SERVER['REQUEST_METHOD']
			) {
				// Submission via ajax - check.
				check_ajax_referer(
					'stswticketfrontend',
					'stswticketfrontend'
				);

				// Stop running function if form wasn't submitted.
				if ( ! isset( $_POST['title'], $_POST['content'], $_REQUEST['ticketuser'] ) ) {
					return;
				}

				// Check user.
				$ticket_user = (int) $_REQUEST['ticketuser'];
				if (
					! function_exists( 'get_current_user_id' ) ||
					get_current_user_id() !== $ticket_user
				) {
					esc_html_e( 'Wrong user.', 'support-ticket-system-for-woocommerce' );
					return;
				}

				// Check that the nonce was set and valid.
				if (
					! wp_verify_nonce(
						sanitize_text_field( wp_unslash( $_POST['stswticketfrontend'] ) ),
						'stswticketfrontend'
					)
				) {
					esc_html_e( 'Did not save because your submission has issues.', 'support-ticket-system-for-woocommerce' );
					return;
				}

				// Form validation to make sure there is content.
				if (
					! isset( $_POST['title'] ) ||
					strlen( sanitize_text_field( wp_unslash( $_POST['title'] ) ) ) < 3
				) {
					esc_html_e( 'Please enter a proper title. Titles must be at least 3 characters long.', 'support-ticket-system-for-woocommerce' );
					return;
				}
				if (
					! isset( $_POST['content'] ) ||
					strlen( sanitize_text_field( wp_unslash( $_POST['content'] ) ) ) < 1
				) {
					esc_html_e( 'Please enter content more than 1 characters in length.', 'support-ticket-system-for-woocommerce' );
					return;
				}

				// Add the content of the form to $post as an array.
				$post      = array(
					'post_title'   => sanitize_text_field( wp_unslash( $_POST['title'] ) ),
					'post_content' => sanitize_textarea_field( wp_unslash( $_POST['content'] ) ), // Keeps line breaks.
					'post_type'    => 'stsw_tickets',
					'post_status'  => 'publish',
				);
				$ticket_id = wp_insert_post( $post );
				$ticket_id = (int) $ticket_id;
				if ( ! $ticket_id ) {
					esc_html_e( 'Your request could not be saved. Please try again.', 'support-ticket-system-for-woocommerce' );
					return;
				}
				$this->last_saved = true;

				// Display a message.
				if (
					get_option( $this->plugin . 'textforTicketSave' ) &&
					! empty( get_option( $this->plugin . 'textforTicketSave' ) )
				) {
					echo wp_kses(
						wpautop( (string) get_option( $this->plugin . 'textforTicketSave' ) ), // Editor stores paragraphs as blank lines.
						WPFactory_WC_STS_Init::$allowed_html
					);
				}

				// Update user for ticket.
				$this->set_ticket_user_id( $ticket_id, $ticket_user );

				// Set ticket status as open.
				$this->set_ticket_status( $ticket_id, 'open' );

				$user = get_user_by( 'id', $ticket_user );

				// Send with placeholders.
				$response_id    = '';
				$ticket_title   = sanitize_text_field( wp_unslash( $_POST['title'] ) );
				$ticket_content = sanitize_textarea_field( wp_unslash( $_POST['content'] ) );
				$to_email       = sanitize_email( $user->user_email );
				$to_first_name  = esc_html( $user->first_name );
				$to_last_name   = esc_html( $user->last_name );

				$this->send_with_placeholders(
					$ticket_id,
					$response_id,
					$ticket_title,
					$ticket_content,
					$to_email,
					$to_first_name,
					$to_last_name,
					$user
				);
			}
		}

		/**
		 * Send with placeholders.
		 *
		 * @version 2.2.0
		 *
		 * @param int     $ticket_id     The ID of the ticket.
		 * @param int     $response_id   The ID of the response.
		 * @param string  $title         The title.
		 * @param string  $content       The content.
		 * @param string  $to_email      The email address of the recipient.
		 * @param string  $to_first_name The first name of the recipient.
		 * @param string  $to_last_name  The last name of the recipient.
		 * @param WP_User $user          The WP_User object of the recipient.
		 */
		public function send_with_placeholders( $ticket_id, $response_id, $title, $content, $to_email, $to_first_name, $to_last_name, $user ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- called after the form nonce was verified.

			// Plain values (callers may pass pre-escaped text) and their safe HTML versions.
			$title_plain   = wp_specialchars_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES );
			$content_plain = wp_specialchars_decode( (string) $content, ENT_QUOTES );
			$content_html  = nl2br( esc_html( $content_plain ) );
			$site_name     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$admin_email   = $this->get_store_email();
			$customer_link = '<a href="' . esc_url( $this->get_customer_tickets_url() ) . '">' . esc_html__( 'View your request', 'support-ticket-system-for-woocommerce' ) . '</a>';
			$admin_link    = '<a href="' . esc_url( admin_url( 'post.php?post=' . (int) $ticket_id . '&action=edit' ) ) . '">' . esc_html__( 'Open the ticket', 'support-ticket-system-for-woocommerce' ) . '</a>';
			$custom_sub    = (string) get_option( $this->plugin . 'mailIt_subjectToCust' );
			$custom_body   = wpautop( (string) get_option( $this->plugin . 'mailIt_contentToCust' ) ); // Editor stores paragraphs as blank lines.
			$notify_admin  = 1 === (int) get_option( $this->plugin . 'mailToADmin' );
			$notify_cust   = 1 === (int) get_option( $this->plugin . 'mailToCustomer' ) && $user;

			// New ticket.
			if ( isset( $_POST['title'] ) ) {
				if ( $notify_admin ) {
					/* translators: 1: store name, 2: ticket ID, 3: ticket title. */
					$sub = sprintf( __( 'New ticket on %1$s - #%2$d %3$s', 'support-ticket-system-for-woocommerce' ), $site_name, (int) $ticket_id, $title_plain );
					$msg = '<strong>' . esc_html( $title_plain ) . '</strong><br/>' . $content_html . '<br/><br/>' . $admin_link;
					$this->notify_users( $admin_email, $sub, $msg, $to_email );
				}
				if ( $notify_cust ) {
					/* translators: 1: store name, 2: ticket ID, 3: ticket title. */
					$sub = '' !== $custom_sub ? $custom_sub : sprintf( __( 'We received your request on %1$s - #%2$d %3$s', 'support-ticket-system-for-woocommerce' ), $site_name, (int) $ticket_id, $title_plain );
					$msg = '' !== $custom_body
						? wp_kses( $custom_body, WPFactory_WC_STS_Init::$allowed_html )
						: '<strong>' . esc_html( $title_plain ) . '</strong><br/>' . $content_html . '<br/><br/>' . $customer_link;
					$this->notify_users( sanitize_email( $user->user_email ), $sub, $msg );
				}
			}

			// New response from the customer.
			if ( isset( $_POST['response_content'] ) ) {
				$ticket_title = get_the_title( $ticket_id );
				if ( $notify_admin ) {
					/* translators: 1: ticket ID, 2: ticket title. */
					$sub = sprintf( __( 'New reply on ticket #%1$d %2$s', 'support-ticket-system-for-woocommerce' ), (int) $ticket_id, wp_specialchars_decode( $ticket_title, ENT_QUOTES ) );
					$msg = $content_html . '<br/><br/>' . $admin_link;
					$this->notify_users( $admin_email, $sub, $msg, $to_email );
				}
				if ( $notify_cust ) {
					/* translators: 1: store name, 2: ticket ID. */
					$sub = '' !== $custom_sub ? $custom_sub : sprintf( __( '%1$s received your reply on ticket #%2$d', 'support-ticket-system-for-woocommerce' ), $site_name, (int) $ticket_id );
					$msg = '' !== $custom_body
						? wp_kses( $custom_body, WPFactory_WC_STS_Init::$allowed_html )
						: $content_html . '<br/><br/>' . $customer_link;
					$this->notify_users( sanitize_email( $user->user_email ), $sub, $msg );
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}

		/**
		 * Store email used as sender and for store notifications (setting, or the site admin email).
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @return string
		 */
		public function get_store_email() {
			$email = sanitize_email( (string) get_option( $this->plugin . 'AdminEmailAddress' ) );
			return $email ? $email : sanitize_email( get_bloginfo( 'admin_email' ) );
		}

		/**
		 * Sends a test email to the store address ("Send test email" button in Settings > Notifications).
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 */
		public function send_test_email() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ) );
			}
			check_admin_referer( 'wpfactory_wc_sts_test_email' );

			$to   = $this->get_store_email();
			$sent = $this->notify_test_send( $to );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'         => 'support-ticket-system-woocommerce',
						'tab'          => 'settings',
						'wpfactory_wc_sts_test_email' => $sent ? 'sent' : 'failed',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		/**
		 * Sends the test message and reports whether WordPress accepted it.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @param string $to Recipient.
		 *
		 * @return bool
		 */
		public function notify_test_send( $to ) {
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			/* translators: %s: store name. */
			$subject = sprintf( __( 'Test email from %s support tickets', 'support-ticket-system-for-woocommerce' ), $site );
			$message = esc_html__( 'This is a test. If you received it, ticket notifications from your store can reach this address.', 'support-ticket-system-for-woocommerce' );
			$headers = array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $site . ' <' . $to . '>',
			);
			return (bool) wp_mail( $to, $subject, $message, $headers );
		}

		/**
		 * "Send test email" button and the result of the last test.
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 */
		public function render_test_email_button() {
			$result = isset( $_GET['wpfactory_wc_sts_test_email'] ) ? sanitize_key( wp_unslash( $_GET['wpfactory_wc_sts_test_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			if ( 'sent' === $result ) {
				echo '<div class="notice notice-success inline"><p>' . esc_html(
					sprintf(
						/* translators: %s: email address. */
						__( 'Test email sent to %s. If it does not arrive within a few minutes, check the spam folder or set up an SMTP plugin.', 'support-ticket-system-for-woocommerce' ),
						$this->get_store_email()
					)
				) . '</p></div>';
			} elseif ( 'failed' === $result ) {
				echo '<div class="notice notice-error inline"><p>' . esc_html__( 'WordPress could not send the test email. Your site probably needs an SMTP plugin to send email.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
			}
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=wpfactory_wc_sts_test_email' ), 'wpfactory_wc_sts_test_email' );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Send test email', 'support-ticket-system-for-woocommerce' ) . '</a> <span class="description">' .
				esc_html(
					sprintf(
						/* translators: %s: email address. */
						__( 'Sends a test to %s (Admin Address, saved value).', 'support-ticket-system-for-woocommerce' ),
						$this->get_store_email()
					)
				) . '</span></p>';
		}

		/**
		 * Customer-facing tickets page (works with plain and pretty permalinks).
		 *
		 * @version 2.2.1
		 * @since   2.2.1
		 *
		 * @return string
		 */
		public function get_customer_tickets_url() {
			return wc_get_endpoint_url( 'tickets', '', wc_get_page_permalink( 'myaccount' ) );
		}

		/**
		 * Notify users.
		 *
		 * @version 2.2.0
		 *
		 * @param string $to      The email address of the recipient.
		 * @param string $subject The subject of the email.
		 * @param string $message The message content of the email.
		 */
		public function notify_users( $to, $subject, $message, $reply_to = '' ) {
			if ( ! empty( get_option( $this->plugin . 'AdminEmailAddress' ) ) ) {
				$admin_email = sanitize_email( get_option( $this->plugin . 'AdminEmailAddress' ) );
			} else {
				$admin_email = sanitize_email( get_bloginfo( 'admin_email' ) );
			}

			// Reply-To: the customer on store notifications, the store on customer notifications
			// (before 2.2.1 it was the recipient's own address, so replies went nowhere).
			$reply_to = sanitize_email( $reply_to );
			if ( ! $reply_to || $reply_to === sanitize_email( $to ) ) {
				$reply_to = $admin_email;
			}

			$headers   = array();
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$headers[] = 'From: ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' <' . $admin_email . '>';
			if ( $reply_to !== sanitize_email( $to ) ) {
				$headers[] = 'Reply-To: ' . $reply_to;
			}

			wp_mail( $to, $subject, $message, $headers );
		}

		/**
		 * Notify user on WP edit adding response.
		 *
		 * @version 2.2.0
		 *
		 * @param int $post_id The ID of the post being edited.
		 */
		public function notify_user_on_wp_edit( $post_id ) {
			if (
				! empty( $_REQUEST[ $this->plugin . 'response' ] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				get_option( $this->plugin . 'mailToCustomer' ) &&
				1 === (int) get_option( $this->plugin . 'mailToCustomer' )
			) {
				// If this isn't a `stsw_tickets` post, don't update it.
				$post_type = get_post_type( $post_id );
				if ( 'stsw_tickets' !== $post_type ) {
					return;
				}

				$response_content = sanitize_textarea_field( wp_unslash( $_REQUEST[ $this->plugin . 'response' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- post.php verified the edit nonce; save_fields() checks ours.
				$user_id          = $this->get_ticket_user_id( $post_id );
				$user             = get_user_by( 'id', $user_id );
				$title            = get_the_title( $post_id );
				$content          = get_post_field( 'post_content', $post_id, 'raw' );

				if ( ! $user ) {
					return;
				}
				$to = sanitize_email( $user->user_email );

				if (
					get_option( $this->plugin . 'mailIt_subjectToCust' ) &&
					! empty( get_option( $this->plugin . 'mailIt_subjectToCust' ) )
				) {
					$sub = (string) get_option( $this->plugin . 'mailIt_subjectToCust' );
				} else {
					/* translators: 1: ticket ID, 2: ticket title. */
					$sub = sprintf( __( 'New reply on your request #%1$d %2$s', 'support-ticket-system-for-woocommerce' ), (int) $post_id, wp_specialchars_decode( $title, ENT_QUOTES ) );
				}

				// Body: the merchant's content template followed by the reply, or the default text.
				// (Before 2.2.1 the body was replaced by the subject text when a custom subject was set.)
				$custom_content = get_option( $this->plugin . 'mailIt_contentToCust' );
				if ( ! empty( $custom_content ) ) {
					$msg = (
						wp_kses( wpautop( (string) $custom_content ), WPFactory_WC_STS_Init::$allowed_html ) .
						'<br/>' .
						nl2br( esc_html( $response_content ) ) .
						'<br/><a href="' . esc_url( $this->get_customer_tickets_url() ) . '">' .
							esc_html__( 'Check it Here', 'support-ticket-system-for-woocommerce' ) .
						'</a>'
					);
				} else {
					$msg = (
						'<strong>' . esc_html( wp_specialchars_decode( $title, ENT_QUOTES ) ) . '</strong>' .
						'<br/>' .
						nl2br( esc_html( $content ) ) .
						'<br/>' .
						esc_html__( 'Last Response:', 'support-ticket-system-for-woocommerce' ) . ' ' .
						nl2br( esc_html( $response_content ) ) .
						'<br/>
						<a href="' . esc_url( $this->get_customer_tickets_url() ) . '">' .
							esc_html__( 'Check it Here', 'support-ticket-system-for-woocommerce' ) .
						'</a>'
					);
				}

				// Store reply to the customer: replies go back to the store.
				$this->notify_users( $to, $sub, $msg );
			}
		}

		/**
		 * Filter tickets.
		 *
		 * @version 2.2.0
		 *
		 * @param string $post_type The post type being filtered.
		 */
		public function stswpro_filter_tickets( $post_type ) {
			// This function adds filtering based on status in tickets list table.
			// Apply this only on stsw_tickets specific post type.
			if ( 'stsw_tickets' !== $post_type ) {
				return;
			}

			// A list of taxonomy slugs to filter by.
			$taxonomies = array( 'stsw_tickets_status' );

			foreach ( $taxonomies as $taxonomy_slug ) {
				// Retrieve taxonomy data.
				$taxonomy_obj  = get_taxonomy( $taxonomy_slug );
				$taxonomy_name = $taxonomy_obj->labels->name;

				// Retrieve taxonomy terms.
				$terms = get_terms( $taxonomy_slug );

				// Display filter HTML.
				echo "<select name='" . esc_attr( $taxonomy_slug ) . "' id='" . esc_attr( $taxonomy_slug ) . "' class='postform'>";
				echo '<option value="">' .
				sprintf(
					/* Translators: %s: Taxonomy name. */
					esc_html__( 'Show All %s', 'support-ticket-system-for-woocommerce' ),
					esc_attr( $taxonomy_name )
				) .
				'</option>';
				foreach ( $terms as $term ) {
					printf(
						'<option value="%1$s" %2$s>%3$s (%4$s)</option>',
						esc_attr( $term->slug ),
						(
							( isset( $_GET[ $taxonomy_slug ] ) && ( $_GET[ $taxonomy_slug ] === $term->slug ) ) ? // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							' selected="selected"' :
							''
						),
						esc_attr( $term->name ),
						esc_attr( $term->count )
					);
				}
				echo '</select>';
			}
		}

		/**
		 * View order page: a short "get help with this order" block.
		 *
		 * Before 2.2.1 every order page repeated the whole ticket list and form under an empty heading.
		 * The free version does not link tickets to orders, so this points to the tickets page with the order pre-filled.
		 *
		 * @version 2.2.1
		 *
		 * @param int $order_id Order ID.
		 */
		public function stswpro_view_order( $order_id = 0 ) {
			$order = $order_id ? wc_get_order( $order_id ) : false;
			if ( ! $order ) {
				return;
			}
			$label = get_option( $this->plugin . 'renameAccountTabLink' );
			$url   = add_query_arg( 'order', $order->get_order_number(), $this->get_customer_tickets_url() );
			?>
			<section class="wpfactory-wc-sts-order-help">
				<h2><?php echo esc_html( ! empty( $label ) ? $label : __( 'Tickets', 'support-ticket-system-for-woocommerce' ) ); ?></h2>
				<p><?php esc_html_e( 'A problem with this order? Send us a message and we will reply by email.', 'support-ticket-system-for-woocommerce' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Get help with this order', 'support-ticket-system-for-woocommerce' ); ?></a></p>
			</section>
			<?php
		}
	}

endif;

WPFactory_WC_STS_Inc::$main_instance = new WPFactory_WC_STS_Inc();
return WPFactory_WC_STS_Inc::$main_instance;
