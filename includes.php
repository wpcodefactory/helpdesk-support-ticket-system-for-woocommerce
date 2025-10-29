<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - STSWooCommerceInc Class
 *
 * @version 2.1.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

class STSWooCommerceInc {

	public $plugin                   = 'STSWooCommerce';
	public $name                     = 'Support Ticket System for WooCommerce';
	public $tableName                = 'stsw_responses';
	public $stswpro_table_db_version = '1.4';
	public $mailIt_allowed_html      = array(
		'a' => array(
			'style' => array(),
			'href'  => array(),
			'title' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'i'          => array('style' => array(),'class' => array(),'id'=>array() ),
		'br'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'em'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'strong'     => array('style' => array(),'class' => array(),'id'=>array() ),
		'h1'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'h2'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'h3'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'h4'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'h5'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'h6'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'img'        => array('style' => array(),'class' => array(),'id'=>array() ),
		'p'          => array('style' => array(),'class' => array(),'id'=>array() ),
		'div'        => array('style' => array(),'class' => array(),'id'=>array() ),
		'section'    => array('style' => array(),'class' => array(),'id'=>array() ),
		'ul'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'li'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'ol'         => array('style' => array(),'class' => array(),'id'=>array() ),
		'video'      => array('style' => array(),'class' => array(),'id'=>array() ),
		'blockquote' => array('style' => array(),'class' => array(),'id'=>array() ),
		'figure'     => array('style' => array(),'class' => array(),'id'=>array() ),
		'figcaption' => array('style' => array(),'class' => array(),'id'=>array() ),
		'style'      => array(),
		'iframe' => array(
			'height'          => array(),
			'src'             => array(),
			'width'           => array(),
			'allowfullscreen' => array(),
			'style'           => array(),
			'class'           => array(),
			'id'              => array(),
		),
		'img' => array(
			'alt'   => array(),
			'src'   => array(),
			'title' => array(),
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'video' => array(
			'width'    => array(),
			'height'   => array(),
			'controls' => array(),
			'class'    => array(),
			'id'       => array(),
		),
		'source' => array(
			'src'   => array(),
			'type'  => array(),
			'class' => array(),
			'id'    => array(),
		),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'Tickets' ) );

		add_action( 'admin_init', array( $this, 'metaBox' ) );

		add_action( 'save_post', array( $this, 'saveFields' ) );
		add_action( 'post_updated', array( $this, 'notifyUserOnWPedit' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'menu_page' ) );
		add_action( 'admin_footer', array( $this, 'deleteResponseEvent' ) );
		add_action( 'wp_ajax_responseDelete', array( $this, 'responseDelete' ) );
		add_action( 'before_delete_post', array( $this, 'deleteRelevantResponses' ) );

		add_filter( 'woocommerce_account_menu_items', array( $this,'stswproTicketsLink' ) );
		add_action( 'init', array( $this,'stswpro_add_endpoint' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'stswpro_add_my_account_order_actions'), 10, 2 );
		add_action( 'woocommerce_account_tickets_endpoint', array( $this, 'stswpro_my_account_endpoint_content' ) );
		add_shortcode( 'stsw_user_tickets' , array( $this, 'stswpro_my_account_endpoint_content' ) );

		add_filter( 'manage_stsw_tickets_posts_columns', array( $this, 'addColumnHeader' ) );
		add_action( 'manage_stsw_tickets_posts_custom_column', array( $this, 'addAdColumns' ), 10, 2 );
		add_filter( 'manage_edit-stsw_tickets_sortable_columns', array( $this, 'addColumnHeader' ) );
		add_filter( 'manage_stsw_tickets_posts_columns', array( $this, 'column_order' ) );

		add_action( 'restrict_manage_posts', array( $this, 'stswpro_filter_tickets' ), 10, 2);
		register_activation_hook( __FILE__, array( $this, 'stswpro_ticketReponse_table_install' ) );
		add_action( 'plugins_loaded', array( $this, 'stswpro_tickets_table_update_db_check' ) );
		add_action( 'woocommerce_view_order', array( $this, 'stswpro_view_order' ), 20 );

		add_filter( 'hook', array( $this, 'sendWithPlaceholders' ), 10, 2 );

	}

	/**
	 * stswpro_ticketReponse_table_install.
	 */
	public function stswpro_ticketReponse_table_install(){
		global $wpdb;

		$table_name = $wpdb->prefix . $this->tableName;

		$sql = "CREATE TABLE " . sanitize_text_field( $table_name ). " (
			id int(11) NOT NULL AUTO_INCREMENT,
			user int(11) NOT NULL,
			post_id int(11) NOT NULL,
			creationdate DATETIME NULL,
			agent int(11) NOT NULL,
			content longtext NOT NULL,
			attachments longtext NOT NULL,
			PRIMARY KEY  (id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		// save current database version for later use (on upgrade)
		add_option( 'stswpro_tickets_table_db_version', sanitize_text_field( $this->stswpro_table_db_version ) );

		/**
		*  new version of table
		*/
		$installed_ver = get_option( 'stswpro_tickets_table_db_version' );
		if ($installed_ver != $this->stswpro_table_db_version) {
			$sql = "CREATE TABLE " . sanitize_text_field ( $table_name ) . " (
			id int(11) NOT NULL AUTO_INCREMENT,
			user int(11) NOT NULL,
			post_id int(11) NOT NULL,
			creationdate DATETIME NULL,
			agent int(11) NOT NULL,
			content longtext NOT NULL,
			attachments longtext NOT NULL,
			PRIMARY KEY  (id)
			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);

			// notice that we are updating option, rather than adding it
			update_option('stswpro_tickets_table_db_version', sanitize_text_field( $this->stswpro_table_db_version ) );
		}
	}

	/**
	 * Trick to update plugin database, see docs.
	 */
	public function stswpro_tickets_table_update_db_check() {
		if (get_site_option('stswpro_tickets_table_db_version') != $this->stswpro_table_db_version) {
			$this->stswpro_ticketReponse_table_install();
		}
	}

