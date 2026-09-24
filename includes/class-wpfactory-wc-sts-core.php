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
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );

			add_action( 'wp_enqueue_scripts', array( $this, 'my_account_tickets_css' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'backend_scripts' ) );

			add_filter( 'widget_text', 'do_shortcode' );

			add_action( 'wpfactory_wc_sts_output_settings', array( $this, 'init' ) );

			add_action( 'admin_footer', array( $this, 'pro_modal' ) );

			add_action( 'admin_init', array( $this, 'admin_panels' ) );

			add_action( 'all_admin_notices', array( $this, 'add_tabs_to_tickets' ) );
		}

		/**
		 * Enqueue CSS for the My Account tickets page.
		 *
		 * @version 2.2.0
		 * @since   2.2.0
		 */
		public function my_account_tickets_css() {
			if (
				! is_account_page() ||
				null === get_query_var( 'tickets', null )
			) {
				return;
			}

			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style(
				'wpfactory-wc-sts-tickets',
				plugins_url( 'assets/css/my-account' . $min . '.css', WPFACTORY_WC_STS_FILE ),
				array(),
				WPFACTORY_WC_STS_VERSION
			);
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
				$this->admin_header();
				$this->admin_tabs();
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * Pro modal.
		 *
		 * @version 2.2.0
		 */
		public function pro_modal() {
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
		public function backend_scripts() {
			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style(
				'wpfactory-wc-sts-backend',
				plugins_url( 'assets/css/backend' . $min . '.css', WPFACTORY_WC_STS_FILE ),
				array(),
				WPFACTORY_WC_STS_VERSION
			);

			if ( ! wp_script_is( 'wpfactory-wc-sts-fa', 'enqueued' ) ) {
				wp_enqueue_style(
					'wpfactory-wc-sts-fa',
					plugins_url( 'assets/css/font-awesome.min.css', WPFACTORY_WC_STS_FILE ),
					array(),
					WPFACTORY_WC_STS_VERSION
				);
			}

			wp_enqueue_style(
				'jquery-ui-style',
				plugins_url( 'assets/css/jquery-ui.css', WPFACTORY_WC_STS_FILE ),
				array(),
				WPFACTORY_WC_STS_VERSION
			);

			wp_enqueue_script( 'jquery-ui-accordion' );

			wp_enqueue_script(
				'wpfactory-wc-sts-backend',
				plugins_url( 'assets/js/backend' . $min . '.js', WPFACTORY_WC_STS_FILE ),
				array( 'jquery', 'jquery-ui-tabs', 'jquery-ui-accordion' ),
				WPFACTORY_WC_STS_VERSION,
				true
			);

			wp_localize_script(
				'wpfactory-wc-sts-backend',
				'WPFactory_WC_STS_Backend',
				array(
					'responseDeleteNonce' => wp_create_nonce( 'wpfactory_wc_sts_response_delete' ),
				)
			);
		}

		/**
		 * Frontend scripts.
		 *
		 * @version 2.2.0
		 */
		public function frontend_scripts() {
			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style(
				'wpfactory-wc-sts-frontend',
				plugins_url( 'assets/css/frontend' . $min . '.css', WPFACTORY_WC_STS_FILE ),
				array(),
				WPFACTORY_WC_STS_VERSION
			);

			if ( ! wp_script_is( 'wpfactory-wc-sts-fa', 'enqueued' ) ) {
				wp_enqueue_style(
					'wpfactory-wc-sts-fa',
					plugins_url( 'assets/css/font-awesome.min.css', WPFACTORY_WC_STS_FILE ),
					array(),
					WPFACTORY_WC_STS_VERSION
				);
			}

			wp_enqueue_style(
				'jquery-ui-style',
				plugins_url( 'assets/css/jquery-ui.css', WPFACTORY_WC_STS_FILE ),
				array(),
				WPFACTORY_WC_STS_VERSION
			);

			wp_enqueue_script( 'jquery-ui-accordion' );

			wp_enqueue_script(
				'wpfactory-wc-sts-frontend',
				plugins_url( 'assets/js/frontend' . $min . '.js', WPFACTORY_WC_STS_FILE ),
				array( 'jquery' ),
				WPFACTORY_WC_STS_VERSION,
				true
			);
		}

		/**
		 * Init.
		 *
		 * @version 2.2.0
		 */
		public function init() {
			print "<div class='" . esc_attr( $this->plugin ) . "'>";
				$this->admin_header();
				printf(
					/* translators: %s: shortcode [stsw_user_tickets] */
					esc_html__( 'Use the %s shortcode in any page you like as alternative to provide the ticketing system.', 'support-ticket-system-for-woocommerce' ),
					'<code>[stsw_user_tickets]</code>'
				);
				$this->admin_settings();
				$this->admin_footer();
			print '</div>';
		}
	}

endif;

return new WPFactory_WC_STS_Core();
