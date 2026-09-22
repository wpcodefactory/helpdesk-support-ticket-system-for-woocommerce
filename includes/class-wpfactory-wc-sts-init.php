<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - WPFactory_WC_STS_Init Class
 *
 * @version 2.2.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Init' ) ) :

	/**
	 * WPFactory_WC_STS_Init class.
	 *
	 * @version 2.2.0
	 */
	class WPFactory_WC_STS_Init {

		/**
		 * Tabs.
		 *
		 * @var array
		 */
		public $tab;

		/**
		 * Active tab.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $active_tab;

		/**
		 * Hide closed.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $hide_closed = '';

		/**
		 * Text for ticket save.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $text_for_ticket_save = 'Saved your ticket successfully! We will come back to you soon';

		/**
		 * Text for response save.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $text_for_response_save = 'Saved your response successfully! We will come back to you soon';

		/**
		 * Rename account tab link.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $rename_account_tab_link = '';

		/**
		 * Rename order button link.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $rename_order_button_link = '';

		/**
		 * Mail to admin.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $mail_to_admin = 'mailToADmin';

		/**
		 * Admin email address.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $admin_email_address = 'AdminEmailAddress';

		/**
		 * Mail to user.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $mail_to_user = 'mailToUser';

		/**
		 * Mail to customer.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $mail_to_customer = '';

		/**
		 * Mail content to customer.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $mail_content_to_customer = '';

		/**
		 * Mail subject to customer.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $mail_subject_to_customer = '';

		/**
		 * Allowed HTML tags and attributes.
		 *
		 * @version 2.2.0
		 *
		 * @var array
		 */
		public static $allowed_html = array(
			'a'          => array(
				'style' => array(),
				'href'  => array(),
				'title' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'i'          => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'br'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'em'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'strong'     => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'h1'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'h2'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'h3'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'h4'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'h5'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'h6'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'p'          => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'div'        => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'section'    => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'ul'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'li'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'ol'         => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'blockquote' => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'figure'     => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'figcaption' => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'style'      => array(),
			'iframe'     => array(
				'height'          => array(),
				'src'             => array(),
				'width'           => array(),
				'allowfullscreen' => array(),
				'style'           => array(),
				'class'           => array(),
				'id'              => array(),
			),
			'img'        => array(
				'alt'   => array(),
				'src'   => array(),
				'title' => array(),
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'video'      => array(
				'width'    => array(),
				'height'   => array(),
				'controls' => array(),
				'style'    => array(),
				'class'    => array(),
				'id'       => array(),
			),
			'source'     => array(
				'src'   => array(),
				'type'  => array(),
				'class' => array(),
				'id'    => array(),
			),
		);

		/**
		 * Admin header.
		 *
		 * @version 2.2.0
		 */
		public function admin_header() {
			?>
			<h1 style='display:flex;align-items:center;' ><a target='_blank' href='<?php print esc_url( $this->pro_url ); ?>'>

			<img   style='width:170px;padding-right:30px' src='<?php echo esc_url( plugins_url( 'images/extendwp.png', WPFACTORY_WC_STS_FILE ) ); ?>' alt='<?php esc_html_e( 'Get more plugins by extendWP', 'support-ticket-system-for-woocommerce' ); ?>' title='<?php esc_html_e( 'Get more plugins by extendWP', 'support-ticket-system-for-woocommerce' ); ?>' />
				</a> <span style='color:#2271b1;'><?php print esc_html( $this->name ); ?></span></h1>

			<?php
		}

		/**
		 * Admin settings.
		 *
		 * @version 2.2.0
		 */
		public function admin_settings() {
			$this->admin_tabs(); // Add tabs for tickets screen.

			?>
			<p><b><?php esc_html_e( 'Use Ticket System in any page with the shortcode [stsw_user_tickets]', 'support-ticket-system-for-woocommerce' ); ?></b>
			<br/><i><?php esc_html_e( "Important Note: If you don't see the tickets dashboard in my account page, flush your permalinks from WP Backend", 'support-ticket-system-for-woocommerce' ); ?></i></p>
			<?php

			if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$this->active_action = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
			} else {
				$this->active_tab = 'general';
			}

			if ( 'settings' === $this->active_tab ) {
				?>
				<form method="post" id='<?php echo esc_attr( $this->plugin ); ?>Form' >

					<div class='result'><?php $this->admin_process_settings(); ?> </div>

					<div id="tabs">
						<ul>
							<li><a href="#general"><?php esc_html_e( 'General', 'support-ticket-system-for-woocommerce' ); ?></a></li>
							<li><a href="#notifications"><?php esc_html_e( 'Notifications', 'support-ticket-system-for-woocommerce' ); ?></a></li>
						</ul>
						<div id="general">

						<?php
						settings_fields( esc_html( $this->plugin ) . 'general-options' );
						do_settings_sections( esc_html( $this->plugin ) . 'general-options' );

						?>

						</div>
						<div id="notifications">
							<h4>
							<span class='proVersion' >
							<?php esc_html_e( 'Available placeholders for your Emails:', 'support-ticket-system-for-woocommerce' ); ?>
								<i> {ticketId},{responseId},{title},{content},{toEmail},{toFirstName},{toLastName} </i>
							<?php esc_html_e( 'in PRO Version', 'support-ticket-system-for-woocommerce' ); ?>
								</span>
							</h4>
							<?php
							settings_fields( esc_html( $this->plugin ) . 'notifications-options' );
							do_settings_sections( esc_html( $this->plugin ) . 'notifications-options' );
							?>
						</div>
						<?php
						wp_nonce_field( esc_html( $this->plugin ) );
						?>
					<p id='save_changes' ><?php esc_html( submit_button() ); ?></p>

				</form>
				<?php
			} else {
				// Display dashboard.
				$tickets = new WPFactory_WC_STS_Inc();
				$tickets->tickets_dashboard();
			}
		}

		/**
		 * Admin tabs.
		 *
		 * @version 2.2.0
		 */
		public function admin_tabs() {
			// The tabs in tickets screen.
			$this->tab = array(
				'general'    => 'Dashboard',
				'all'        => 'Tickets',
				'settings'   => 'Settings',
				'priorities' => 'Priorities',
				'subject'    => 'Subject',
				'more'       => 'Go PRO',
			);
			if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif (
				isset( $_GET['post_type'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'stsw_tickets' === sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			) {
				$this->active_tab = 'all';
			} else {
				$this->active_tab = 'general';
			}

			echo '<h2 class="nav-tab-wrapper" >';
			foreach ( $this->tab as $tab => $name ) {
				$class = ( $tab === $this->active_tab ) ? ' nav-tab-active' : '';
				if ( 'all' === $tab ) {
					echo "<a class='nav-tab" . esc_attr( $class ) . "' href='edit.php?post_type=stsw_tickets&tab=" . esc_attr( $tab ) . "'>" . esc_html( $name ) . '</a>';
				} elseif ( 'priorities' === $tab ) {
					echo "<a class='nav-tab" . esc_attr( $class ) . " proVersion disabled' href='#'>" . esc_html( $name ) . '</a>';
				} elseif ( 'subject' === $tab ) {
					echo "<a class='nav-tab" . esc_attr( $class ) . " proVersion disabled' href='#'>" . esc_html( $name ) . '</a>';
				} elseif ( 'more' === $tab ) {
					echo "<a class='nav-tab" . esc_attr( $class ) . " proVersion' href='#'>" . esc_html( $name ) . '</a>';
				} else {
					echo "<a class='nav-tab" . esc_attr( $class ) . " ' href='?page=support-ticket-system-woocommerce&tab=" . esc_attr( $tab ) . "'>" . wp_kses( $name, self::$allowed_html ) . '</a>';
				}
			}
			echo '</h2>';
		}

		/**
		 * Admin footer.
		 *
		 * @version 2.2.0
		 */
		public function admin_footer() {
			?>
			<hr>
			<a target='_blank' class='web_logo' href='https://extend-wp.com/wordpress-premium-plugins/'>
				<img  src='<?php echo esc_url( plugins_url( 'images/extendwp.png', WPFACTORY_WC_STS_FILE ) ); ?>' alt='<?php esc_html_e( 'Get more plugins by extendWP', 'support-ticket-system-for-woocommerce' ); ?>' title='<?php esc_html_e( 'Get more plugins by extendWP', 'support-ticket-system-for-woocommerce' ); ?>' />
			</a>
			<?php
		}

		/**
		 * Admin panels.
		 *
		 * @version 2.2.0
		 */
		public function admin_panels() {
			// Add settings for ticket system.
			add_settings_section(
				$this->plugin . 'general',
				'',
				null,
				$this->plugin . 'general-options'
			);
			add_settings_section(
				$this->plugin . 'notifications',
				'',
				null,
				$this->plugin . 'notifications-options'
			);

			add_settings_field(
				'renameAccountTabLink',
				esc_html__( 'Rename Tab Link in My Account Page for Tickets', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'rename_account_tab_link' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);
			register_setting(
				$this->plugin . 'general',
				$this->plugin . $this->rename_account_tab_link,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				)
			);

			add_settings_field(
				'enableAttachments',
				"<span class='proVersion'>" . esc_html__( 'Enable Attachments to Ticket', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'enable_attachments' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);

			add_settings_field(
				'allowedExtensions',
				"<span class='proVersion'>" . esc_html__( 'Allowed Attachments File Types', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'allowed_extensions' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);

			add_settings_field(
				'allowedSize',
				"<span class='proVersion'>" . esc_html__( 'Max File Size ', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'allowed_size' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);

			add_settings_field(
				'allowedAttachNum',
				"<span class='proVersion'>" . esc_html__( 'Allowed Number of Attachments ', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'allowed_attach_num' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);

			add_settings_field(
				'enablePriority',
				"<span class='proVersion'>" . esc_html__( 'Enable Priority Field to Ticket Creation', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'enable_priority' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);

			add_settings_field(
				'renameOrderButtonLink',
				esc_html__( 'Rename Button on Orders Table in My Account Page for Tickets', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'rename_order_button_link' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);
			register_setting(
				$this->plugin . 'general',
				$this->plugin . $this->rename_order_button_link,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				)
			);

			add_settings_field(
				'hideClosed',
				esc_html__( 'Show only Open in Frontend', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'hide_closed' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);
			register_setting(
				$this->plugin . 'general',
				$this->plugin . $this->hide_closed,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => function ( $value ) {
						return (bool) $value;
					},
					'default'           => false,
				)
			);

			add_settings_field(
				'assignToRole',
				"<span class='proVersion'>" . esc_html__( 'Default User Role for Ticket Assignment', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'assign_to_role' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);

			add_settings_field(
				'assignToUser',
				"<span class='proVersion'>" . esc_html__( 'Default Assignee', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'assign_to_user' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);

			add_settings_field(
				'textforTicketSave',
				esc_html__( 'Text to display once Ticket is saved', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'text_for_ticket_save' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);
			register_setting(
				$this->plugin . 'general',
				$this->plugin . $this->text_for_ticket_save,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
					'default'           => '',
				)
			);

			add_settings_field(
				'textforResponseSave',
				esc_html__( 'Text to display once Response is saved', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'text_for_response_save' ),
				$this->plugin . 'general-options',
				$this->plugin . 'general'
			);
			register_setting(
				$this->plugin . 'general',
				$this->plugin . $this->text_for_response_save,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
					'default'           => '',
				)
			);

			add_settings_field(
				'mailToAssignee',
				"<span class='proVersion'>" . esc_html__( 'Notify Assignee by Email', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'mail_to_assignee' ),
				$this->plugin . 'notifications-options',
				$this->plugin . 'notifications'
			);

			add_settings_field(
				'mailToADmin',
				esc_html__( 'Notify Admin by Email', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'mail_to_admin' ),
				$this->plugin . 'notifications-options',
				$this->plugin . 'notifications'
			);
			register_setting(
				$this->plugin . 'notifications',
				$this->plugin . $this->mail_to_admin,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => function ( $value ) {
						return (bool) $value;
					},
					'default'           => false,
				)
			);

			add_settings_field(
				'AdminEmailAddress',
				esc_html__( 'Admin Address', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'admin_email_address' ),
				$this->plugin . 'notifications-options',
				$this->plugin . 'notifications'
			);
			register_setting(
				$this->plugin . 'notifications',
				$this->plugin . $this->admin_email_address,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_email',
					'default'           => '',
				)
			);

			add_settings_field(
				'mailToCustomer',
				esc_html__( 'Notify Customer by Email', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'mail_to_customer' ),
				$this->plugin . 'notifications-options',
				$this->plugin . 'notifications'
			);
			register_setting(
				$this->plugin . 'notifications',
				$this->plugin . $this->mail_to_customer,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => function ( $value ) {
						return (bool) $value;
					},
					'default'           => false,
				)
			);

			add_settings_field(
				'mailIt_subjectToAs',
				"<span class='proVersion'>" . esc_html__( 'Email Subject sent to Assignee', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'mail_subject_to_assignee' ),
				$this->plugin . 'notifications-options',
				$this->plugin . 'notifications'
			);

			add_settings_field(
				'mailIt_contentToAs',
				"<span class='proVersion'>" . esc_html__( 'Email Content sent to Assignee', 'support-ticket-system-for-woocommerce' ) . '</span>',
				array( $this, 'mail_content_to_assignee' ),
				$this->plugin . 'notifications-options',
				$this->plugin . 'notifications'
			);

			add_settings_field(
				'mailIt_subjectToCust',
				esc_html__( 'Email Subject sent to Customer', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'mail_subject_to_customer' ),
				$this->plugin . 'notifications-options',
				$this->plugin . 'notifications'
			);
			register_setting(
				$this->plugin . 'notifications',
				$this->plugin . $this->mail_subject_to_customer,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				)
			);

			add_settings_field(
				'mailIt_contentToCust',
				esc_html__( 'Email Content sent to Customer', 'support-ticket-system-for-woocommerce' ),
				array( $this, 'mail_content_to_customer' ),
				$this->plugin . 'notifications-options',
				$this->plugin . 'notifications'
			);
			register_setting(
				$this->plugin . 'notifications',
				$this->plugin . $this->mail_content_to_customer,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
					'default'           => '',
				)
			);
		}

		/**
		 * Assign to role.
		 *
		 * @version 2.2.0
		 */
		public function assign_to_role() {
			?>
			<select disabled>
				<?php wp_dropdown_roles(); ?>
			</select>
			<?php
		}

		/**
		 * Assign to user.
		 *
		 * @version 2.2.0
		 */
		public function assign_to_user() {
			$users_query = new WP_User_Query(
				array(
					'fields'  => 'all_with_meta',
					'orderby' => 'display_name',
				)
			);
			$results     = $users_query->get_results();

			?>
			<select disabled>
				<?php
				foreach ( $results as $res ) {
					$user_info = get_userdata( (int) $res->ID );
					echo "<option value=''>" . esc_attr( $user_info->first_name ) . ' ' . esc_attr( $user_info->last_name ) . '</option>';
				}
				?>
			</select>
			<?php
		}

		/**
		 * Rename account tab link.
		 *
		 * @version 2.2.0
		 */
		public function rename_account_tab_link() {
			if ( isset( $_REQUEST[ $this->plugin . 'renameAccountTabLink' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->rename_account_tab_link = sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'renameAccountTabLink' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else {
				$this->rename_account_tab_link = get_option( $this->plugin . 'renameAccountTabLink' );
			}
			?>
			<input type="text" name="<?php esc_attr( print esc_html( $this->plugin ) . 'renameAccountTabLink' ); ?>" id="<?php print esc_attr( $this->plugin . 'renameAccountTabLink' ); ?>" value='
			<?php
			if ( $this->rename_account_tab_link ) {
				print esc_attr( $this->rename_account_tab_link );}
			?>
			' />
			<?php
		}

		/**
		 * Rename order button link.
		 *
		 * @version 2.2.0
		 */
		public function rename_order_button_link() {
			if ( isset( $_REQUEST[ $this->plugin . 'renameOrderButtonLink' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->rename_order_button_link = sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'renameOrderButtonLink' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else {
				$this->rename_order_button_link = get_option( $this->plugin . 'renameOrderButtonLink' );
			}
			?>
			<input type="text" name="<?php esc_attr( print esc_html( $this->plugin ) . 'renameOrderButtonLink' ); ?>" id="<?php print esc_attr( $this->plugin . 'renameOrderButtonLink' ); ?>" value='
			<?php
			if ( $this->rename_order_button_link ) {
				print esc_attr( $this->rename_order_button_link );}
			?>
			' />
			<?php
		}

		/**
		 * Hide closed.
		 *
		 * @version 2.2.0
		 */
		public function hide_closed() {
			if ( isset( $_REQUEST[ $this->plugin . 'hideClosed' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->hide_closed = sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'hideClosed' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else {
				$this->hide_closed = get_option( $this->plugin . 'hideClosed' );
			}
			?>
			<input type="checkbox" name="<?php print esc_attr( $this->plugin . 'hideClosed' ); ?>" id="<?php print esc_attr( $this->plugin . 'hideClosed' ); ?>" value='1'
			<?php
			if ( '1' === $this->hide_closed ) {
				print 'checked';
			}
			?>
			/>
			<?php
		}

		/**
		 * Enable attachments.
		 *
		 * @version 2.2.0
		 */
		public function enable_attachments() {

			?>
			<input type="checkbox" disabled />
			<?php
		}

		/**
		 * Enable priority.
		 *
		 * @version 2.2.0
		 */
		public function enable_priority() {
			?>
			<input type="checkbox" disabled />
			<?php
		}

		/**
		 * Mail to assignee.
		 *
		 * @version 2.2.0
		 */
		public function mail_to_assignee() {
			?>
			<input <input type="checkbox" class='proVersion' disabled />
			<?php
		}

		/**
		 * Mail to admin.
		 *
		 * @version 2.2.0
		 */
		public function mail_to_admin() {
			if ( isset( $_REQUEST[ $this->plugin . 'mailToADmin' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->mail_to_admin = sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'mailToADmin' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else {
				$this->mail_to_admin = get_option( $this->plugin . 'mailToADmin' );
			}
			?>
			<input type="checkbox" name="<?php echo esc_attr( $this->plugin . 'mailToADmin' ); ?>" id="<?php echo esc_attr( $this->plugin . 'mailToADmin' ); ?>" value='1'
			<?php
			if ( '1' === $this->mail_to_admin ) {
				print 'checked';}
			?>
			/>
			<?php
		}

		/**
		 * Admin email address.
		 *
		 * @version 2.2.0
		 */
		public function admin_email_address() {
			if ( isset( $_REQUEST[ $this->plugin . 'AdminEmailAddress' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->admin_email_address = sanitize_email( wp_unslash( $_REQUEST[ $this->plugin . 'AdminEmailAddress' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( get_option( $this->plugin . 'AdminEmailAddress' ) ) {
				$this->admin_email_address = get_option( $this->plugin . 'AdminEmailAddress' );
			} else {
				$this->admin_email_address = sanitize_email( get_bloginfo( 'admin_email' ) );
			}
			?>
			<input type="text"  name="<?php print esc_attr( $this->plugin . 'AdminEmailAddress' ); ?>" id="<?php print esc_attr( $this->plugin . 'AdminEmailAddress' ); ?>" placeholder='<?php print esc_html__( 'Admin Email Address', 'support-ticket-system-for-woocommerce' ); ?>' value="<?php echo esc_attr( $this->admin_email_address ); ?>"  />
			<?php
		}

		/**
		 * Mail to customer.
		 *
		 * @version 2.2.0
		 */
		public function mail_to_customer() {
			if ( isset( $_REQUEST[ $this->plugin . 'mailToCustomer' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->mail_to_customer = sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'mailToCustomer' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else {
				$this->mail_to_customer = get_option( $this->plugin . 'mailToCustomer' );
			}
			?>
			<input type="checkbox" name="<?php print esc_attr( $this->plugin . 'mailToCustomer' ); ?>" id="<?php print esc_attr( $this->plugin . 'mailToCustomer' ); ?>" value='1'
			<?php
			if ( '1' === $this->mail_to_customer ) {
				print 'checked';}
			?>
			/>
			<?php
		}

		/**
		 * Email subject sent to assignee.
		 *
		 * @version 2.2.0
		 */
		public function mail_subject_to_assignee() {
			?>
			<input class='proVersion' disabled />
			<?php
		}

		/**
		 * Email content sent to assignee.
		 *
		 * @version 2.2.0
		 */
		public function mail_content_to_assignee() {
			?>
			<textarea class='proVersion' disabled placeholder='<?php print esc_html__( 'Pro Version Only - html enabled', 'support-ticket-system-for-woocommerce' ); ?>' ></textarea>
			<?php
		}

		/**
		 * Text for ticket save.
		 *
		 * @version 2.2.0
		 */
		public function text_for_ticket_save() {
			if ( isset( $_REQUEST[ $this->plugin . 'textforTicketSave' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->text_for_ticket_save = wp_kses( wp_unslash( $_REQUEST[ $this->plugin . 'textforTicketSave' ] ), self::$allowed_html ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( ! empty( get_option( $this->plugin . 'textforTicketSave' ) ) ) {
				$this->text_for_ticket_save = get_option( $this->plugin . 'textforTicketSave' );
			}

			wp_editor(
				apply_filters( 'wpfactory_wc_sts_ticket_save_text', $this->text_for_ticket_save ),
				$this->plugin . 'textforTicketSave',
				array(
					'wpautop'       => true,
					'textarea_name' => $this->plugin . 'textforTicketSave',
					'textarea_rows' => '5',
					'editor_height' => 125,
				)
			);
		}

		/**
		 * Text for response save.
		 *
		 * @version 2.2.0
		 */
		public function text_for_response_save() {
			if ( isset( $_REQUEST[ $this->plugin . 'textforResponseSave' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->text_for_response_save = wp_kses( wp_unslash( $_REQUEST[ $this->plugin . 'textforResponseSave' ] ), self::$allowed_html ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( ! empty( get_option( $this->plugin . 'textforResponseSave' ) ) ) {
				$this->text_for_response_save = get_option( $this->plugin . 'textforResponseSave' );
			}

			wp_editor(
				apply_filters( 'wpfactory_wc_sts_response_save_text', $this->text_for_response_save ),
				$this->plugin . 'textforResponseSave',
				array(
					'wpautop'       => true,
					'textarea_name' => $this->plugin . 'textforResponseSave',
					'textarea_rows' => '5',
					'editor_height' => 125,
				)
			);
		}

		/**
		 * Mail subject to customer.
		 *
		 * @version 2.2.0
		 */
		public function mail_subject_to_customer() {
			if ( isset( $_REQUEST[ $this->plugin . 'mailIt_subjectToCust' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->mail_subject_to_customer = sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'mailIt_subjectToCust' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else {
				$this->mail_subject_to_customer = get_option( $this->plugin . 'mailIt_subjectToCust' );
			}
			?>
			<input type="text"  name="<?php echo esc_attr( $this->plugin . 'mailIt_subjectToCust' ); ?>" id="<?php echo esc_attr( $this->plugin . 'mailIt_subjectToCust' ); ?>" placeholder='<?php echo esc_attr__( 'Mail Subject', 'support-ticket-system-for-woocommerce' ); ?>' value="<?php echo esc_attr( $this->mail_subject_to_customer ); ?>"  />
			<?php
		}

		/**
		 * Allowed extensions.
		 *
		 * @version 2.2.0
		 */
		public function allowed_extensions() {
			?>
			<input disabled class='proVersion' placeholder='<?php print esc_html__( 'Extensions allowed - Pro Version', 'support-ticket-system-for-woocommerce' ); ?>'  />
			<?php
		}

		/**
		 * Allowed size.
		 *
		 * @version 2.2.0
		 */
		public function allowed_size() {
			?>
			<input type="text" disabled class='proVersion' placeholder='<?php print esc_html__( 'Files size - Pro Version', 'support-ticket-system-for-woocommerce' ); ?>'   />
			<?php
		}

		/**
		 * Allowed number of attachments.
		 *
		 * @version 2.2.0
		 */
		public function allowed_attach_num() {
			?>
			<input type="number" disabled class='proVersion'  placeholder='<?php print esc_html__( 'Allowed N.Files  - Pro Version ', 'support-ticket-system-for-woocommerce' ); ?>'  />
			<?php
		}

		/**
		 * Mail content to customer.
		 *
		 * @version 2.2.0
		 */
		public function mail_content_to_customer() {
			if ( isset( $_REQUEST[ $this->plugin . 'mailIt_contentToCust' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->mail_content_to_customer = wp_kses( wp_unslash( $_REQUEST[ $this->plugin . 'mailIt_contentToCust' ] ), self::$allowed_html ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else {
				$this->mail_content_to_customer = get_option( $this->plugin . 'mailIt_contentToCust' );
			}

			wp_editor(
				apply_filters( 'wpfactory_wc_sts_customer_email_content', $this->mail_content_to_customer ),
				$this->plugin . 'mailIt_contentToCust',
				array(
					'wpautop'       => true,
					'textarea_name' => $this->plugin . 'mailIt_contentToCust',
					'textarea_rows' => '5',
					'editor_height' => 225,
				)
			);
		}

		/**
		 * Admin process settings.
		 *
		 * @version 2.2.0
		 */
		public function admin_process_settings() {
			if (
				isset( $_SERVER['REQUEST_METHOD'] ) &&
				'POST' === $_SERVER['REQUEST_METHOD'] &&
				current_user_can( 'manage_options' )
			) {
				check_admin_referer( $this->plugin );
				check_ajax_referer( $this->plugin );

				if (
					isset( $_REQUEST[ $this->plugin . 'mailToADmin' ] ) &&
					sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'mailToADmin' ] ) ) === '1'
				) {
					update_option(
						$this->plugin . 'mailToADmin',
						sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'mailToADmin' ] ) )
					);
				} else {
					update_option(
						$this->plugin . 'mailToADmin',
						''
					);
				}

				if ( isset( $_REQUEST[ $this->plugin . 'AdminEmailAddress' ] ) ) {
					update_option(
						$this->plugin . 'AdminEmailAddress',
						sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'AdminEmailAddress' ] ) )
					);
				}

				if (
					isset( $_REQUEST[ $this->plugin . 'mailToCustomer' ] ) &&
					sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'mailToCustomer' ] ) ) === '1'
				) {
					update_option(
						$this->plugin . 'mailToCustomer',
						sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'mailToCustomer' ] ) )
					);
				} else {
					update_option(
						$this->plugin . 'mailToCustomer',
						''
					);
				}

				if (
					isset( $_REQUEST[ $this->plugin . 'hideClosed' ] ) &&
					sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'hideClosed' ] ) ) === '1'
				) {
					update_option(
						$this->plugin . 'hideClosed',
						sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'hideClosed' ] ) )
					);
				} else {
					update_option(
						$this->plugin . 'hideClosed',
						''
					);
				}

				if ( isset( $_REQUEST[ $this->plugin . 'mailIt_contentToCust' ] ) ) {
					$mail_content_to_customer = wp_kses( wp_unslash( $_REQUEST[ $this->plugin . 'mailIt_contentToCust' ] ), self::$allowed_html );
					update_option(
						$this->plugin . 'mailIt_contentToCust',
						$mail_content_to_customer
					);
				}

				if ( isset( $_REQUEST[ $this->plugin . 'textforTicketSave' ] ) ) {
					$text_for_ticket_save = wp_kses( wp_unslash( $_REQUEST[ $this->plugin . 'textforTicketSave' ] ), self::$allowed_html );
					update_option(
						$this->plugin . 'textforTicketSave',
						$text_for_ticket_save
					);
				}

				if ( isset( $_REQUEST[ $this->plugin . 'textforResponseSave' ] ) ) {
					$text_for_response_save = wp_kses( wp_unslash( $_REQUEST[ $this->plugin . 'textforResponseSave' ] ), self::$allowed_html );
					update_option(
						$this->plugin . 'textforResponseSave',
						$text_for_response_save
					);
				}

				if ( isset( $_REQUEST[ $this->plugin . 'mailIt_subjectToCust' ] ) ) {
					update_option(
						$this->plugin . 'mailIt_subjectToCust',
						sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'mailIt_subjectToCust' ] ) )
					);
				}

				if ( isset( $_REQUEST[ $this->plugin . 'renameOrderButtonLink' ] ) ) {
					update_option(
						$this->plugin . 'renameOrderButtonLink',
						sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'renameOrderButtonLink' ] ) )
					);
				}
				if ( isset( $_REQUEST[ $this->plugin . 'renameAccountTabLink' ] ) ) {
					update_option(
						$this->plugin . 'renameAccountTabLink',
						sanitize_text_field( wp_unslash( $_REQUEST[ $this->plugin . 'renameAccountTabLink' ] ) )
					);
				}
			}
		}
	}

endif;