	/**
	 * Tickets.
	 *
	 * @version 2.0.0
	 */
	public function Tickets() {

		//TICKETS POST TYPE
		register_post_type( 'stsw_tickets',
			array(
				'labels'                => array(
					'name'               => esc_html__( 'Tickets' ,'support-ticket-system-for-woocommerce' ),
					'singular_name'      => esc_html__( 'Ticket','support-ticket-system-for-woocommerce' ),
					'search_items'       => esc_html__( 'Search Tickets' ,'support-ticket-system-for-woocommerce' ),
					'all_items'          => esc_html__( 'All Tickets' ,'support-ticket-system-for-woocommerce' ),
					'parent_item'        => esc_html__( 'Parent Ticket','support-ticket-system-for-woocommerce' ),
					'parent_item_colon'  => esc_html__( 'Parent Ticket:','support-ticket-system-for-woocommerce' ),
					'edit_item'          => esc_html__( 'Edit Ticket','support-ticket-system-for-woocommerce' ),
					'update_item'        => esc_html__( 'Update Ticket' ,'support-ticket-system-for-woocommerce' ),
					'add_new_item'       => esc_html__( 'Add New Ticket' ,'support-ticket-system-for-woocommerce' ),
					'add_new'            => esc_html__( 'New Ticket','support-ticket-system-for-woocommerce' ),
					'new_item_name'      => esc_html__( 'New Ticket Name','support-ticket-system-for-woocommerce' ),
					'new_item'           => esc_html__( 'New Ticket','support-ticket-system-for-woocommerce' ),
					'menu_name'          => esc_html__( 'Tickets','support-ticket-system-for-woocommerce' ),
					'not_found'          => esc_html__( 'No Tickets found','support-ticket-system-for-woocommerce' ),
				),
				'description'           => esc_html__('Adding and editing my Tickets','support-ticket-system-for-woocommerce' ),
				'menu_icon'             => 'dashicons-calendar',
				'supports'              => array( 'title'),
				'show_in_rest'          => true,
				'rest_base'             => 'stsw_tickets',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'capability_type'       => 'page',
				'hierarchical'          => false,
				'menu_position'         => null,
				'public'                => false, // it's not public, it shouldn't have it's own permalink, and so on
				'publicly_queryable'    => true,  // you should be able to query it
				'show_ui'               => true,  // you should be able to edit it in wp-admin
				'show_in_menu'          => false,
				'exclude_from_search'   => true,  // you should exclude it from search results
				'show_in_nav_menus'     => false, // you shouldn't be able to add it to menus
				'has_archive'           => false, // it shouldn't have archive page
				'rewrite'               => false, // it shouldn't have rewrite rules
			)
		);

		//STATUS TAXONOMY
		$labels = array(
			'name'              => _x( 'Status', 'taxonomy general name', 'support-ticket-system-for-woocommerce' ),
			'singular_name'     => _x( 'Status', 'taxonomy singular name', 'support-ticket-system-for-woocommerce' ),
			'search_items'      => esc_html__( 'Search Status', 'support-ticket-system-for-woocommerce' ),
			'all_items'         => esc_html__( 'All Status','support-ticket-system-for-woocommerce' ),
			'parent_item'       => esc_html__( 'Parent Status','support-ticket-system-for-woocommerce' ),
			'parent_item_colon' => esc_html__( 'Parent Status:','support-ticket-system-for-woocommerce' ),
			'edit_item'         => esc_html__( 'Edit Status','support-ticket-system-for-woocommerce' ),
			'update_item'       => esc_html__( 'Update Status','support-ticket-system-for-woocommerce' ),
			'add_new_item'      => esc_html__( 'Add New Status' ,'support-ticket-system-for-woocommerce' ),
			'new_item_name'     => esc_html__( 'New Status Name' ,'support-ticket-system-for-woocommerce' ),
			'not_found'         => esc_html__( 'No Status found.','support-ticket-system-for-woocommerce' ),
			'menu_name'         => esc_html__( 'Status', 'support-ticket-system-for-woocommerce' ),
		);

		register_taxonomy( 'stsw_tickets_status',array('stsw_tickets'), array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'stsw_tickets_status' ),
		) );

	}

	/**
	 * Add metaboxes to tickets newly created post type.
	 */
	public function metaBox($post){

		add_meta_box(
			'stswpro_ticketContent',
			esc_html__( 'Ticket Content', 'support-ticket-system-for-woocommerce' ),
			array( $this, 'ticketContent' ),
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
			array( $this, 'appInfoCreate' ),
			'stsw_tickets',
			'side',
			'high'
		);

		add_meta_box(
			'assignTouser',
			esc_html__( 'Assign to User', 'support-ticket-system-for-woocommerce' ),
			array( $this, 'assignTouser' ),
			'stsw_tickets',
			'side',
			'high'
		);

		add_meta_box(
			'stswpro_ticketResponses',
			esc_html__( 'New Response', 'support-ticket-system-for-woocommerce' ),
			array( $this, 'responseCreate' ),
			'stsw_tickets',
			'normal',
			'high'
		);

	}

	/**
	 * get_ticket_user_id.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 */
	public function get_ticket_user_id( $ticket_id ) {
		return get_post_meta( $ticket_id, 'STSWooCommerceProticketuser', true );
	}

	/**
	 * set_ticket_user_id.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 *
	 * @todo    (v2.1.0) why `STSWooCommerceProticketuser` (and not `STSWooCommerceticketuser`)?
	 */
	public function set_ticket_user_id( $ticket_id, $user_id ) {
		return update_post_meta( $ticket_id, 'STSWooCommerceProticketuser', $user_id );
	}

	/**
	 * appInfoCreate.
	 *
	 * @version 2.1.0
	 */
	public function appInfoCreate( $post ) {
		global $post;

		?>
		<b><?php esc_html_e( 'Order', 'support-ticket-system-for-woocommerce' ); ?></b>:
		<span class="proVersion"><?php esc_html_e( 'Pro Version', 'support-ticket-system-for-woocommerce' ); ?></span>
		<br/>

		<?php $user = (int) $this->get_ticket_user_id( $post->ID ); ?>
		<b><?php esc_html_e( 'User', 'support-ticket-system-for-woocommerce' ); ?></b>:
		<?php
		if ( ! empty( $user ) ) {
			printf(
				'<a href="%1$s" target="_blank">%2$s</a>',
				esc_url( admin_url( 'user-edit.php?user_id=' . $user ) ),
				esc_attr( $this->getUsername( $user ) )
			);
		}
		?>
		<br/>

		<b><?php esc_html_e( 'Ticket Assignee', 'support-ticket-system-for-woocommerce' )?></b>:
		<span class="proVersion"><?php esc_html_e( 'Pro Version', 'support-ticket-system-for-woocommerce' ); ?></span>
		<?php

	}

	/**
	 * Function to return the name of a user based on id.
	 */
	public function getUsername( $id ) {
		$user = get_user_by( 'id', $id );
		return esc_html( $user->first_name . ' ' . $user->last_name );
	}

	/**
	 * Display ticket content in ticket edit screen post box.
	 */
	public function ticketContent($post){
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
					<th><?php echo esc_html( $post->post_content ); ?></th>
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
	 */
	public function responses($post){

		global $post;
		global $wpdb;
		$table_name = esc_html( $wpdb->prefix . $this->tableName );

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . esc_html( $table_name ) . " WHERE post_id=%d  AND user !='0' ORDER BY creationdate DESC ",
				$post->ID
			)
		);
		$count = 0;
		if(!empty($result)){
			print "<table class='wp-list-table widefat fixed striped posts'>";
			print "<tr>
			<th>".esc_html__("Date",'support-ticket-system-for-woocommerce' )."</th>
			<th>".esc_html__("Who Sent It",'support-ticket-system-for-woocommerce' )."</th>
			<th>".esc_html__("Message",'support-ticket-system-for-woocommerce' )."</th>
			<th>".esc_html__("Attachments",'support-ticket-system-for-woocommerce' )."</th>
			<th>".esc_html__("Action",'support-ticket-system-for-woocommerce' )."</th>";

			foreach($result as $res){
						if( $res->user  =='1' ){
							$who = 'site';
						}elseif($res->user !='1'){
							$who = 'customer';
						}else $who = 'site';

				print "<tr class='".(int)$res->id."'><th>".esc_html( $res->creationdate )."</th><th>".esc_html( $who )."</th><th>".esc_html( $res->content )."</th><th>";
						?>
						<span class='proVersion' ><?php print esc_html__( "Pro Version",'support-ticket-system-for-woocommerce' ) ; ?></span>
						<?php
				print "</th>
				<th><p id='deleteResponse'><a href='".esc_url( $res->id )."' id='".esc_attr( $res->id )."'>Delete</a></th>
				</tr>";
			}
			print "</table>";
		}else print esc_html__("No responses yet",'support-ticket-system-for-woocommerce' );
	}

	/**
	 * responseCreate.
	 */
	public function responseCreate($post){
		// wp editor for adding a new response to ticket from ticket edit screen
		global $post;
		echo wp_editor( '', esc_html( $this->plugin )."response" , array( 'textarea_name' => esc_html( $this->plugin )."response" ) );
	}

	/**
	 * assignTouser.
	 *
	 * @version 2.1.0
	 */
	public function assignTouser( $post ) {
		?>
		<span class="proVersion"><?php esc_html_e( 'Pro Version', 'support-ticket-system-for-woocommerce' ); ?></span>
		<?php
	}

	/**
	 * Function to save any custom meta fields for tickets created.
	 *
	 * @version 2.1.0
	 */
	public function saveFields() {

		if (
			! isset( $_POST[ esc_html( $this->plugin ) . 'response' ] ) ||
			empty( $_POST[ esc_html( $this->plugin ) . 'response' ]
		) {
			return;
		}

		global $post, $wpdb;

		$user         = $this->get_ticket_user_id( $post->ID );
		$current_user = wp_get_current_user();
		$response     = htmlspecialchars( sanitize_textarea_field( $_POST[ esc_html( $this->plugin ) . 'response' ] ) );
		$table_name   = esc_html( $wpdb->prefix . $this->tableName );

		$wpdb->insert(
			$table_name,
			array(
				'user'         => $user,
				'creationdate' => current_time( 'mysql', 1 ),
				'content'      => $response,
				'agent'        => (int) $current_user->ID,
				'post_id'      => (int) $post->ID
			)
		);

	}

	/**
	 * deleteResponseEvent.
	 */
	public function deleteResponseEvent() {
		// on delete button click, delete the response and clear the row from the table - via ajax call to responseDelete
		global $post;
		?>
		<script type="text/javascript" >

		jQuery(function ($) {

			$(document).on("click", '#deleteResponse a', function(event){
				event.preventDefault();

				var ajax_options = {
					action: 'responseDelete',
					nonce: '<?php echo wp_create_nonce( 'responseDelete'); ?>',
					ajaxurl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
					id: $( this ).attr( "id" )
				};

				$.post( ajaxurl, ajax_options, function(data) {
					$("tr."+data).remove(); //remove row of the deleted item
				});
			});

		});

		</script>
		<?php
	}

	/**
	 * responseDelete.
	 */
	public function responseDelete() {
		// function to delete the response and clear the row from the table
		if ( isset( $_POST['nonce'] ) &&  isset( $_POST['id'] ) && wp_verify_nonce( $_POST['nonce'], 'responseDelete' ) ) {

			check_ajax_referer( 'responseDelete','nonce' );

			global $post_type;
			global $wpdb;
			$id = (int)$_POST['id'];

			$table_name = $wpdb->prefix . $this->tableName;
			$wpdb->delete( esc_html( $table_name ), array( 'id' => $id ) );
			die(); // this is required to return a proper result
		}
	}

	/**
	 * On ticket delete, clear all responses from table.
	 */
	public function deleteRelevantResponses( $post_id ) {

		// Do not run on other post types
		$post_type = get_post_type( $post_id );
		if ( 'stsw_tickets' !== $post_type ) {
			return true;
		}

		global $post;
		global $wpdb;

		$table_name = $wpdb->prefix . $this->tableName;
		$wpdb->delete(
			esc_html( $table_name ),
			array( 'post_id' => (int) $post_id )
		);

	}

	/**
	 * Add extra columns to tickets list table for better management.
	 */
	public function addColumnHeader( $columns ) {
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
	 * @version 2.1.0
	 */
	public function addAdColumns( $column_name, $post_id ) {

		if ( in_array( $column_name, array( 'priority', 'Order', 'subject', 'Assignee' ) ) ) {
			?>
			<span class="proVersion"><?php esc_html_e( 'Pro', 'support-ticket-system-for-woocommerce' ); ?></span>
			<?php
		}

		if ( 'User' === $column_name ) {
			$user = (int) $this->get_ticket_user_id( $post_id );
			if ( $user ) {
				echo esc_html( $this->getUsername( $user ) );
			}
		}

		if ( 'Last Response' === $column_name ) {
			global $wpdb;
			$table_name = $wpdb->prefix . $this->tableName;
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . esc_html( $table_name ) . " WHERE post_id=%d AND user != '0' ORDER BY creationdate DESC ",
					(int) $post_id
				)
			);
			if ( ! empty( $result ) ) {
				echo esc_html( $result->creationdate );
			}
		}

	}

	/**
	 * column_order.
	 */
	public function column_order( $columns ) {

		// Reorder columns
		unset($columns['title']);
		unset($columns['date']);
		unset($columns['Assignee']);
		unset($columns['Last Response']);
		unset($columns['Order']);
		unset($columns['User']);
		unset($columns['subject']);
		unset($columns['title']);
		unset($columns['taxonomy-stsw_tickets_status']);
		unset($columns['priority']);

		return array_merge ( $columns, array (
			'title'                        => esc_html__('Title','support-ticket-system-for-woocommerce' ),
			'taxonomy-stsw_tickets_status' => esc_html__('Status','support-ticket-system-for-woocommerce' ),
			'User'                         => esc_html__('User','support-ticket-system-for-woocommerce' ),
			'Last Response'                => esc_html__('Last Response','support-ticket-system-for-woocommerce' ),
			'date'                         => esc_html__('Date','support-ticket-system-for-woocommerce' ),
			'subject'                      => esc_html__('Subject','support-ticket-system-for-woocommerce' ),
			'priority'                     => esc_html__('Priority','support-ticket-system-for-woocommerce' ),
			'Order'                        => esc_html__('Order','support-ticket-system-for-woocommerce' ),
			'Assignee'                     => esc_html__('Assignee','support-ticket-system-for-woocommerce' ),
		) );

	}

	/**
	 * menu_page.
	 *
	 * Add submenu pages to supports tickets link.
	 *
	 * @version 2.0.0
	 */
	public function menu_page() {

		add_submenu_page(
			'support-ticket-system-woocommerce',
			__( 'Tickets', 'support-ticket-system-for-woocommerce' ),
			__( 'Tickets', 'support-ticket-system-for-woocommerce' ),
			'manage_woocommerce',
			'edit.php?post_type=stsw_tickets',
			NULL
		);

		add_submenu_page(
			'support-ticket-system-woocommerce',
			__( 'Priorities', 'support-ticket-system-for-woocommerce' ),
			__( 'Priorities', 'support-ticket-system-for-woocommerce' ),
			'manage_woocommerce',
			'#',
			NULL
		);

		add_submenu_page(
			'support-ticket-system-woocommerce',
			__( 'Subject', 'support-ticket-system-for-woocommerce' ),
			__( 'Subject', 'support-ticket-system-for-woocommerce' ),
			'manage_woocommerce',
			'#',
			NULL
		);

		add_submenu_page(
			'support-ticket-system-woocommerce',
			__( 'Settings', 'support-ticket-system-for-woocommerce' ),
			__( 'Settings', 'support-ticket-system-for-woocommerce' ),
			'manage_woocommerce',
			admin_url( 'admin.php?page=support-ticket-system-woocommerce&tab=settings' ),
			array( $this, 'init' )
		);

	}

	/**
	 * ticketsDashboard.
	 */
	public function ticketsDashboard() {
		// content for dashboard tab  - the default in support tickets
		?>
		<div class='clearfix'>
			<div class='report_widget <?php print esc_html( $this->plugin ); ?>columns3 em'>
				<b>
					<a href='<?php print esc_url ( admin_url() );?>edit.php?post_type=stsw_tickets'>
						<?php  esc_html_e('ALL','support-ticket-system-for-woocommerce' );?><br/>
						<?php print esc_html( $this->getAllTickets() ); ?>
					</a>
				</b>
				</a>
			</div>
			<div class='report_widget <?php print esc_html( $this->plugin ); ?>columns3 em'>
				<b>
					<a href='<?php print esc_url ( admin_url() );?>edit.php?s&post_status=all&post_type=stsw_tickets&m=0&stsw_tickets_status=open'>
						<?php  esc_html_e('OPEN','support-ticket-system-for-woocommerce' );?><br/>
						<?php print esc_html( $this->getOpenTickets() ); ?>
					</a>
				</b>
				</a>
			</div>
			<div class='report_widget <?php print esc_html( $this->plugin ); ?>columns3 em'>
				<b>
					<a href='<?php print esc_url ( admin_url() );?>edit.php?s&post_status=all&post_type=stsw_tickets&m=0&stsw_tickets_status=in-progress'>
						<?php  esc_html_e('IN PROGRESS','support-ticket-system-for-woocommerce' );?><br/>
						<?php print esc_html( $this->getInProgressTickets() ); ?>
					</a>
				</b>
				</a>
			</div>
		</div>
	<?php
	}

	/**
	 * getAllTickets.
	 */
	public function getAllTickets(){
		// function to populate the dashboard screen
		$args = array(
			'post_type' => 'stsw_tickets'
		);
		$the_query = new WP_Query( $args );
		$totalpost = $the_query->found_posts;
		return esc_html( $totalpost );
	}

	/**
	 * getOpenTickets.
	 */
	public function getOpenTickets(){
		// function to populate the dashboard screen
		$the_query = new WP_Query( array(
			'post_type' => 'stsw_tickets',
			'tax_query' => array(
				array (
					'taxonomy' => 'stsw_tickets_status',
					'field' => 'slug',
					'terms' => 'open',
				)
			),
		) );
		$totalpost = $the_query->found_posts;
		return esc_html( $totalpost );
	}

	/**
	 * getInProgressTickets.
	 */
	public function getInProgressTickets(){
		// function to populate the dashboard screen
		$the_query = new WP_Query( array(
			'post_type' => 'stsw_tickets',
			'tax_query' => array(
				array (
					'taxonomy' => 'stsw_tickets_status',
					'field' => 'slug',
					'terms' => 'in-progress',
				)
			),
		) );
		$totalpost = $the_query->found_posts;
		return esc_html( $totalpost );
	}

	/**
	 * stswpro_add_my_account_order_actions.
	 *
	 * @version 2.0.0
	 */
	public function stswpro_add_my_account_order_actions( $actions, $order ) {
		//add a button to actions column of my account orders page
		if( get_option($this->plugin.'renameOrderButtonLink') && !empty( get_option($this->plugin.'renameOrderButtonLink') ) ){
			$buttonTitle = get_option( esc_html( $this->plugin ).'renameOrderButtonLink');
		}else $buttonTitle = esc_html__( 'Get Help'  ,'support-ticket-system-for-woocommerce' );
		$actions['help'] = array(
			// adjust URL as needed
			'url'  => esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ).'/tickets/' ),
			'name' => esc_html( $buttonTitle ),
		);

		return $actions;
	}

	/**
	 * stswproTicketsLink.
	 *
	 * Add ticketing functionality to users account page.
	 *
	 * @version 2.0.0
	 */
	public function stswproTicketsLink( $menu_links ){
		//add tab to my account page to ticketing system
		if( get_option( esc_html( $this->plugin ).'renameAccountTabLink') && !empty( get_option( esc_html( $this->plugin ).'renameAccountTabLink') ) ){
			$new = array( 'tickets' => esc_html( get_option( $this->plugin . 'renameAccountTabLink' ) ) );

		}else $new = array( 'tickets' => esc_html__( 'Tickets', 'support-ticket-system-for-woocommerce' ) );

		$menu_links = array_slice( $menu_links, 0, 5, true )
		+ $new
		+ array_slice( $menu_links, 1, NULL, true );
		return $menu_links ;

	}

	/**
	 * Register Permalink Endpoint.
	 */
	public function stswpro_add_endpoint() {
		add_rewrite_endpoint( 'tickets', EP_PAGES );
	}

	/**
	 * stswpro_my_account_endpoint_content.
	 *
	 * Add content to support ticketing system.
	 *
	 * @version 2.0.0
	 */
	public function stswpro_my_account_endpoint_content() {
		//user needs to be logged in
		if ( is_user_logged_in() ) {
			$this->stswproSaveTicket();
			$this->stswproSaveResponse();
			?>
			<style>
			.entry-title{display:none;}
			.ui-accordion-content{
				height: auto !important;
			}
			</style>
		 <div class='stswproaccordion'>
			<?php
			$customer = wp_get_current_user();

			$cat_query ='';
			/// hide closed tickets setting
			if( get_option( esc_html( $this->plugin ).'hideClosed') && get_option( esc_html( $this->plugin ).'hideClosed') ==='1' ){
				$category = 'stsw_tickets_status';
				$term = 'closed';

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
			//SHOW IN ADMIN ALL, SHOW IN ASSIGNEED ONLY WHAT IS ASSIGNED, SHOW TO CUSTOMER WHAT HE/SHE OPENED
			if (in_array("customer", $customer->roles) && !in_array("administrator", $customer->roles) ){
				$user_id = array('key'     => 'STSWooCommerceProticketuser','value'   => (int)$customer->ID,'compare' => '=');
				array_push($meta_query,$user_id );

			}else{
				if(in_array("administrator", $customer->roles)){

				}else{
					$user_id = array('key'     => 'STSWooCommerceProticketagent','value'   => (int)$customer->ID,'compare' => '=');
					array_push($meta_query,$user_id );
				}
			}

			$args = array(
				'meta_query' => $meta_query,
				'tax_query'  => $cat_query,
				'post_type'  => 'stsw_tickets',
				'posts_per_page' => -1
			);

			$query = new WP_Query( $args );
			   if($query->have_posts()) {
				?>
				<h3>
					<?php esc_html_e("TICKETS",'support-ticket-system-for-woocommerce' );?> <i class='fa fa-angle-down'></i>
				</h3>
				<div class="postbox">
					<div class='stswproaccordion2'>
					<?php
					while($query->have_posts()) {
					 $query->the_post();

					 $status = wp_get_post_terms( get_the_ID(), 'stsw_tickets_status', 'name' );
					 ?>

					 <h3>
						<?php the_title(); ?> - <?php echo esc_html( get_the_date() ); ?> - <?php if(!empty($status) )print  esc_html( $status[0]->name ) ; ?> <i class='fa fa-angle-down'></i>
					 </h3>
					 <div class='post-content'>

					 <table class='wp-list-table widefat fixed striped posts'>
						<thead>
							<tr>
								<th><?php esc_html_e("Title",'support-ticket-system-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th><?php the_content() ?></th>
							</tr>
						</tbody>
					 </table>

					<?php

					global $wpdb;
					$table_name = esc_html( $wpdb->prefix . $this->tableName ); // do not forget about tables prefix
					$result = $wpdb->get_results( $wpdb->prepare("SELECT * FROM ".$table_name."  WHERE post_id=%d  ORDER BY creationdate DESC ",(int)get_the_ID() ) );
					$count = 0;
					if(!empty($result)){
						print "<h4>".esc_html__("Responses",'support-ticket-system-for-woocommerce' )."</h4>";
						print "<table class='wp-list-table widefat fixed striped posts'>";
						print "<tr>
								<th>".esc_html__( "Date",'support-ticket-system-for-woocommerce' )."</th>
								<th>".esc_html__( "Who Responded",'support-ticket-system-for-woocommerce' )."</th>
								<th>".esc_html__( "Content",'support-ticket-system-for-woocommerce' )."</th>";

						foreach($result as $res){

							if( current_user_can("administrator") ){
								if( $res->user == '1' ){

									$who = 'you';
								}else{

									$who = 'customer';
								}
							}else{
								if( $res->user == '1' ){

									$who = 'site';
								}else{

									$who = 'you';
								}
							}

								print "<tr>
										<th>".esc_html( $res->creationdate )."</th>
										<th>".esc_html( $who )."</th>
										<th>".esc_html( $res->content )."</th>";

							print " </tr>";
						}
						print "</table>";
					}
					?>
			   <?php if( (!empty($status) && $status[0]->name !='Closed' )|| $status!='' ){ ?>
						<div class='stswproaccordion3'>
							<h4><?php esc_html_e( "Add a response",'support-ticket-system-for-woocommerce' ); ?> <i class='fa fa-plus'></i></h4>
							<div>
								<form class="<?php print esc_html( $this->plugin );?>new_response" name="<?php print esc_html( $this->plugin );?>new_response" method="post" >
									<textarea id="content" tabindex="3" class='tinymce-enabled' name="response_content" cols="50" rows="3"></textarea>
									<input type="hidden" value="<?php print esc_attr( $customer->ID );?>"  name="customer_id" />
									<input type="hidden" value="<?php print esc_attr( get_the_ID() );?>"  name="post_id" />

									<label for='closeTicket'><?php esc_html_e( "Close Ticket", 'support-ticket-system-for-woocommerce' ); ?></label> <input type='checkbox' name='closeTicket' value='1' />
									<p></p>
									<?php wp_nonce_field( 'stswresponsefrontend','stswresponsefrontend' ); ?>
									<input type="submit" value="<?php esc_html_e( "Send", 'support-ticket-system-for-woocommerce' ); ?>" tabindex="6" id="submit" name="submit" />

								</form>
							</div>
						</div>
						<?php } ?>
						</div>
						<?php
					}
					?>
				</div>
				</div><?php
				}else esc_html_e( " No Tickets found",'support-ticket-system-for-woocommerce' ) ; ?>

			<h3><?php esc_html_e( "ADD NEW TICKET",'support-ticket-system-for-woocommerce' ); ?> <i class='fa fa-plus'></i></h3>

			<div class="postbox">
				<form  class="<?php print esc_html( $this->plugin );?>new_ticket" name="<?php print esc_html( $this->plugin );?>new_ticket" method="post">

				<p>
					<label for="title">
						<?php esc_html_e( "Title",'support-ticket-system-for-woocommerce' ) ; ?>
					</label><br />
					<input type="text" id="title" value="" tabindex="1" size="20" name="title" />
				</p>

				<p>
					<label for="content">
						<?php esc_html_e( "Message",'support-ticket-system-for-woocommerce' );?>
					</label><br />
					<textarea id="content" tabindex="3" name="content" class='tinymce-enabled' cols="50" rows="3"></textarea>
				</p>

				<input type="hidden" id="ticketuser" value="<?php print (int)get_current_user_id(); ?>" tabindex="1" size="20" name="ticketuser" />

				<?php wp_nonce_field( 'stswticketfrontend','stswticketfrontend' ); ?>

				<p align="right"><input type="submit" value="<?php esc_html_e( "Send",'support-ticket-system-for-woocommerce' );?>" tabindex="6" id="submit" name="submit" /></p>

				</form>
			</div>
		 </div>
		<?php
		}else{//check if user is logged in, if not display login form
			echo do_shortcode('[woocommerce_my_account]');
		}

	}

	/**
	 * verify_user.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 */
	public function verify_user( $user_id, $ticket_id ) {

		if (
			! function_exists( 'wp_get_current_user' ) ||
			! ( $current_user = wp_get_current_user() ) ||
			$user_id !== $current_user->ID
		) {
			return false;
		}

		if (
			! current_user_can( 'manage_woocommerce' ) &&
			(
				! ( $ticket_user_id = (int) $this->get_ticket_user_id( $ticket_id ) ) ||
				$user_id !== $ticket_user_id
			)
		) {
			return false;
		}

		return true;

	}

	/**
	 * Function to save a response from frontend.
	 *
	 * @version 2.1.0
	 */
	public function stswproSaveResponse() {

		if (
			'POST' === $_SERVER['REQUEST_METHOD'] &&
			isset( $_POST['stswresponsefrontend'] )
		) {

			// Form is submitted via ajax
			check_ajax_referer( 'stswresponsefrontend', 'stswresponsefrontend' );

			// Stop running function if form wasn't submitted
			if (
				! isset( $_POST['response_content'] ) ||
				strlen( $_POST['response_content'] ) <= 1
			) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['stswresponsefrontend'], 'stswresponsefrontend' ) ) {
				esc_html_e( 'Did not save because your submission is invalid...', 'support-ticket-system-for-woocommerce' );
				return;
			}

			$response    = sanitize_textarea_field( $_POST['response_content'] ) ;
			$post_id     = (int) $_POST['post_id'];
			$customer_id = (int) $_POST['customer_id'];

			// Verify user
			if ( ! $this->verify_user( $customer_id, $post_id ) ) {
				esc_html_e( 'Wrong user.' ,'support-ticket-system-for-woocommerce' );
				return;
			}

			global $wpdb;

			$table_name = $wpdb->prefix . $this->tableName;
			$wpdb->insert(
				$table_name,
				array(
					'user'         => $customer_id,
					'creationdate' => current_time( 'mysql', 1 ),
					'content'      => $response,
					'post_id'      => $post_id,
				)
			);

			if (
				get_option( esc_html( $this->plugin ) . 'textforResponseSave' ) &&
				! empty( get_option( $this->plugin . 'textforResponseSave' ) )
			) {
				echo wp_kses(
					get_option( esc_html( $this->plugin ) . 'textforResponseSave' ),
					$this->mailIt_allowed_html
				);
			}

			if ( ! empty( $_POST['closeTicket'] ) ) {
				wp_set_object_terms( $post_id, 'Closed', 'stsw_tickets_status' );
			}

			$lastid = (int) $wpdb->insert_id;

			$user = get_user_by( 'id', $customer_id );

			// sendWithPlaceholders
			$ticketId        = $post_id;
			$responseId      = $lastid;
			$title           = '';
			$responseContent = $response;
			$toEmail         = sanitize_email( $user->user_email );
			$toFirstName     = esc_html( $user->first_name );
			$toLastName      = esc_html( $user->last_name );

			$this->sendWithPlaceholders(
				$ticketId,
				$responseId,
				$title,
				$responseContent,
				$toEmail,
				$toFirstName,
				$toLastName,
				$user
			);

		}

	}

	/**
	 * Save ticket submitted from frontend.
	 *
	 * @version 2.1.0
	 */
	public function stswproSaveTicket() {

		if (
			'POST' === $_SERVER['REQUEST_METHOD'] &&
			isset( $_POST['stswticketfrontend'] )
		) {

			// Submission via ajax - check
			check_ajax_referer( 'stswticketfrontend','stswticketfrontend' );

			// Stop running function if form wasn't submitted
			if ( ! isset( $_POST['title'], $_POST['content'], $_REQUEST['ticketuser'] ) ) {
				return;
			}

			// Check user
			if (
				! function_exists( 'get_current_user_id' ) ||
				get_current_user_id() !== ( $ticket_user = (int) $_REQUEST['ticketuser'] )
			) {
				esc_html_e( 'Wrong user.', 'support-ticket-system-for-woocommerce' );
				return;
			}

			// Check that the nonce was set and valid
			if ( ! wp_verify_nonce( $_POST['stswticketfrontend'], 'stswticketfrontend' ) ) {
				esc_html_e( 'Did not save because your submission has issues.', 'support-ticket-system-for-woocommerce' );
				return;
			}

			// Form validation to make sure there is content
			if ( strlen( $_POST['title'] ) < 3 ) {
				esc_html_e( 'Please enter a proper title. Titles must be at least 3 characters long.', 'support-ticket-system-for-woocommerce' ) ;
				return;
			}
			if ( strlen( $_POST['content'] ) < 1 ) {
				esc_html_e( 'Please enter content more than 1 characters in length.', 'support-ticket-system-for-woocommerce' ) ;
				return;
			}

			// Add the content of the form to $post as an array
			$post = array(
				'post_title'   => sanitize_text_field( $_POST['title'] ),
				'post_content' => sanitize_text_field( $_POST['content'] ),
				'post_type'    => 'stsw_tickets',
				'post_status'  => 'publish',
			);
			$ticket_id = wp_insert_post( $post );
			$ticket_id = (int) $ticket_id;

			// Display a message
			if (
				get_option( esc_html( $this->plugin ) . 'textforTicketSave' ) &&
				! empty( get_option( esc_html( $this->plugin ) . 'textforTicketSave' ) )
			) {
				echo wp_kses(
					get_option( esc_html( $this->plugin ) . 'textforTicketSave' ),
					$this->mailIt_allowed_html
				);
			}

			// Update user for ticket
			$this->set_ticket_user_id( $ticket_id, $ticket_user );

			//set ticket status as open
			wp_set_object_terms( $ticket_id, 'Open', 'stsw_tickets_status' );

			$user = get_user_by( 'id', $ticket_user );

			// sendWithPlaceholders
			$ticketId      = $ticket_id;
			$responseId    ='';
			$ticketTitle   = esc_html( $_POST['title'] );
			$ticketContent = esc_html( $_POST['content'] );
			$toEmail       = sanitize_email( $user->user_email );
			$toFirstName   = esc_html( $user->first_name );
			$toLastName    = esc_html( $user->last_name );

			$this->sendWithPlaceholders(
				$ticketId,
				$responseId,
				$ticketTitle,
				$ticketContent,
				$toEmail,
				$toFirstName,
				$toLastName,
				$user
			);

		}

	}

	/**
	 * sendWithPlaceholders.
	 */
	public function sendWithPlaceholders($ticketId,$responseId,$title,$content,$toEmail,$toFirstName,$toLastName,$user){

			//proversion placeholders

			//TICKET SUBMITTED CASE - THEN SEND EMAIL
			if ( isset($_POST['title']) ) {
				//SEND EMAIL TO ADMIN
				if(get_option( esc_html( $this->plugin ).'mailToADmin' ) && get_option( esc_html( $this->plugin ).'mailToADmin' )=='1'){

					if (!empty(get_option( esc_html( $this->plugin ).'AdminEmailAddress' )) ) {
						$adminEmail = sanitize_email( get_option( $this->plugin.'AdminEmailAddress' ) );
					}else $adminEmail = sanitize_email( get_bloginfo("admin_email") );

					$sub = esc_html__( "New Ticket to ",'support-ticket-system-for-woocommerce' ).esc_html( get_bloginfo('name') )." - #".(int)$ticketId." ".esc_html( $_POST['title'] );

					$msg = esc_html( $_POST['title'] ). "<br/>".esc_html( $_POST['content'] )."<br/><a href='".esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) )."/tickets'>". esc_html__( "Check it Here", 'support-ticket-system-for-woocommerce' ) ."</a>";

					$this->notifyUsers($adminEmail,$sub,$msg);
				}

				//SEND EMAIL TO USER
				if(get_option( esc_html( $this->plugin ).'mailToCustomer' ) && get_option( esc_html( $this->plugin ).'mailToCustomer' )=='1'){
					$to = sanitize_email( $user->user_email );

					if(get_option( esc_html( $this->plugin ).'mailIt_subjectToCust' ) && !empty(get_option( esc_html( $this->plugin ).'mailIt_subjectToCust' )) ){
						$sub = esc_html( get_option( $this->plugin.'mailIt_subjectToCust' ) );

					}else  $sub = esc_html__( "New Ticket to ",'support-ticket-system-for-woocommerce' ).esc_html( get_bloginfo('name') )." - #".(int)$ticketId." ".esc_html( $_POST['title'] );

					if(get_option( esc_html( $this->plugin ).'mailIt_contentToCust' ) && !empty(get_option( esc_html( $this->plugin ).'mailIt_contentToCust' )) ){
						$msg =  wp_kses( get_option( esc_html( $this->plugin ).'mailIt_contentToCust' ) , $this->mailIt_allowed_html );
					}else  $msg = esc_html( $title ). "<br/>".esc_html( $content )."
					<br/><a href='".esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) )."/tickets'>". esc_html__( "Check it Here",'support-ticket-system-for-woocommerce' ) ."</a>";

					$this->notifyUsers($to,$sub,$msg);
				}
			}

			//RESPONSE TO TICKET SUBMITTED CASE - THEN SEND EMAIL
			if ( isset($_POST['response_content']) ) {

				//SEND EMAIL TO ADMIN
				if(get_option( esc_html( $this->plugin ).'mailToADmin' ) && get_option( esc_html( $this->plugin ).'mailToADmin' )=='1'){

					if (!empty(get_option( esc_html( $this->plugin ).'AdminEmailAddress' )) ) {
						$adminEmail = sanitize_email( get_option( $this->plugin.'AdminEmailAddress' ) );
					}else $adminEmail = sanitize_email( get_bloginfo("admin_email") );

					$sub = esc_html__( "New Response to ticket #",'support-ticket-system-for-woocommerce' ).(int)$ticketId." - #".(int)$responseId;

					$msg = esc_html( $_POST['response_content'])."
					<br/><a href='".esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) )."/tickets'>". esc_html__( "Check it Here",'support-ticket-system-for-woocommerce' ) ."</a>";

					$this->notifyUsers($adminEmail,$sub,$msg);
				}

				//SEND EMAIL TO USER
				if(get_option( esc_html( $this->plugin ).'mailToCustomer' ) && get_option( esc_html( $this->plugin ).'mailToCustomer' )=='1'){
					$to = sanitize_email( $user->user_email );
						if(get_option( esc_html( $this->plugin ).'mailIt_subjectToCust' ) && !empty(get_option( esc_html( $this->plugin ).'mailIt_subjectToCust' )) ){
							$sub =  esc_html( get_option( esc_html( $this->plugin ).'mailIt_subjectToCust' ) ) ;
						}else  $sub = esc_html( get_bloginfo('name') )." - we received #".(int)$responseId." for ticket #".(int)$ticketId;

						if(get_option( esc_html( $this->plugin ).'mailIt_contentToCust' ) && !empty(get_option( esc_html( $this->plugin ).'mailIt_contentToCust' )) ){
							$msg =  wp_kses( get_option( esc_html( $this->plugin ).'mailIt_contentToCust' ) , $this->mailIt_allowed_html );
						}else  $msg = esc_html( $title ). "<br/>".esc_html( $content )."
						<br/><a href='".esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) )."/tickets'>". esc_html__( "Check it Here", 'support-ticket-system-for-woocommerce' ) ."</a>";

					$this->notifyUsers($to,$sub,$msg);
				}
			}
	}

	/**
	 * notifyUsers.
	 */
	public function notifyUsers($to,$subject,$message){

		if (!empty(get_option( esc_html( $this->plugin ).'AdminEmailAddress' )) ) {
			$adminEmail = sanitize_email( get_option( esc_html( $this->plugin ).'AdminEmailAddress' ) );
		}else $adminEmail = sanitize_email( get_bloginfo("admin_email") );

		$headers[] = "Content-Type: text/html; charset=UTF-8";
		$headers[] = "From: ".esc_html( get_bloginfo('name') )." <".esc_html( $adminEmail ).">";
		$headers[] = "Reply-To: ".$to." <".$to.">";
		$sent_message = wp_mail( $to, $subject, $message, $headers);
	}

	/**
	 * Notify user on WP edit adding response.
	 *
	 * @version 2.1.0
	 *
	 * @todo    (v2.1.0) use `get_ticket_user_id()`
	 */
	public function notifyUserOnWPedit( $post_id, $post, $update ) {

		if (
			! empty( $_REQUEST[ $this->plugin . 'response' ] ) &&
			get_option( $this->plugin . 'mailToCustomer' ) &&
			'1' == get_option( $this->plugin . 'mailToCustomer' )
		) {

			$ticketId        = $post_id;
			$responseContent = esc_html( $_REQUEST[ $this->plugin . 'response' ] );
			$user_id         = get_post_meta( $post_id, $this->plugin . "ticketuser", true );
			$user            = get_user_by( 'id', $user_id );
			$toEmail         = sanitize_email( $user->user_email );
			$toFirstName     = esc_html( $user->first_name );
			$toLastName      = esc_html( $user->last_name );
			$title           = get_the_title( $post_id );
			$content         = get_the_content( $post_id );

			// If this isn't a `stsw_tickets` post, don't update it
			$post_type = get_post_type($post_id);
			if ( 'stsw_tickets' !== $post_type ) {
				return;
			}

			if ( $user ) {
				$to = sanitize_email( $user->user_email ) ;
			}

			if (
				get_option( $this->plugin . 'mailIt_subjectToCust' ) &&
				! empty( get_option( $this->plugin . 'mailIt_subjectToCust' ) )
			) {
				$sub = esc_html( $subjectToCust );
			} else {
				$sub = (
					esc_html__( 'New Response to ticket #', 'support-ticket-system-for-woocommerce' ) .
					(int) $post_id . ' - ' .
					esc_html( $title )
				);
			}

			if(
				get_option( $this->plugin . 'mailIt_contentToCust' ) &&
				! empty( get_option( $this->plugin . 'mailIt_contentToCust' ) )
			) {
				$msg = esc_html( $messageToCust );
			} else {
				$msg = (
					esc_html( $responseContent ) .
					'
					<br/><a href="' . esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) ) . '/tickets">' .
						esc_html__( 'Check it Here', 'support-ticket-system-for-woocommerce' ) .
					'</a>'
				);
			}

			if (
				get_option( $this->plugin . 'mailIt_subjectToCust' ) &&
				! empty( get_option( $this->plugin . 'mailIt_subjectToCust' ) )
			) {
				$msg = wp_kses(
					get_option( $this->plugin . 'mailIt_subjectToCust' ),
					$this->mailIt_allowed_html
				);
			} else {
				$msg = (
					esc_html( $title ) .
					'<br/>' .
					esc_html( $content ) .
					'<br/>
					Last Response: ' .
					esc_html( $responseContent ) .
					'<br/>
					<a href="' . esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) ) . '/tickets">' .
						esc_html__( 'Check it Here', 'support-ticket-system-for-woocommerce' ) .
					'</a>'
				);
			}

			if ( ! empty( get_option( $this->plugin . 'AdminEmailAddress' ) ) ) {
				$adminEmail = esc_html( get_option( $this->plugin . 'AdminEmailAddress' ) );
			} else {
				$adminEmail = sanitize_email( get_bloginfo( 'admin_email' ) );
			}

			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$headers[] = 'From: ' . esc_html( get_bloginfo( 'name' ) ) . ' <' . $adminEmail . '>';
			$headers[] = 'Reply-To: ' . $to . ' <' . $to . '>';

			$sent_message = wp_mail( $to, $sub, $msg, $headers );

		}

	}

	/**
	 * stswpro_filter_tickets.
	 */
	public function stswpro_filter_tickets( $post_type, $which ) {
		// this function adds filtering based on status in tickets list table
		// Apply this only on stsw_tickets specific post type
		if ( 'stsw_tickets' !== $post_type )
			return;

		// A list of taxonomy slugs to filter by
		$taxonomies = array( 'stsw_tickets_status');

		foreach ( $taxonomies as $taxonomy_slug ) {

			// Retrieve taxonomy data
			$taxonomy_obj = get_taxonomy( $taxonomy_slug );
			$taxonomy_name = $taxonomy_obj->labels->name;

			// Retrieve taxonomy terms
			$terms = get_terms( $taxonomy_slug );

			// Display filter HTML
			echo "<select name='".esc_attr( $taxonomy_slug )."' id='".esc_attr( $taxonomy_slug )."' class='postform'>";
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
					( ( isset( $_GET[$taxonomy_slug] ) && ( $_GET[$taxonomy_slug] == $term->slug ) ) ? ' selected="selected"' : '' ),
					esc_attr( $term->name ),
					esc_attr( $term->count )
				);
			}
			echo '</select>';
		}
	}

	/**
	 * stswpro_view_order.
	 *
	 * @version 2.0.0
	 */
	public function stswpro_view_order( $order_id ){
		// this function adds a title to ticket support page
		if( get_option( esc_html( $this->plugin ).'renameAccountTabLink') && !empty( get_option( esc_html( $this->plugin ).'renameAccountTabLink') ) ){ ?>
			<h2><?php esc_html( get_option( $this->plugin . 'renameAccountTabLink' ) ) ; ?></h2>
			<?php
		}else{ ?> <h2><?php esc_html__("Tickets",'support-ticket-system-for-woocommerce' ) ; ?></h2> <?php } ?>
		<?php
		$this->stswpro_my_account_endpoint_content();
	}

}

/**
 * start.
 */
$start = new STSWooCommerceInc();
