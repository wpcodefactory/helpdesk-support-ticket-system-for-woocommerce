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
			add_filter( 'wpfactory_wc_sts_header_tabs', array( $this, 'automation_tab' ), 30 );
			add_action( 'all_admin_notices', array( $this, 'add_tabs_to_tickets' ) );
		}

		/**
		 * Plugin header above the old WordPress list screens that still exist (none by default; kept for add-ons).
		 */
		public function add_tabs_to_tickets() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && 'edit' === $screen->base && 'stsw_tickets' === $screen->post_type ) {
				echo '<div class="wpf-hd hd-app">';
				WPFactory_WC_STS_UI::header( '' );
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
			return WPFactory_WC_STS_UI::is_screen();
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
			if ( ! WPFactory_WC_STS_UI::is_screen() ) {
				// The plugin screens use admin-ui.css only; the old styles stay for the WordPress ticket screens.
				wp_enqueue_style( 'wpfactory-wc-sts-backend', plugins_url( 'assets/css/backend' . $min . '.css', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION );
			}
			wp_enqueue_script(
				'wpfactory-wc-sts-backend',
				plugins_url( 'assets/js/backend' . $min . '.js', WPFACTORY_WC_STS_FILE ),
				array( 'jquery' ),
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
			wp_localize_script(
				'wpfactory-wc-sts-frontend',
				'WPFactory_WC_STS_Frontend',
				array(
					'sending'      => __( 'Sending...', 'support-ticket-system-for-woocommerce' ),
					'ajaxurl'      => admin_url( 'admin-ajax.php' ),
					/* translators: %d: number of articles. */
					'suggestFound' => __( '%d help articles might answer your question. They are listed under the message.', 'support-ticket-system-for-woocommerce' ),
				)
			);

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
		 * Plugin screen: header, then the current tab.
		 */
		public function init() {
			$this->active_tab = $this->current_tab();
			echo '<div class="wrap wpf-hd hd-app ' . esc_attr( $this->plugin ) . '">';
			echo '<h1 class="screen-reader-text">' . esc_html( WPFACTORY_WC_STS_NAME ) . '</h1>';
			WPFactory_WC_STS_UI::header( 'inbox' === $this->active_tab ? '' : $this->active_tab );
			$this->admin_settings();
			echo '</div>';
		}

		/**
		 * Current tab key.
		 *
		 * @return string
		 */
		public function current_tab() {
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'canned' === $tab ) {
				$tab = 'saved';
			}
			if ( class_exists( 'WPFactory_WC_STS_Dashboard' ) && WPFactory_WC_STS_Dashboard::is_request() ) {
				return 'dashboard';
			}
			$known = array_merge( array( 'priorities', 'subjects', 'statuses', 'fields', 'saved', 'settings', 'tools' ), array_keys( (array) apply_filters( 'wpfactory_wc_sts_header_tabs', array() ) ) );
			return in_array( $tab, $known, true ) ? $tab : 'inbox';
		}

		/**
		 * Page header (kept for add-ons that call it).
		 */
		public function admin_header() {
			WPFactory_WC_STS_UI::header( 'inbox' === $this->active_tab ? '' : $this->active_tab );
		}

		/**
		 * Tabs (kept for add-ons; the header prints them now).
		 */
		public function admin_tabs() {
			$this->active_tab = $this->current_tab();
		}

		/**
		 * Fields already printed on the settings page being drawn: section => key => true.
		 *
		 * @var array
		 */
		protected $rendered = array();

		/**
		 * Sections already drawn on the page being drawn: id => true.
		 *
		 * @var array
		 */
		protected $drawn = array();

		/**
		 * Settings sections with their fields, cached for one request: id => { label, intro, fields, ... }.
		 *
		 * @var array|null
		 */
		protected $all_sections = null;

		/**
		 * Settings sections shown in the Automation tab (workflow rules, reply targets and automatic closing, working
		 * hours, proactive tickets).
		 *
		 * @return string[]
		 */
		public static function automation_sections() {
			/**
			 * Settings sections shown in the Automation tab instead of Settings.
			 *
			 * @param string[] $ids Section IDs.
			 */
			return (array) apply_filters( 'wpfactory_wc_sts_automation_sections', array( 'pro_workflows', 'pro_automation', 'pro_hours', 'pro_proactive' ) );
		}

		/**
		 * "Automation" in the plugin header when there are automation settings (Pro), for people who manage the store.
		 *
		 * @param array $tabs Tabs.
		 *
		 * @return array
		 */
		public function automation_tab( $tabs ) {
			if ( current_user_can( 'manage_woocommerce' ) && array_intersect( self::automation_sections(), array_keys( $this->get_extra_sections() ) ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				$tabs['automation'] = __( 'Automation', 'support-ticket-system-for-woocommerce' );
			}
			return $tabs;
		}

		/**
		 * Tab content: inbox, a term screen, saved replies, an add-on tab or the settings.
		 *
		 * @version 2.3.0
		 */
		public function admin_settings() {
			if ( '' === $this->active_tab ) {
				$this->active_tab = $this->current_tab();
			}
			$ui = WPFactory_WC_STS_UI::get_instance();
			switch ( $this->active_tab ) {
				case 'inbox':
					WPFactory_WC_STS_Inbox::get_instance()->render();
					return;
				case 'dashboard':
					WPFactory_WC_STS_Dashboard::get_instance()->render();
					return;
				case 'priorities':
				case 'subjects':
				case 'statuses':
				case 'tools':
					$this->render_settings_page( $this->active_tab );
					return;
				case 'fields':
					$this->render_settings_page( sanitize_key( wp_unslash( $_GET['field'] ?? 'priorities' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
					return;
				case 'saved':
					$ui->render_saved();
					return;
				case 'settings':
					break;
				case 'automation':
					if ( current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
						$this->render_settings_form( 'automation' );
					}
					return;
				default:
					/**
					 * Content of an add-on header tab.
					 */
					do_action( 'wpfactory_wc_sts_render_tab_' . $this->active_tab );
					return;
			}
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				if ( WPFactory_WC_STS_Inc::get_main_instance()->can_manage_tickets() ) {
					$this->render_settings_page( 'priorities' ); // Team members without settings access still manage ticket fields.
					return;
				}
				echo '<div class="page narrow">';
				WPFactory_WC_STS_UI::notice( __( 'You are not allowed to change these settings.', 'support-ticket-system-for-woocommerce' ), true );
				echo '</div>';
				return;
			}
			$this->render_settings_form( 'settings' );
		}

		/*
		|--------------------------------------------------------------------------
		| Settings and Automation pages
		|--------------------------------------------------------------------------
		*/

		/**
		 * Every settings section with its fields: the core ones, then the sections added by Pro and add-ons.
		 *
		 * @return array id => { label, intro, fields, render? }
		 */
		protected function sections() {
			if ( null === $this->all_sections ) {
				$this->all_sections = array(
					'general'       => array(
						'label'  => __( 'General', 'support-ticket-system-for-woocommerce' ),
						'fields' => $this->get_general_fields(),
					),
					'form'          => array(
						'label'  => __( 'Ticket form', 'support-ticket-system-for-woocommerce' ),
						'fields' => $this->get_form_fields(),
					),
					'assignment'    => array(
						'label'  => __( 'Assignment', 'support-ticket-system-for-woocommerce' ),
						'fields' => $this->get_assignment_fields(),
					),
					'notifications' => array(
						'label'  => __( 'Emails', 'support-ticket-system-for-woocommerce' ),
						'fields' => $this->get_notification_fields(),
					),
				) + $this->get_extra_sections();
			}
			return $this->all_sections;
		}

		/**
		 * Sections whose fields are on a page: Settings holds every section but the automation ones, and the other way
		 * round. A save only touches these.
		 *
		 * @param string $scope `settings` or `automation`.
		 *
		 * @return string[]
		 */
		protected function page_sections( $scope ) {
			$auto = self::automation_sections();
			$out  = array();
			foreach ( array_keys( $this->sections() ) as $id ) {
				if ( ( 'automation' === $scope ) === in_array( $id, $auto, true ) ) {
					$out[] = $id;
				}
			}
			return $out;
		}

		/**
		 * Tabs of a page: key => { label, sections (IDs whose old links open this tab), render (callback) }.
		 *
		 * @param string $scope `settings` or `automation`.
		 *
		 * @return array
		 */
		protected function panes( $scope ) {
			$sections = $this->sections();
			$on_page  = $this->page_sections( $scope );
			if ( 'automation' === $scope ) {
				$known = array(
					'pro_automation' => 'targets',
					'pro_proactive'  => 'proactive',
					'pro_workflows'  => 'rules',
					'pro_hours'      => 'hours',
				);
				$panes = array();
				foreach ( $known as $id => $key ) {
					if ( in_array( $id, $on_page, true ) ) {
						$panes[ $key ] = array(
							'label'    => $sections[ $id ]['label'],
							'sections' => array( $id, 'pro_automation' === $id ? 'targets' : substr( $id, 4 ) ),
							'render'   => array( $this, 'pane_automation' ),
						);
					}
				}
				foreach ( $on_page as $id ) {
					if ( ! isset( $known[ $id ] ) ) {
						$panes[ sanitize_key( preg_replace( '/^pro_/', '', $id ) ) ] = array(
							'label'    => $sections[ $id ]['label'],
							'sections' => array( $id ),
							'render'   => array( $this, 'pane_automation' ),
						);
					}
				}
				return $panes;
			}
			$placed = array( 'general', 'form', 'assignment', 'notifications', 'ai', 'pro_ai', 'pro_team', 'pro_ratings', 'pro_orders', 'pro_piping', 'pro_plans', 'pro_api' );
			$orders = array( 'pro_orders', 'pro_piping', 'pro_plans', 'help', 'kb', 'chat', 'piping', 'channels' );
			$other  = array( 'pro_api', 'api', 'webhooks', 'tools', 'import', 'export', 'data', 'privacy', 'uninstall' );
			foreach ( array_diff( $on_page, $placed ) as $id ) {
				if ( 'workflows' === ( $sections[ $id ]['group'] ?? '' ) ) {
					$orders[] = $id;
				} else {
					$other[] = $id; // Sections of add-ons: Advanced, unless they belong with orders and channels.
				}
			}
			return array(
				'basics'   => array(
					'label'    => __( 'Basics', 'support-ticket-system-for-woocommerce' ),
					'sections' => array( 'general', 'form', 'customers', 'messages', 'numbers', 'spam' ),
					'render'   => array( $this, 'pane_basics' ),
				),
				'emails'   => array(
					'label'    => __( 'Emails', 'support-ticket-system-for-woocommerce' ),
					'sections' => array( 'notifications', 'email', 'mail', 'test-email' ),
					'render'   => array( $this, 'pane_emails' ),
				),
				'team'     => array(
					'label'    => __( 'Team', 'support-ticket-system-for-woocommerce' ),
					'sections' => array( 'assignment', 'pro_team', 'pro_ratings', 'permissions' ),
					'render'   => array( $this, 'pane_team' ),
				),
				'fields'   => array(
					'label'    => __( 'Ticket fields', 'support-ticket-system-for-woocommerce' ),
					'sections' => array( 'ticket-fields', 'priorities', 'subjects', 'statuses', 'reasons', 'tags' ),
					'render'   => array( $this, 'pane_fields' ),
				),
				'orders'   => array(
					'label'    => __( 'Orders and channels', 'support-ticket-system-for-woocommerce' ),
					'sections' => $orders,
					'render'   => array( $this, 'pane_orders' ),
				),
				'ai'       => array(
					'label'    => __( 'AI', 'support-ticket-system-for-woocommerce' ),
					'sections' => array( 'ai', 'pro_ai' ),
					'render'   => array( $this, 'pane_ai' ),
				),
				'advanced' => array(
					'label'    => __( 'Advanced', 'support-ticket-system-for-woocommerce' ),
					'sections' => $other,
					'render'   => array( $this, 'pane_advanced' ),
				),
			);
		}

		/**
		 * Short names that open a tab (old links such as #emails, #pro_piping or #ratings): name => tab key.
		 *
		 * @param array $panes Tabs.
		 *
		 * @return array
		 */
		protected function pane_aliases( $panes ) {
			$out = array();
			foreach ( $panes as $key => $pane ) {
				$out[ $key ] = $key;
				foreach ( $pane['sections'] as $id ) {
					$out[ $id ]                                = $key;
					$out[ preg_replace( '/^pro_/', '', $id ) ] = $key;
				}
				$out[ sanitize_title( $pane['label'] ) ] = $key;
			}
			return $out;
		}

		/**
		 * The Settings form (seven tabs) or the Automation form (four tabs): one form, every tab on the page, the tab
		 * shown switched in place by the settings script and kept in the address (`&section=`).
		 *
		 * @param string $scope `settings` or `automation`.
		 */
		protected function render_settings_form( $scope ) {
			$panes   = $this->panes( $scope );
			$aliases = $this->pane_aliases( $panes );
			$asked   = sanitize_key( wp_unslash( $_GET['section'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
			$current = $aliases[ $asked ] ?? (string) array_key_first( $panes );
			$moved   = array();
			if ( 'settings' === $scope ) {
				foreach ( $this->pane_aliases( $this->panes( 'automation' ) ) as $alias => $key ) {
					if ( ! isset( $aliases[ $alias ] ) ) {
						$moved[ $alias ] = WPFactory_WC_STS_UI::url( 'automation', array( 'section' => $key ) );
					}
				}
			}
			$this->rendered = array();
			$this->drawn    = array();
			echo '<div class="page narrow" data-hd-settings-page>';
			$this->admin_process_settings();
			if ( 'automation' === $scope ) {
				WPFactory_WC_STS_UI::page_head( __( 'Automation', 'support-ticket-system-for-woocommerce' ), __( 'Targets, auto-close and rules that run without you.', 'support-ticket-system-for-woocommerce' ), '', __( 'Automation', 'support-ticket-system-for-woocommerce' ) );
			} else {
				$wizard = '<a class="btn btn-text" href="' . esc_url( admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '-setup' ) ) . '">' . esc_html__( 'Run setup wizard again', 'support-ticket-system-for-woocommerce' ) . '</a>';
				WPFactory_WC_STS_UI::page_head( __( 'Helpdesk settings', 'support-ticket-system-for-woocommerce' ), __( 'How customers reach support, what your team can do, and how the helpdesk connects to the rest of your store.', 'support-ticket-system-for-woocommerce' ), $wizard, __( 'Settings', 'support-ticket-system-for-woocommerce' ) );
			}
			// Each tab is drawn first, so fields no tab placed can still be shown (and saved) at the end of the last tab.
			$html = array();
			foreach ( $panes as $key => $pane ) {
				ob_start();
				call_user_func( $pane['render'], $pane['sections'] );
				$html[ $key ] = ob_get_clean();
			}
			ob_start();
			$this->render_leftovers( $scope );
			$html[ (string) array_key_last( $panes ) ] .= ob_get_clean();
			?>
			<form method="post" id="<?php echo esc_attr( $this->plugin ); ?>Form" class="hd-settings" data-hd-settings="<?php echo esc_attr( $scope ); ?>" data-hd-aliases="<?php echo esc_attr( wp_json_encode( $aliases ) ); ?>"<?php echo $moved ? ' data-hd-moved="' . esc_attr( wp_json_encode( $moved ) ) . '"' : ''; ?>>
				<?php // The first submit button is the one Enter presses: saving, never "Turn AI off" or another action. ?>
				<button type="submit" name="submit" class="screen-reader-text" tabindex="-1" aria-hidden="true"><?php esc_html_e( 'Save changes', 'support-ticket-system-for-woocommerce' ); ?></button>
				<nav class="tabs" role="tablist" aria-label="<?php echo esc_attr( 'automation' === $scope ? __( 'Automation sections', 'support-ticket-system-for-woocommerce' ) : __( 'Settings sections', 'support-ticket-system-for-woocommerce' ) ); ?>">
					<?php foreach ( $panes as $key => $pane ) : ?>
						<a href="<?php echo esc_url( WPFactory_WC_STS_UI::url( $scope, array( 'section' => $key ) ) ); ?>" role="tab" id="hd-tab-<?php echo esc_attr( $key ); ?>" aria-controls="hd-pane-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo $key === $current ? 'true' : 'false'; ?>"<?php echo $key === $current ? ' class="on"' : ' tabindex="-1"'; ?> data-hd-pane-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $pane['label'] ); ?></a>
					<?php endforeach; ?>
				</nav>
				<?php foreach ( $panes as $key => $pane ) : ?>
					<div class="pane stack" id="hd-pane-<?php echo esc_attr( $key ); ?>" role="tabpanel" aria-labelledby="hd-tab-<?php echo esc_attr( $key ); ?>" data-hd-pane="<?php echo esc_attr( $key ); ?>"<?php echo $key === $current ? '' : ' hidden'; ?>>
						<?php echo $html[ $key ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while drawing each tab. ?>
					</div>
				<?php endforeach; ?>
				<?php wp_nonce_field( $this->plugin ); ?>
				<?php // Every section on this page is saved; sections of the other page are never touched. ?>
				<input type="hidden" name="stsw_section" value="<?php echo esc_attr( implode( ',', $this->page_sections( $scope ) ) ); ?>" data-hd-section />
				<div class="savebar" id="save_changes">
					<span class="small muted state" data-hd-state aria-live="polite"><?php esc_html_e( 'All changes saved', 'support-ticket-system-for-woocommerce' ); ?></span>
					<button type="submit" name="submit" class="btn btn-primary"><?php esc_html_e( 'Save changes', 'support-ticket-system-for-woocommerce' ); ?></button>
				</div>
			</form>
			<?php
			echo '</div>';
		}

		/**
		 * Fields of the page's sections that no tab placed (an add-on field, say): shown in one card so they still save.
		 *
		 * @param string $scope `settings` or `automation`.
		 */
		protected function render_leftovers( $scope ) {
			$sections = $this->sections();
			$left     = array();
			foreach ( $this->page_sections( $scope ) as $id ) {
				foreach ( $sections[ $id ]['fields'] as $key => $field ) {
					if ( ! isset( $this->rendered[ $id ][ $key ] ) ) {
						$left[ $id ][] = $key;
					}
				}
			}
			if ( ! $left ) {
				return;
			}
			$this->card_start( __( 'More settings', 'support-ticket-system-for-woocommerce' ) );
			foreach ( $left as $id => $keys ) {
				$this->rows( $id, $keys );
			}
			$this->card_end();
		}

		/*
		|--------------------------------------------------------------------------
		| Building blocks for the tabs (public: Pro and add-ons draw their own cards with them)
		|--------------------------------------------------------------------------
		*/

		/**
		 * Field definition.
		 *
		 * @param string $section Section ID.
		 * @param string $key     Field key.
		 *
		 * @return array|null
		 */
		public function field( $section, $key ) {
			$sections = $this->sections();
			return $sections[ $section ]['fields'][ $key ] ?? null;
		}

		/**
		 * Keys of a section's fields not printed yet.
		 *
		 * @param string $section Section ID.
		 *
		 * @return string[]
		 */
		public function unrendered( $section ) {
			$sections = $this->sections();
			return array_values( array_diff( array_keys( (array) ( $sections[ $section ]['fields'] ?? array() ) ), array_keys( (array) ( $this->rendered[ $section ] ?? array() ) ) ) );
		}

		/**
		 * Marks a field as placed (its value is posted by markup drawn elsewhere).
		 *
		 * @param string $section Section ID.
		 * @param string $key     Field key.
		 */
		public function mark( $section, $key ) {
			$this->rendered[ $section ][ $key ] = true;
		}

		/**
		 * Opens a card.
		 *
		 * @param string $title Title.
		 * @param array  $args  `id`, `intro`, `action` (escaped HTML: a head with a title and this action), `class`.
		 */
		public function card_start( $title, $args = array() ) {
			echo '<section class="card' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '"' . ( ! empty( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '" tabindex="-1"' : '' ) . '>';
			if ( isset( $args['action'] ) ) {
				echo '<div class="card-head"><div><h2 class="card-title">' . esc_html( $title ) . '</h2>' . ( ! empty( $args['intro'] ) ? '<div class="row-meta">' . esc_html( $args['intro'] ) . '</div>' : '' ) . '</div>' . $args['action'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller.
				return;
			}
			echo '<h2 class="section-label">' . esc_html( $title ) . '</h2>';
			if ( ! empty( $args['intro'] ) ) {
				echo '<p class="card-intro">' . esc_html( $args['intro'] ) . '</p>';
			}
		}

		/**
		 * Closes a card.
		 *
		 * @param string $foot Card foot (escaped HTML).
		 */
		public function card_end( $foot = '' ) {
			if ( '' !== $foot ) {
				echo '<div class="card-foot">' . $foot . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller.
			}
			echo '</section>';
		}

		/**
		 * A card with setting rows: the keys given, or every field of the section not printed yet. Nothing when none
		 * of the fields exist.
		 *
		 * @param string     $section Section ID.
		 * @param string     $title   Title.
		 * @param array|null $keys    Field keys (null: the rest of the section).
		 * @param array      $args    Card arguments (see card_start()), plus `foot` and `pairs` (key => key drawn in
		 *                            the same row).
		 */
		public function card( $section, $title, $keys = null, $args = array() ) {
			$keys = null === $keys ? $this->unrendered( $section ) : array_values(
				array_filter(
					$keys,
					function ( $key ) use ( $section ) {
						return null !== $this->field( $section, $key ) && ! isset( $this->rendered[ $section ][ $key ] );
					}
				)
			);
			if ( ! $keys ) {
				return;
			}
			$this->card_start( $title, $args );
			$this->rows( $section, $keys, $args['pairs'] ?? array() );
			$this->card_end( $args['foot'] ?? '' );
		}

		/**
		 * Setting rows.
		 *
		 * @param string $section Section ID.
		 * @param array  $keys    Field keys.
		 * @param array  $pairs   key => second key drawn in the same row.
		 */
		public function rows( $section, $keys, $pairs = array() ) {
			foreach ( $keys as $key ) {
				$this->row( $section, $key, isset( $pairs[ $key ] ) ? array( 'pair' => $pairs[ $key ] ) : array() );
			}
		}

		/**
		 * One setting row: title and help on the left, the control on the right (below it for editors and lists).
		 *
		 * @param string $section Section ID.
		 * @param string $key     Field key.
		 * @param array  $args    `pair` (second field in the same row), `when` (shown only when an input has a value:
		 *                        `name=value`), `full` (control below the title), `bare` (an HTML field prints itself;
		 *                        also set by the field's own `bare` key).
		 */
		public function row( $section, $key, $args = array() ) {
			$field = $this->field( $section, $key );
			if ( ! $field || isset( $this->rendered[ $section ][ $key ] ) ) {
				return;
			}
			if ( 'html' === $field['type'] && ( ! empty( $args['bare'] ) || ! empty( $field['bare'] ) ) ) {
				$this->mark( $section, $key );
				call_user_func( $field['render'] );
				return;
			}
			$name    = $this->option_name( $key );
			$id      = sanitize_key( $name );
			$help_id = $id . '-help';
			$wide    = ! empty( $args['full'] ) || in_array( $field['type'], array( 'editor', 'textarea', 'roles' ), true ) || ( 'html' === $field['type'] && ! empty( $field['wide'] ) );
			$when    = ! empty( $args['when'] ) ? ' data-hd-when="' . esc_attr( $args['when'] ) . '"' : '';
			echo '<div class="setting' . ( $wide ? ' full' : '' ) . '" data-hd-field="' . esc_attr( $name ) . '"' . $when . '><div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $when escaped above.
			if ( in_array( $field['type'], array( 'roles', 'html' ), true ) ) {
				echo '<p class="row-title" id="' . esc_attr( $id ) . '-label">' . esc_html( $field['label'] ) . '</p>';
			} else {
				echo '<label class="row-title" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
			}
			if ( ! empty( $field['description'] ) ) {
				echo '<div class="row-meta" id="' . esc_attr( $help_id ) . '">' . esc_html( $field['description'] ) . '</div>';
			}
			echo '</div><div class="ctl">';
			echo $this->control( $section, $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in control().
			if ( ! empty( $args['pair'] ) && $this->field( $section, $args['pair'] ) ) {
				echo $this->control( $section, $args['pair'], array( 'aria-label' => $this->field( $section, $args['pair'] )['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in control().
			}
			echo '</div></div>';
		}

		/**
		 * The control of a field (marks it as placed).
		 *
		 * @param string $section Section ID.
		 * @param string $key     Field key.
		 * @param array  $attrs   More attributes (`aria-label`, `aria-labelledby`, `id`).
		 *
		 * @return string HTML.
		 */
		public function control( $section, $key, $attrs = array() ) {
			$field = $this->field( $section, $key );
			if ( ! $field ) {
				return '';
			}
			$this->mark( $section, $key );
			$name  = $this->option_name( $key );
			$id    = $attrs['id'] ?? sanitize_key( $name );
			$value = get_option( $name, $field['default'] ?? '' );
			$extra = '';
			foreach ( array( 'aria-label', 'aria-labelledby' ) as $attr ) {
				if ( ! empty( $attrs[ $attr ] ) ) {
					$extra .= ' ' . $attr . '="' . esc_attr( $attrs[ $attr ] ) . '"';
				}
			}
			if ( ! empty( $field['description'] ) && empty( $attrs['id'] ) ) {
				$extra .= ' aria-describedby="' . esc_attr( $id . '-help' ) . '"';
			}
			ob_start();
			switch ( $field['type'] ) {
				case 'checkbox':
					$toggle = array(
						'id'               => $id,
						'aria-describedby' => ! empty( $field['description'] ) && empty( $attrs['id'] ) ? $id . '-help' : false,
						'aria-label'       => $attrs['aria-label'] ?? false,
						'aria-labelledby'  => $attrs['aria-labelledby'] ?? false,
					);
					echo wp_kses( WPFactory_WC_STS_UI::toggle( $name, '1' === (string) $value, $toggle ), WPFactory_WC_STS_UI::toggle_kses() );
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
					echo '<select class="select" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $extra . '><option value="">' . esc_html__( 'None', 'support-ticket-system-for-woocommerce' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra escaped above.
					wp_dropdown_roles( (string) $value );
					echo '</select>';
					break;
				case 'user':
					WPFactory_WC_STS_Admin::get_instance()->render_user_select( $name, (int) $value, __( 'Nobody', 'support-ticket-system-for-woocommerce' ), $id );
					break;
				case 'roles':
					echo '<fieldset id="' . esc_attr( $id ) . '" class="checks" aria-labelledby="' . esc_attr( $id ) . '-label">';
					foreach ( wp_roles()->get_names() as $role => $role_name ) {
						echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $role ) . '"' . checked( in_array( $role, (array) $value, true ), true, false ) . ' /> ' . esc_html( translate_user_role( $role_name ) ) . '</label>';
					}
					echo '</fieldset>';
					break;
				case 'select':
					echo '<select class="select" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra escaped above.
					foreach ( $field['options'] as $option => $label ) {
						echo '<option value="' . esc_attr( $option ) . '"' . selected( (string) $value, (string) $option, false ) . '>' . esc_html( $label ) . '</option>';
					}
					echo '</select>';
					break;
				case 'password':
					echo '<input type="password" class="input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( '' !== (string) $value ? __( 'Saved. Leave empty to keep it.', 'support-ticket-system-for-woocommerce' ) : '' ) . '"' . $extra . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra escaped above.
					break;
				case 'textarea':
					echo '<textarea class="input" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $extra . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra escaped above.
					break;
				case 'html':
					call_user_func( $field['render'] );
					break;
				default:
					$class = 'number' === $field['type'] ? 'input input-num' : 'input';
					echo '<input type="' . esc_attr( $field['type'] ) . '" class="' . esc_attr( $class ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . ( isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '' ) . ( 'number' === $field['type'] ? ' min="0" step="1"' : '' ) . $extra . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra escaped above.
			}
			return (string) ob_get_clean();
		}

		/**
		 * A button that opens a region in place (no page load). Without scripts the region is simply shown and the
		 * button stays hidden.
		 *
		 * @param string $region  Region ID.
		 * @param string $label   Button text.
		 * @param string $classes Button classes.
		 * @param string $aria    Accessible name ('' : the text).
		 *
		 * @return string HTML.
		 */
		public static function expand_button( $region, $label, $classes = 'btn btn-text btn-sm', $aria = '' ) {
			return '<button type="button" class="' . esc_attr( $classes ) . '" data-hd-expand aria-controls="' . esc_attr( $region ) . '" aria-expanded="true"' . ( '' !== $aria ? ' aria-label="' . esc_attr( $aria ) . '"' : '' ) . ' hidden>' . esc_html( $label ) . '</button>';
		}

		/*
		|--------------------------------------------------------------------------
		| Settings tabs
		|--------------------------------------------------------------------------
		*/

		/**
		 * Basics: customer access, messages, ticket numbers, the ticket form, spam protection.
		 */
		public function pane_basics() {
			// Guest access is saved with the ticket form; it is shown with customer access.
			$this->card_start( __( 'Customer access', 'support-ticket-system-for-woocommerce' ), array( 'id' => 'general' ) );
			$this->rows( 'general', array( 'renameAccountTabLink', 'renameOrderButtonLink' ) );
			$this->rows( 'form', array( '@wpfactory_wc_sts_guest_tickets', '@wpfactory_wc_sts_guest_otp' ) );
			$this->rows( 'general', array( 'hideClosed', '@wpfactory_wc_sts_support_page' ) );
			$this->card_end();
			$this->card( 'general', __( 'Messages', 'support-ticket-system-for-woocommerce' ), array( 'textforTicketSave', 'textforResponseSave' ), array( 'id' => 'messages' ) );
			$this->card( 'general', __( 'Ticket numbers', 'support-ticket-system-for-woocommerce' ), $this->keys_with_card( 'general', 'numbers' ), array( 'id' => 'numbers' ) );

			$this->card_start( __( 'Ticket form', 'support-ticket-system-for-woocommerce' ), array( 'id' => 'form' ) );
			$count = count( WPFactory_WC_STS_Fields::get_instance()->get_fields() );
			echo '<div class="setting"><div><p class="row-title" id="hd-custom-fields-t">' . esc_html__( 'Custom fields', 'support-ticket-system-for-woocommerce' ) . '</p><div class="row-meta">';
			/* translators: %d: number of fields. */
			echo esc_html( $count ? sprintf( _n( '%d field, shown by product, category, topic or reason', '%d fields, shown by product, category, topic or reason', $count, 'support-ticket-system-for-woocommerce' ), $count ) : __( 'Extra questions on the new ticket form. None yet.', 'support-ticket-system-for-woocommerce' ) );
			echo '</div></div><div class="ctl">' . self::expand_button( 'hd-custom-fields', __( 'Manage fields', 'support-ticket-system-for-woocommerce' ), 'btn btn-secondary btn-sm' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in expand_button().
			echo '<div class="expand" id="hd-custom-fields" data-hd-region role="group" aria-labelledby="hd-custom-fields-t">';
			WPFactory_WC_STS_Fields::get_instance()->render_settings();
			echo '</div>';
			$this->rows( 'form', array( 'enablePriority', 'enableAttachments', 'allowedExtensions', 'allowedSize', 'allowedAttachNum' ) );
			$this->card_end();

			$this->card( 'form', __( 'Spam protection', 'support-ticket-system-for-woocommerce' ), $this->keys_with_card( 'form', 'spam' ), array( 'id' => 'spam' ) );
		}

		/**
		 * Keys of a section's fields that carry a card name.
		 *
		 * @param string $section Section ID.
		 * @param string $card    Card name.
		 *
		 * @return string[]
		 */
		protected function keys_with_card( $section, $card ) {
			$sections = $this->sections();
			$keys     = array();
			foreach ( (array) ( $sections[ $section ]['fields'] ?? array() ) as $key => $field ) {
				if ( (string) ( $field['card'] ?? '' ) === $card ) {
					$keys[] = $key;
				}
			}
			return $keys;
		}

		/**
		 * Placeholders of the email templates.
		 *
		 * @return string
		 */
		protected function email_placeholders() {
			return __( 'Placeholders:', 'support-ticket-system-for-woocommerce' ) . ' {ticket_number} {ticket_title} {ticket_status} {customer_first_name} {customer_last_name} {customer_email} {order_number} {store_name} {content} {responseContent} {ticket_url} {ticket_url_plain} ' . __( 'Add a fallback after a bar: {customer_first_name|there}. Older names such as {ticketId} and {ticketLink} still work.', 'support-ticket-system-for-woocommerce' );
		}

		/**
		 * One email: its On/Off switch and "Edit", which opens the subject and text in place.
		 *
		 * @param string $section Section of the switch.
		 * @param string $toggle  Field key of the switch.
		 * @param string $title   Title.
		 * @param string $meta    When it is sent.
		 * @param array  $editors Field keys of the subject and text (in the Emails section).
		 */
		protected function email_row( $section, $toggle, $title, $meta, $editors = array() ) {
			$field = $this->field( $section, $toggle );
			if ( ! $field ) {
				return;
			}
			$slug   = sanitize_key( $this->option_name( $toggle ) );
			$region = 'hd-mail-' . $slug;
			$meta   = trim( $meta . ' ' . ( $field['description'] ?? '' ) );
			echo '<div class="row" data-hd-field="' . esc_attr( $this->option_name( $toggle ) ) . '"><div><p class="row-title" id="' . esc_attr( $region ) . '-t">' . esc_html( $title ) . '</p>' . ( '' !== $meta ? '<div class="row-meta">' . esc_html( $meta ) . '</div>' : '' ) . '</div><div class="row-actions">';
			echo $this->control( $section, $toggle, array( 'aria-labelledby' => $region . '-t' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in control().
			$editors = array_filter(
				$editors,
				function ( $key ) {
					return null !== $this->field( 'notifications', $key );
				}
			);
			if ( $editors ) {
				/* translators: %s: email name. */
				echo self::expand_button( $region, __( 'Edit', 'support-ticket-system-for-woocommerce' ), 'btn btn-text btn-sm', sprintf( __( 'Edit the "%s" email', 'support-ticket-system-for-woocommerce' ), $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in expand_button().
			}
			echo '</div></div>';
			if ( $editors ) {
				echo '<div class="expand" id="' . esc_attr( $region ) . '" data-hd-region role="group" aria-labelledby="' . esc_attr( $region ) . '-t">';
				$this->rows( 'notifications', $editors );
				echo '<p class="hint expand-hint">' . esc_html( $this->email_placeholders() ) . '</p></div>';
			}
		}

		/**
		 * Emails: the sender and a test email, then each email to customers and to the team with its switch and text.
		 */
		public function pane_emails() {
			$this->card_start( __( 'Sender', 'support-ticket-system-for-woocommerce' ), array( 'id' => 'notifications' ) );
			$this->rows( 'notifications', array( 'AdminEmailAddress' ) );
			WPFactory_WC_STS_Emails::get_instance()->render_test_email_button();
			$this->card_end();

			$this->card_start( __( 'Emails to customers', 'support-ticket-system-for-woocommerce' ), array( 'id' => 'emails-customers' ) );
			$this->email_row( 'notifications', 'mailToCustomer', __( 'Ticket received and store replies', 'support-ticket-system-for-woocommerce' ), __( 'A confirmation when a customer opens a ticket, and each reply from your team.', 'support-ticket-system-for-woocommerce' ), array( 'mailIt_subjectToCust', 'mailIt_contentToCust' ) );
			$this->email_row( 'notifications', '@wpfactory_wc_sts_mail_reply_received', __( 'We received your reply', 'support-ticket-system-for-woocommerce' ), __( 'When a customer replies.', 'support-ticket-system-for-woocommerce' ) );
			$this->email_row( 'notifications', '@wpfactory_wc_sts_mail_status', __( 'Status changed', 'support-ticket-system-for-woocommerce' ), '', array( '@wpfactory_wc_sts_mail_status_subject', '@wpfactory_wc_sts_mail_status_body' ) );
			$this->email_row( 'notifications', '@wpfactory_wc_sts_mail_closed', __( 'Ticket closed', 'support-ticket-system-for-woocommerce' ), '', array( '@wpfactory_wc_sts_mail_closed_subject', '@wpfactory_wc_sts_mail_closed_body' ) );
			$this->card_end( '<span class="small muted">' . esc_html__( 'Edit an email to change its subject and text. Empty: the default text.', 'support-ticket-system-for-woocommerce' ) . '</span>' );

			$this->card_start( __( 'Emails to your team', 'support-ticket-system-for-woocommerce' ), array( 'id' => 'emails-team' ) );
			$this->email_row( 'notifications', 'mailToADmin', __( 'New tickets and replies, to the store', 'support-ticket-system-for-woocommerce' ), __( 'Sent to the store email address above.', 'support-ticket-system-for-woocommerce' ), array( 'mailIt_subjectToAs', 'mailIt_contentToAs' ) );
			$this->email_row( 'assignment', 'mailToAssignee', __( 'New tickets and replies, to the assignee', 'support-ticket-system-for-woocommerce' ), __( 'Uses the same subject and text as the store email.', 'support-ticket-system-for-woocommerce' ) );
			$this->card_end();

			$this->card( 'notifications', __( 'More email settings', 'support-ticket-system-for-woocommerce' ) );
		}

		/**
		 * Team: assignment, team permissions and ratings (Pro).
		 */
		public function pane_team() {
			$this->card( 'assignment', __( 'Assignment', 'support-ticket-system-for-woocommerce' ), array( 'assignToUser', 'assignToRole', '@wpfactory_wc_sts_confirm_send', '@wpfactory_wc_sts_admin_bar' ), array( 'id' => 'assignment' ) );
			$this->section_card( 'pro_team' );
			$this->section_card( 'pro_ratings' );
			$this->card( 'assignment', __( 'More assignment settings', 'support-ticket-system-for-woocommerce' ) );
		}

		/**
		 * Ticket fields: statuses, priorities, subjects, reasons and tags with their values; Manage opens each list.
		 */
		public function pane_fields() {
			$this->card_start( __( 'Ticket fields', 'support-ticket-system-for-woocommerce' ), array( 'id' => 'ticket-fields' ) );
			$lists = array(
				'statuses'   => array( __( 'Statuses', 'support-ticket-system-for-woocommerce' ), 'stsw_tickets_status' ),
				'priorities' => array( __( 'Priorities', 'support-ticket-system-for-woocommerce' ), 'stsw_tickets_priority' ),
				'subjects'   => array( __( 'Subjects', 'support-ticket-system-for-woocommerce' ), 'stsw_tickets_subject' ),
			);
			foreach ( $lists as $key => $list ) {
				$names = wp_list_pluck( WPFactory_WC_STS_UI::ordered_terms( $list[1] ), 'name' );
				$this->list_row( $key, $list[0], $names, '<a class="btn btn-secondary btn-sm" href="' . esc_url( WPFactory_WC_STS_UI::term_url( $key ) ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: list name. */ __( 'Manage %s', 'support-ticket-system-for-woocommerce' ), $list[0] ) ) . '">' . esc_html__( 'Manage', 'support-ticket-system-for-woocommerce' ) . '</a>' );
			}
			$reasons = $this->keys_with_card( 'form', 'reasons' );
			$this->list_row( 'reasons', __( 'Reasons', 'support-ticket-system-for-woocommerce' ), array_values( WPFactory_WC_STS_Inc::get_main_instance()->get_reasons() ), $reasons ? self::expand_button( 'hd-reasons', __( 'Manage', 'support-ticket-system-for-woocommerce' ), 'btn btn-secondary btn-sm', __( 'Manage reasons', 'support-ticket-system-for-woocommerce' ) ) : '' );
			if ( $reasons ) {
				echo '<div class="expand" id="hd-reasons" data-hd-region role="group" aria-labelledby="hd-list-reasons-t"><p class="hint expand-hint">' . esc_html__( 'Customers asking about an order choose a reason; customers with a general question choose a topic. Each reason can file the ticket under one of your topics.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				$this->rows( 'form', $reasons );
				echo '</div>';
			}
			$tags = get_terms(
				array(
					'taxonomy'   => 'stsw_tickets_tag',
					'hide_empty' => false,
					'number'     => 12,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'fields'     => 'names',
				)
			);
			$tags = is_wp_error( $tags ) ? array() : $tags;
			$this->list_row( 'tags', __( 'Tags', 'support-ticket-system-for-woocommerce' ), $tags, '', __( 'Added from the ticket screen. None yet.', 'support-ticket-system-for-woocommerce' ) );
			$this->card_end();
		}

		/**
		 * A row of the Ticket fields card.
		 *
		 * @param string $key    Key.
		 * @param string $title  Title.
		 * @param array  $values Value names.
		 * @param string $action Action (escaped HTML).
		 * @param string $none   Text when there are no values.
		 */
		protected function list_row( $key, $title, $values, $action, $none = '' ) {
			$text = $values ? implode( ', ', array_map( 'strval', $values ) ) : ( '' !== $none ? $none : __( 'None yet.', 'support-ticket-system-for-woocommerce' ) );
			echo '<div class="row" id="hd-list-' . esc_attr( $key ) . '"><div><p class="row-title" id="hd-list-' . esc_attr( $key ) . '-t">' . esc_html( $title ) . '</p><div class="row-meta">' . esc_html( $text ) . '</div></div>' . ( '' !== $action ? '<div class="row-actions">' . $action . '</div>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $action escaped by the caller.
		}

		/**
		 * Orders and channels: order actions, email piping, paid support (Pro), the help center and live chat.
		 *
		 * @param array $ids Section IDs of this tab.
		 */
		public function pane_orders( $ids = array() ) {
			foreach ( array( 'pro_orders', 'pro_piping', 'pro_plans' ) as $id ) {
				$this->section_card( $id );
			}
			$known = array( 'renameAccountTabLink', 'renameOrderButtonLink', 'hideClosed', 'textforTicketSave', 'textforResponseSave', '@wpfactory_wc_sts_delete_data_on_uninstall', '@wpfactory_wc_sts_support_page' );
			$help  = array_diff( $this->unrendered( 'general' ), $known, $this->keys_with_card( 'general', 'numbers' ), $this->keys_with_card( 'general', 'data' ) );
			$this->card( 'general', __( 'Help center and live chat', 'support-ticket-system-for-woocommerce' ), $help, array( 'id' => 'help' ) );
			foreach ( $ids as $id ) {
				if ( 0 === strpos( $id, 'pro_' ) || ! in_array( $id, array( 'help', 'kb', 'chat', 'piping', 'channels' ), true ) ) {
					$this->section_card( $id );
				}
			}
		}

		/**
		 * AI: how AI connects, limits and what it does (the AI section draws its own cards).
		 */
		public function pane_ai() {
			$sections = $this->sections();
			$id       = isset( $sections['pro_ai'] ) ? 'pro_ai' : ( isset( $sections['ai'] ) ? 'ai' : '' );
			if ( '' === $id ) {
				$this->card_start( __( 'How should AI connect?', 'support-ticket-system-for-woocommerce' ), array( 'id' => 'ai' ) );
				echo '<div class="empty">' . esc_html__( 'WPFactory AI is not available in this copy of the plugin.', 'support-ticket-system-for-woocommerce' ) . '</div>';
				$this->card_end();
				return;
			}
			$this->section_card( $id );
		}

		/**
		 * Advanced: API and webhooks (Pro), tools, data, and removing data on uninstall.
		 *
		 * @param array $ids Section IDs of this tab.
		 */
		public function pane_advanced( $ids = array() ) {
			$this->section_card( 'pro_api' );
			foreach ( $ids as $id ) {
				if ( 'pro_api' !== $id ) {
					$this->section_card( $id );
				}
			}
			$this->card_start( __( 'Tools', 'support-ticket-system-for-woocommerce' ), array( 'id' => 'tools' ) );
			if ( array_key_exists( 'tools', (array) apply_filters( 'wpfactory_wc_sts_header_tabs', array() ) ) && class_exists( 'WPFactory_WC_STS_Import' ) ) {
				$import = WPFactory_WC_STS_Import::get_instance();
				$this->list_row( 'import', __( 'Import tickets', 'support-ticket-system-for-woocommerce' ), array( sprintf( /* translators: %s: plugin names. */ __( 'From %s. A check shows the counts first.', 'support-ticket-system-for-woocommerce' ), implode( ', ', $import->sources() ) ) ), '<a class="btn btn-secondary btn-sm" href="' . esc_url( WPFactory_WC_STS_UI::url( 'tools' ) ) . '">' . esc_html__( 'Start import', 'support-ticket-system-for-woocommerce' ) . '</a>' );
				$this->list_row( 'export', __( 'Export tickets', 'support-ticket-system-for-woocommerce' ), array( __( 'CSV of every ticket. To export some tickets, filter the inbox and use Export CSV there.', 'support-ticket-system-for-woocommerce' ) ), '<a class="btn btn-secondary btn-sm" href="' . esc_url( $import->export_url() ) . '">' . esc_html__( 'Export CSV', 'support-ticket-system-for-woocommerce' ) . '</a>' );
			}
			WPFactory_WC_STS_Emails::get_instance()->render_email_health();
			$this->card_end();
			$this->card( 'general', __( 'Data', 'support-ticket-system-for-woocommerce' ), $this->keys_with_card( 'general', 'data' ), array( 'id' => 'data' ) );
			$this->card(
				'general',
				__( 'Careful here', 'support-ticket-system-for-woocommerce' ),
				array( '@wpfactory_wc_sts_delete_data_on_uninstall' ),
				array(
					'class' => 'danger',
					'id'    => 'uninstall',
				)
			);
		}

		/**
		 * A section in one card (or the cards it draws itself through its `render` callback).
		 *
		 * @param string $id Section ID.
		 */
		public function section_card( $id ) {
			$sections = $this->sections();
			if ( ! isset( $sections[ $id ] ) || ! in_array( $id, $this->page_sections( in_array( $id, self::automation_sections(), true ) ? 'automation' : 'settings' ), true ) ) {
				return;
			}
			if ( isset( $this->drawn[ $id ] ) ) {
				return;
			}
			$this->drawn[ $id ] = true;
			$section            = $sections[ $id ];
			if ( ! empty( $section['render'] ) && is_callable( $section['render'] ) ) {
				call_user_func( $section['render'], $this, $id );
				return;
			}
			$this->card(
				$id,
				$section['card'] ?? $section['label'],
				null,
				array(
					'id'    => $id,
					'intro' => $section['intro'] ?? '',
				)
			);
		}

		/**
		 * One Automation tab.
		 *
		 * @param array $ids Section IDs of the tab (the first is the section).
		 */
		public function pane_automation( $ids ) {
			$id = (string) reset( $ids );
			if ( 'pro_automation' === $id ) {
				$sections = $this->sections();
				$this->card(
					$id,
					__( 'Support period and auto-close', 'support-ticket-system-for-woocommerce' ),
					array( '@wpfactory_wc_sts_pro_support_days', '@wpfactory_wc_sts_pro_close_days', '@wpfactory_wc_sts_pro_warn_days' ),
					array(
						'id'    => $id,
						'intro' => $sections[ $id ]['intro'] ?? '',
					)
				);
				$this->card( $id, __( 'Team targets', 'support-ticket-system-for-woocommerce' ), array( '@wpfactory_wc_sts_pro_first_hours', '@wpfactory_wc_sts_pro_next_hours' ), array( 'id' => 'targets' ) );
				$this->card( $id, __( 'More settings', 'support-ticket-system-for-woocommerce' ) );
				return;
			}
			$this->section_card( $id );
		}

		/**
		 * A screen inside Settings: a ticket field list (statuses, priorities, subjects) or Tools (import and export).
		 *
		 * @param string $what `priorities`, `subjects`, `statuses` or `tools`.
		 */
		protected function render_settings_page( $what ) {
			$fields = array(
				'statuses'   => __( 'Statuses', 'support-ticket-system-for-woocommerce' ),
				'priorities' => __( 'Priorities', 'support-ticket-system-for-woocommerce' ),
				'subjects'   => __( 'Subjects', 'support-ticket-system-for-woocommerce' ),
			);
			if ( 'tools' !== $what && ! isset( $fields[ $what ] ) ) {
				$what = 'priorities';
			}
			if ( 'tools' === $what ) {
				/**
				 * Content of the Tools screen (import and export).
				 */
				do_action( 'wpfactory_wc_sts_render_tab_tools' );
				return;
			}
			WPFactory_WC_STS_UI::get_instance()->render_terms( $what, $fields );
		}

		/**
		 * Settings sections added by the Pro edition.
		 *
		 * @return array id => { label, intro, fields }
		 */
		protected function get_extra_sections() {
			/**
			 * Extra settings tabs (Pro). Each: label, intro (optional), fields (same format as the core fields), and
			 * optionally `render` (callback drawing its own cards with the public building blocks of this class).
			 *
			 * @param array $sections id => section.
			 */
			return (array) apply_filters( 'wpfactory_wc_sts_settings_sections', array() );
		}

		/**
		 * General settings.
		 *
		 * @return array
		 */
		protected function get_general_fields() {
			$fields = array(
				'renameAccountTabLink'  => array(
					'type'        => 'text',
					'label'       => __( 'My Account menu label', 'support-ticket-system-for-woocommerce' ),
					'placeholder' => __( 'Tickets', 'support-ticket-system-for-woocommerce' ),
				),
				'renameOrderButtonLink' => array(
					'type'        => 'text',
					'label'       => __( 'Order button label', 'support-ticket-system-for-woocommerce' ),
					'placeholder' => __( 'Get help', 'support-ticket-system-for-woocommerce' ),
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
					'description' => __( 'Delete all tickets, replies, saved replies and these settings when the plugin is deleted. Off by default. Data is kept while the free or Pro edition is still installed, because both use the same tickets.', 'support-ticket-system-for-woocommerce' ),
				),
			);
			/**
			 * General settings (the knowledge base and live chat add theirs).
			 *
			 * @param array $fields Fields.
			 */
			return (array) apply_filters( 'wpfactory_wc_sts_settings_general', $fields );
		}

		/**
		 * Ticket form settings.
		 *
		 * @return array
		 */
		protected function get_form_fields() {
			/**
			 * Ticket form settings (spam protection adds its own).
			 *
			 * @param array $fields Fields.
			 */
			$topics = array( '' => __( 'No topic', 'support-ticket-system-for-woocommerce' ) );
			foreach ( WPFactory_WC_STS_UI::ordered_terms( 'stsw_tickets_subject' ) as $term ) {
				$topics[ (string) $term->term_id ] = $term->name;
			}
			$by_reason = array();
			foreach ( WPFactory_WC_STS_Inc::get_main_instance()->get_reasons() as $slug => $label ) {
				$by_reason[ '@wpfactory_wc_sts_reason_subject_' . $slug ] = array(
					'type'    => 'select',
					/* translators: %s: reason. */
					'label'   => sprintf( __( 'Topic for "%s"', 'support-ticket-system-for-woocommerce' ), $label ),
					'options' => $topics,
					'card'    => 'reasons',
				);
			}
			return (array) apply_filters(
				'wpfactory_wc_sts_settings_form',
				$by_reason + array(
					'@wpfactory_wc_sts_guest_tickets' => array(
						'type'        => 'checkbox',
						'label'       => __( 'Guest tickets', 'support-ticket-system-for-woocommerce' ),
						'description' => __( 'Customers without an account can ask for help with an order by entering the order number and billing email (on the My Account login screen and on pages with the shortcode). They follow their request through a private link sent by email.', 'support-ticket-system-for-woocommerce' ),
					),
					'@wpfactory_wc_sts_guest_otp'     => array(
						'type'        => 'checkbox',
						'default'     => '1',
						'label'       => __( 'Guests can see all their tickets', 'support-ticket-system-for-woocommerce' ),
						'description' => __( 'With guest tickets on, a guest can ask for a one-time code by email and then see every ticket of that email address.', 'support-ticket-system-for-woocommerce' ),
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
				)
			);
		}

		/**
		 * Assignment settings.
		 *
		 * @return array
		 */
		protected function get_assignment_fields() {
			return array(
				'assignToRole'                   => array(
					'type'        => 'role',
					'label'       => __( 'Role that can be assigned tickets', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Users with this role can see and answer tickets in wp-admin and, for tickets assigned to them, in My Account. Shop managers and administrators always can.', 'support-ticket-system-for-woocommerce' ),
				),
				'assignToUser'                   => array(
					'type'        => 'user',
					'label'       => __( 'Default assignee', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Handles tickets that are not assigned to anyone. Subjects can assign their own person (Helpdesk > Subjects).', 'support-ticket-system-for-woocommerce' ),
				),
				'mailToAssignee'                 => array(
					'type'  => 'checkbox',
					'label' => __( 'Email the assignee about new tickets and replies', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_confirm_send' => array(
					'type'        => 'checkbox',
					'label'       => __( 'Ask before sending a reply or note', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'The inbox asks for a confirmation that names who will see the message, so a private note is never sent to a customer by mistake.', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_admin_bar'    => array(
					'type'        => 'checkbox',
					'default'     => '1',
					'label'       => __( 'Show tickets waiting for a reply in the admin bar', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'A count in the top bar for team members, linked to the inbox.', 'support-ticket-system-for-woocommerce' ),
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
				'mailToADmin'                           => array(
					'type'  => 'checkbox',
					'label' => __( 'Email the store about new tickets and replies', 'support-ticket-system-for-woocommerce' ),
				),
				'AdminEmailAddress'                     => array(
					'type'        => 'email',
					'label'       => __( 'Store email address', 'support-ticket-system-for-woocommerce' ),
					'placeholder' => get_bloginfo( 'admin_email' ),
				),
				'mailToCustomer'                        => array(
					'type'  => 'checkbox',
					'label' => __( 'Email the customer (confirmations and store replies)', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_mail_reply_received' => array(
					'type'        => 'checkbox',
					'label'       => __( 'Email customers "We received your reply" when they reply', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Off: customer replies only notify your team.', 'support-ticket-system-for-woocommerce' ),
				),
				'mailIt_subjectToCust'                  => array(
					'type'  => 'text',
					'label' => __( 'Customer email subject', 'support-ticket-system-for-woocommerce' ),
				),
				'mailIt_contentToCust'                  => array(
					'type'  => 'editor',
					'label' => __( 'Customer email text', 'support-ticket-system-for-woocommerce' ),
				),
				'mailIt_subjectToAs'                    => array(
					'type'        => 'text',
					'label'       => __( 'Staff email subject', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Used for the store and assignee emails. Empty: a subject with the ticket number and title.', 'support-ticket-system-for-woocommerce' ),
				),
				'mailIt_contentToAs'                    => array(
					'type'  => 'editor',
					'label' => __( 'Staff email text', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_mail_closed'         => array(
					'type'        => 'checkbox',
					'label'       => __( 'Email the customer when the team closes a ticket', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Also when a ticket closes automatically. Not sent when customers close their own ticket.', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_mail_closed_subject' => array(
					'type'        => 'text',
					'label'       => __( 'Closed email subject', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Empty: a subject with the ticket number and title.', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_mail_closed_body'    => array(
					'type'  => 'editor',
					'label' => __( 'Closed email text', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_mail_status'         => array(
					'type'        => 'checkbox',
					'label'       => __( 'Email the customer when the team changes the status', 'support-ticket-system-for-woocommerce' ),
					'description' => __( 'Not sent when the change comes with a reply: the reply email already tells them.', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_mail_status_subject' => array(
					'type'  => 'text',
					'label' => __( 'Status email subject', 'support-ticket-system-for-woocommerce' ),
				),
				'@wpfactory_wc_sts_mail_status_body'    => array(
					'type'  => 'editor',
					'label' => __( 'Status email text', 'support-ticket-system-for-woocommerce' ),
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
		public function option_name( $key ) {
			return 0 === strpos( $key, '@' ) ? substr( $key, 1 ) : $this->plugin . $key;
		}

		/**
		 * Saves the settings: every section listed in `stsw_section` (the sections of the page that was posted, comma
		 * separated). An empty list saves every section (older forms and scripts).
		 *
		 * @version 2.3.0
		 */
		public function admin_process_settings() {
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				return;
			}
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				WPFactory_WC_STS_UI::notice( __( 'You are not allowed to change these settings.', 'support-ticket-system-for-woocommerce' ), true );
				return;
			}
			check_admin_referer( $this->plugin );

			$old_role = (string) get_option( $this->plugin . 'assignToRole' );
			$by_tab   = array();
			foreach ( $this->sections() as $id => $section ) {
				$by_tab[ $id ] = (array) $section['fields'];
			}
			// Only the sections of the page that was posted are saved: a section with no fields posted is never cleared.
			$posted = array_filter( array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_POST['stsw_section'] ?? '' ) ) ) ) );
			$tabs   = array_values( array_intersect( array_unique( $posted ), array_keys( $by_tab ) ) );
			$tabs   = $tabs ? $tabs : array( '' );

			/**
			 * Before the settings are saved (the nonce is checked). Choices drawn as other controls are turned into the
			 * posted values of their fields here (Settings > AI: the connection choice).
			 *
			 * @param string[] $tabs Sections saved ('' : all sections).
			 */
			do_action( 'wpfactory_wc_sts_settings_before_save', $tabs );

			foreach ( $tabs as $tab ) {
				$fields = '' !== $tab ? $by_tab[ $tab ] : array_merge( ...array_values( $by_tab ) );
				foreach ( $fields as $key => $field ) {
					$this->save_field( $key, $field );
				}
			}

			// Ticket access moves with the assignment role. Administrators always keep it; missing roles are skipped.
			$new_role = (string) get_option( $this->plugin . 'assignToRole' );
			if ( ! array_intersect( $tabs, array( '', 'assignment' ) ) ) {
				$new_role = $old_role;
			}
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
			if ( array_intersect( $tabs, array( '', 'form' ) ) && isset( $_POST['wpfactory_wc_sts_custom_fields'] ) && is_array( $_POST['wpfactory_wc_sts_custom_fields'] ) ) {
				update_option( WPFactory_WC_STS_Fields::OPTION, WPFactory_WC_STS_Fields::get_instance()->sanitize_fields( $_POST['wpfactory_wc_sts_custom_fields'] ), false ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_fields().
			}

			foreach ( $tabs as $tab ) {
				/**
				 * Settings were saved (the nonce is checked), once per section saved. Pro saves its lists here
				 * (mailboxes, rules, hours, scopes).
				 *
				 * @param string $tab Section saved ('' : all sections).
				 */
				do_action( 'wpfactory_wc_sts_settings_saved', $tab );
			}

			$this->all_sections = null; // Draw the page with the values just saved.
			WPFactory_WC_STS_UI::notice( __( 'Settings saved.', 'support-ticket-system-for-woocommerce' ) );
		}

		/**
		 * Saves one posted field.
		 *
		 * @param string $key   Field key.
		 * @param array  $field Field.
		 */
		protected function save_field( $key, $field ) {
			if ( 'html' === $field['type'] ) {
				return;
			}
			$name = $this->option_name( $key );
			$raw  = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in admin_process_settings(); sanitized per type below.
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
				case 'roles':
					$value = array_values( array_intersect( array_map( 'sanitize_key', (array) $raw ), array_keys( wp_roles()->get_names() ) ) );
					break;
				case 'select':
					$value = isset( $field['options'][ (string) $raw ] ) ? (string) $raw : (string) array_key_first( $field['options'] );
					break;
				case 'password':
					if ( '' === trim( (string) $raw ) ) {
						return; // Keep the saved secret.
					}
					$value = trim( sanitize_text_field( (string) $raw ) );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( (string) $raw );
					break;
				default:
					$value = sanitize_text_field( (string) $raw );
			}
			$autoload = in_array( $field['type'], array( 'editor', 'password', 'textarea' ), true ) ? false : null;
			update_option( $name, $value, $autoload );
		}
	}

endif;

return new WPFactory_WC_STS_Init();
