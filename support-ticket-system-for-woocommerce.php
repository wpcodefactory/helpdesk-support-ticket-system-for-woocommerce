<?php
/*
 * Plugin Name: Helpdesk Support Ticket System for WooCommerce
 * Description: WordPress ticket system - Manage customer queries and issues on your WordPress eShop with helpdesk WooCommerce support ticket system.
 * Plugin URI: https://extend-wp.com/support-ticket-system-for-woocommerce
 * Version: 2.0.3
 * Author: WPFactory
 * Author URI: https://wpfactory.com
 * Text Domain: support-ticket-system-for-woocommerce
 * Domain Path: /langs
 * WC requires at least: 2.2
 * WC tested up to: 10.2
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Created On: 09-10-2019
 * Updated On: 10-06-2025
 */

defined( 'ABSPATH' ) || exit;

defined( 'WPFACTORY_WC_STS_VERSION' ) || define( 'WPFACTORY_WC_STS_VERSION', '2.0.3' );

defined( 'WPFACTORY_WC_STS_FILE' ) || define( 'WPFACTORY_WC_STS_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpfactory-wc-sts.php';

if ( ! function_exists( 'wpfactory_wc_sts' ) ) {
	/**
	 * Returns the main instance of WPFactory_WC_STS to prevent the need to use globals.
	 *
	 * @version 2.0.0
	 * @since   2.0.0
	 */
	function wpfactory_wc_sts() {
		return WPFactory_WC_STS::instance();
	}
}

add_action( 'plugins_loaded', 'wpfactory_wc_sts' );

/**
 * includes.
 */
include_once( plugin_dir_path(__FILE__) ."/init.php");
include_once( plugin_dir_path(__FILE__) ."/includes.php");

/**
 * STSWooCommerce.
 *
 * @version 2.0.0
 */
class STSWooCommerce extends STSWooCommerceInit {

	public $plugin = 'STSWooCommerce';
	public $name   = 'Helpdesk Support Ticket System for WooCommerce';
	public $proUrl = 'https://extend-wp.com/product/helpdesk-support-ticket-system-woocommerce';

	public $localizeBackend;
	public $localizeFrontend;

	/**
	 * Constructor.
	 *
	 * @version 2.0.0
	 */
	public function __construct() {

		add_action('wp_enqueue_scripts', array($this, 'FrontEndScripts') );

		add_action('admin_enqueue_scripts', array($this, 'BackEndScripts') );
		add_filter('widget_text', 'do_shortcode');

		add_action( 'wpfactory_wc_sts_output_settings', array( $this, 'init' ) );

		add_action("admin_footer", array($this,"proModal" ) );

		add_action("admin_init", array($this, 'adminPanels') );

		add_action("all_admin_notices", array($this, 'addTabsToTIckets') );

		// deactivation survey

		include( plugin_dir_path(__FILE__) .'/lib/codecabin/plugin-deactivation-survey/deactivate-feedback-form.php');
		add_filter('codecabin_deactivate_feedback_form_plugins', function($plugins) {

			$plugins[] = (object)array(
				'slug'    => 'support-ticket-system-woocommerce',
				'version' => '1.5',
			);

			return $plugins;

		});

		register_activation_hook( __FILE__, array( $this, 'notification_hook' ) );

		add_action( 'admin_notices', array( $this,'notification' ) );
		add_action( 'wp_ajax_nopriv_push_not',array( $this, 'push_not'  ) );
		add_action( 'wp_ajax_push_not', array( $this, 'push_not' ) );

	}

	/**
	 * notification.
	 *
	 * @version 2.0.0
	 */
	public function notification(){

		$screen = get_current_screen();
		if ( 'toplevel_page_support-ticket-system-woocommerce' !== $screen->base ) {
			return;
		}

		/* Check transient, if available display notice */
		if( get_transient( $this->plugin."_notification" ) ){
			?>
			<div class="updated notice  stsWooCommerce_notification">
				<a href="#" class='dismiss' style='float:right;padding:4px' >close</a>
				<h3><?php esc_html_e( "Add your Email below & get ", 'support-ticket-system-for-woocommerce' ); ?><strong style='color:#00a32a'>10%</strong><?php esc_html_e( " in our PRO plugins! ", 'support-ticket-system-for-woocommerce' ); ?></h3>
				<form method='post' id='stsWooCommerce_signup'>
					<p>
					<input required type='email' name='woopei_email' />
					<input required type='hidden' name='product' value='2829' />
					<input type='submit' class='button button-primary' name='submit' value='<?php esc_html_e("Sign up", "support-ticket-system-for-woocommerce" ); ?>' />
					<i><?php esc_html_e( "By adding your email you will be able to use your email as coupon to a future purchase at ", 'support-ticket-system-for-woocommerce' ); ?><a href='https://extend-wp.com' target='_blank' >extend-wp.com</a></i>
					</p>

				</form>
			</div>
			<?php
		}
	}

	public function push_not(){
		delete_transient( $this->plugin."_notification" );
	}

	public function notification_hook() {
		set_transient( $this->plugin."_notification", true );
	}

	public function addTabsToTIckets(){

		if( isset($_REQUEST['post_type']) && $_REQUEST['post_type'] ==='stsw_tickets'){
				esc_html( $this->adminHeader() );
				esc_html( $this->adminTabs() );
		}
		if( isset($_REQUEST['taxonomy']) && $_REQUEST['taxonomy'] ==='stsw_tickets_status' ){
				esc_html( $this->adminHeader() );
				esc_html( $this->adminTabs() );
		}
	}

	/**
	 * proModal.
	 *
	 * @version 2.0.0
	 */
	public function proModal(){ ?>
		<div id="<?php print esc_html( $this->plugin ).'Modal'; ?>">
		  <!-- Modal content -->
		  <div class="modal-content">
			<div class='<?php print esc_html( $this->plugin ); ?>clearfix'><span class="close">&times;</span></div>
			<div class='<?php print esc_html( $this->plugin ); ?>clearfix'>
				<div class='<?php print esc_html( $this->plugin ); ?>columns2'>
					<center>
						<img style='width:90%' src='<?php echo esc_url( plugins_url( 'images/'.'support-ticket-system-woocommerce'.'-pro.png', __FILE__ ) ); ?>' style='width:100%' />
					</center>
				</div>

				<div class='<?php print esc_html( $this->plugin ); ?>columns2'>
					<h3><?php esc_html_e('Go PRO and get more important features!','support-ticket-system-for-woocommerce' ); ?></h3>
					<p><i class='fa fa-check'></i> <?php esc_html_e('Enable Attachments on Ticket Submission','support-ticket-system-for-woocommerce' ); ?></p>
					<p><i class='fa fa-check'></i> <?php esc_html_e('Choose File Size, Number and Type for Upload','support-ticket-system-for-woocommerce' ); ?></p>
					<p><i class='fa fa-check'></i> <?php esc_html_e('Enable Ticket Priorities for Better Management','support-ticket-system-for-woocommerce' ); ?></strong></p>
					<p><i class='fa fa-check'></i> <?php esc_html_e('Assign Ticket to Different Users','support-ticket-system-for-woocommerce' ); ?></p>
					<p><i class='fa fa-check'></i> <?php esc_html_e('Private Notes that customer cannot view ','support-ticket-system-for-woocommerce' ); ?></p>
					<p><i class='fa fa-check'></i> <?php esc_html_e('Add Ticket Subject and Automate Ticket Assignment','support-ticket-system-for-woocommerce' ); ?></p>
					<p><i class='fa fa-check'></i> <?php esc_html_e('Customize your Email Notifications ','support-ticket-system-for-woocommerce' ); ?></p>
					<p><i class='fa fa-check'></i> <?php esc_html_e('Useful Placeholders for your Notification Template','support-ticket-system-for-woocommerce' ); ?></p>
					<p><i class='fa fa-check'></i> <?php esc_html_e('.. and a lot more!','support-ticket-system-for-woocommerce' ); ?></p>
					<p class='bottomToUp'><center><a target='_blank' class='proUrl' href='<?php print esc_url( $this->proUrl ); ?>'><?php esc_html_e('GET IT HERE', 'support-ticket-system-for-woocommerce' ); ?></a></center></p>
				</div>
			</div>
		  </div>
		</div>
		<?php
	}

	public function BackEndScripts(){
		wp_enqueue_style( esc_html( $this->plugin )."adminCss", plugins_url( "/css/backend.css", __FILE__ ) );
		wp_enqueue_style( esc_html( $this->plugin )."adminCss");

		if( ! wp_script_is( esc_html( $this->plugin )."_fa", 'enqueued' ) ) {
			wp_enqueue_style( esc_html( $this->plugin )."_fa", plugins_url( '/css/font-awesome.min.css', __FILE__ ));
		}

		wp_enqueue_style( 'jquery-ui-style', plugins_url( "/css/jquery-ui.css", __FILE__ ), true);
		wp_enqueue_script('jquery-ui-accordion');

		wp_enqueue_script( esc_html( $this->plugin )."adminJs", plugins_url( "/js/backend.js" , __FILE__ ) , array('jquery','jquery-ui-tabs','jquery-ui-accordion',) , null, true);

		$this->localizeBackend = array(
				'plugin_url'    => esc_url( plugins_url( '', __FILE__ ) ),
				'ajaxurl'       => esc_url( admin_url( 'admin-ajax.php' ) ),
				'siteUrl'       => esc_url( site_url() ),
				'plugin_wrapper'=> esc_html( $this->plugin ),
		);

		wp_localize_script( esc_html( $this->plugin )."adminJs", esc_html( $this->plugin ) , $this->localizeBackend );
		wp_enqueue_script( esc_html( $this->plugin )."adminJs");
	}

	public function FrontEndScripts(){
		wp_enqueue_style( esc_html( $this->plugin )."css", esc_url( plugins_url( "/css/frontend.css", __FILE__ ) ) );
		wp_enqueue_style( esc_html( $this->plugin )."css");

		if( ! wp_script_is( esc_html( $this->plugin )."_fa", 'enqueued' ) ) {
			wp_enqueue_style( esc_html( $this->plugin )."_fa", esc_url( plugins_url( '/css/font-awesome.min.css', __FILE__ ) ) );
		}
		wp_enqueue_style( 'jquery-ui-style', plugins_url( "/css/jquery-ui.css", __FILE__ ), true);
		wp_enqueue_script('jquery-ui-accordion');
		wp_enqueue_script( esc_html( $this->plugin )."jsfront", esc_url( plugins_url( "/js/frontend.js", __FILE__ ) ) , array('jquery') , null, true);

		$this->localizeFrontend = array(
			'plugin_url'    => esc_url( plugins_url( '', __FILE__ ) ),
			'ajax_url'      => esc_url( admin_url( 'admin-ajax.php' ) ),
			'siteUrl'       => esc_url( site_url() ),
			'plugin_wrapper'=> esc_html( $this->plugin ),
		);
		wp_localize_script( esc_html( $this->plugin )."jsfront", esc_html( $this->plugin ) , $this->localizeFrontend );
		wp_enqueue_script( esc_html( $this->plugin )."jsfront");

	}

	public function init(){
		print "<div class='".esc_html( $this->plugin )."'>";
				esc_html( $this->adminHeader() );
				print esc_html__('Use the shortcode [stsw_user_tickets] in any page you like as alternative to provide the ticketing system.','support-ticket-system-for-woocommerce' );
				esc_html( $this->adminSettings() );
				esc_html( $this->adminFooter() );
		print "</div>";
	}

}

$instantiate = new STSWooCommerce();
