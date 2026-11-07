<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: settings screen, admin tabs and assets.
 *
 * Shared by the free and Pro editions. Settings keep their stored names (prefix + setting, e.g. `STSWooCommercehideClosed`).
 *
 * @version 2.3.0
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
	 * @version 2.3.0
	 */
	class WPFactory_WC_STS_Init {

		/**
		 * Settings prefix (free `STSWooCommerce`, Pro `STSWooCommercePro`).
		 *
		 * @var string
		 */
		public $plugin = 'STSWooCommerce';

		/**
		 * Active tab.
		 *
		 * @var string
		 */
		public $active_tab = '';

		/**
		 * HTML allowed in the merchant's messages and email templates.
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
			),
			'br'         => array(),
			'em'         => array( 'style' => array() ),
			'strong'     => array( 'style' => array() ),
			'h1'         => array( 'style' => array() ),
			'h2'         => array( 'style' => array() ),
			'h3'         => array( 'style' => array() ),
			'h4'         => array( 'style' => array() ),
			'h5'         => array( 'style' => array() ),
			'h6'         => array( 'style' => array() ),
			'p'          => array(
				'style' => array(),
				'class' => array(),
			),
			'div'        => array(
				'style' => array(),
				'class' => array(),
			),
			'section'    => array(
				'style' => array(),
				'class' => array(),
			),
			'ul'         => array( 'style' => array() ),
			'ol'         => array( 'style' => array() ),
			'li'         => array( 'style' => array() ),
			'blockquote' => array( 'style' => array() ),
			'figure'     => array( 'style' => array() ),
			'figcaption' => array( 'style' => array() ),
			'img'        => array(
				'alt'   => array(),
				'src'   => array(),
				'title' => array(),
				'style' => array(),
				'class' => array(),
				'width' => array(),
			),
		);

		/**
		 * Constructor.
		 *
		 * @version 2.3.0
		 */
		public function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'backend_scripts' ) );
			add_action( 'wpfactory_wc_sts_output_settings', array( $this, 'init' ) );
			add_action( 'all_admin_notices', array( $this, 'add_tabs_to_tickets' ) );
		}

		/**
		 * Plugin and Pro (or free) tabs above the plugin's list screens.
		 */
		public function add_tabs_to_tickets() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && in_array( $screen->base, array( 'edit', 'edit-tags', 'term' ), true ) && ( in_array( $screen->post_type, array( 'stsw_tickets', 'stsw_canned_reply' ), true ) ) ) {
				echo '<div class="' . esc_attr( $this->plugin ) . '">';
				$this->admin_header();
				$this->admin_tabs();
				echo '</div>';
			}
		}

		/**
		 * Whether the current admin screen belongs to the plugin.
		 *
		 * @return bool
		 */
		public static function is_plugin_screen() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && in_array( $screen->post_type, array( 'stsw_tickets', 'stsw_canned_reply' ), true ) ) {
				return true;
			}
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return WPFACTORY_WC_STS_PAGE === $page;
		}

		/**
		 * Admin styles and scripts, only on the plugin's own screens.
		 *
		 * @version 2.3.0
		 */
		public function backend_scripts() {
			if ( ! self::is_plugin_screen() ) {
				return;
			}
			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_style( 'wpfactory-wc-sts-backend', plugins_url( 'assets/css/backend' . $min . '.css', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION );
			wp_enqueue_style( 'wpfactory-wc-sts-fa', plugins_url( 'assets/css/font-awesome.min.css', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION );
			wp_enqueue_style( 'jquery-ui-style', plugins_url( 'assets/css/jquery-ui.css', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION );
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script(
				'wpfactory-wc-sts-backend',
				plugins_url( 'assets/js/backend' . $min . '.js', WPFACTORY_WC_STS_FILE ),
				array( 'jquery', 'jquery-ui-tabs', 'wp-color-picker' ),
				WPFACTORY_WC_STS_VERSION,
				true
			);
			wp_localize_script(
				'wpfactory-wc-sts-backend',
				'WPFactory_WC_STS_Backend',
				array(
					'responseDeleteNonce' => wp_create_nonce( 'wpfactory_wc_sts_response_delete' ),
					'confirmDelete'       => __( 'Delete this message? This cannot be undone.', 'support-ticket-system-for-woocommerce' ),
					'confirmDeleteNote'   => __( 'Delete this note? This cannot be undone.', 'support-ticket-system-for-woocommerce' ),
					'confirmReplace'      => __( 'Replace the text you already typed?', 'support-ticket-system-for-woocommerce' ),
				)
			);
		}

		/**
		 * Registers the customer screen assets; enqueued on My Account pages and pages with the shortcode.
		 *
		 * @version 2.3.0
		 */
		public function frontend_scripts() {
			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_style( 'wpfactory-wc-sts-frontend', plugins_url( 'assets/css/frontend' . $min . '.css', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION );
			wp_register_script( 'wpfactory-wc-sts-frontend', plugins_url( 'assets/js/frontend' . $min . '.js', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION, true );
			wp_localize_script( 'wpfactory-wc-sts-frontend', 'WPFactory_WC_STS_Frontend', array( 'sending' => __( 'Sending...', 'support-ticket-system-for-woocommerce' ) ) );

			if ( function_exists( 'is_account_page' ) && is_account_page() ) {
				self::enqueue_frontend_assets();
				return;
			}
			$post = get_post();
			if ( $post && has_shortcode( (string) $post->post_content, 'stsw_user_tickets' ) ) {
				self::enqueue_frontend_assets();
			}
		}

		/**
		 * Enqueues the customer screen assets (registered in `frontend_scripts()`).
		 */
		public static function enqueue_frontend_assets() {
			wp_enqueue_style( 'wpfactory-wc-sts-frontend' );
			wp_enqueue_script( 'wpfactory-wc-sts-frontend' );
		}

		/**
		 * Dashboard and settings page.
		 */
		public function init() {
			echo '<div class="wrap ' . esc_attr( $this->plugin ) . '">';
			$this->admin_header();
			$this->admin_settings();
			echo '</div>';
		}

		/**
		 * Page header.
		 */
		public function admin_header() {
			echo '<h1 class="wpfactory-wc-sts-title">' . esc_html( WPFACTORY_WC_STS_NAME ) . '</h1>';
		}

		/**
		 * Tabs: Dashboard, Tickets, Priorities, Subjects, Canned replies, Settings.
		 */
		public function admin_tabs() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $screen && 'stsw_canned_reply' === $screen->post_type ) {
				$active = 'canned';
			} elseif ( $screen && in_array( $screen->taxonomy, array( 'stsw_tickets_priority', 'stsw_tickets_subject', 'stsw_tickets_status' ), true ) ) {
				$active = $screen->taxonomy;
			} else {
				$active = 'settings' === $tab ? 'settings' : 'general';
			}
			$this->active_tab = $active;
			$tabs             = array(
				'general'               => array( __( 'Inbox', 'support-ticket-system-for-woocommerce' ), admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE ) ),
				'stsw_tickets_priority' => array( __( 'Priorities', 'support-ticket-system-for-woocommerce' ), admin_url( 'edit-tags.php?taxonomy=stsw_tickets_priority&post_type=stsw_tickets' ) ),
				'stsw_tickets_subject'  => array( __( 'Subjects', 'support-ticket-system-for-woocommerce' ), admin_url( 'edit-tags.php?taxonomy=stsw_tickets_subject&post_type=stsw_tickets' ) ),
				'canned'                => array( __( 'Canned replies', 'support-ticket-system-for-woocommerce' ), admin_url( 'edit.php?post_type=stsw_canned_reply' ) ),
				'settings'              => array( __( 'Settings', 'support-ticket-system-for-woocommerce' ), admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&tab=settings' ) ),
			);
			echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Support tickets sections', 'support-ticket-system-for-woocommerce' ) . '">';
			foreach ( $tabs as $key => $item ) {
				$current = $key === $active;
				echo '<a class="nav-tab' . ( $current ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $item[1] ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $item[0] ) . '</a>';
			}
			echo '</nav>';
		}

		/**
		 * Settings form or dashboard.
		 *
		 * @version 2.3.0
		 */
		public function admin_settings() {
			$this->admin_tabs();
			if ( 'settings' !== $this->active_tab ) {
				WPFactory_WC_STS_Inbox::get_instance()->render();
				return;
			}
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				echo '<div class="notice notice-error inline"><p>' . esc_html__( 'You are not allowed to change these settings.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
				return;
			}
			$sections = array(
				'general'       => __( 'General', 'support-ticket-system-for-woocommerce' ),
				'form'          => __( 'Ticket form', 'support-ticket-system-for-woocommerce' ),
				'assignment'    => __( 'Assignment', 'support-ticket-system-for-woocommerce' ),
				'notifications' => __( 'Notifications', 'support-ticket-system-for-woocommerce' ),
			);
			?>
			<form method="post" id="<?php echo esc_attr( $this->plugin ); ?>Form">
				<div class="result" role="status"><?php $this->admin_process_settings(); ?></div>
				<div id="tabs">
					<ul>
						<?php foreach ( $sections as $id => $label ) : ?>
							<li><a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
					<div id="general"><?php $this->render_fields( $this->get_general_fields() ); ?></div>
					<div id="form">
						<?php $this->render_fields( $this->get_form_fields() ); ?>
						<h2><?php esc_html_e( 'Extra fields', 'support-ticket-system-for-woocommerce' ); ?></h2>
						<?php WPFactory_WC_STS_Fields::get_instance()->render_settings(); ?>
					</div>
					<div id="assignment"><?php $this->render_fields( $this->get_assignment_fields() ); ?></div>
					<div id="notifications">
						<p>
							<?php esc_html_e( 'Placeholders for subjects and texts:', 'support-ticket-system-for-woocommerce' ); ?>
							<code>{ticketId} {responseId} {title} {content} {responseContent} {toEmail} {toFirstName} {toLastName} {ticketLink}</code>
						</p>
						<?php
						$this->render_fields( $this->get_notification_fields() );
						WPFactory_WC_STS_Emails::get_instance()->render_test_email_button();
						WPFactory_WC_STS_Emails::get_instance()->render_email_health();
						?>
					</div>
				</div>
				<?php wp_nonce_field( $this->plugin ); ?>
				<p id="save_changes"><?php submit_button(); ?></p>
			</form>
			<?php
		}

		/**
		 * General settings.
		 *
		 * @return array
		 */
		protected function get_general_fields() {
			return array(
				'renameAccountTabLink'  => array(
					'type'        => 'text',
					'label'       => __( 'My Account menu label', 'support-ticket-system-for-woocommerce' ),
					'placeholder' => __( 'Tickets', 'support-ticket-system-for-woocommerce' ),
				),
				'renameOrderButtonLink' => array(
					'type'        => 'text',
					'label'       => __( 'Order button label', 'support-ticket-system-for-woocommerce' ),
					'placeholder' => __( 'Get Help', 'support-ticket-system-for-woocommerce' ),
				),
				'hideClosed'            => array(
					'type'  => 'checkbox',
					'label' => __( 'Hide closed tickets from customers', 'support-ticket-system-for-woocommerce' ),
				),
				'textforTicketSave'     => array(
					'type'  => 'editor',
					'label' => __( 'Message after a customer opens a ticket', 'support-ticket-system-for-woocommerce' ),
				),
				'textforResponseSave'   => array(
					'type'  => 'editor',
					'label' => __( 'Message after a customer replies', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_delete_data_on_uninstall' => array(
					'type'        => 'checkbox',
					'label'       => __( 'Remove data on delete', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Delete all tickets, replies, canned replies and these settings when the plugin is deleted. Off by default. Data is kept while the free or Pro edition is still installed, because both use the same tickets.', 'support-ticket-system-for-woocommerce' ),
				),
			);
		}

		/**
		 * Ticket form settings.
		 *
		 * @return array
		 */
		protected function get_form_fields() {
			return array(
				'@wpfactory_wc_sts_guest_tickets' => array(
					'type'        => 'checkbox',
					'label'       => __( 'Guest tickets', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Customers without an account can ask for help with an order by entering the order number and billing email (on the My Account login screen and on pages with the shortcode). They follow their request through a private link sent by email.', 'support-ticket-system-for-woocommerce' ),
				),
				'enablePriority'                  => array(
					'type'  => 'checkbox',
					'label' => __( 'Let customers choose a priority', 'support-ticket-system-for-woocommerce' ),
				),
				'enableAttachments'               => array(
					'type'  => 'checkbox',
					'label' => __( 'Let customers attach files', 'support-ticket-system-for-woocommerce' ),
				),
				'allowedExtensions'               => array(
					'type'        => 'text',
					'label'       => __( 'Allowed file types', 'support-ticket-system-for-woocommerce' ),
					'placeholder' => WPFactory_WC_STS_Files::DEFAULT_EXTENSIONS,
					'description' => __( 'Comma separated, for example jpg,png,pdf. Empty: jpg, jpeg, png, gif and pdf.', 'support-ticket-system-for-woocommerce' ),
				),
				'allowedSize'                     => array(
					'type'        => 'number',
					'label'       => __( 'Maximum file size (MB)', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Never above the upload limit of your server.', 'support-ticket-system-for-woocommerce' ),
				),
				'allowedAttachNum'                => array(
					'type'        => 'number',
					'label'       => __( 'Files per message', 'support-ticket-system-for-woocommerce' ),
					'placeholder' => '5',
				),
			);
		}

		/**
		 * Assignment settings.
		 *
		 * @return array
		 */
		protected function get_assignment_fields() {
			return array(
				'assignToRole'   => array(
					'type'        => 'role',
					'label'       => __( 'Role that can be assigned tickets', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Users with this role can see and answer tickets in wp-admin and, for tickets assigned to them, in My Account. Shop managers and administrators always can.', 'support-ticket-system-for-woocommerce' ),
				),
				'assignToUser'   => array(
					'type'        => 'user',
					'label'       => __( 'Default assignee', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Handles tickets that are not assigned to anyone. Subjects can assign their own person (Support Tickets > Subjects).', 'support-ticket-system-for-woocommerce' ),
				),
				'mailToAssignee' => array(
					'type'  => 'checkbox',
					'label' => __( 'Email the assignee about new tickets and replies', 'support-ticket-system-for-woocommerce' ),
				),
			);
		}

		/**
		 * Notification settings.
		 *
		 * @return array
		 */
		protected function get_notification_fields() {
			return array(
				'mailToADmin'          => array(
					'type'  => 'checkbox',
					'label' => __( 'Email the store about new tickets and replies', 'support-ticket-system-for-woocommerce' ),
				),
				'AdminEmailAddress'    => array(
					'type'        => 'email',
					'label'       => __( 'Store email address', 'support-ticket-system-for-woocommerce' ),
					'placeholder' => get_bloginfo( 'admin_email' ),
				),
				'mailToCustomer'       => array(
					'type'  => 'checkbox',
					'label' => __( 'Email the customer (confirmations and store replies)', 'support-ticket-system-for-woocommerce' ),
				),
				'mailIt_subjectToCust' => array(
					'type'  => 'text',
					'label' => __( 'Customer email subject', 'support-ticket-system-for-woocommerce' ),
				),
				'mailIt_contentToCust' => array(
					'type'  => 'editor',
					'label' => __( 'Customer email text', 'support-ticket-system-for-woocommerce' ),
				),
				'mailIt_subjectToAs'   => array(
					'type'        => 'text',
					'label'       => __( 'Staff email subject', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Used for the store and assignee emails. Empty: a subject with the ticket number and title.', 'support-ticket-system-for-woocommerce' ),
				),
				'mailIt_contentToAs'   => array(
					'type'  => 'editor',
					'label' => __( 'Staff email text', 'support-ticket-system-for-woocommerce' ),
				),
			);
		}

		/**
		 * Option name of a field: the prefixed setting, or the literal name after `@` (options shared by both editions).
		 *
		 * @param string $key Field key.
		 *
		 * @return string
		 */
		protected function option_name( $key ) {
			return 0 === strpos( $key, '@' ) ? substr( $key, 1 ) : $this->plugin . $key;
		}

		/**
		 * Prints fields as a form table.
		 *
		 * @param array $fields Fields.
		 */
		protected function render_fields( $fields ) {
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $fields as $key => $field ) {
				$name  = $this->option_name( $key );
				$id    = sanitize_key( $name );
				$value = get_option( $name, '' );
				echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
				switch ( $field['type'] ) {
					case 'checkbox':
						echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( '1', (string) $value, false ) . ' />';
						break;
					case 'editor':
						wp_editor(
							(string) $value,
							$id,
							array(
								'wpautop'       => true,
								'textarea_name' => $name,
								'textarea_rows' => 5,
								'editor_height' => 140,
								'media_buttons' => false,
							)
						);
						break;
					case 'role':
						echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'None', 'support-ticket-system-for-woocommerce' ) . '</option>';
						wp_dropdown_roles( (string) $value );
						echo '</select>';
						break;
					case 'user':
						WPFactory_WC_STS_Admin::get_instance()->render_user_select( $name, (int) $value, __( 'Nobody', 'support-ticket-system-for-woocommerce' ), $id );
						break;
					default:
						echo '<input type="' . esc_attr( $field['type'] ) . '" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . ( isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '' ) . ( 'number' === $field['type'] ? ' min="0" step="1"' : '' ) . ' />';
				}
				if ( ! empty( $field['description'] ) ) {
					echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		/**
		 * Saves the settings.
		 *
		 * @version 2.3.0
		 */
		public function admin_process_settings() {
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				return;
			}
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				echo '<div class="notice notice-error inline"><p>' . esc_html__( 'You are not allowed to change these settings.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
				return;
			}
			check_admin_referer( $this->plugin );

			$old_role = (string) get_option( $this->plugin . 'assignToRole' );
			$fields   = array_merge( $this->get_general_fields(), $this->get_form_fields(), $this->get_assignment_fields(), $this->get_notification_fields() );
			foreach ( $fields as $key => $field ) {
				$name = $this->option_name( $key );
				$raw  = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per type below.
				switch ( $field['type'] ) {
					case 'checkbox':
						$value = '1' === (string) $raw ? '1' : '';
						break;
					case 'editor':
						$value = wp_kses( (string) $raw, self::$allowed_html );
						break;
					case 'email':
						$value = sanitize_email( (string) $raw );
						break;
					case 'number':
						$value = '' === trim( (string) $raw ) ? '' : (string) absint( $raw );
						break;
					case 'role':
						$value = sanitize_key( (string) $raw );
						$value = ( '' === $value || get_role( $value ) ) ? $value : '';
						break;
					case 'user':
						$value = absint( $raw ) ? (string) absint( $raw ) : '';
						break;
					default:
						$value = sanitize_text_field( (string) $raw );
				}
				$autoload = in_array( $field['type'], array( 'editor' ), true ) ? false : null;
				update_option( $name, $value, $autoload );
			}

			// Ticket access moves with the assignment role. Administrators always keep it; missing roles are skipped.
			$new_role = (string) get_option( $this->plugin . 'assignToRole' );
			if ( $old_role && $old_role !== $new_role && 'administrator' !== $old_role ) {
				$role = get_role( $old_role );
				if ( $role ) {
					$role->remove_cap( 'stsw_tickets' );
				}
			}
			foreach ( array( $new_role, 'administrator' ) as $role_name ) {
				$role = $role_name ? get_role( $role_name ) : null;
				if ( $role && ! $role->has_cap( 'stsw_tickets' ) ) {
					$role->add_cap( 'stsw_tickets' );
				}
			}

			// Extra form fields (each value is unslashed and sanitized in `sanitize_fields()`).
			if ( isset( $_POST['wpfactory_wc_sts_custom_fields'] ) && is_array( $_POST['wpfactory_wc_sts_custom_fields'] ) ) {
				update_option( WPFactory_WC_STS_Fields::OPTION, WPFactory_WC_STS_Fields::get_instance()->sanitize_fields( $_POST['wpfactory_wc_sts_custom_fields'] ), false ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_fields().
			}

			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Settings saved.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
		}
	}

endif;

return new WPFactory_WC_STS_Init();
