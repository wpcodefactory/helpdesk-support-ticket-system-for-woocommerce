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
				echo '<div class="wpf-hd wpf-hd--bar">';
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
			wp_enqueue_style( 'wpfactory-wc-sts-backend', plugins_url( 'assets/css/backend' . $min . '.css', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION );
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
			echo '<div class="wrap wpf-hd ' . esc_attr( $this->plugin ) . '">';
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
		 * Settings sub-navigation groups.
		 *
		 * @return array group => label
		 */
		protected function setting_groups() {
			return array(
				'basics'    => __( 'Basics', 'support-ticket-system-for-woocommerce' ),
				'workflows' => __( 'Orders and channels', 'support-ticket-system-for-woocommerce' ),
				'advanced'  => __( 'Advanced', 'support-ticket-system-for-woocommerce' ),
				'rules'     => __( 'Automation', 'support-ticket-system-for-woocommerce' ),
			);
		}

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
				echo '<div class="hd-page">';
				WPFactory_WC_STS_UI::notice( __( 'You are not allowed to change these settings.', 'support-ticket-system-for-woocommerce' ), true );
				echo '</div>';
				return;
			}
			$this->render_settings_form( 'settings' );
		}

		/**
		 * The settings form: Settings (every section but automation) or the Automation tab.
		 *
		 * @param string $scope `settings` or `automation`.
		 */
		protected function render_settings_form( $scope ) {
			$sections = $this->settings_nav_sections( $scope );
			$moved    = array();
			if ( 'settings' === $scope ) {
				foreach ( array_keys( $this->settings_nav_sections( 'automation' ) ) as $id ) {
					$moved[ $id ] = WPFactory_WC_STS_UI::url( 'automation' ) . '#' . $id;
				}
			}
			?>
			<div class="hd-page hd-page--settings">
				<?php $this->admin_process_settings(); ?>
				<form method="post" id="<?php echo esc_attr( $this->plugin ); ?>Form" class="hd-settings" data-hd-settings<?php echo $moved ? ' data-hd-moved="' . esc_attr( wp_json_encode( $moved ) ) . '"' : ''; ?>>
					<?php $this->render_subnav( $sections, '' ); ?>
					<div class="hd-settings__panels">
						<?php
						foreach ( $sections as $id => $section ) :
							if ( isset( $section['url'] ) ) {
								continue;
							}
							?>
							<section id="<?php echo esc_attr( $id ); ?>" class="hd-panel" aria-labelledby="hd-panel-h-<?php echo esc_attr( $id ); ?>" tabindex="-1">
								<h2 class="hd-h1" id="hd-panel-h-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $section['label'] ); ?></h2>
								<?php if ( '' !== $section['intro'] ) : ?>
									<p class="hd-muted"><?php echo esc_html( $section['intro'] ); ?></p>
								<?php endif; ?>
								<?php $this->render_section( $id ); ?>
							</section>
						<?php endforeach; ?>
					</div>
					<?php wp_nonce_field( $this->plugin ); ?>
					<?php // The settings script sets the section shown, so a save only touches that section. ?>
					<input type="hidden" name="stsw_section" value="" data-hd-section />
					<div class="hd-savebar" id="save_changes">
						<span class="hd-savebar__state" data-hd-state aria-live="polite"><?php esc_html_e( 'All changes saved', 'support-ticket-system-for-woocommerce' ); ?></span>
						<button type="submit" name="submit" class="hd-btn hd-btn--primary"><?php esc_html_e( 'Save changes', 'support-ticket-system-for-woocommerce' ); ?></button>
					</div>
				</form>
			</div>
			<?php
		}

		/**
		 * Sections of the Settings navigation: setting panels, plus "Ticket fields" and "Tools", which are screens of
		 * their own (entries with a `url`).
		 *
		 * @param string $scope `settings` (default) or `automation`.
		 *
		 * @return array id => `label`, `group`, `intro`, optional `url`.
		 */
		protected function settings_nav_sections( $scope = 'settings' ) {
			if ( 'automation' === $scope ) {
				$out = array();
				foreach ( $this->get_extra_sections() as $id => $section ) {
					if ( in_array( $id, self::automation_sections(), true ) ) {
						$out[ $id ] = array(
							'label' => $section['label'],
							'group' => 'rules',
							'intro' => $section['intro'] ?? '',
						);
					}
				}
				return $out;
			}
			$group_of = array(
				'pro_ratings'    => 'basics',
				'pro_automation' => 'workflows',
				'pro_orders'     => 'workflows',
				'pro_piping'     => 'workflows',
				'pro_ai'         => 'advanced',
				'pro_api'        => 'advanced',
			);
			$sections = array(
				'general'       => array(
					'label' => __( 'General', 'support-ticket-system-for-woocommerce' ),
					'group' => 'basics',
					'intro' => __( 'How customers reach support and what they see after they write.', 'support-ticket-system-for-woocommerce' ),
				),
				'form'          => array(
					'label' => __( 'Ticket form', 'support-ticket-system-for-woocommerce' ),
					'group' => 'basics',
					'intro' => __( 'Who can open tickets, file uploads and extra questions on the form.', 'support-ticket-system-for-woocommerce' ),
				),
				'assignment'    => array(
					'label' => __( 'Assignment', 'support-ticket-system-for-woocommerce' ),
					'group' => 'basics',
					'intro' => __( 'Who answers tickets and who gets new ones.', 'support-ticket-system-for-woocommerce' ),
				),
				'notifications' => array(
					'label' => __( 'Emails', 'support-ticket-system-for-woocommerce' ),
					'group' => 'basics',
					'intro' => __( 'Emails to your team and to customers.', 'support-ticket-system-for-woocommerce' ),
				),
			);
			foreach ( $this->get_extra_sections() as $id => $section ) {
				if ( in_array( $id, self::automation_sections(), true ) ) {
					continue; // In the Automation tab.
				}
				$sections[ $id ] = array(
					'label' => $section['label'],
					'group' => $section['group'] ?? ( $group_of[ $id ] ?? 'workflows' ),
					'intro' => $section['intro'] ?? '',
				);
			}
			$sections['fields'] = array(
				'label' => __( 'Ticket fields', 'support-ticket-system-for-woocommerce' ),
				'group' => 'basics',
				'intro' => '',
				'url'   => WPFactory_WC_STS_UI::term_url( 'priorities' ),
			);
			if ( array_key_exists( 'tools', (array) apply_filters( 'wpfactory_wc_sts_header_tabs', array() ) ) ) {
				$sections['tools'] = array(
					'label' => __( 'Tools', 'support-ticket-system-for-woocommerce' ),
					'group' => 'advanced',
					'intro' => '',
					'url'   => WPFactory_WC_STS_UI::url( 'tools' ),
				);
			}
			return $sections;
		}

		/**
		 * Settings navigation.
		 *
		 * @param array  $sections Sections.
		 * @param string $current  Screen shown ('' : the settings panels, switched by the script).
		 */
		protected function render_subnav( $sections, $current ) {
			$manager = current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
			?>
			<nav class="hd-subnav" aria-label="<?php echo esc_attr( 'automation' === $this->active_tab ? __( 'Automation sections', 'support-ticket-system-for-woocommerce' ) : __( 'Settings sections', 'support-ticket-system-for-woocommerce' ) ); ?>">
				<?php
				foreach ( $this->setting_groups() as $group => $group_label ) :
					$in_group = array_filter(
						$sections,
						function ( $s, $id ) use ( $group, $manager ) {
							return $s['group'] === $group && ( $manager || 'fields' === $id );
						},
						ARRAY_FILTER_USE_BOTH
					);
					if ( ! $in_group ) {
						continue;
					}
					?>
					<p class="hd-subnav__group" id="hd-group-<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $group_label ); ?></p>
					<ul aria-labelledby="hd-group-<?php echo esc_attr( $group ); ?>">
						<?php foreach ( $in_group as $id => $section ) : ?>
							<li>
								<?php if ( isset( $section['url'] ) ) : ?>
									<a href="<?php echo esc_url( $section['url'] ); ?>"<?php echo $current === $id ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $section['label'] ); ?></a>
								<?php elseif ( '' === $current ) : ?>
									<a href="#<?php echo esc_attr( $id ); ?>" data-hd-panel="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $section['label'] ); ?></a>
								<?php else : ?>
									<a href="<?php echo esc_url( WPFactory_WC_STS_UI::url( 'settings' ) . '#' . $id ); ?>"><?php echo esc_html( $section['label'] ); ?></a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
				<?php if ( $manager && 'automation' !== $this->active_tab ) : ?>
					<a class="hd-subnav__wizard" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '-setup' ) ); ?>"><?php esc_html_e( 'Run setup wizard again', 'support-ticket-system-for-woocommerce' ); ?></a>
				<?php endif; ?>
			</nav>
			<?php
		}

		/**
		 * A screen inside Settings: Ticket fields (priorities, subjects, statuses) or Tools (import and export).
		 *
		 * @param string $what `priorities`, `subjects`, `statuses` or `tools`.
		 */
		protected function render_settings_page( $what ) {
			$fields = array(
				'priorities' => __( 'Priorities', 'support-ticket-system-for-woocommerce' ),
				'subjects'   => __( 'Subjects', 'support-ticket-system-for-woocommerce' ),
				'statuses'   => __( 'Statuses', 'support-ticket-system-for-woocommerce' ),
			);
			if ( 'tools' !== $what && ! isset( $fields[ $what ] ) ) {
				$what = 'priorities';
			}
			echo '<div class="hd-page hd-page--settings"><div class="hd-settings">';
			$this->render_subnav( $this->settings_nav_sections(), 'tools' === $what ? 'tools' : 'fields' );
			echo '<div class="hd-settings__panels hd-settings__screen">';
			if ( 'tools' === $what ) {
				/**
				 * Content of the Tools screen (import and export).
				 */
				do_action( 'wpfactory_wc_sts_render_tab_tools' );
			} else {
				echo '<nav class="hd-fieldtabs" aria-label="' . esc_attr__( 'Ticket fields', 'support-ticket-system-for-woocommerce' ) . '">';
				foreach ( $fields as $key => $label ) {
					echo '<a class="hd-fieldtab" href="' . esc_url( WPFactory_WC_STS_UI::term_url( $key ) ) . '"' . ( $key === $what ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
				}
				echo '</nav>';
				WPFactory_WC_STS_UI::get_instance()->render_terms( $what );
			}
			echo '</div></div></div>';
		}

		/**
		 * Cards of one settings section.
		 *
		 * @param string $id Section.
		 */
		protected function render_section( $id ) {
			switch ( $id ) {
				case 'general':
					$all    = $this->get_general_fields();
					$carded = $this->fields_by_card( $all );
					$fields = $carded[''] ?? array();
					$this->render_card( __( 'Customer access', 'support-ticket-system-for-woocommerce' ), array_intersect_key( $fields, array_flip( array( 'renameAccountTabLink', 'renameOrderButtonLink', 'hideClosed' ) ) ) );
					$this->render_card( __( 'Messages', 'support-ticket-system-for-woocommerce' ), array_intersect_key( $fields, array_flip( array( 'textforTicketSave', 'textforResponseSave' ) ) ) );
					$this->render_card( __( 'Ticket numbers', 'support-ticket-system-for-woocommerce' ), $carded['numbers'] ?? array() );
					$known = array( 'renameAccountTabLink', 'renameOrderButtonLink', 'hideClosed', 'textforTicketSave', 'textforResponseSave', '@wpfactory_wc_sts_delete_data_on_uninstall' );
					$this->render_card( __( 'Help center and live chat', 'support-ticket-system-for-woocommerce' ), array_diff_key( $fields, array_flip( $known ) ) );
					$this->render_card( __( 'Data', 'support-ticket-system-for-woocommerce' ), array_merge( array_intersect_key( $fields, array_flip( array( '@wpfactory_wc_sts_delete_data_on_uninstall' ) ) ), $carded['data'] ?? array() ) );
					break;
				case 'form':
					$carded = $this->fields_by_card( $this->get_form_fields() );
					$this->render_card( __( 'Form options', 'support-ticket-system-for-woocommerce' ), $carded[''] ?? array() );
					$this->render_card( __( 'Spam protection', 'support-ticket-system-for-woocommerce' ), $carded['spam'] ?? array() );
					$this->render_card( __( 'Reasons for order questions', 'support-ticket-system-for-woocommerce' ), $carded['reasons'] ?? array(), __( 'Customers asking about an order choose a reason; customers with a general question choose a topic. Each reason can file the ticket under one of your topics.', 'support-ticket-system-for-woocommerce' ) );
					echo '<div class="hd-card"><div class="hd-card__head"><h3 class="hd-h2">' . esc_html__( 'Extra fields', 'support-ticket-system-for-woocommerce' ) . '</h3></div><div class="hd-card__body">';
					WPFactory_WC_STS_Fields::get_instance()->render_settings();
					echo '</div></div>';
					break;
				case 'assignment':
					$this->render_card( __( 'Assignment', 'support-ticket-system-for-woocommerce' ), $this->get_assignment_fields() );
					break;
				case 'notifications':
					$fields = $this->get_notification_fields();
					$this->render_card( __( 'Alerts', 'support-ticket-system-for-woocommerce' ), array_intersect_key( $fields, array_flip( array( 'mailToADmin', 'AdminEmailAddress', 'mailToCustomer', '@wpfactory_wc_sts_mail_reply_received' ) ) ) );
					$codes = __( 'Placeholders:', 'support-ticket-system-for-woocommerce' ) . ' {ticket_number} {ticket_title} {ticket_status} {customer_first_name} {customer_last_name} {customer_email} {order_number} {store_name} {content} {responseContent} {ticket_url} {ticket_url_plain} ' . __( 'Add a fallback after a bar: {customer_first_name|there}. Older names such as {ticketId} and {ticketLink} still work.', 'support-ticket-system-for-woocommerce' );
					$this->render_card(
						__( 'Email templates', 'support-ticket-system-for-woocommerce' ),
						array_diff_key( $fields, array_flip( array( 'mailToADmin', 'AdminEmailAddress', 'mailToCustomer', '@wpfactory_wc_sts_mail_reply_received', '@wpfactory_wc_sts_mail_closed', '@wpfactory_wc_sts_mail_closed_subject', '@wpfactory_wc_sts_mail_closed_body', '@wpfactory_wc_sts_mail_status', '@wpfactory_wc_sts_mail_status_subject', '@wpfactory_wc_sts_mail_status_body' ) ) ),
						$codes
					);
					$this->render_card( __( 'Closed and status emails', 'support-ticket-system-for-woocommerce' ), array_intersect_key( $fields, array_flip( array( '@wpfactory_wc_sts_mail_closed', '@wpfactory_wc_sts_mail_closed_subject', '@wpfactory_wc_sts_mail_closed_body', '@wpfactory_wc_sts_mail_status', '@wpfactory_wc_sts_mail_status_subject', '@wpfactory_wc_sts_mail_status_body' ) ) ), $codes );
					echo '<div class="hd-card"><div class="hd-card__head"><h3 class="hd-h2">' . esc_html__( 'Email delivery', 'support-ticket-system-for-woocommerce' ) . '</h3></div><div class="hd-card__body hd-card__pad">';
					WPFactory_WC_STS_Emails::get_instance()->render_test_email_button();
					WPFactory_WC_STS_Emails::get_instance()->render_email_health();
					echo '</div></div>';
					break;
				default:
					$extra = $this->get_extra_sections();
					if ( isset( $extra[ $id ] ) ) {
						$this->render_card( $extra[ $id ]['card'] ?? $extra[ $id ]['label'], $extra[ $id ]['fields'] );
					}
			}
		}

		/**
		 * Fields grouped by their `card` key ('' for fields without one).
		 *
		 * @param array $fields Fields.
		 *
		 * @return array card => fields
		 */
		protected function fields_by_card( $fields ) {
			$cards = array();
			foreach ( $fields as $key => $field ) {
				$cards[ (string) ( $field['card'] ?? '' ) ][ $key ] = $field;
			}
			return $cards;
		}

		/**
		 * One settings card.
		 *
		 * @param string $title  Title.
		 * @param array  $fields Fields.
		 * @param string $note   Note under the title.
		 */
		protected function render_card( $title, $fields, $note = '' ) {
			if ( ! $fields ) {
				return;
			}
			echo '<div class="hd-card"><div class="hd-card__head"><h3 class="hd-h2">' . esc_html( $title ) . '</h3>' . ( $note ? '<p class="hd-help hd-mono">' . esc_html( $note ) . '</p>' : '' ) . '</div><div class="hd-card__body">';
			$this->render_fields( $fields );
			echo '</div></div>';
		}

		/**
		 * Settings sections added by the Pro edition.
		 *
		 * @return array id => { label, intro, fields }
		 */
		protected function get_extra_sections() {
			/**
			 * Extra settings tabs (Pro). Each: label, intro (optional), fields (same format as the core fields).
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
		protected function option_name( $key ) {
			return 0 === strpos( $key, '@' ) ? substr( $key, 1 ) : $this->plugin . $key;
		}

		/**
		 * Prints fields as card rows: label and help on the left, control on the right.
		 *
		 * @param array $fields Fields.
		 */
		protected function render_fields( $fields ) {
			foreach ( $fields as $key => $field ) {
				$name    = $this->option_name( $key );
				$id      = sanitize_key( $name );
				$value   = get_option( $name, $field['default'] ?? '' );
				$help_id = $id . '-help';
				$help    = ! empty( $field['description'] ) ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : '';
				$wide    = in_array( $field['type'], array( 'editor', 'html', 'textarea', 'roles' ), true );
				$group   = in_array( $field['type'], array( 'roles', 'html' ), true );
				echo '<div class="hd-row' . ( $wide ? ' hd-row--wide' : '' ) . '"><div class="hd-row__label">';
				if ( $group ) {
					echo '<span class="hd-row__title" id="' . esc_attr( $id ) . '-label">' . esc_html( $field['label'] ) . '</span>';
				} else {
					echo '<label class="hd-row__title" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
				}
				if ( ! empty( $field['description'] ) ) {
					echo '<span class="hd-help" id="' . esc_attr( $help_id ) . '">' . esc_html( $field['description'] ) . '</span>';
				}
				echo '</div><div class="hd-row__control">';
				switch ( $field['type'] ) {
					case 'checkbox':
						echo '<input type="checkbox" role="switch" class="hd-switch" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( '1', (string) $value, false ) . $help . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $help escaped above.
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
						echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $help . '><option value="">' . esc_html__( 'None', 'support-ticket-system-for-woocommerce' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $help escaped above.
						wp_dropdown_roles( (string) $value );
						echo '</select>';
						break;
					case 'user':
						WPFactory_WC_STS_Admin::get_instance()->render_user_select( $name, (int) $value, __( 'Nobody', 'support-ticket-system-for-woocommerce' ), $id );
						break;
					case 'roles':
						echo '<fieldset id="' . esc_attr( $id ) . '" class="hd-checks" aria-labelledby="' . esc_attr( $id ) . '-label">';
						foreach ( wp_roles()->get_names() as $role => $role_name ) {
							echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $role ) . '"' . checked( in_array( $role, (array) $value, true ), true, false ) . ' /> ' . esc_html( translate_user_role( $role_name ) ) . '</label>';
						}
						echo '</fieldset>';
						break;
					case 'select':
						echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $help . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $help escaped above.
						foreach ( $field['options'] as $option => $label ) {
							echo '<option value="' . esc_attr( $option ) . '"' . selected( (string) $value, (string) $option, false ) . '>' . esc_html( $label ) . '</option>';
						}
						echo '</select>';
						break;
					case 'password':
						echo '<input type="password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( '' !== (string) $value ? __( 'Saved. Leave empty to keep it.', 'support-ticket-system-for-woocommerce' ) : '' ) . '"' . $help . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $help escaped above.
						break;
					case 'textarea':
						echo '<textarea rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $help . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $help escaped above.
						break;
					case 'html':
						call_user_func( $field['render'] );
						break;
					default:
						echo '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . ( isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '' ) . ( 'number' === $field['type'] ? ' min="0" step="1"' : '' ) . $help . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $help escaped above.
				}
				echo '</div></div>';
			}
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
				WPFactory_WC_STS_UI::notice( __( 'You are not allowed to change these settings.', 'support-ticket-system-for-woocommerce' ), true );
				return;
			}
			check_admin_referer( $this->plugin );

			$old_role = (string) get_option( $this->plugin . 'assignToRole' );
			$by_tab   = array(
				'general'       => $this->get_general_fields(),
				'form'          => $this->get_form_fields(),
				'assignment'    => $this->get_assignment_fields(),
				'notifications' => $this->get_notification_fields(),
			);
			foreach ( $this->get_extra_sections() as $id => $section ) {
				$by_tab[ $id ] = $section['fields'];
			}
			// Only the section on screen is saved (a section with no fields posted can never clear another one).
			$tab    = sanitize_key( wp_unslash( $_POST['stsw_section'] ?? '' ) );
			$tab    = isset( $by_tab[ $tab ] ) ? $tab : '';
			$fields = '' !== $tab ? $by_tab[ $tab ] : array_merge( ...array_values( $by_tab ) );
			foreach ( $fields as $key => $field ) {
				if ( 'html' === $field['type'] ) {
					continue;
				}
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
					case 'roles':
						$value = array_values( array_intersect( array_map( 'sanitize_key', (array) $raw ), array_keys( wp_roles()->get_names() ) ) );
						break;
					case 'select':
						$value = isset( $field['options'][ (string) $raw ] ) ? (string) $raw : (string) array_key_first( $field['options'] );
						break;
					case 'password':
						if ( '' === trim( (string) $raw ) ) {
							continue 2; // Keep the saved secret.
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

			// Ticket access moves with the assignment role. Administrators always keep it; missing roles are skipped.
			$new_role = (string) get_option( $this->plugin . 'assignToRole' );
			if ( ! in_array( $tab, array( '', 'assignment' ), true ) ) {
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
			if ( in_array( $tab, array( '', 'form' ), true ) && isset( $_POST['wpfactory_wc_sts_custom_fields'] ) && is_array( $_POST['wpfactory_wc_sts_custom_fields'] ) ) {
				update_option( WPFactory_WC_STS_Fields::OPTION, WPFactory_WC_STS_Fields::get_instance()->sanitize_fields( $_POST['wpfactory_wc_sts_custom_fields'] ), false ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_fields().
			}

			/**
			 * Settings were saved (the nonce is checked). Pro saves its lists here (mailboxes, rules, hours, scopes).
			 *
			 * @param string $tab Section saved ('' : all sections, when scripts are off).
			 */
			do_action( 'wpfactory_wc_sts_settings_saved', $tab );

			WPFactory_WC_STS_UI::notice( __( 'Settings saved.', 'support-ticket-system-for-woocommerce' ) );
		}
	}

endif;

return new WPFactory_WC_STS_Init();
