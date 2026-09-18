<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - WPFactory_WC_STS_Core Class
 *
 * @version 2.2.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Core' ) ) :

	/**
	 * WPFactory_WC_STS_Core class.
	 *
	 * @version 2.2.0
	 */
	class WPFactory_WC_STS_Core extends WPFactory_WC_STS_Init {

		/**
		 * Plugin.
		 *
		 * @var string
		 */
		public $plugin = 'STSWooCommerce';

		/**
		 * Name.
		 *
		 * @var string
		 */
		public $name = 'Helpdesk Support Ticket System for WooCommerce';

		/**
		 * Pro URL.
		 *
		 * @version 2.2.0
		 *
		 * @var string
		 */
		public $pro_url = 'https://extend-wp.com/product/helpdesk-support-ticket-system-woocommerce';

		/**
		 * Constructor.
		 *
		 * @version 2.2.0
		 */
		public function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'FrontEndScripts' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'BackEndScripts' ) );

			add_filter( 'widget_text', 'do_shortcode' );

			add_action( 'wpfactory_wc_sts_output_settings', array( $this, 'init' ) );

			add_action( 'admin_footer', array( $this, 'proModal' ) );

			add_action( 'admin_init', array( $this, 'adminPanels' ) );

			add_action( 'all_admin_notices', array( $this, 'add_tabs_to_tickets' ) );

			// Deactivation survey.
			include plugin_dir_path( WPFACTORY_WC_STS_FILE ) . '/lib/codecabin/plugin-deactivation-survey/deactivate-feedback-form.php';
			add_filter(
				'codecabin_deactivate_feedback_form_plugins',
				function ( $plugins ) {
					$plugins[] = (object) array(
						'slug'    => 'support-ticket-system-woocommerce',
						'version' => '1.5',
					);

					return $plugins;
				}
			);

			register_activation_hook( WPFACTORY_WC_STS_FILE, array( $this, 'notification_hook' ) );

			add_action( 'admin_notices', array( $this, 'notification' ) );
			add_action( 'wp_ajax_nopriv_push_not', array( $this, 'push_not' ) );
			add_action( 'wp_ajax_push_not', array( $this, 'push_not' ) );
		}

		/**
		 * Notification.
		 *
		 * @version 2.0.0
		 */
		public function notification() {
			$screen = get_current_screen();
			if ( 'toplevel_page_support-ticket-system-woocommerce' !== $screen->base ) {
				return;
			}

			/* Check transient, if available display notice */
			if ( get_transient( $this->plugin . '_notification' ) ) {
				?>
				<div class="updated notice  stsWooCommerce_notification">
					<a href="#" class='dismiss' style='float:right;padding:4px' >close</a>
					<h3><?php esc_html_e( 'Add your Email below & get ', 'support-ticket-system-for-woocommerce' ); ?><strong style='color:#00a32a'>10%</strong><?php esc_html_e( ' in our PRO plugins! ', 'support-ticket-system-for-woocommerce' ); ?></h3>
					<form method='post' id='stsWooCommerce_signup'>
						<p>
						<input required type='email' name='woopei_email' />
						<input required type='hidden' name='product' value='2829' />
						<input type='submit' class='button button-primary' name='submit' value='<?php esc_html_e( 'Sign up', 'support-ticket-system-for-woocommerce' ); ?>' />
						<i><?php esc_html_e( 'By adding your email you will be able to use your email as coupon to a future purchase at ', 'support-ticket-system-for-woocommerce' ); ?><a href='https://extend-wp.com' target='_blank' >extend-wp.com</a></i>
						</p>

					</form>
				</div>
				<?php
			}
		}

		/**
		 * Push not.
		 */
		public function push_not() {
			delete_transient( $this->plugin . '_notification' );
		}

		/**
		 * Notification hook.
		 */
		public function notification_hook() {
			set_transient( $this->plugin . '_notification', true );
		}

		/**
		 * Add tabs to tickets.
		 *
		 * @version 2.2.0
		 */
		public function add_tabs_to_tickets() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if (
				(
					isset( $_REQUEST['post_type'] ) &&
					'stsw_tickets' === sanitize_text_field( wp_unslash( $_REQUEST['post_type'] ) )
				) ||
				(
					isset( $_REQUEST['taxonomy'] ) &&
					'stsw_tickets_status' === sanitize_text_field( wp_unslash( $_REQUEST['taxonomy'] ) )
				)
			) {
				$this->adminHeader();
				$this->adminTabs();
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * Pro modal.
		 *
		 * @version 2.2.0
		 */
		public function proModal() {
			?>
			<div id="<?php print esc_attr( $this->plugin ) . 'Modal'; ?>">
				<!-- Modal content -->
				<div class="modal-content">
					<div class='<?php print esc_attr( $this->plugin ); ?>clearfix'><span class="close">&times;</span></div>
					<div class='<?php print esc_attr( $this->plugin ); ?>clearfix'>
						<div class='<?php print esc_attr( $this->plugin ); ?>columns2'>
							<center>
								<img style='width:90%' src='<?php echo esc_url( plugins_url( 'images/support-ticket-system-woocommerce-pro.png', WPFACTORY_WC_STS_FILE ) ); ?>' style='width:100%' />
							</center>
						</div>

						<div class='<?php print esc_attr( $this->plugin ); ?>columns2'>
							<h3><?php esc_html_e( 'Go PRO and get more important features!', 'support-ticket-system-for-woocommerce' ); ?></h3>
							<p><i class='fa fa-check'></i> <?php esc_html_e( 'Enable Attachments on Ticket Submission', 'support-ticket-system-for-woocommerce' ); ?></p>
							<p><i class='fa fa-check'></i> <?php esc_html_e( 'Choose File Size, Number and Type for Upload', 'support-ticket-system-for-woocommerce' ); ?></p>
							<p><i class='fa fa-check'></i> <?php esc_html_e( 'Enable Ticket Priorities for Better Management', 'support-ticket-system-for-woocommerce' ); ?></strong></p>
							<p><i class='fa fa-check'></i> <?php esc_html_e( 'Assign Ticket to Different Users', 'support-ticket-system-for-woocommerce' ); ?></p>
							<p><i class='fa fa-check'></i> <?php esc_html_e( 'Private Notes that customer cannot view ', 'support-ticket-system-for-woocommerce' ); ?></p>
							<p><i class='fa fa-check'></i> <?php esc_html_e( 'Add Ticket Subject and Automate Ticket Assignment', 'support-ticket-system-for-woocommerce' ); ?></p>
							<p><i class='fa fa-check'></i> <?php esc_html_e( 'Customize your Email Notifications ', 'support-ticket-system-for-woocommerce' ); ?></p>
							<p><i class='fa fa-check'></i> <?php esc_html_e( 'Useful Placeholders for your Notification Template', 'support-ticket-system-for-woocommerce' ); ?></p>
							<p><i class='fa fa-check'></i> <?php esc_html_e( '.. and a lot more!', 'support-ticket-system-for-woocommerce' ); ?></p>
							<p class='bottomToUp'><center><a target='_blank' class='proUrl' href='<?php print esc_url( $this->pro_url ); ?>'><?php esc_html_e( 'GET IT HERE', 'support-ticket-system-for-woocommerce' ); ?></a></center></p>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Backend scripts.
		 *
		 * @version 2.2.0
		 */
		public function BackEndScripts() {
			wp_enqueue_style(
				$this->plugin . 'adminCss',
				plugins_url( '/css/backend.css', WPFACTORY_WC_STS_FILE ),
				array(),
				WPFACTORY_WC_STS_VERSION
			);

			if ( ! wp_script_is( $this->plugin . '_fa', 'enqueued' ) ) {
				wp_enqueue_style(
					$this->plugin . '_fa',
					plugins_url( '/css/font-awesome.min.css', WPFACTORY_WC_STS_FILE ),
					array(),
					WPFACTORY_WC_STS_VERSION
				);
			}

			wp_enqueue_style(
				'jquery-ui-style',
				plugins_url( '/css/jquery-ui.css', WPFACTORY_WC_STS_FILE ),
				array(),
				WPFACTORY_WC_STS_VERSION
			);

			wp_enqueue_script( 'jquery-ui-accordion' );

			wp_enqueue_script(
				$this->plugin . 'adminJs',
				plugins_url( '/js/backend.js', WPFACTORY_WC_STS_FILE ),
				array( 'jquery', 'jquery-ui-tabs', 'jquery-ui-accordion' ),
				WPFACTORY_WC_STS_VERSION,
				true
			);

			wp_localize_script(
				$this->plugin . 'adminJs',
				$this->plugin,
				array(
					'plugin_url'     => plugins_url( '', WPFACTORY_WC_STS_FILE ),
					'ajaxurl'        => admin_url( 'admin-ajax.php' ),
					'siteUrl'        => site_url(),
					'plugin_wrapper' => $this->plugin,
				)
			);
		}

		/**
		 * Frontend scripts.
		 *
		 * @version 2.2.0
		 */
		public function FrontEndScripts() {
			wp_enqueue_style(
				$this->plugin . 'css',
				plugins_url( '/css/frontend.css', WPFACTORY_WC_STS_FILE ),
				array(),
				WPFACTORY_WC_STS_VERSION
			);

			if ( ! wp_script_is( $this->plugin . '_fa', 'enqueued' ) ) {
				wp_enqueue_style(
					$this->plugin . '_fa',
					plugins_url( '/css/font-awesome.min.css', WPFACTORY_WC_STS_FILE ),
					array(),
					WPFACTORY_WC_STS_VERSION
				);
			}

			wp_enqueue_style(
				'jquery-ui-style',
				plugins_url( '/css/jquery-ui.css', WPFACTORY_WC_STS_FILE ),
				array(),
				WPFACTORY_WC_STS_VERSION
			);

			wp_enqueue_script( 'jquery-ui-accordion' );

			wp_enqueue_script(
				$this->plugin . 'jsfront',
				plugins_url( '/js/frontend.js', WPFACTORY_WC_STS_FILE ),
				array( 'jquery' ),
				WPFACTORY_WC_STS_VERSION,
				true
			);

			wp_localize_script(
				$this->plugin . 'jsfront',
				$this->plugin,
				array(
					'plugin_url'     => plugins_url( '', WPFACTORY_WC_STS_FILE ),
					'ajax_url'       => admin_url( 'admin-ajax.php' ),
					'siteUrl'        => site_url(),
					'plugin_wrapper' => $this->plugin,
				)
			);
		}

		/**
		 * Init.
		 *
		 * @version 2.2.0
		 */
		public function init() {
			print "<div class='" . esc_attr( $this->plugin ) . "'>";
				esc_html( $this->adminHeader() );
				print esc_html__( 'Use the shortcode [stsw_user_tickets] in any page you like as alternative to provide the ticketing system.', 'support-ticket-system-for-woocommerce' );
				esc_html( $this->adminSettings() );
				esc_html( $this->adminFooter() );
			print '</div>';
		}
	}

endif;

return new WPFactory_WC_STS_Core();
