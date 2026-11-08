<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: tickets, replies, notes, access and the customer screens.
 *
 * This file is shared by the free and Pro editions (Pro receives a copy through `qa/sync-core.py`).
 *
 * @version 2.3.0
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
	 * @version 2.3.0
	 */
	class WPFactory_WC_STS_Inc {

		/**
		 * Ticket meta: customer user ID (name shared by both editions since 1.x; never rename).
		 */
		const META_USER = 'STSWooCommerceProticketuser';

		/**
		 * Ticket meta: assigned staff user ID.
		 */
		const META_AGENT = 'STSWooCommerceProticketagent';

		/**
		 * Ticket meta: linked order ID.
		 */
		const META_ORDER = 'STSWooCommerceProticketorder';

		/**
		 * Ticket meta: comma separated file URLs of the ticket's first message.
		 */
		const META_FILES = 'images';

		/**
		 * Ticket meta: email address of a guest (ticket opened without an account).
		 */
		const META_GUEST_EMAIL = '_stsw_guest_email';

		/**
		 * Term meta: priority colour.
		 */
		const META_COLOR = 'STSWooCommerceProcolor';

		/**
		 * Term meta: user that tickets with this subject are assigned to.
		 */
		const META_SUBJECT_ASSIGNEE = 'STSWooCommerceProAssignee';

		/**
		 * Ticket meta: who wrote last, `customer` or `store` (2.3.0+).
		 */
		const META_LAST_BY = '_stsw_last_by';

		/**
		 * Ticket meta: time of the last message, GMT `Y-m-d H:i:s` (2.3.0+).
		 */
		const META_LAST_AT = '_stsw_last_at';

		/**
		 * Ticket meta: time of the first store reply, GMT (2.3.0+).
		 */
		const META_FIRST_REPLY = '_stsw_first_reply_at';

		/**
		 * Ticket meta: order item IDs the request is about.
		 */
		const META_ITEMS = '_stsw_items';

		/**
		 * Ticket meta: reason chosen in the ask flow.
		 */
		const META_REASON = '_stsw_reason';

		/**
		 * Ticket meta: staff user who opened the ticket for the customer.
		 */
		const META_STARTED_BY = '_stsw_started_by';

		/**
		 * Settings prefix (free `STSWooCommerce`, Pro `STSWooCommercePro`).
		 *
		 * @var string
		 */
		public $plugin = 'STSWooCommerce';

		/**
		 * Replies table (without the WordPress prefix).
		 *
		 * @var string
		 */
		public $table_name = 'stsw_responses';

		/**
		 * Private notes table (without the WordPress prefix).
		 *
		 * @var string
		 */
		public $notes_table_name = 'stsw_priv_responses';

		/**
		 * Database version of both tables (option shared by both editions).
		 *
		 * @var string
		 */
		public $stswpro_table_db_version = '1.9';

		/**
		 * Whether the last front-end form submission saved something.
		 *
		 * @var bool
		 */
		public $last_saved = false;

		/**
		 * ID of the ticket saved by the last front-end submission.
		 *
		 * @var int
		 */
		public $last_ticket_id = 0;

		/**
		 * The instance created when the plugin loads.
		 *
		 * @var WPFactory_WC_STS_Inc|null
		 */
		public static $main_instance = null;

		/**
		 * Returns the loaded instance. Creating another one would register every hook a second time.
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
		 * @version 2.3.0
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'register_ticket_post_type_and_taxonomy' ) );
			add_filter( 'user_has_cap', array( $this, 'grant_tickets_cap_to_shop_staff' ) );

			add_action( 'admin_menu', array( $this, 'menu_page' ) );
			add_action( 'before_delete_post', array( $this, 'delete_relevant_responses' ) );

			add_filter( 'woocommerce_account_menu_items', array( $this, 'tickets_link' ) );
			add_action( 'init', array( $this, 'stswpro_add_endpoint' ) );
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'stswpro_add_my_account_order_actions' ), 10, 2 );
			add_filter( 'woocommerce_my_account_my_orders_columns', array( $this, 'add_my_account_orders_column' ) );
			add_action( 'woocommerce_my_account_my_orders_column_order-tickets', array( $this, 'render_my_account_orders_column' ) );
			add_action( 'woocommerce_account_tickets_endpoint', array( $this, 'stswpro_my_account_endpoint_content' ) );
			add_action( 'woocommerce_view_order', array( $this, 'stswpro_view_order' ), 20 );
			add_shortcode( 'stsw_user_tickets', array( $this, 'shortcode_output' ) );

			// My Account endpoint registered with WooCommerce (title, `is_wc_endpoint_url()`).
			add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
			add_filter( 'woocommerce_endpoint_tickets_title', array( $this, 'endpoint_title' ) );

			// One-time steps after install or update.
			add_action( 'init', array( $this, 'maybe_run_version_update' ), 20 );
			register_activation_hook( WPFACTORY_WC_STS_FILE, array( $this, 'on_activation' ) );
			register_activation_hook( WPFACTORY_WC_STS_FILE, array( $this, 'ticket_response_table_install' ) );
			add_action( 'plugins_loaded', array( $this, 'stswpro_tickets_table_update_db_check' ) );
			add_action( 'admin_init', array( $this, 'maybe_backfill_activity' ) );

			// Front-end forms are handled before output, then redirected (no double submit on refresh).
			add_action( 'template_redirect', array( $this, 'handle_frontend_forms' ) );
		}

		/*
		|--------------------------------------------------------------------------
		| Install and update
		|--------------------------------------------------------------------------
		*/

		/**
		 * Creates or updates the replies and private notes tables.
		 *
		 * @version 2.3.0
		 */
		public function ticket_response_table_install() {
			global $wpdb;

			$charset = $wpdb->get_charset_collate();
			$replies = $wpdb->prefix . $this->table_name;
			$notes   = $wpdb->prefix . $this->notes_table_name;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			dbDelta(
				'CREATE TABLE ' . $replies . ' (
				id int(11) NOT NULL AUTO_INCREMENT,
				user int(11) NOT NULL,
				post_id int(11) NOT NULL,
				creationdate DATETIME NULL,
				agent int(11) NOT NULL,
				content longtext NOT NULL,
				attachments longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY post_id (post_id)
				) ' . $charset . ';'
			);
			dbDelta(
				'CREATE TABLE ' . $notes . ' (
				id int(11) NOT NULL AUTO_INCREMENT,
				user int(11) NOT NULL,
				post_id int(11) NOT NULL,
				creationdate DATETIME NULL,
				agent int(11) NOT NULL,
				content longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY post_id (post_id)
				) ' . $charset . ';'
			);

			update_option( 'stswpro_tickets_table_db_version', $this->stswpro_table_db_version );
		}

		/**
		 * Runs the table install when the stored table version differs (idempotent).
		 *
		 * @version 2.3.0
		 */
		public function stswpro_tickets_table_update_db_check() {
			if ( get_option( 'stswpro_tickets_table_db_version' ) !== $this->stswpro_table_db_version ) {
				$this->ticket_response_table_install();
			}
		}

		/**
		 * On activation: the next request runs the one-time steps (rewrite rules need the endpoint registered first).
		 *
		 * @version 2.3.0
		 */
		public function on_activation() {
			delete_option( WPFACTORY_WC_STS_VERSION_OPTION );
			set_transient( 'wpfactory_wc_sts_activation_redirect', 1, 60 );
			$role = get_role( 'administrator' );
			if ( $role ) {
				$role->add_cap( 'stsw_tickets' );
			}
		}

		/**
		 * One-time steps when the plugin version changes (new install, update, reactivation).
		 *
		 * Safe for existing stores: only adds what is missing. Settings of existing stores are never changed;
		 * a brand new store gets sensible defaults for the ticket form.
		 *
		 * @version 2.3.0
		 */
		public function maybe_run_version_update() {
			$previous = get_option( WPFACTORY_WC_STS_VERSION_OPTION );
			if ( WPFACTORY_WC_STS_VERSION === $previous ) {
				return;
			}

			$this->insert_default_terms();

			if ( false === $previous && $this->is_new_store() ) {
				$defaults = array(
					'enablePriority'    => '1',
					'enableAttachments' => '1',
					'allowedExtensions' => 'jpg,jpeg,png,gif,pdf',
					'allowedSize'       => '5',
					'allowedAttachNum'  => '3',
				);
				foreach ( $defaults as $key => $value ) {
					add_option( $this->plugin . $key, $value );
				}
			}

			// The `tickets` endpoint is registered by now, so customers do not get a 404 on My Account > Tickets.
			flush_rewrite_rules( false );

			// Long texts are read only when a ticket is saved or an email is sent: do not load them on every page.
			if ( function_exists( 'wp_set_option_autoload_values' ) ) {
				wp_set_option_autoload_values(
					array(
						$this->plugin . 'mailIt_contentToAs'   => false,
						$this->plugin . 'mailIt_contentToCust' => false,
						$this->plugin . 'textforTicketSave'    => false,
						$this->plugin . 'textforResponseSave'  => false,
					)
				);
			}

			update_option( WPFACTORY_WC_STS_VERSION_OPTION, WPFACTORY_WC_STS_VERSION, false );
		}

		/**
		 * Whether this store never used the plugin (no tickets, no saved settings).
		 *
		 * @version 2.3.0
		 *
		 * @return bool
		 */
		public function is_new_store() {
			if ( false !== get_option( $this->plugin . 'mailToADmin' ) || false !== get_option( $this->plugin . 'renameAccountTabLink' ) ) {
				return false;
			}
			$counts = wp_count_posts( 'stsw_tickets' );
			$total  = 0;
			foreach ( (array) $counts as $count ) {
				$total += (int) $count;
			}
			return 0 === $total;
		}

		/**
		 * Default statuses and priorities. Terms matched by slug or name are left as the merchant set them.
		 *
		 * @version 2.3.0
		 */
		public function insert_default_terms() {
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

			$priorities = array(
				'low'    => array( __( 'Low', 'support-ticket-system-for-woocommerce' ), '#0000FF' ),
				'normal' => array( __( 'Normal', 'support-ticket-system-for-woocommerce' ), '#008000' ),
				'high'   => array( __( 'High', 'support-ticket-system-for-woocommerce' ), '#DC143C' ),
			);
			foreach ( $priorities as $slug => $data ) {
				if ( ! term_exists( $slug, 'stsw_tickets_priority' ) && ! term_exists( $data[0], 'stsw_tickets_priority' ) ) {
					$term = wp_insert_term( $data[0], 'stsw_tickets_priority', array( 'slug' => $slug ) );
					if ( ! is_wp_error( $term ) ) {
						update_term_meta( $term['term_id'], self::META_COLOR, $data[1] );
					}
				}
			}
		}

		/**
		 * Registers `tickets` as a WooCommerce My Account endpoint.
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
		 * @return string
		 */
		public function endpoint_title() {
			$label = get_option( $this->plugin . 'renameAccountTabLink' );
			return ! empty( $label ) ? $label : __( 'Help', 'support-ticket-system-for-woocommerce' );
		}

		/**
		 * Registers the endpoint rewrite rule.
		 */
		public function stswpro_add_endpoint() {
			add_rewrite_endpoint( 'tickets', EP_PAGES );
		}

		/*
		|--------------------------------------------------------------------------
		| Post type, taxonomies, capabilities
		|--------------------------------------------------------------------------
		*/

		/**
		 * Registers the ticket post type and the status, priority and subject taxonomies.
		 *
		 * @version 2.3.0
		 */
		public function register_ticket_post_type_and_taxonomy() {
			register_post_type(
				'stsw_tickets',
				array(
					'labels'              => array(
						'name'          => esc_html__( 'Tickets', 'support-ticket-system-for-woocommerce' ),
						'singular_name' => esc_html__( 'Ticket', 'support-ticket-system-for-woocommerce' ),
						'search_items'  => esc_html__( 'Search Tickets', 'support-ticket-system-for-woocommerce' ),
						'all_items'     => esc_html__( 'All Tickets', 'support-ticket-system-for-woocommerce' ),
						'edit_item'     => esc_html__( 'Edit Ticket', 'support-ticket-system-for-woocommerce' ),
						'add_new_item'  => esc_html__( 'Add New Ticket', 'support-ticket-system-for-woocommerce' ),
						'add_new'       => esc_html__( 'New Ticket', 'support-ticket-system-for-woocommerce' ),
						'new_item'      => esc_html__( 'New Ticket', 'support-ticket-system-for-woocommerce' ),
						'menu_name'     => esc_html__( 'Tickets', 'support-ticket-system-for-woocommerce' ),
						'not_found'     => esc_html__( 'No Tickets found', 'support-ticket-system-for-woocommerce' ),
					),
					'description'         => esc_html__( 'Customer support tickets', 'support-ticket-system-for-woocommerce' ),
					'menu_icon'           => 'dashicons-calendar',
					'supports'            => array( 'title' ),
					'show_in_rest'        => false,
					'capability_type'     => 'page',
					// Tickets hold customer personal data: only staff with `stsw_tickets` (all `manage_woocommerce` users, see below) may open them.
					'capabilities'        => $this->get_ticket_capabilities( 'stsw_tickets' ),
					'map_meta_cap'        => true,
					'hierarchical'        => false,
					'public'              => false,
					'publicly_queryable'  => false,
					'show_ui'             => true,
					'show_in_menu'        => false,
					'exclude_from_search' => true,
					'show_in_nav_menus'   => false,
					'has_archive'         => false,
					'rewrite'             => false,
				)
			);

			$taxonomies = array(
				'stsw_tickets_status'   => array(
					'name'     => _x( 'Status', 'taxonomy general name', 'support-ticket-system-for-woocommerce' ),
					'add_new'  => __( 'Add New Status', 'support-ticket-system-for-woocommerce' ),
					'edit'     => __( 'Edit Status', 'support-ticket-system-for-woocommerce' ),
					'search'   => __( 'Search Status', 'support-ticket-system-for-woocommerce' ),
					'notfound' => __( 'No Status found.', 'support-ticket-system-for-woocommerce' ),
				),
				'stsw_tickets_priority' => array(
					'name'     => _x( 'Priority', 'taxonomy general name', 'support-ticket-system-for-woocommerce' ),
					'add_new'  => __( 'Add New Priority', 'support-ticket-system-for-woocommerce' ),
					'edit'     => __( 'Edit Priority', 'support-ticket-system-for-woocommerce' ),
					'search'   => __( 'Search Priority', 'support-ticket-system-for-woocommerce' ),
					'notfound' => __( 'No Priority found.', 'support-ticket-system-for-woocommerce' ),
				),
				'stsw_tickets_subject'  => array(
					'name'     => _x( 'Subject', 'taxonomy general name', 'support-ticket-system-for-woocommerce' ),
					'add_new'  => __( 'Add New Subject', 'support-ticket-system-for-woocommerce' ),
					'edit'     => __( 'Edit Subject', 'support-ticket-system-for-woocommerce' ),
					'search'   => __( 'Search Subjects', 'support-ticket-system-for-woocommerce' ),
					'notfound' => __( 'No Subject found.', 'support-ticket-system-for-woocommerce' ),
				),
			);
			foreach ( $taxonomies as $taxonomy => $label ) {
				register_taxonomy(
					$taxonomy,
					array( 'stsw_tickets' ),
					array(
						'hierarchical'      => true,
						'labels'            => array(
							'name'          => $label['name'],
							'singular_name' => $label['name'],
							'menu_name'     => $label['name'],
							'all_items'     => $label['name'],
							'add_new_item'  => $label['add_new'],
							'edit_item'     => $label['edit'],
							'search_items'  => $label['search'],
							'not_found'     => $label['notfound'],
						),
						'show_ui'           => true,
						'show_admin_column' => true,
						'query_var'         => true,
						'rewrite'           => array( 'slug' => $taxonomy ),
						'capabilities'      => array(
							'manage_terms' => 'stsw_tickets',
							'edit_terms'   => 'stsw_tickets',
							'delete_terms' => 'stsw_tickets',
							'assign_terms' => 'stsw_tickets',
						),
					)
				);
			}
			// Tags (2.3.0): set from the ticket header or by Pro workflow rules. Staff only.
			register_taxonomy(
				'stsw_tickets_tag',
				array( 'stsw_tickets' ),
				array(
					'hierarchical' => false,
					'public'       => false,
					'show_ui'      => false,
					'query_var'    => false,
					'rewrite'      => false,
					'labels'       => array(
						'name'          => __( 'Tags', 'support-ticket-system-for-woocommerce' ),
						'singular_name' => __( 'Tag', 'support-ticket-system-for-woocommerce' ),
					),
					'capabilities' => array(
						'manage_terms' => 'stsw_tickets',
						'edit_terms'   => 'stsw_tickets',
						'delete_terms' => 'stsw_tickets',
						'assign_terms' => 'stsw_tickets',
					),
				)
			);
		}

		/**
		 * Primitive capabilities for the `stsw_tickets` post type, all mapped to one capability.
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
		 * Store managers keep ticket access without a stored `stsw_tickets` cap.
		 *
		 * @param array $allcaps All capabilities of the user.
		 *
		 * @return array
		 */
		public function grant_tickets_cap_to_shop_staff( $allcaps ) {
			if ( ! empty( $allcaps['manage_woocommerce'] ) ) {
				$allcaps['stsw_tickets'] = true;
			}
			return $allcaps;
		}

		/**
		 * Whether the user is store staff allowed to manage every ticket.
		 *
		 * @param int $user_id User ID, current user when empty.
		 *
		 * @return bool
		 */
		public function can_manage_tickets( $user_id = 0 ) {
			$user_id = $user_id ? (int) $user_id : get_current_user_id();
			return $user_id && ( user_can( $user_id, 'manage_woocommerce' ) || user_can( $user_id, 'stsw_tickets' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		}

		/**
		 * Whether the user may read and answer a ticket: staff, the ticket's customer, or the assigned agent.
		 *
		 * @param int $ticket_id Ticket ID.
		 * @param int $user_id   User ID, current user when empty.
		 *
		 * @return bool
		 */
		public function user_can_access_ticket( $ticket_id, $user_id = 0 ) {
			$user_id = $user_id ? (int) $user_id : get_current_user_id();
			if ( ! $user_id || 'stsw_tickets' !== get_post_type( $ticket_id ) ) {
				return false;
			}
			$allowed = $this->can_manage_tickets( $user_id ) ||
				( ! get_post_meta( $ticket_id, '_stsw_internal', true ) && $this->get_ticket_user_id( $ticket_id ) === $user_id ) ||
				$this->get_ticket_agent_id( $ticket_id ) === $user_id;
			/**
			 * Whether a user may see and answer a ticket on the customer side (Pro: colleagues of the same company).
			 *
			 * @param bool $allowed   Allowed.
			 * @param int  $ticket_id Ticket ID.
			 * @param int  $user_id   User ID.
			 */
			return (bool) apply_filters( 'wpfactory_wc_sts_user_can_access_ticket', $allowed, (int) $ticket_id, $user_id );
		}

		/**
		 * Public number of a ticket, without "#": the ticket ID unless ticket numbers are set up.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string
		 */
		public function get_ticket_number( $ticket_id ) {
			/**
			 * Public number of a ticket (the ticket numbers setting stores its own).
			 *
			 * @param string $number    Number (the ticket ID by default).
			 * @param int    $ticket_id Ticket ID.
			 */
			return (string) apply_filters( 'wpfactory_wc_sts_ticket_number', (string) (int) $ticket_id, (int) $ticket_id );
		}

		/**
		 * Kept for add-ons written against 2.2: whether the current user is the ticket's customer or staff.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return bool
		 */
		public function verify_ticket_current_user_id( $ticket_id ) {
			return $this->user_can_access_ticket( $ticket_id );
		}

		/*
		|--------------------------------------------------------------------------
		| Ticket data
		|--------------------------------------------------------------------------
		*/

		/**
		 * Customer user ID of a ticket (0 for guests).
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return int
		 */
		public function get_ticket_user_id( $ticket_id ) {
			return (int) get_post_meta( $ticket_id, self::META_USER, true );
		}

		/**
		 * Sets the customer of a ticket.
		 *
		 * @param int $ticket_id Ticket ID.
		 * @param int $user_id   User ID.
		 *
		 * @return int|bool
		 */
		public function set_ticket_user_id( $ticket_id, $user_id ) {
			return update_post_meta( $ticket_id, self::META_USER, (int) $user_id );
		}

		/**
		 * Assigned staff user ID (0 when none is stored).
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return int
		 */
		public function get_ticket_agent_id( $ticket_id ) {
			return (int) get_post_meta( $ticket_id, self::META_AGENT, true );
		}

		/**
		 * Who handles the ticket: the assigned agent, or the default assignee from the settings.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return int
		 */
		public function get_ticket_assignee_id( $ticket_id ) {
			$agent = $this->get_ticket_agent_id( $ticket_id );
			return $agent ? $agent : (int) get_option( $this->plugin . 'assignToUser' );
		}

		/**
		 * Linked order ID (0 when none).
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return int
		 */
		public function get_ticket_order_id( $ticket_id ) {
			return absint( get_post_meta( $ticket_id, self::META_ORDER, true ) );
		}

		/**
		 * Email and names of the person who opened the ticket (account or guest).
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return array { email, first_name, last_name, display_name, user (WP_User|false) }
		 */
		public function get_ticket_contact( $ticket_id ) {
			$user = get_user_by( 'id', $this->get_ticket_user_id( $ticket_id ) );
			if ( $user ) {
				return array(
					'email'        => $user->user_email,
					'first_name'   => $user->first_name,
					'last_name'    => $user->last_name,
					'display_name' => $user->display_name,
					'user'         => $user,
				);
			}
			$email = (string) get_post_meta( $ticket_id, self::META_GUEST_EMAIL, true );
			$named = (string) get_post_meta( $ticket_id, '_stsw_guest_name', true );
			$first = '';
			$last  = '';
			$order = $this->get_ticket_order_id( $ticket_id ) ? wc_get_order( $this->get_ticket_order_id( $ticket_id ) ) : false;
			if ( $order ) {
				$first = $order->get_billing_first_name();
				$last  = $order->get_billing_last_name();
			}
			return array(
				'email'        => $email,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : ( '' !== $named ? $named : $email ),
				'user'         => false,
			);
		}

		/**
		 * Display name of a user ("First Last", or the display name).
		 *
		 * @param int $id User ID.
		 *
		 * @return string
		 */
		public function get_user_name( $id ) {
			$user = get_user_by( 'id', (int) $id );
			if ( ! $user ) {
				return '';
			}
			$name = trim( $user->first_name . ' ' . $user->last_name );
			return '' !== $name ? $name : $user->display_name;
		}

		/**
		 * Sets a ticket status by slug, so statuses the merchant renamed keep working.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $slug      Status slug: open, in-progress or closed.
		 */
		public function set_ticket_status( $ticket_id, $slug ) {
			$old  = $this->get_ticket_status_slug( $ticket_id );
			$term = get_term_by( 'slug', $slug, 'stsw_tickets_status' );
			if ( $term ) {
				wp_set_object_terms( $ticket_id, (int) $term->term_id, 'stsw_tickets_status' );
			} else {
				$names = array(
					'open'        => 'Open',
					'in-progress' => 'In Progress',
					'closed'      => 'Closed',
				);
				wp_set_object_terms( $ticket_id, $names[ $slug ] ?? $slug, 'stsw_tickets_status' );
			}
			if ( $old !== $slug ) {
				/**
				 * A ticket's status changed.
				 *
				 * @param int    $ticket_id Ticket ID.
				 * @param string $slug      New status slug.
				 * @param string $old       Previous status slug.
				 */
				do_action( 'wpfactory_wc_sts_status_changed', (int) $ticket_id, $slug, $old );
			}
		}

		/**
		 * Reasons offered in the ask flow.
		 *
		 * @return array slug => label
		 */
		public function get_reasons() {
			/**
			 * Reasons offered in the ask flow.
			 *
			 * @param array $reasons slug => label.
			 */
			return (array) apply_filters(
				'wpfactory_wc_sts_reasons',
				array(
					'where'   => __( 'Where is my order?', 'support-ticket-system-for-woocommerce' ),
					'return'  => __( 'Return or exchange', 'support-ticket-system-for-woocommerce' ),
					'damaged' => __( 'Damaged or faulty item', 'support-ticket-system-for-woocommerce' ),
					'wrong'   => __( 'Wrong or missing item', 'support-ticket-system-for-woocommerce' ),
					'billing' => __( 'Billing or payment', 'support-ticket-system-for-woocommerce' ),
					'product' => __( 'Question about a product', 'support-ticket-system-for-woocommerce' ),
					'other'   => __( 'Something else', 'support-ticket-system-for-woocommerce' ),
				)
			);
		}

		/**
		 * Who wrote last and when. Tickets from before 2.3.0 are worked out from their replies once.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return array { by: customer|store, at: GMT date }
		 */
		public function get_activity( $ticket_id ) {
			$by = (string) get_post_meta( $ticket_id, self::META_LAST_BY, true );
			$at = (string) get_post_meta( $ticket_id, self::META_LAST_AT, true );
			if ( '' === $by || '' === $at ) {
				$this->backfill_activity( $ticket_id );
				$by = (string) get_post_meta( $ticket_id, self::META_LAST_BY, true );
				$at = (string) get_post_meta( $ticket_id, self::META_LAST_AT, true );
			}
			return array(
				'by' => $by,
				'at' => $at,
			);
		}

		/**
		 * Records the last message of a ticket (meta, and the post's modified date for sorting).
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $by        `customer` or `store`.
		 * @param string $at        GMT date (now when empty).
		 */
		public function touch_activity( $ticket_id, $by, $at = '' ) {
			global $wpdb;
			$at = $at ? $at : current_time( 'mysql', 1 );
			update_post_meta( $ticket_id, self::META_LAST_BY, 'store' === $by ? 'store' : 'customer' );
			update_post_meta( $ticket_id, self::META_LAST_AT, $at );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->posts,
				array(
					'post_modified_gmt' => $at,
					'post_modified'     => get_date_from_gmt( $at ),
				),
				array( 'ID' => (int) $ticket_id )
			);
			clean_post_cache( $ticket_id );
		}

		/**
		 * Works out last activity and first store reply of a ticket from its replies (tickets from before 2.3.0).
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function backfill_activity( $ticket_id ) {
			$replies = $this->get_replies( $ticket_id );
			$last    = end( $replies );
			if ( $last ) {
				$this->touch_activity( $ticket_id, (int) $last->agent > 0 ? 'store' : 'customer', $last->creationdate );
			} else {
				$this->touch_activity( $ticket_id, get_post_meta( $ticket_id, self::META_STARTED_BY, true ) ? 'store' : 'customer', get_post_field( 'post_date_gmt', $ticket_id ) );
			}
			foreach ( $replies as $reply ) {
				if ( (int) $reply->agent > 0 ) {
					update_post_meta( $ticket_id, self::META_FIRST_REPLY, $reply->creationdate );
					break;
				}
			}
		}

		/**
		 * Fills the activity meta of older tickets in batches (runs on admin requests until done).
		 */
		public function maybe_backfill_activity() {
			if ( 'done' === get_option( 'wpfactory_wc_sts_activity_backfill' ) ) {
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
							'key'     => self::META_LAST_AT,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
			foreach ( $ids as $id ) {
				$this->backfill_activity( (int) $id );
			}
			if ( count( $ids ) < 200 ) {
				update_option( 'wpfactory_wc_sts_activity_backfill', 'done', false );
			}
		}

		/**
		 * Names of the order items a ticket is about.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string[]
		 */
		public function get_ticket_item_names( $ticket_id ) {
			$items = (array) get_post_meta( $ticket_id, self::META_ITEMS, true );
			$order = $this->get_ticket_order_id( $ticket_id ) ? wc_get_order( $this->get_ticket_order_id( $ticket_id ) ) : false;
			$names = array();
			if ( $order ) {
				foreach ( $items as $item_id ) {
					$item = $order->get_item( (int) $item_id );
					if ( $item ) {
						$names[] = $item->get_name() . ' x ' . $item->get_quantity();
					}
				}
			}
			return $names;
		}

		/**
		 * Current status slug of a ticket.
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
		 * Name of the first term of a ticket in a taxonomy.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $taxonomy  Taxonomy.
		 *
		 * @return WP_Term|null
		 */
		public function get_ticket_term( $ticket_id, $taxonomy ) {
			$terms = wp_get_object_terms( $ticket_id, $taxonomy );
			return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : null;
		}

		/**
		 * Replies of a ticket, oldest first.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return array
		 */
		public function get_replies( $ticket_id ) {
			global $wpdb;
			return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT * FROM %i WHERE post_id = %d ORDER BY creationdate ASC, id ASC',
					$wpdb->prefix . $this->table_name,
					(int) $ticket_id
				)
			);
		}

		/**
		 * Private notes of a ticket, oldest first.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return array
		 */
		public function get_notes( $ticket_id ) {
			global $wpdb;
			return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT * FROM %i WHERE post_id = %d ORDER BY creationdate ASC, id ASC',
					$wpdb->prefix . $this->notes_table_name,
					(int) $ticket_id
				)
			);
		}

		/**
		 * Resolves an order number typed by a customer to an order ID (sequential order number plugins use this filter).
		 *
		 * @param string $number Order number.
		 *
		 * @return int
		 */
		public function resolve_order_number( $number ) {
			$number = ltrim( trim( (string) $number ), '#' );
			if ( '' === $number ) {
				return 0;
			}
			return absint( apply_filters( 'woocommerce_shortcode_order_tracking_order_id', $number ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core filter.
		}

		/*
		|--------------------------------------------------------------------------
		| Ticket API (used by the customer forms, guest tickets and wp-admin)
		|--------------------------------------------------------------------------
		*/

		/**
		 * Creates a ticket.
		 *
		 * @version 2.3.0
		 *
		 * @param array $args {
		 *     @type string $title       Title.
		 *     @type string $content     Message (plain text).
		 *     @type int    $user_id     Customer user ID (0 for a guest).
		 *     @type string $guest_email Guest email (when `user_id` is 0).
		 *     @type int    $order_id    Linked order ID (must already be checked by the caller).
		 *     @type int    $subject_id  Subject term ID.
		 *     @type string $priority    Priority term name.
		 *     @type array  $fields      Custom field values.
		 *     @type string $files_field `$_FILES` key holding attachments.
		 *     @type string $channel     Where it came from: '' (web), `chat` or `email`.
		 *     @type bool   $internal    For the team only until a team member replies (no customer email).
		 * }
		 *
		 * @return int|WP_Error Ticket ID.
		 */
		public function create_ticket( $args ) {
			$args = wp_parse_args(
				$args,
				array(
					'title'       => '',
					'content'     => '',
					'user_id'     => 0,
					'guest_email' => '',
					'order_id'    => 0,
					'subject_id'  => 0,
					'priority'    => '',
					'fields'      => array(),
					'files_field' => '',
					'items'       => array(),
					'reason'      => '',
					'started_by'  => 0,
					'channel'     => '',
					'internal'    => false,
				)
			);

			$ticket_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $args['title'] ),
					'post_content' => sanitize_textarea_field( $args['content'] ),
					'post_type'    => 'stsw_tickets',
					'post_status'  => 'publish',
				),
				true
			);
			if ( is_wp_error( $ticket_id ) ) {
				return $ticket_id;
			}
			$ticket_id = (int) $ticket_id;

			$this->set_ticket_user_id( $ticket_id, (int) $args['user_id'] );
			if ( ! $args['user_id'] && is_email( $args['guest_email'] ) ) {
				update_post_meta( $ticket_id, self::META_GUEST_EMAIL, sanitize_email( $args['guest_email'] ) );
			}
			if ( $args['order_id'] ) {
				update_post_meta( $ticket_id, self::META_ORDER, absint( $args['order_id'] ) );
			}

			// Subject, with automatic assignment.
			$subject = $args['subject_id'] ? get_term( (int) $args['subject_id'], 'stsw_tickets_subject' ) : null;
			if ( $subject && ! is_wp_error( $subject ) ) {
				wp_set_object_terms( $ticket_id, (int) $subject->term_id, 'stsw_tickets_subject' );
				$assignee = (int) get_term_meta( $subject->term_id, self::META_SUBJECT_ASSIGNEE, true );
				if ( $assignee && get_user_by( 'id', $assignee ) ) {
					update_post_meta( $ticket_id, self::META_AGENT, $assignee );
				}
			}

			// Priority: the customer's choice when the field is on, otherwise Normal.
			$priority = '' !== $args['priority'] ? get_term_by( 'name', $args['priority'], 'stsw_tickets_priority' ) : false;
			if ( ! $priority ) {
				$priority = get_term_by( 'slug', 'normal', 'stsw_tickets_priority' );
			}
			if ( $priority ) {
				wp_set_object_terms( $ticket_id, (int) $priority->term_id, 'stsw_tickets_priority' );
			}

			$this->set_ticket_status( $ticket_id, 'open' );
			if ( $args['items'] ) {
				update_post_meta( $ticket_id, self::META_ITEMS, array_map( 'absint', (array) $args['items'] ) );
			}
			if ( '' !== $args['reason'] ) {
				update_post_meta( $ticket_id, self::META_REASON, sanitize_key( $args['reason'] ) );
			}
			if ( $args['started_by'] ) {
				update_post_meta( $ticket_id, self::META_STARTED_BY, (int) $args['started_by'] );
			}
			if ( '' !== $args['channel'] ) {
				update_post_meta( $ticket_id, '_stsw_channel', sanitize_key( $args['channel'] ) );
			}
			if ( $args['internal'] ) {
				update_post_meta( $ticket_id, '_stsw_internal', '1' );
			}
			$this->touch_activity( $ticket_id, $args['started_by'] ? 'store' : 'customer' );

			if ( ! empty( $args['fields'] ) && class_exists( 'WPFactory_WC_STS_Fields' ) ) {
				WPFactory_WC_STS_Fields::get_instance()->save_values( $ticket_id, (array) $args['fields'] );
			}

			if ( '' !== $args['files_field'] && class_exists( 'WPFactory_WC_STS_Files' ) ) {
				$urls = WPFactory_WC_STS_Files::get_instance()->handle_upload( $args['files_field'], $ticket_id, 'urls' );
				if ( ! empty( $urls ) ) {
					update_post_meta( $ticket_id, self::META_FILES, sanitize_text_field( implode( ',', $urls ) ) );
				}
			}

			/**
			 * A ticket was created (emails are sent from here).
			 *
			 * @param int $ticket_id Ticket ID.
			 */
			do_action( 'wpfactory_wc_sts_ticket_created', $ticket_id );

			return $ticket_id;
		}

		/**
		 * Adds a reply to a ticket and updates its status.
		 *
		 * Staff replies store the staff user in `agent`; customer replies have `agent` 0.
		 *
		 * @version 2.3.0
		 *
		 * @param int    $ticket_id   Ticket ID.
		 * @param string $content     Reply (plain text).
		 * @param int    $author_id   Author user ID (0 for a guest).
		 * @param bool   $by_staff    Whether the author answers for the store.
		 * @param array  $options     { close: bool, files_field: string, notify: bool }.
		 *
		 * @return int Reply ID, 0 on failure.
		 */
		public function add_reply( $ticket_id, $content, $author_id, $by_staff, $options = array() ) {
			global $wpdb;
			$options = wp_parse_args(
				$options,
				array(
					'close'       => false,
					'files_field' => '',
					'notify'      => true,
				)
			);

			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prefix . $this->table_name,
				array(
					'user'         => $this->get_ticket_user_id( $ticket_id ),
					'post_id'      => (int) $ticket_id,
					'creationdate' => current_time( 'mysql', 1 ),
					'agent'        => $by_staff ? (int) $author_id : 0,
					'content'      => sanitize_textarea_field( $content ),
					'attachments'  => '',
				)
			);
			// Read the new ID before other queries run (they overwrite `insert_id`).
			$reply_id = $inserted ? (int) $wpdb->insert_id : 0;
			if ( ! $reply_id ) {
				return 0;
			}

			if ( '' !== $options['files_field'] && class_exists( 'WPFactory_WC_STS_Files' ) ) {
				$ids = WPFactory_WC_STS_Files::get_instance()->handle_upload( $options['files_field'], $ticket_id, 'ids' );
				if ( ! empty( $ids ) ) {
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prefix . $this->table_name,
						array( 'attachments' => implode( ',', array_map( 'absint', $ids ) ) ),
						array( 'id' => $reply_id )
					);
				}
			}

			$this->touch_activity( $ticket_id, $by_staff ? 'store' : 'customer' );
			if ( $by_staff ) {
				delete_post_meta( $ticket_id, '_stsw_internal' );
			}
			if ( $by_staff && '' === (string) get_post_meta( $ticket_id, self::META_FIRST_REPLY, true ) ) {
				update_post_meta( $ticket_id, self::META_FIRST_REPLY, current_time( 'mysql', 1 ) );
			}

			$status = $this->get_ticket_status_slug( $ticket_id );
			if ( $options['close'] ) {
				$this->set_ticket_status( $ticket_id, 'closed' );
			} elseif ( $by_staff && in_array( $status, array( '', 'open' ), true ) ) {
				$this->set_ticket_status( $ticket_id, 'in-progress' );
			} elseif ( ! $by_staff && 'closed' === $status ) {
				// A customer reply to a closed ticket reopens it, so it is not missed.
				$this->set_ticket_status( $ticket_id, 'open' );
			}

			if ( $options['notify'] ) {
				/**
				 * A reply was added (emails are sent from here).
				 *
				 * @param int  $ticket_id Ticket ID.
				 * @param int  $reply_id  Reply ID.
				 * @param bool $by_staff  Whether the store replied.
				 */
				do_action( 'wpfactory_wc_sts_reply_added', (int) $ticket_id, $reply_id, (bool) $by_staff );
			}
			return $reply_id;
		}

		/**
		 * Adds a private note (staff only, never shown to the customer).
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $content   Note.
		 * @param int    $author_id Staff user ID.
		 *
		 * @return int Note ID.
		 */
		public function add_note( $ticket_id, $content, $author_id ) {
			global $wpdb;
			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prefix . $this->notes_table_name,
				array(
					'user'         => $this->get_ticket_user_id( $ticket_id ),
					'post_id'      => (int) $ticket_id,
					'creationdate' => current_time( 'mysql', 1 ),
					'agent'        => (int) $author_id,
					'content'      => sanitize_textarea_field( $content ),
				)
			);
			return $inserted ? (int) $wpdb->insert_id : 0;
		}

		/**
		 * Assigns a ticket to a staff user (0 removes the assignment).
		 *
		 * @param int $ticket_id Ticket ID.
		 * @param int $user_id   Staff user ID.
		 */
		public function assign_ticket( $ticket_id, $user_id ) {
			if ( $user_id ) {
				update_post_meta( $ticket_id, self::META_AGENT, (int) $user_id );
			} else {
				delete_post_meta( $ticket_id, self::META_AGENT );
			}
		}

		/**
		 * On ticket delete: remove its replies, notes and files.
		 *
		 * @param int $post_id Post ID.
		 */
		public function delete_relevant_responses( $post_id ) {
			if ( 'stsw_tickets' !== get_post_type( $post_id ) ) {
				return;
			}
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . $this->table_name, array( 'post_id' => (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->prefix . $this->notes_table_name, array( 'post_id' => (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			foreach ( get_attached_media( '', $post_id ) as $attachment ) {
				wp_delete_attachment( $attachment->ID, true );
			}
		}

		/*
		|--------------------------------------------------------------------------
		| Formatting
		|--------------------------------------------------------------------------
		*/

		/**
		 * "Store: Name" or "Customer: Name" for a reply row.
		 *
		 * @param object $res Reply row.
		 *
		 * @return string
		 */
		public function get_response_author_label( $res ) {
			if ( (int) $res->agent > 0 ) {
				$agent = get_user_by( 'id', (int) $res->agent );
				/* translators: %s: name of the store staff member who replied. */
				return sprintf( __( 'Store: %s', 'support-ticket-system-for-woocommerce' ), $agent ? $agent->display_name : get_bloginfo( 'name' ) );
			}
			$contact = $this->get_ticket_contact( (int) $res->post_id );
			$name    = $contact['display_name'] ? $contact['display_name'] : __( 'unknown', 'support-ticket-system-for-woocommerce' );
			/* translators: %s: customer name. */
			return sprintf( __( 'Customer: %s', 'support-ticket-system-for-woocommerce' ), $name );
		}

		/**
		 * Formats a stored GMT date in the site's date and time format.
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
		 * Escapes a ticket or reply text for display, keeping line breaks (older rows were stored HTML encoded; not double encoded).
		 *
		 * @param string $text Stored text.
		 *
		 * @return string Safe HTML.
		 */
		public function format_message( $text ) {
			return nl2br( esc_html( (string) $text ) );
		}

		/**
		 * Customer-facing tickets page (works with plain and pretty permalinks).
		 *
		 * @return string
		 */
		public function get_customer_tickets_url() {
			return wc_get_endpoint_url( 'tickets', '', wc_get_page_permalink( 'myaccount' ) );
		}

		/**
		 * Link the customer uses to see a ticket: My Account, or the signed guest link.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string
		 */
		public function get_ticket_customer_url( $ticket_id ) {
			if ( ! $this->get_ticket_user_id( $ticket_id ) && class_exists( 'WPFactory_WC_STS_Guest' ) ) {
				return WPFactory_WC_STS_Guest::get_instance()->get_ticket_link( $ticket_id );
			}
			return add_query_arg(
				array(
					'stsw_view' => 'ticket',
					'ticket'    => (int) $ticket_id,
				),
				$this->get_customer_tickets_url()
			);
		}

		/*
		|--------------------------------------------------------------------------
		| wp-admin menu and dashboard
		|--------------------------------------------------------------------------
		*/

		/**
		 * Submenu pages.
		 *
		 * @version 2.3.0
		 */
		public function menu_page() {
			$items = array(
				'edit-tags.php?taxonomy=stsw_tickets_priority&post_type=stsw_tickets' => __( 'Priorities', 'support-ticket-system-for-woocommerce' ),
				'edit-tags.php?taxonomy=stsw_tickets_subject&post_type=stsw_tickets' => __( 'Subjects', 'support-ticket-system-for-woocommerce' ),
				'edit.php?post_type=stsw_canned_reply' => __( 'Saved replies', 'support-ticket-system-for-woocommerce' ),
			);
			foreach ( $items as $slug => $title ) {
				add_submenu_page( WPFACTORY_WC_STS_PAGE, $title, $title, 'stsw_tickets', $slug, null ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
			}
			add_submenu_page(
				WPFACTORY_WC_STS_PAGE,
				__( 'Settings', 'support-ticket-system-for-woocommerce' ),
				__( 'Settings', 'support-ticket-system-for-woocommerce' ),
				'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown
				'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&tab=settings',
				null
			);
		}

		/**
		 * Dashboard: counters and the overview.
		 *
		 * @version 2.3.0
		 */
		public function tickets_dashboard() {
			$base     = admin_url( 'edit.php?post_type=stsw_tickets' );
			$counters = array(
				array( __( 'All', 'support-ticket-system-for-woocommerce' ), $base, $this->count_tickets( '' ) ),
				array( __( 'Open', 'support-ticket-system-for-woocommerce' ), add_query_arg( 'stsw_tickets_status', 'open', $base ), $this->count_tickets( 'open' ) ),
				array( __( 'In Progress', 'support-ticket-system-for-woocommerce' ), add_query_arg( 'stsw_tickets_status', 'in-progress', $base ), $this->count_tickets( 'in-progress' ) ),
			);
			echo '<div class="clearfix">';
			foreach ( $counters as $counter ) {
				echo '<div class="report_widget ' . esc_attr( $this->plugin ) . 'columns3 em"><b><a href="' . esc_url( $counter[1] ) . '">' . esc_html( $counter[0] ) . '<br/>' . (int) $counter[2] . '</a></b></div>';
			}
			echo '</div>';
			$this->dashboard_overview();
		}

		/**
		 * Number of tickets, optionally in one status.
		 *
		 * @param string $status Status slug, empty for all.
		 *
		 * @return int
		 */
		public function count_tickets( $status ) {
			$args = array(
				'post_type'      => 'stsw_tickets',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			);
			if ( '' !== $status ) {
				$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'stsw_tickets_status',
						'field'    => 'slug',
						'terms'    => $status,
					),
				);
			}
			$query = new WP_Query( $args );
			return (int) $query->found_posts;
		}

		/**
		 * Kept for add-ons written against 2.2.
		 *
		 * @return int
		 */
		public function get_all_tickets() {
			return $this->count_tickets( '' );
		}

		/**
		 * Kept for add-ons written against 2.2.
		 *
		 * @return int
		 */
		public function get_open_tickets() {
			return $this->count_tickets( 'open' );
		}

		/**
		 * Kept for add-ons written against 2.2.
		 *
		 * @return int
		 */
		public function get_in_progress_tickets() {
			return $this->count_tickets( 'in-progress' );
		}

		/**
		 * Dashboard overview: setup checks, quick links and the tickets waiting for a store reply.
		 *
		 * @version 2.3.0
		 */
		public function dashboard_overview() {
			$settings_url = admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&tab=settings' );

			if ( '1' !== (string) get_option( $this->plugin . 'mailToADmin' ) ) {
				echo '<div class="notice notice-warning inline"><p>' .
					esc_html__( 'Email alerts are off: you will not be told when a customer opens or replies to a ticket.', 'support-ticket-system-for-woocommerce' ) .
					' <a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Turn them on in Settings > Notifications', 'support-ticket-system-for-woocommerce' ) . '</a></p></div>';
			}

			echo '<p>' .
				'<a class="button" target="_blank" href="' . esc_url( $this->get_customer_tickets_url() ) . '">' . esc_html__( 'View the customer page', 'support-ticket-system-for-woocommerce' ) . '</a> ' .
				'<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=stsw_tickets' ) ) . '">' . esc_html__( 'All tickets', 'support-ticket-system-for-woocommerce' ) . '</a> ' .
				'<a class="button" href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'support-ticket-system-for-woocommerce' ) . '</a>' .
			'</p>';

			echo '<h2>' . esc_html__( 'Waiting for your reply', 'support-ticket-system-for-woocommerce' ) . '</h2>';
			$waiting = $this->get_tickets_waiting_for_reply( 10 );
			if ( empty( $waiting ) ) {
				echo '<p>' . esc_html__( 'No customer is waiting for a reply. Customers open tickets from My Account > Tickets, from their orders, or on any page with the [stsw_user_tickets] shortcode.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				return;
			}
			echo '<table class="widefat striped"><thead><tr>' .
				'<th>' . esc_html__( 'Ticket', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th>' . esc_html__( 'Customer', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th>' . esc_html__( 'Assignee', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th>' . esc_html__( 'Last message', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'</tr></thead><tbody>';
			foreach ( $waiting as $row ) {
				$contact = $this->get_ticket_contact( $row['id'] );
				echo '<tr>' .
					'<td><a href="' . esc_url( get_edit_post_link( $row['id'] ) ) . '">#' . esc_html( $this->get_ticket_number( $row['id'] ) ) . ' ' . esc_html( get_the_title( $row['id'] ) ) . '</a></td>' .
					'<td>' . esc_html( $contact['display_name'] ) . '</td>' .
					'<td>' . esc_html( $this->get_user_name( $this->get_ticket_assignee_id( $row['id'] ) ) ) . '</td>' .
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

			// Latest reply per ticket in one query.
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

		/*
		|--------------------------------------------------------------------------
		| My Account integration
		|--------------------------------------------------------------------------
		*/

		/**
		 * My Account menu item.
		 *
		 * @param array $menu_links Menu links.
		 *
		 * @return array
		 */
		public function tickets_link( $menu_links ) {
			$new = array( 'tickets' => $this->endpoint_title() );
			return array_slice( $menu_links, 0, 5, true ) + $new + array_slice( $menu_links, 1, null, true );
		}

		/**
		 * "Get Help" button on each order in My Account > Orders.
		 *
		 * @param array    $actions Order actions.
		 * @param WC_Order $order   Order.
		 *
		 * @return array
		 */
		public function stswpro_add_my_account_order_actions( $actions, $order = null ) {
			$label           = get_option( $this->plugin . 'renameOrderButtonLink' );
			$actions['help'] = array(
				'url'  => add_query_arg( 'order', $order ? $order->get_order_number() : '', $this->get_customer_tickets_url() ),
				'name' => ! empty( $label ) ? $label : __( 'Get Help', 'support-ticket-system-for-woocommerce' ),
			);
			return $actions;
		}

		/**
		 * "Tickets" column in My Account > Orders.
		 *
		 * @param array $columns Columns.
		 *
		 * @return array
		 */
		public function add_my_account_orders_column( $columns ) {
			$new = array();
			foreach ( $columns as $key => $name ) {
				$new[ $key ] = $name;
				if ( 'order-status' === $key ) {
					$new['order-tickets'] = __( 'Tickets', 'support-ticket-system-for-woocommerce' );
				}
			}
			return $new;
		}

		/**
		 * Number of tickets linked to an order (My Account > Orders column).
		 *
		 * @param WC_Order $order Order.
		 */
		public function render_my_account_orders_column( $order ) {
			$count = count( $this->get_order_ticket_ids( $order->get_id(), get_current_user_id() ) );
			if ( $count ) {
				echo '<a href="' . esc_url( add_query_arg( 'order', $order->get_order_number(), $this->get_customer_tickets_url() ) ) . '">' . (int) $count . '</a>';
			}
		}

		/**
		 * Ticket IDs linked to an order, optionally only those of one customer.
		 *
		 * @param int $order_id Order ID.
		 * @param int $user_id  Customer user ID (0: any).
		 *
		 * @return int[]
		 */
		public function get_order_ticket_ids( $order_id, $user_id = 0 ) {
			$meta = array(
				array(
					'key'   => self::META_ORDER,
					'value' => (int) $order_id,
				),
			);
			if ( $user_id ) {
				$meta[] = array(
					'key'   => self::META_USER,
					'value' => (int) $user_id,
				);
			}
			return array_map(
				'intval',
				get_posts(
					array(
						'post_type'      => 'stsw_tickets',
						'post_status'    => 'publish',
						'posts_per_page' => 50,
						'fields'         => 'ids',
						'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					)
				)
			);
		}

		/**
		 * View order page: the order's tickets and a "get help with this order" button.
		 *
		 * @version 2.3.0
		 *
		 * @param int $order_id Order ID.
		 */
		public function stswpro_view_order( $order_id = 0 ) {
			$order = $order_id ? wc_get_order( $order_id ) : false;
			if ( ! $order ) {
				return;
			}
			$url = add_query_arg(
				array(
					'stsw_view' => 'new',
					'order'     => $order->get_order_number(),
				),
				$this->get_customer_tickets_url()
			);
			?>
			<section class="wpfactory-wc-sts-order-help">
				<h2><?php echo esc_html( $this->endpoint_title() ); ?></h2>
				<?php
				$ids = $this->get_order_ticket_ids( $order->get_id(), get_current_user_id() );
				if ( $ids ) {
					echo '<ul>';
					foreach ( $ids as $id ) {
						$status = $this->get_ticket_term( $id, 'stsw_tickets_status' );
						echo '<li><a href="' . esc_url( $this->get_ticket_customer_url( $id ) ) . '">#' . esc_html( $this->get_ticket_number( $id ) ) . ' ' . esc_html( get_the_title( $id ) ) . '</a>' . ( $status ? ' (' . esc_html( $status->name ) . ')' : '' ) . '</li>';
					}
					echo '</ul>';
				} else {
					echo '<p>' . esc_html__( 'A problem with this order? Send us a message and we will reply by email.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				}
				?>
				<p><a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Get help with this order', 'support-ticket-system-for-woocommerce' ); ?></a></p>
			</section>
			<?php
		}

		/**
		 * Shortcode `[stsw_user_tickets]`: returns the ticket screen.
		 *
		 * @return string
		 */
		public function shortcode_output() {
			if ( class_exists( 'WPFactory_WC_STS_Init' ) ) {
				WPFactory_WC_STS_Init::enqueue_frontend_assets(); // Covers shortcodes in widgets and page builders.
			}
			ob_start();
			WPFactory_WC_STS_Customer::get_instance()->render_page();
			return ob_get_clean();
		}

		/*
		|--------------------------------------------------------------------------
		| Customer forms (screens: WPFactory_WC_STS_Customer)
		|--------------------------------------------------------------------------
		*/

		/**
		 * Handles the customer forms before any output, then redirects (Post/Redirect/Get).
		 *
		 * New request: to the confirmation screen. Reply, close, reopen: back to the request.
		 *
		 * @version 2.3.0
		 */
		public function handle_frontend_forms() {
			if (
				! isset( $_SERVER['REQUEST_METHOD'] ) ||
				'POST' !== $_SERVER['REQUEST_METHOD'] ||
				! is_user_logged_in() ||
				( ! isset( $_POST['stswticketfrontend'] ) && ! isset( $_POST['stswresponsefrontend'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
			) {
				return;
			}
			$nonce_field = isset( $_POST['stswticketfrontend'] ) ? 'stswticketfrontend' : 'stswresponsefrontend'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$customer    = WPFactory_WC_STS_Customer::get_instance();
			$return      = isset( $_POST['stsw_return'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['stsw_return'] ) ), '' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$base        = $return ? $return : $this->get_customer_tickets_url();

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ?? '' ) ), $nonce_field ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$this->add_frontend_notice( __( 'This form expired. Please refresh the page and send your message again.', 'support-ticket-system-for-woocommerce' ), 'error' );
				$this->redirect_back();
			}

			if ( 'stswticketfrontend' === $nonce_field ) {
				$message = $this->save_ticket();
				if ( $this->last_saved ) {
					if ( '' !== $message ) {
						$this->add_frontend_notice( $message, 'notice' );
					}
					wp_safe_redirect( $customer->get_view_url( 'sent', array( 'ticket' => $this->last_ticket_id ), $base ) );
					exit;
				}
				// Keep what the customer typed for the form shown again.
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
				set_transient(
					'wpfactory_wc_sts_draft_' . get_current_user_id(),
					array(
						'title'   => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
						'content' => sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) ),
						'order'   => absint( $_POST['ticketorder'] ?? 0 ),
						'reason'  => sanitize_key( wp_unslash( $_POST['stsw_reason'] ?? '' ) ),
					),
					10 * MINUTE_IN_SECONDS
				);
				// phpcs:enable WordPress.Security.NonceVerification.Missing
				$this->add_frontend_notice( '' !== $message ? $message : __( 'Your message could not be sent. Please check the form and try again.', 'support-ticket-system-for-woocommerce' ), 'error' );
				wp_safe_redirect( $customer->get_view_url( 'new', array(), $base ) );
				exit;
			}

			$message   = $this->save_response();
			$ticket_id = absint( $_POST['post_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $this->last_saved ) {
				$this->add_frontend_notice( '' !== $message ? $message : __( 'Thanks, your message was sent. We will reply by email.', 'support-ticket-system-for-woocommerce' ), 'success' );
			} else {
				$this->add_frontend_notice( '' !== $message ? $message : __( 'Your message could not be sent. Please check the form and try again.', 'support-ticket-system-for-woocommerce' ), 'error' );
			}
			wp_safe_redirect( $customer->get_view_url( 'ticket', array( 'ticket' => $ticket_id ), $base ) );
			exit;
		}

		/**
		 * Adds a notice shown on the next page view.
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
		 */
		public function redirect_back() {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			$url  = set_url_scheme( 'http://' . $host . $uri );
			wp_safe_redirect( $url ? $url : wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		/**
		 * Merchant text shown after a save (settings "Message after a customer opens a ticket / replies").
		 *
		 * @param string $option Option suffix.
		 *
		 * @return string
		 */
		public function get_saved_message( $option ) {
			$text = (string) get_option( $this->plugin . $option );
			return '' !== trim( $text ) ? wp_kses( wpautop( $text ), WPFactory_WC_STS_Init::$allowed_html ) : '';
		}

		/**
		 * Whether the attachment field is shown to customers.
		 *
		 * @return bool
		 */
		public function attachments_enabled() {
			return '1' === (string) get_option( $this->plugin . 'enableAttachments' );
		}

		/**
		 * Reads and checks the "new request" fields shared by the customer and guest forms (nonce verified by callers).
		 *
		 * @param WC_Order|false $order Linked order.
		 *
		 * @return array|string Ticket arguments, or an error message.
		 */
		public function get_posted_request( $order ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the callers.
			$content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
			$title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
			$reason  = sanitize_key( wp_unslash( $_POST['stsw_reason'] ?? '' ) );
			$reasons = $this->get_reasons();
			$reason  = isset( $reasons[ $reason ] ) ? $reason : '';
			$items   = array();
			if ( $order && ! empty( $_POST['stsw_items'] ) && is_array( $_POST['stsw_items'] ) ) {
				$valid = array_map( 'intval', array_keys( $order->get_items() ) );
				$items = array_values( array_intersect( array_map( 'absint', wp_unslash( $_POST['stsw_items'] ) ), $valid ) );
			}
			$priority = '1' === (string) get_option( $this->plugin . 'enablePriority' ) ? sanitize_text_field( wp_unslash( $_POST['stsw_tickets_priority'] ?? '' ) ) : '';
			$subject  = absint( $_POST['stsw_tickets_subject'] ?? 0 );
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			if ( '' === trim( $content ) ) {
				return __( 'Please write a message.', 'support-ticket-system-for-woocommerce' );
			}
			if ( strlen( $title ) < 3 ) {
				// No summary: the reason and order make the title, or the start of the message.
				$title = $reason ? $reasons[ $reason ] : wp_trim_words( $content, 8, '' );
				if ( $order ) {
					/* translators: 1: reason or message start, 2: order number. */
					$title = sprintf( __( '%1$s (order #%2$s)', 'support-ticket-system-for-woocommerce' ), $title, $order->get_order_number() );
				}
			}
			$fields = array();
			if ( class_exists( 'WPFactory_WC_STS_Fields' ) ) {
				$fields = WPFactory_WC_STS_Fields::get_instance()->get_posted_values();
				$error  = WPFactory_WC_STS_Fields::get_instance()->validate( $fields );
				if ( '' !== $error ) {
					return $error;
				}
			}
			$request = array(
				'title'       => $title,
				'content'     => $content,
				'order_id'    => $order ? (int) $order->get_id() : 0,
				'items'       => $items,
				'reason'      => $reason,
				'subject_id'  => $subject,
				'priority'    => $priority,
				'fields'      => $fields,
				'files_field' => $this->attachments_enabled() ? 'my_file_upload' : '',
			);
			/**
			 * Error message to refuse a new request, or an empty string (Pro: support period of the items).
			 *
			 * @param string $error   Error.
			 * @param array  $request Request arguments.
			 */
			$error = (string) apply_filters( 'wpfactory_wc_sts_validate_new_ticket', '', $request );
			return '' !== $error ? $error : $request;
		}

		/**
		 * Saves a request posted from the customer form (nonce verified by the caller).
		 *
		 * @version 2.3.0
		 *
		 * @return string Message for the customer.
		 */
		public function save_ticket() {
			$this->last_saved = false;
			$user_id          = get_current_user_id();
			if ( ! $user_id ) {
				return '';
			}
			// A linked order must belong to the customer (staff may link any order). The posted owner is never trusted.
			$order_id = absint( $_POST['ticketorder'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handle_frontend_forms().
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			if ( $order_id && ( ! $order || ( (int) $order->get_customer_id() !== $user_id && ! $this->can_manage_tickets( $user_id ) ) ) ) {
				return __( 'Wrong order.', 'support-ticket-system-for-woocommerce' );
			}
			/**
			 * Error message to refuse a sender, or an empty string (blocked senders).
			 *
			 * @param string $error  Error.
			 * @param string $email  Email.
			 * @param string $source Where: `account`, `guest`, `chat`, `otp` or `email`.
			 */
			$blocked = (string) apply_filters( 'wpfactory_wc_sts_check_sender', '', wp_get_current_user()->user_email, 'account' );
			if ( '' !== $blocked ) {
				return $blocked;
			}
			$request = $this->get_posted_request( $order );
			if ( ! is_array( $request ) ) {
				return $request;
			}
			$request['user_id'] = $user_id;
			$ticket_id          = $this->create_ticket( $request );
			if ( is_wp_error( $ticket_id ) ) {
				return __( 'Your request could not be saved. Please try again.', 'support-ticket-system-for-woocommerce' );
			}
			$this->last_saved     = true;
			$this->last_ticket_id = $ticket_id;
			return $this->get_upload_messages();
		}

		/**
		 * Saves a reply, or closes or reopens a request, from the customer screen (nonce verified by the caller).
		 *
		 * The ticket's customer, its assigned agent and store staff may act. Agents and staff reply for the store.
		 *
		 * @version 2.3.0
		 *
		 * @return string Message for the user.
		 */
		public function save_response() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle_frontend_forms().
			$this->last_saved = false;
			$ticket_id        = absint( $_POST['post_id'] ?? 0 );
			$content          = sanitize_textarea_field( wp_unslash( $_POST['response_content'] ?? '' ) );
			$action           = sanitize_key( wp_unslash( $_POST['stsw_ticket_action'] ?? 'reply' ) );
			$close            = 'close' === $action || ! empty( $_POST['closeTicket'] );
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$user_id = get_current_user_id();

			if ( ! $this->user_can_access_ticket( $ticket_id, $user_id ) ) {
				return __( 'Wrong user.', 'support-ticket-system-for-woocommerce' );
			}
			$by_staff = $this->get_ticket_user_id( $ticket_id ) !== $user_id;

			if ( 'reopen' === $action ) {
				$this->set_ticket_status( $ticket_id, 'open' );
				$this->last_saved     = true;
				$this->last_ticket_id = $ticket_id;
				return __( 'Your request is open again. Add a reply to tell us what you still need.', 'support-ticket-system-for-woocommerce' );
			}
			if ( '' === trim( $content ) ) {
				if ( $close ) {
					$this->set_ticket_status( $ticket_id, 'closed' );
					$this->last_saved     = true;
					$this->last_ticket_id = $ticket_id;
					return __( 'Your request is closed. You can reopen it at any time.', 'support-ticket-system-for-woocommerce' );
				}
				return __( 'Please write a message.', 'support-ticket-system-for-woocommerce' );
			}
			$reply_id = $this->add_reply(
				$ticket_id,
				$content,
				$user_id,
				$by_staff,
				array(
					'close'       => $close,
					'files_field' => $this->attachments_enabled() ? 'my_file_upload' : '',
				)
			);
			if ( ! $reply_id ) {
				return '';
			}
			$this->last_saved     = true;
			$this->last_ticket_id = $ticket_id;
			return trim( ( $by_staff ? '' : $this->get_saved_message( 'textforResponseSave' ) ) . ' ' . $this->get_upload_messages() );
		}

		/**
		 * Problems reported by the last upload (file type or size), as one line.
		 *
		 * @return string
		 */
		public function get_upload_messages() {
			if ( ! class_exists( 'WPFactory_WC_STS_Files' ) ) {
				return '';
			}
			return esc_html( implode( ' ', WPFactory_WC_STS_Files::get_instance()->errors ) );
		}

		/**
		 * Order ID the customer came from: the View Order page, or `?order=<number>` from the Get Help button.
		 *
		 * @return int Order ID owned by the current user, or 0.
		 */
		public function get_requested_order_id() {
			$order_id = 0;
			if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' ) ) {
				global $wp;
				$order_id = absint( $wp->query_vars['view-order'] ?? 0 );
			} elseif ( ! empty( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pre-fill.
				$order_id = $this->resolve_order_number( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			$order = $order_id ? wc_get_order( $order_id ) : false;
			if ( ! $order || ( (int) $order->get_customer_id() !== get_current_user_id() && ! $this->can_manage_tickets() ) ) {
				return 0;
			}
			return (int) $order->get_id();
		}

		/**
		 * Requests shown on the customer screen, latest activity first: the customer's own and tickets assigned to them
		 * (administrators see all).
		 *
		 * @param int $order_id Only tickets linked to this order (0: all).
		 * @param int $page     Page (20 tickets a page); 0: up to 100 without pages.
		 *
		 * @return WP_Query
		 */
		public function query_customer_tickets( $order_id = 0, $page = 0 ) {
			$user       = wp_get_current_user();
			$meta_query = array();
			if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'   => self::META_USER,
						'value' => (int) $user->ID,
					),
					array(
						'key'   => self::META_AGENT,
						'value' => (int) $user->ID,
					),
				);
			}
			if ( $order_id ) {
				$meta_query[] = array(
					'key'   => self::META_ORDER,
					'value' => (int) $order_id,
				);
			}
			$tax_query = array();
			if ( '1' === (string) get_option( $this->plugin . 'hideClosed' ) ) {
				$tax_query[] = array(
					'taxonomy' => 'stsw_tickets_status',
					'field'    => 'slug',
					'terms'    => 'closed',
					'operator' => 'NOT IN',
				);
			}
			if ( ! $this->can_manage_tickets( (int) $user->ID ) ) {
				$meta_query[] = array(
					'key'     => '_stsw_internal',
					'compare' => 'NOT EXISTS',
				);
			}
			$args = array(
				'post_type'      => 'stsw_tickets',
				'post_status'    => 'publish',
				'posts_per_page' => $page ? 20 : 100,
				'paged'          => max( 1, (int) $page ),
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'orderby'        => 'modified',
				'order'          => 'DESC',
			);
			/**
			 * Tickets listed in My Account (Pro: adds the company's shared tickets).
			 *
			 * @param array $args    WP_Query arguments.
			 * @param int   $user_id User ID.
			 */
			return new WP_Query( (array) apply_filters( 'wpfactory_wc_sts_customer_tickets_args', $args, (int) $user->ID ) );
		}

		/**
		 * My Account > Help content.
		 */
		public function stswpro_my_account_endpoint_content() {
			WPFactory_WC_STS_Customer::get_instance()->render_page();
		}
	}

endif;

WPFactory_WC_STS_Inc::$main_instance = new WPFactory_WC_STS_Inc(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- class property.
return WPFactory_WC_STS_Inc::$main_instance;
