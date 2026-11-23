<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: admin design (header, icons, Priorities, Subjects,
 * Saved replies, setup wizard).
 *
 * All admin markup sits inside `.wpf-hd` so nothing leaks into other wp-admin screens. Shared by both editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_UI' ) ) :

	/**
	 * WPFactory_WC_STS_UI class.
	 */
	class WPFactory_WC_STS_UI {

		/**
		 * Term meta: display order of priorities and subjects.
		 */
		const META_ORDER = '_stsw_order';

		/**
		 * Option: setup wizard finished or skipped (shared by both editions).
		 */
		const WIZARD_OPTION = 'wpfactory_wc_sts_setup_done';

		/**
		 * Option: the support page (a page with the [stsw_user_tickets] support form).
		 */
		const SUPPORT_PAGE = 'wpfactory_wc_sts_support_page';

		/**
		 * Nonce action.
		 */
		const NONCE = 'wpfactory_wc_sts_ui';

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_UI|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_UI
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			self::$instance = $this;
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 5 );
			add_action( 'admin_post_wpfactory_wc_sts_term', array( $this, 'handle_term' ) );
			add_action( 'admin_post_wpfactory_wc_sts_saved_reply', array( $this, 'handle_saved_reply' ) );
			add_action( 'admin_post_wpfactory_wc_sts_wizard', array( $this, 'handle_wizard' ) );
			add_action( 'wp_ajax_wpfactory_wc_sts_term_order', array( $this, 'ajax_term_order' ) );
			add_action( 'admin_menu', array( $this, 'register_wizard_page' ), 99 );
			add_action( 'admin_init', array( $this, 'maybe_render_wizard' ), 1 );
			add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ), 5 );
			add_action( 'admin_init', array( $this, 'redirect_old_tabs' ) );
			add_action( 'load-edit-tags.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-term.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-edit.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-post-new.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-post.php', array( $this, 'redirect_old_screens' ) );
			add_filter( 'admin_body_class', array( $this, 'body_class' ) );
			add_filter( 'wpfactory_wc_sts_settings_general', array( $this, 'support_page_field' ) );
			add_action( 'update_option_' . self::SUPPORT_PAGE, array( $this, 'on_support_page_change' ), 10, 2 );
			add_action( 'add_option_' . self::SUPPORT_PAGE, array( $this, 'on_support_page_add' ), 10, 2 );
		}

		/**
		 * Core instance.
		 *
		 * @return WPFactory_WC_STS_Inc
		 */
		protected function core() {
			return WPFactory_WC_STS_Inc::get_main_instance();
		}

		/**
		 * URL of a plugin screen.
		 *
		 * @param string $tab  Tab (empty: inbox).
		 * @param array  $args Extra args.
		 *
		 * @return string
		 */
		public static function url( $tab = '', $args = array() ) {
			$base = admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE );
			return add_query_arg( array_merge( $tab ? array( 'tab' => $tab ) : array(), $args ), $base );
		}

		/**
		 * Address of a header tab: the dashboard is the Helpdesk page itself, the inbox opens on Needs reply.
		 *
		 * @param string $tab Tab key ('' : the inbox).
		 *
		 * @return string
		 */
		public static function tab_url( $tab ) {
			if ( 'dashboard' === $tab ) {
				return self::url();
			}
			return '' === $tab ? self::url( '', array( 'view' => 'needs_reply' ) ) : self::url( $tab );
		}

		/**
		 * Address of a ticket field screen (Settings > Ticket fields).
		 *
		 * @param string $field `priorities`, `subjects` or `statuses`.
		 * @param array  $args  More query arguments.
		 *
		 * @return string
		 */
		public static function term_url( $field, $args = array() ) {
			return self::url( 'fields', array_merge( array( 'field' => $field ), $args ) );
		}

		/**
		 * Screens shown inside Settings (header tab "Settings" stays current there).
		 *
		 * @return array
		 */
		public static function settings_pages() {
			return array( 'settings', 'fields', 'tools', 'priorities', 'subjects', 'statuses' );
		}

		/**
		 * Main sections in menu order: Inbox, Reports, Saved replies, Knowledge base, Companies, other add-on tabs,
		 * Settings. Used by the plugin header and the WordPress admin menu.
		 *
		 * @return array key => label ('' is the inbox)
		 */
		public static function nav_tabs() {
			$tabs = array(
				'dashboard' => __( 'Dashboard', 'support-ticket-system-for-woocommerce' ),
				''          => __( 'Inbox', 'support-ticket-system-for-woocommerce' ),
				'saved'     => __( 'Saved replies', 'support-ticket-system-for-woocommerce' ),
			);
			/**
			 * Header tabs (the knowledge base, Reports and Companies add their own).
			 *
			 * @param array $tabs key => label.
			 */
			$tabs  = (array) apply_filters( 'wpfactory_wc_sts_header_tabs', $tabs );
			$order = array( 'dashboard', '', 'reports', 'saved', 'kb', 'companies', 'automation' );
			$out   = array();
			foreach ( $order as $key ) {
				if ( isset( $tabs[ $key ] ) ) {
					$out[ $key ] = $tabs[ $key ];
				}
			}
			foreach ( $tabs as $key => $label ) {
				if ( ! isset( $out[ $key ] ) && ! in_array( $key, self::settings_pages(), true ) ) {
					$out[ $key ] = $label;
				}
			}
			$out['settings'] = __( 'Settings', 'support-ticket-system-for-woocommerce' );
			return $out;
		}

		/**
		 * Old addresses of screens that moved into Settings open their new place.
		 */
		public function redirect_old_tabs() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
			$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
			$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) );
			if ( WPFACTORY_WC_STS_PAGE !== $page || ! in_array( $tab, array( 'priorities', 'subjects', 'statuses' ), true ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
				return;
			}
			$args = array();
			foreach ( array( 'edit', 'hd_msg' ) as $key ) {
				if ( isset( $_GET[ $key ] ) ) {
					$args[ $key ] = sanitize_key( wp_unslash( $_GET[ $key ] ) );
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( self::term_url( $tab, $args ) );
			exit;
		}

		/**
		 * Whether the current request is a plugin screen.
		 *
		 * @return bool
		 */
		public static function is_screen() {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 0 === strpos( $page, WPFACTORY_WC_STS_PAGE ) || 'wpfactory-wc-sts-pro-reports' === $page;
		}

		/**
		 * Admin design assets on plugin screens.
		 */
		public function enqueue() {
			if ( ! self::is_screen() ) {
				return;
			}
			wp_enqueue_style( 'wpfactory-wc-sts-admin-ui', plugins_url( 'assets/css/admin-ui.css', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION );
			wp_enqueue_script( 'wpfactory-wc-sts-admin-ui', plugins_url( 'assets/js/admin-ui.js', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION, true );
			wp_localize_script(
				'wpfactory-wc-sts-admin-ui',
				'WPFactory_WC_STS_UI',
				array(
					'ajaxurl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( self::NONCE ),
					'unsaved'      => __( 'Unsaved changes', 'support-ticket-system-for-woocommerce' ),
					'saved'        => __( 'All changes saved', 'support-ticket-system-for-woocommerce' ),
					'orderSaved'   => __( 'Order saved', 'support-ticket-system-for-woocommerce' ),
					'confirmTerm'  => __( 'Delete this item? Tickets keep their other details.', 'support-ticket-system-for-woocommerce' ),
					'confirmReply' => __( 'Delete this saved reply?', 'support-ticket-system-for-woocommerce' ),
					'yes'          => __( 'Continue', 'support-ticket-system-for-woocommerce' ),
					'no'           => __( 'Cancel', 'support-ticket-system-for-woocommerce' ),
					'ok'           => __( 'OK', 'support-ticket-system-for-woocommerce' ),
					'deleteYes'    => __( 'Delete', 'support-ticket-system-for-woocommerce' ),
					'moved'        => __( 'Moved to position %d', 'support-ticket-system-for-woocommerce' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- position number.
					'checking'     => __( 'Counting...', 'support-ticket-system-for-woocommerce' ),
					/* translators: 1: tickets found, 2: new, 3: already imported, 4: replies, 5: private notes, 6: files. */
					'dryResult'    => __( '%1$d tickets found: %2$d to import, %3$d already imported. %4$d replies, %5$d private notes, %6$d attached files (files are not copied).', 'support-ticket-system-for-woocommerce' ),
					/* translators: 1: tickets imported so far, 2: tickets left. */
					'importing'    => __( 'Imported %1$d so far, %2$d left...', 'support-ticket-system-for-woocommerce' ),
					/* translators: %d: tickets imported. */
					'imported'     => __( 'Done: %d tickets imported. They are in the inbox.', 'support-ticket-system-for-woocommerce' ),
					'importError'  => __( 'The import stopped. Run it again to continue where it stopped.', 'support-ticket-system-for-woocommerce' ),
				)
			);
		}

		/**
		 * Body class for scoping full-height layouts.
		 *
		 * @param string $classes Classes.
		 *
		 * @return string
		 */
		public function body_class( $classes ) {
			return self::is_screen() ? $classes . ' wpf-hd-screen' : $classes;
		}

		/**
		 * Old WordPress screens for priorities, subjects and saved replies open the plugin screens instead.
		 */
		public function redirect_old_screens() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
			global $pagenow;
			$taxonomy  = sanitize_key( wp_unslash( $_GET['taxonomy'] ?? '' ) );
			$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) );
			$post_id   = absint( $_GET['post'] ?? 0 );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$map = array(
				'stsw_tickets_priority' => 'priorities',
				'stsw_tickets_subject'  => 'subjects',
				'stsw_tickets_status'   => 'statuses',
			);
			if ( in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) && isset( $map[ $taxonomy ] ) ) {
				wp_safe_redirect( self::term_url( $map[ $taxonomy ] ) );
				exit;
			}
			$is_reply = 'stsw_canned_reply' === $post_type || ( 'post.php' === $pagenow && $post_id && 'stsw_canned_reply' === get_post_type( $post_id ) );
			if ( $is_reply && in_array( $pagenow, array( 'edit.php', 'post-new.php', 'post.php' ), true ) ) {
				wp_safe_redirect( self::url( 'saved', $post_id ? array( 'reply' => $post_id ) : array() ) );
				exit;
			}
		}

		/*
		|--------------------------------------------------------------------------
		| Building blocks
		|--------------------------------------------------------------------------
		*/

		/**
		 * Inline stroke icon.
		 *
		 * @param string $name Icon.
		 * @param int    $size Size in px.
		 *
		 * @return string SVG (decorative).
		 */
		public static function icon( $name, $size = 18 ) {
			$paths = array(
				'chat'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
				'inbox'    => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
				'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
				'userx'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 12.5-6.6"/><path d="m17 17 4 4M21 17l-4 4"/>',
				'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
				'check'    => '<path d="m5 12 5 5 9-10"/>',
				'checkc'   => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
				'layers'   => '<path d="m12 3 9 5-9 5-9-5 9-5z"/><path d="m3 13 9 5 9-5"/>',
				'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
				'plus'     => '<path d="M12 5v14M5 12h14"/>',
				'more'     => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
				'keyboard' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/>',
				'warn'     => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
				'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
				'lines'    => '<path d="M4 6h16M4 12h10M4 18h7"/>',
				'clip'     => '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.6 8.6a2 2 0 0 1-2.8-2.8L15.5 7"/>',
				'grip'     => '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>',
				'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
				'trash'    => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
				'back'     => '<path d="m15 18-6-6 6-6"/>',
				'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
				'book'     => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
				'store'    => '<path d="M3 9h18l-2-5H5z"/><path d="M5 9v11h14V9"/><path d="M9 20v-6h6v6"/>',
				'truck'    => '<path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
				'sparkle'  => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
				'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
				'zap'      => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
				'chart'    => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
				'users'    => '<circle cx="9" cy="8" r="4"/><path d="M1 21a8 8 0 0 1 16 0"/><path d="M17 4a4 4 0 0 1 0 8M23 21a8 8 0 0 0-5-7.4"/>',
				'code'     => '<path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>',
				'coin'     => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5a3 3 0 0 0-3-1.5c-1.7 0-3 .9-3 2s1.3 1.7 3 2 3 .9 3 2-1.3 2-3 2a3 3 0 0 1-3-1.5M12 6v2M12 16v2"/>',
				'star'     => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
			);
			$path  = $paths[ $name ] ?? $paths['chat'];
			return '<svg class="icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
		}

		/**
		 * Allowed SVG markup for wp_kses.
		 *
		 * @return array
		 */
		public static function svg_kses() {
			$common = array(
				'd'  => true,
				'cx' => true,
				'cy' => true,
				'r'  => true,
				'x'  => true,
				'y'  => true,
			);
			return array(
				'svg'    => array(
					'class'           => true,
					'width'           => true,
					'height'          => true,
					'viewbox'         => true,
					'fill'            => true,
					'stroke'          => true,
					'stroke-width'    => true,
					'stroke-linecap'  => true,
					'stroke-linejoin' => true,
					'aria-hidden'     => true,
					'focusable'       => true,
				),
				'path'   => $common,
				'circle' => $common,
				'rect'   => $common + array(
					'width'  => true,
					'height' => true,
					'rx'     => true,
				),
			);
		}

		/**
		 * Prints an icon.
		 *
		 * @param string $name Icon.
		 * @param int    $size Size.
		 */
		public static function e_icon( $name, $size = 18 ) {
			echo wp_kses( self::icon( $name, $size ), self::svg_kses() );
		}

		/**
		 * Initials of a name.
		 *
		 * @param string $name Name.
		 *
		 * @return string
		 */
		public static function initials( $name ) {
			$parts = preg_split( '/\s+/', trim( (string) $name ) );
			$out   = '';
			foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $part ) {
				$out .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $part, 0, 1 ) ) : strtoupper( substr( $part, 0, 1 ) );
			}
			return '' !== $out ? $out : '?';
		}

		/**
		 * Avatar with initials and a colour picked from the name.
		 *
		 * @param string $name Name.
		 * @param string $size `md`, `sm`, or `agent` (team member, dark).
		 *
		 * @return string HTML.
		 */
		public static function avatar( $name, $size = 'md' ) {
			$class = 'avatar' . ( 'sm' === $size ? ' sm' : '' ) . ( 'agent' === $size ? ' agent' : '' );
			return '<span class="' . esc_attr( $class ) . '" aria-hidden="true">' . esc_html( self::initials( $name ) ) . '</span>';
		}

		/**
		 * Priority chip: the merchant's colour, or the design colours for Low, Normal and High.
		 *
		 * @param WP_Term|null $term Priority.
		 *
		 * @return string HTML.
		 */
		public static function priority_chip( $term ) {
			if ( ! $term ) {
				return '';
			}
			$tones = array(
				'urgent' => 'bad',
				'high'   => 'bad',
				'normal' => 'neutral',
				'low'    => 'info',
			);
			return self::chip( $term->name, $tones[ $term->slug ] ?? 'neutral' );
		}

		/**
		 * Terms of a ticket taxonomy in the merchant's order.
		 *
		 * @param string $taxonomy Taxonomy.
		 *
		 * @return WP_Term[]
		 */
		public static function ordered_terms( $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $terms ) ) {
				return array();
			}
			$defaults = array(
				'high'        => 1,
				'normal'      => 2,
				'low'         => 3,
				'open'        => 1,
				'in-progress' => 2,
				'closed'      => 3,
			);
			usort(
				$terms,
				function ( $a, $b ) use ( $defaults ) {
					$oa = get_term_meta( $a->term_id, self::META_ORDER, true );
					$ob = get_term_meta( $b->term_id, self::META_ORDER, true );
					$oa = '' === $oa ? ( $defaults[ $a->slug ] ?? 100 ) : (int) $oa;
					$ob = '' === $ob ? ( $defaults[ $b->slug ] ?? 100 ) : (int) $ob;
					return $oa === $ob ? strcasecmp( $a->name, $b->name ) : $oa - $ob;
				}
			);
			return $terms;
		}

		/**
		 * Plugin header: logo, tabs, search and the header actions.
		 *
		 * @param string $current Current tab key.
		 */
		public static function header( $current ) {
			$tabs    = self::nav_tabs();
			$current = in_array( $current, self::settings_pages(), true ) ? 'settings' : $current;
			$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$needs   = class_exists( 'WPFactory_WC_STS_Inbox' ) ? (int) WPFactory_WC_STS_Inbox::get_instance()->count( 'needs_reply' ) : 0;
			?>
			<header class="topbar">
				<div class="left">
					<div class="brand">
						<span class="brand-mark" aria-hidden="true"><?php self::e_icon( 'chat', 18 ); ?></span>
						<span class="brand-name"><?php esc_html_e( 'Helpdesk', 'support-ticket-system-for-woocommerce' ); ?></span>
						<?php
						/**
						 * After the product name (Pro prints its edition chip).
						 */
						do_action( 'wpfactory_wc_sts_header_badge' );
						?>
					</div>
					<nav class="nav" aria-label="<?php esc_attr_e( 'Helpdesk sections', 'support-ticket-system-for-woocommerce' ); ?>">
						<?php foreach ( $tabs as $key => $label ) : ?>
							<a href="<?php echo esc_url( self::tab_url( $key ) ); ?>" data-hd-tab="<?php echo esc_attr( '' === $key ? 'inbox' : $key ); ?>"<?php echo $key === $current ? ' class="on" aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?>
							<?php
							if ( '' === $key && $needs > 0 ) :
								?>
								<span class="count" aria-hidden="true"><?php echo esc_html( number_format_i18n( $needs ) ); ?></span><span class="screen-reader-text">
								<?php
								/* translators: %s: number of tickets. */
								echo esc_html( sprintf( _n( '%s ticket needs a reply', '%s tickets need a reply', $needs, 'support-ticket-system-for-woocommerce' ), number_format_i18n( $needs ) ) );
								?>
							</span><?php endif; ?></a>
						<?php endforeach; ?>
					</nav>
				</div>
				<div class="top-right">
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" role="search" class="search">
						<input type="hidden" name="page" value="<?php echo esc_attr( WPFACTORY_WC_STS_PAGE ); ?>" />
						<input type="hidden" name="view" value="all" />
						<label class="screen-reader-text" for="hd-search"><?php esc_html_e( 'Search tickets', 'support-ticket-system-for-woocommerce' ); ?></label>
						<input type="search" class="input" id="hd-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email or #number', 'support-ticket-system-for-woocommerce' ); ?>" />
						<kbd aria-hidden="true">/</kbd>
					</form>
					<?php
					/**
					 * Header actions (the "AI: N credits" button).
					 */
					do_action( 'wpfactory_wc_sts_header_actions' );
					?>
				</div>
			</header>
			<?php
		}

		/**
		 * A toggle switch: a real checkbox (forms post as before) drawn as a switch with one state label, On or Off.
		 *
		 * @param string $name    Field name ('' : no name, for script-driven toggles).
		 * @param bool   $checked On.
		 * @param array  $attrs   More input attributes (id, value, aria-label, aria-describedby, data-*, disabled).
		 *
		 * @return string HTML.
		 */
		public static function toggle( $name, $checked, $attrs = array() ) {
			$attrs = array_merge( array( 'value' => '1' ), $attrs );
			$html  = '<label class="toggle"><input type="checkbox" role="switch"' . ( '' !== $name ? ' name="' . esc_attr( $name ) . '"' : '' );
			foreach ( $attrs as $key => $value ) {
				if ( false === $value || null === $value ) {
					continue;
				}
				$html .= true === $value ? ' ' . esc_attr( $key ) : ' ' . esc_attr( $key ) . '="' . esc_attr( (string) $value ) . '"';
			}
			$html .= ( $checked ? ' checked' : '' ) . ' /><span class="switch" aria-hidden="true"></span><span class="state" aria-hidden="true" data-on="' . esc_attr__( 'On', 'support-ticket-system-for-woocommerce' ) . '" data-off="' . esc_attr__( 'Off', 'support-ticket-system-for-woocommerce' ) . '">' . ( $checked ? esc_html__( 'On', 'support-ticket-system-for-woocommerce' ) : esc_html__( 'Off', 'support-ticket-system-for-woocommerce' ) ) . '</span></label>';
			return $html;
		}

		/**
		 * Allowed markup of toggle() for wp_kses.
		 *
		 * @return array
		 */
		public static function toggle_kses() {
			return array(
				'label' => array( 'class' => true ),
				'input' => array(
					'type'             => true,
					'role'             => true,
					'name'             => true,
					'id'               => true,
					'value'            => true,
					'checked'          => true,
					'disabled'         => true,
					'class'            => true,
					'aria-label'       => true,
					'aria-describedby' => true,
					'aria-labelledby'  => true,
					'data-*'           => true,
					'form'             => true,
				),
				'span'  => array(
					'class'       => true,
					'aria-hidden' => true,
					'data-on'     => true,
					'data-off'    => true,
				),
			);
		}

		/**
		 * A status chip (never clickable).
		 *
		 * @param string $text Text.
		 * @param string $tone `neutral`, `ok`, `warn`, `bad` or `info`.
		 *
		 * @return string HTML.
		 */
		public static function chip( $text, $tone = 'neutral' ) {
			return '<span class="chip chip-' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
		}

		/**
		 * Page title block.
		 *
		 * @param string $title  Title.
		 * @param string $intro  Intro line.
		 * @param string $action  Action HTML (escaped by the caller): at most one primary button.
		 * @param string $eyebrow Small line above the title (the section name).
		 */
		public static function page_head( $title, $intro, $action = '', $eyebrow = '' ) {
			echo '<div class="page-head"><div>' . ( '' !== $eyebrow ? '<div class="eyebrow">' . esc_html( $eyebrow ) . '</div>' : '' ) . '<h1>' . esc_html( $title ) . '</h1>' . ( $intro ? '<p class="lead">' . esc_html( $intro ) . '</p>' : '' ) . '</div>' . ( '' !== $action ? '<div class="head-actions">' . $action . '</div>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- action escaped by callers.
		}

		/**
		 * Result notice.
		 *
		 * @param string $text  Text.
		 * @param bool   $error Error.
		 */
		public static function notice( $text, $error = false ) {
			echo '<div class="toast ' . ( $error ? 'is-error' : 'is-success' ) . '" role="' . ( $error ? 'alert' : 'status' ) . '">' . wp_kses( self::icon( $error ? 'warn' : 'check', 16 ), self::svg_kses() ) . ' <span>' . esc_html( $text ) . '</span></div>';
		}

		/*
		|--------------------------------------------------------------------------
		| Priorities, subjects, statuses
		|--------------------------------------------------------------------------
		*/

		/**
		 * Settings for each term screen.
		 *
		 * @param string $tab Tab.
		 *
		 * @return array|null
		 */
		protected function term_screen( $tab ) {
			$screens = array(
				'priorities' => array(
					'taxonomy' => 'stsw_tickets_priority',
					'title'    => __( 'Priorities', 'support-ticket-system-for-woocommerce' ),
					'intro'    => __( 'Use priorities to decide what your team answers first. Drag to set the order they appear in the ticket screen.', 'support-ticket-system-for-woocommerce' ),
					'add'      => __( 'Add priority', 'support-ticket-system-for-woocommerce' ),
					'edit'     => __( 'Edit priority', 'support-ticket-system-for-woocommerce' ),
					'example'  => __( 'e.g. Urgent', 'support-ticket-system-for-woocommerce' ),
					'colors'   => true,
				),
				'subjects'   => array(
					'taxonomy' => 'stsw_tickets_subject',
					'title'    => __( 'Subjects', 'support-ticket-system-for-woocommerce' ),
					'intro'    => __( 'Customers pick a subject when they open a ticket. A subject can send its tickets to one team member.', 'support-ticket-system-for-woocommerce' ),
					'add'      => __( 'Add subject', 'support-ticket-system-for-woocommerce' ),
					'edit'     => __( 'Edit subject', 'support-ticket-system-for-woocommerce' ),
					'example'  => __( 'e.g. Returns and exchanges', 'support-ticket-system-for-woocommerce' ),
					'assignee' => true,
				),
				'statuses'   => array(
					'taxonomy' => 'stsw_tickets_status',
					'title'    => __( 'Statuses', 'support-ticket-system-for-woocommerce' ),
					'intro'    => __( 'Open, In Progress and Closed drive the inbox; you can rename them or add your own.', 'support-ticket-system-for-woocommerce' ),
					'add'      => __( 'Add status', 'support-ticket-system-for-woocommerce' ),
					'edit'     => __( 'Edit status', 'support-ticket-system-for-woocommerce' ),
					'example'  => __( 'e.g. Waiting on supplier', 'support-ticket-system-for-woocommerce' ),
				),
			);
			return $screens[ $tab ] ?? null;
		}

		/**
		 * Priority, subject or status screen (Settings > Ticket fields > Manage): the ordered list, and the add or edit
		 * form beside it.
		 *
		 * @param string $tab    Tab.
		 * @param array  $fields The ticket field lists: key => name (tabs above the list).
		 */
		public function render_terms( $tab, $fields = array() ) {
			$screen = $this->term_screen( $tab );
			if ( ! $screen ) {
				return;
			}
			$edit_id       = absint( $_GET['edit'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$editing       = $edit_id ? get_term( $edit_id, $screen['taxonomy'] ) : null;
			$editing       = ( $editing && ! is_wp_error( $editing ) ) ? $editing : null;
			$message       = sanitize_key( wp_unslash( $_GET['hd_msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$swatch        = array(
				'#e8590c' => __( 'Orange', 'support-ticket-system-for-woocommerce' ),
				'#dc2626' => __( 'Red', 'support-ticket-system-for-woocommerce' ),
				'#ca8a04' => __( 'Yellow', 'support-ticket-system-for-woocommerce' ),
				'#16a34a' => __( 'Green', 'support-ticket-system-for-woocommerce' ),
				'#0891b2' => __( 'Teal', 'support-ticket-system-for-woocommerce' ),
				'#2563eb' => __( 'Blue', 'support-ticket-system-for-woocommerce' ),
				'#7c3aed' => __( 'Purple', 'support-ticket-system-for-woocommerce' ),
				'#64748b' => __( 'Gray', 'support-ticket-system-for-woocommerce' ),
			);
			$current_color = $editing ? (string) get_term_meta( $editing->term_id, WPFactory_WC_STS_Inc::META_COLOR, true ) : '#2563eb';
			$texts         = array(
				'saved'   => __( 'Saved.', 'support-ticket-system-for-woocommerce' ),
				'deleted' => __( 'Deleted.', 'support-ticket-system-for-woocommerce' ),
				'error'   => __( 'That did not work. Check the name and try again.', 'support-ticket-system-for-woocommerce' ),
			);
			$back          = current_user_can( 'manage_woocommerce' ) ? '<a class="btn btn-text" href="' . esc_url( self::url( 'settings', array( 'section' => 'fields' ) ) ) . '">' . esc_html__( 'All ticket fields', 'support-ticket-system-for-woocommerce' ) . '</a>' : ''; // phpcs:ignore WordPress.WP.Capabilities.Unknown
			echo '<div class="page narrow" data-hd-terms="' . esc_attr( $tab ) . '">';
			if ( isset( $texts[ $message ] ) ) {
				self::notice( $texts[ $message ], 'error' === $message );
			}
			self::page_head( $screen['title'], $screen['intro'], $back, __( 'Settings', 'support-ticket-system-for-woocommerce' ) );
			if ( $fields ) {
				echo '<nav class="tabs hd-fieldtabs" aria-label="' . esc_attr__( 'Ticket fields', 'support-ticket-system-for-woocommerce' ) . '">';
				foreach ( $fields as $key => $label ) {
					echo '<a class="hd-fieldtab" href="' . esc_url( self::term_url( $key ) ) . '"' . ( $key === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
				}
				echo '</nav>';
			}
			?>
			<div class="grid">
				<section class="card" aria-labelledby="hd-term-list-h">
					<div class="card-head">
						<h2 class="card-title" id="hd-term-list-h"><?php echo esc_html( $screen['title'] ); ?></h2>
						<span class="small muted"><?php esc_html_e( 'Drag to change the order', 'support-ticket-system-for-woocommerce' ); ?></span>
					</div>
					<ol class="term-list hd-sortable" data-taxonomy="<?php echo esc_attr( $screen['taxonomy'] ); ?>">
						<?php
						$terms = self::ordered_terms( $screen['taxonomy'] );
						if ( ! $terms ) {
							echo '<li class="empty">' . esc_html__( 'Nothing here yet.', 'support-ticket-system-for-woocommerce' ) . '</li>';
						}
						foreach ( $terms as $term ) :
							$filter = 'stsw_tickets_priority' === $screen['taxonomy'] ? array( 'prio' => $term->slug ) : ( 'stsw_tickets_subject' === $screen['taxonomy'] ? array( 'subject' => $term->slug ) : array( 'status' => $term->slug ) );
							?>
							<li class="row term-row hd-sortable__row<?php echo $editing && $editing->term_id === $term->term_id ? ' is-editing' : ''; ?>" data-id="<?php echo (int) $term->term_id; ?>" draggable="true">
								<button type="button" class="drag-handle hd-drag" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name. */ __( 'Move %s. Use the arrow keys.', 'support-ticket-system-for-woocommerce' ), $term->name ) ); ?>"><?php self::e_icon( 'grip', 16 ); ?></button>
								<div class="term-name">
									<?php
									if ( 'stsw_tickets_priority' === $screen['taxonomy'] ) {
										echo self::priority_chip( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in priority_chip().
									} else {
										echo '<p class="row-title">' . esc_html( $term->name ) . '</p>';
									}
									if ( $term->description ) {
										echo '<div class="row-meta">' . esc_html( $term->description ) . '</div>';
									}
									?>
								</div>
								<a class="small term-count" href="<?php echo esc_url( self::url( '', array_merge( array( 'view' => 'all' ), $filter ) ) ); ?>">
									<?php
									/* translators: %s: number of tickets. */
									echo esc_html( sprintf( _n( '%s ticket', '%s tickets', (int) $term->count, 'support-ticket-system-for-woocommerce' ), number_format_i18n( (int) $term->count ) ) );
									?>
								</a>
								<div class="row-actions">
									<a class="btn btn-text btn-sm" href="<?php echo esc_url( self::term_url( $tab, array( 'edit' => $term->term_id ) ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name. */ __( 'Edit %s', 'support-ticket-system-for-woocommerce' ), $term->name ) ); ?>"><?php esc_html_e( 'Edit', 'support-ticket-system-for-woocommerce' ); ?></a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline-form" data-hd-confirm="term">
										<input type="hidden" name="action" value="wpfactory_wc_sts_term" />
										<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
										<input type="hidden" name="delete" value="<?php echo (int) $term->term_id; ?>" />
										<?php wp_nonce_field( self::NONCE ); ?>
										<button type="submit" class="btn btn-danger btn-sm" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name. */ __( 'Delete %s', 'support-ticket-system-for-woocommerce' ), $term->name ) ); ?>"><?php esc_html_e( 'Delete', 'support-ticket-system-for-woocommerce' ); ?></button>
									</form>
								</div>
							</li>
						<?php endforeach; ?>
					</ol>
					<p class="hd-live screen-reader-text" aria-live="polite"></p>
				</section>

				<section class="card" aria-labelledby="hd-term-form">
					<div class="card-head"><h2 id="hd-term-form" class="card-title"><?php echo esc_html( $editing ? $screen['edit'] : $screen['add'] ); ?></h2></div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="card-body term-form">
						<input type="hidden" name="action" value="wpfactory_wc_sts_term" />
						<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
						<input type="hidden" name="term_id" value="<?php echo $editing ? (int) $editing->term_id : 0; ?>" />
						<?php wp_nonce_field( self::NONCE ); ?>
						<div class="field">
							<label class="label" for="hd-term-name"><?php esc_html_e( 'Name', 'support-ticket-system-for-woocommerce' ); ?></label>
							<input type="text" class="input" id="hd-term-name" name="name" required value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" placeholder="<?php echo esc_attr( $screen['example'] ); ?>" />
						</div>
						<?php if ( ! empty( $screen['colors'] ) ) : ?>
							<fieldset class="field">
								<legend class="label"><?php esc_html_e( 'Color', 'support-ticket-system-for-woocommerce' ); ?></legend>
								<div class="swatches">
									<?php foreach ( $swatch as $hex => $label ) : ?>
										<label class="swatch" style="--swatch:<?php echo esc_attr( $hex ); ?>">
											<input type="radio" name="color" value="<?php echo esc_attr( $hex ); ?>" <?php checked( strtolower( $current_color ), $hex ); ?> />
											<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<span class="hint"><?php esc_html_e( 'Shown as a dot and chip on every ticket.', 'support-ticket-system-for-woocommerce' ); ?></span>
							</fieldset>
						<?php endif; ?>
						<?php if ( ! empty( $screen['assignee'] ) ) : ?>
							<div class="field">
								<label class="label" for="hd-term-assignee"><?php esc_html_e( 'Send tickets with this subject to', 'support-ticket-system-for-woocommerce' ); ?></label>
								<?php WPFactory_WC_STS_Admin::get_instance()->render_user_select( 'assignee', $editing ? (int) get_term_meta( $editing->term_id, WPFactory_WC_STS_Inc::META_SUBJECT_ASSIGNEE, true ) : 0, __( 'Nobody in particular', 'support-ticket-system-for-woocommerce' ), 'hd-term-assignee' ); ?>
							</div>
						<?php endif; ?>
						<div class="field">
							<label class="label" for="hd-term-desc"><?php esc_html_e( 'Description', 'support-ticket-system-for-woocommerce' ); ?> <span class="muted"><?php esc_html_e( '(optional)', 'support-ticket-system-for-woocommerce' ); ?></span></label>
							<textarea class="input" id="hd-term-desc" name="description" rows="3"><?php echo esc_textarea( $editing ? $editing->description : '' ); ?></textarea>
						</div>
						<details class="field term-advanced">
							<summary><?php esc_html_e( 'Advanced', 'support-ticket-system-for-woocommerce' ); ?></summary>
							<div class="field">
								<label class="label" for="hd-term-slug"><?php esc_html_e( 'Slug', 'support-ticket-system-for-woocommerce' ); ?></label>
								<input type="text" class="input" id="hd-term-slug" name="slug" value="<?php echo esc_attr( $editing ? $editing->slug : '' ); ?>" placeholder="<?php esc_attr_e( 'Created from the name', 'support-ticket-system-for-woocommerce' ); ?>" />
							</div>
						</details>
						<div class="form-actions">
							<?php if ( $editing ) : ?>
								<a class="btn btn-text" href="<?php echo esc_url( self::term_url( $tab ) ); ?>"><?php esc_html_e( 'Cancel', 'support-ticket-system-for-woocommerce' ); ?></a>
							<?php endif; ?>
							<button type="submit" class="btn btn-primary"><?php echo esc_html( $editing ? __( 'Save changes', 'support-ticket-system-for-woocommerce' ) : $screen['add'] ); ?></button>
						</div>
					</form>
				</section>
			</div>
			<?php
			echo '</div>';
		}

		/**
		 * Saves or deletes a term.
		 */
		public function handle_term() {
			check_admin_referer( self::NONCE );
			$tab    = sanitize_key( wp_unslash( $_POST['tab'] ?? '' ) );
			$screen = $this->term_screen( $tab );
			if ( ! $screen || ! $this->core()->can_manage_tickets() ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}
			$taxonomy = $screen['taxonomy'];
			$delete   = absint( $_POST['delete'] ?? 0 );
			if ( $delete ) {
				$term = get_term( $delete, $taxonomy );
				// The three built-in statuses drive the inbox and cannot be deleted.
				$ok = $term && ! is_wp_error( $term ) && ! ( 'stsw_tickets_status' === $taxonomy && in_array( $term->slug, array( 'open', 'in-progress', 'closed' ), true ) ) && ! is_wp_error( wp_delete_term( $delete, $taxonomy ) );
				wp_safe_redirect( self::term_url( $tab, array( 'hd_msg' => $ok ? 'deleted' : 'error' ) ) );
				exit;
			}
			$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
			$args = array(
				'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			);
			$slug = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
			if ( '' !== $slug ) {
				$args['slug'] = $slug;
			}
			$term_id = absint( $_POST['term_id'] ?? 0 );
			if ( '' === $name ) {
				wp_safe_redirect( self::term_url( $tab, array( 'hd_msg' => 'error' ) ) );
				exit;
			}
			if ( $term_id ) {
				$args['name'] = $name;
				$result       = wp_update_term( $term_id, $taxonomy, $args );
			} else {
				$result = wp_insert_term( $name, $taxonomy, $args );
			}
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( self::term_url( $tab, array( 'hd_msg' => 'error' ) ) );
				exit;
			}
			$term_id = (int) $result['term_id'];
			if ( ! empty( $screen['colors'] ) ) {
				$color = sanitize_hex_color( wp_unslash( $_POST['color'] ?? '' ) );
				update_term_meta( $term_id, WPFactory_WC_STS_Inc::META_COLOR, $color ? $color : '' );
			}
			if ( ! empty( $screen['assignee'] ) ) {
				$user = absint( $_POST['assignee'] ?? 0 );
				if ( $user && get_user_by( 'id', $user ) ) {
					update_term_meta( $term_id, WPFactory_WC_STS_Inc::META_SUBJECT_ASSIGNEE, $user );
				} else {
					delete_term_meta( $term_id, WPFactory_WC_STS_Inc::META_SUBJECT_ASSIGNEE );
				}
			}
			wp_safe_redirect( self::term_url( $tab, array( 'hd_msg' => 'saved' ) ) );
			exit;
		}

		/**
		 * Saves the order of terms (drag or arrow keys).
		 */
		public function ajax_term_order() {
			check_ajax_referer( self::NONCE, 'nonce' );
			$taxonomy = sanitize_key( wp_unslash( $_POST['taxonomy'] ?? '' ) );
			if ( ! in_array( $taxonomy, array( 'stsw_tickets_priority', 'stsw_tickets_subject', 'stsw_tickets_status' ), true ) || ! $this->core()->can_manage_tickets() ) {
				wp_send_json_error( null, 403 );
			}
			$ids = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) );
			foreach ( array_values( $ids ) as $position => $id ) {
				$term = get_term( $id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					update_term_meta( $id, self::META_ORDER, $position + 1 );
				}
			}
			wp_send_json_success();
		}

		/*
		|--------------------------------------------------------------------------
		| Saved replies
		|--------------------------------------------------------------------------
		*/

		/**
		 * Starter templates for the empty state.
		 *
		 * @return array title => text
		 */
		public static function starters() {
			return array(
				__( 'Order status update', 'support-ticket-system-for-woocommerce' )   => __( "Hi {customer_first_name},\n\nThanks for your patience. Your order #{order_number} is on its way and you will get the tracking details by email.\n\nThe {store_name} team", 'support-ticket-system-for-woocommerce' ),
				__( 'Return instructions', 'support-ticket-system-for-woocommerce' )   => __( "Hi {customer_first_name},\n\nWe are sorry the item did not work out. Please send it back in its original packaging and reply here with the tracking number. We will take it from there.\n\nThe {store_name} team", 'support-ticket-system-for-woocommerce' ),
				__( 'Thanks for your patience', 'support-ticket-system-for-woocommerce' ) => __( "Hi {customer_first_name},\n\nThank you for your patience while we look into ticket #{ticket_number}. We will update you here as soon as we know more.\n\nThe {store_name} team", 'support-ticket-system-for-woocommerce' ),
			);
		}

		/**
		 * What each saved reply placeholder becomes.
		 *
		 * @return array placeholder => help
		 */
		protected static function placeholder_help() {
			return array(
				'{customer_first_name}' => __( 'Customer first name. With a fallback: {customer_first_name|there}', 'support-ticket-system-for-woocommerce' ),
				'{order_number}'        => __( 'Order linked to the ticket', 'support-ticket-system-for-woocommerce' ),
				'{ticket_number}'       => __( 'Ticket number with prefix', 'support-ticket-system-for-woocommerce' ),
				'{store_name}'          => __( 'Your store name', 'support-ticket-system-for-woocommerce' ),
			);
		}

		/**
		 * Saved replies screen: the list (or starters when there are none), the editor and the placeholders.
		 */
		public function render_saved() {
			$replies = WPFactory_WC_STS_Canned::get_instance()->get_replies();
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
			$reply_id = absint( $_GET['reply'] ?? 0 );
			$starter  = sanitize_text_field( wp_unslash( $_GET['starter'] ?? '' ) );
			$new      = ! empty( $_GET['new'] ) || '' !== $starter;
			$search   = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
			$message  = sanitize_key( wp_unslash( $_GET['hd_msg'] ?? '' ) );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$current  = $reply_id ? get_post( $reply_id ) : null;
			$current  = ( $current && WPFactory_WC_STS_Canned::POST_TYPE === $current->post_type ) ? $current : null;
			$starters = self::starters();
			$title    = $current ? $current->post_title : ( $starter && isset( $starters[ $starter ] ) ? $starter : '' );
			$body     = $current ? wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br />' ), "\n", $current->post_content ) ) : ( $starter && isset( $starters[ $starter ] ) ? $starters[ $starter ] : '' );
			$tags     = array_keys( WPFactory_WC_STS_Canned::get_instance()->get_placeholders( 0 ) );
			$help     = self::placeholder_help();
			$action   = '<a class="btn btn-primary" href="' . esc_url( self::url( 'saved', array( 'new' => 1 ) ) ) . '">' . esc_html__( 'New saved reply', 'support-ticket-system-for-woocommerce' ) . '</a>';
			echo '<div class="page narrow" data-stsw-saved>';
			if ( 'saved' === $message ) {
				self::notice( __( 'Saved reply saved.', 'support-ticket-system-for-woocommerce' ) );
			} elseif ( 'deleted' === $message ) {
				self::notice( __( 'Saved reply deleted.', 'support-ticket-system-for-woocommerce' ) );
			}
			/* translators: %s: number of saved replies. */
			$heading = $replies ? sprintf( _n( '%s saved reply', '%s saved replies', count( $replies ), 'support-ticket-system-for-woocommerce' ), number_format_i18n( count( $replies ) ) ) : __( 'No saved replies yet', 'support-ticket-system-for-woocommerce' );
			self::page_head( $heading, __( 'Answers you send often. Insert them from any ticket with one click; placeholders fill in for each customer.', 'support-ticket-system-for-woocommerce' ), $action, __( 'Saved replies', 'support-ticket-system-for-woocommerce' ) );
			echo '<div class="grid"><div class="stack">';
			if ( $current || $new ) {
				?>
				<section class="card" aria-labelledby="hd-editor-h" data-stsw-saved-editor>
					<div class="card-head"><h2 id="hd-editor-h" class="card-title"><?php echo esc_html( $current ? __( 'Edit saved reply', 'support-ticket-system-for-woocommerce' ) : __( 'New saved reply', 'support-ticket-system-for-woocommerce' ) ); ?></h2></div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wpfactory_wc_sts_saved_reply" />
						<input type="hidden" name="reply_id" value="<?php echo $current ? (int) $current->ID : 0; ?>" />
						<?php wp_nonce_field( self::NONCE ); ?>
						<div class="card-body">
							<div class="field">
								<label class="label" for="hd-reply-title"><?php esc_html_e( 'Title', 'support-ticket-system-for-woocommerce' ); ?></label>
								<input type="text" class="input" id="hd-reply-title" name="title" required value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Return instructions', 'support-ticket-system-for-woocommerce' ); ?>" aria-describedby="hd-reply-title-help" />
								<p class="hint" id="hd-reply-title-help"><?php esc_html_e( 'Only your team sees the title. Use one you can find fast.', 'support-ticket-system-for-woocommerce' ); ?></p>
							</div>
							<div class="field">
								<label class="label" for="hd-reply-body"><?php esc_html_e( 'Message', 'support-ticket-system-for-woocommerce' ); ?></label>
								<textarea class="input" id="hd-reply-body" name="body" rows="9" required><?php echo esc_textarea( $body ); ?></textarea>
							</div>
							<div class="field">
								<span class="label" id="hd-placeholders"><?php esc_html_e( 'Insert customer details', 'support-ticket-system-for-woocommerce' ); ?></span>
								<div class="insert-tags" role="group" aria-labelledby="hd-placeholders">
									<?php foreach ( $tags as $tag ) : ?>
										<button type="button" class="btn btn-secondary btn-sm" data-hd-insert="<?php echo esc_attr( $tag ); ?>" data-hd-target="hd-reply-body"><span class="code"><?php echo esc_html( $tag ); ?></span></button>
									<?php endforeach; ?>
								</div>
								<p class="hint"><?php esc_html_e( 'Replaced with the ticket\'s details when you insert the reply.', 'support-ticket-system-for-woocommerce' ); ?></p>
							</div>
						</div>
						<div class="card-foot">
							<a class="btn btn-text" href="<?php echo esc_url( self::url( 'saved' ) ); ?>"><?php esc_html_e( 'Cancel', 'support-ticket-system-for-woocommerce' ); ?></a>
							<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save reply', 'support-ticket-system-for-woocommerce' ); ?></button>
						</div>
					</form>
				</section>
				<?php
			}
			echo '<section class="card" aria-label="' . esc_attr__( 'Saved replies', 'support-ticket-system-for-woocommerce' ) . '" data-stsw-saved-list>';
			if ( ! $replies ) {
				echo '<div class="empty"><p class="row-title">' . esc_html__( 'No saved replies yet', 'support-ticket-system-for-woocommerce' ) . '</p><p>' . esc_html__( 'Start with one of these common answers, or write your own.', 'support-ticket-system-for-woocommerce' ) . '</p><div class="saved-starters">';
				foreach ( array_keys( $starters ) as $name ) {
					echo '<a class="btn btn-secondary btn-sm" href="' . esc_url( self::url( 'saved', array( 'starter' => $name ) ) ) . '">' . esc_html( $name ) . '</a>';
				}
				echo '</div></div>';
			} else {
				?>
				<form method="get" class="list-tools" role="search" data-hd-filter="hd-saved-rows">
					<input type="hidden" name="page" value="<?php echo esc_attr( WPFACTORY_WC_STS_PAGE ); ?>" />
					<input type="hidden" name="tab" value="saved" />
					<input type="search" class="input" id="hd-saved-q" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search saved replies', 'support-ticket-system-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Search saved replies', 'support-ticket-system-for-woocommerce' ); ?>" />
				</form>
				<?php
				$shown = 0;
				$nonce = wp_create_nonce( self::NONCE );
				echo '<div id="hd-saved-rows">';
				foreach ( $replies as $reply ) {
					$text  = trim( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br />', '<br/>' ), "\n", (string) $reply->post_content ) ) );
					$match = '' === $search || false !== stripos( $reply->post_title . ' ' . $text, $search );
					$shown = $shown + ( $match ? 1 : 0 );
					$first = '';
					foreach ( (array) preg_split( '/\R+/', $text ) as $line ) {
						if ( '' !== trim( $line ) ) {
							$first = trim( $line );
							break;
						}
					}
					$name = get_the_title( $reply );
					echo '<div class="row" data-stsw-saved-row="' . (int) $reply->ID . '" data-hd-filter-text="' . esc_attr( strtolower( $reply->post_title . ' ' . $text ) ) . '"' . ( $match ? '' : ' hidden' ) . '><div><p class="row-title">' . esc_html( $name ) . '</p><div class="row-meta">' . esc_html( wp_trim_words( $first, 12 ) ) . '</div></div><div class="row-actions">';
					/* translators: %s: saved reply title. */
					echo '<a class="btn btn-text btn-sm" href="' . esc_url( self::url( 'saved', array( 'reply' => $reply->ID ) ) ) . '"' . ( $current && $current->ID === $reply->ID ? ' aria-current="true"' : '' ) . ' aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'support-ticket-system-for-woocommerce' ), $name ) ) . '">' . esc_html__( 'Edit', 'support-ticket-system-for-woocommerce' ) . '</a>';
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-hd-confirm="reply"><input type="hidden" name="action" value="wpfactory_wc_sts_saved_reply" /><input type="hidden" name="delete" value="' . (int) $reply->ID . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';
					/* translators: %s: saved reply title. */
					echo '<button type="submit" class="btn btn-danger btn-sm" aria-label="' . esc_attr( sprintf( __( 'Delete %s', 'support-ticket-system-for-woocommerce' ), $name ) ) . '">' . esc_html__( 'Delete', 'support-ticket-system-for-woocommerce' ) . '</button></form></div></div>';
				}
				echo '<div class="empty" data-hd-filter-none' . ( $shown ? ' hidden' : '' ) . '><p class="row-title">' . esc_html__( 'No saved replies match your search', 'support-ticket-system-for-woocommerce' ) . '</p><a class="btn btn-text btn-sm" href="' . esc_url( self::url( 'saved' ) ) . '">' . esc_html__( 'Show all', 'support-ticket-system-for-woocommerce' ) . '</a></div></div>';
			}
			echo '</section></div><div class="stack">';
			echo '<section class="card" aria-labelledby="hd-saved-tags"><div class="card-head"><h2 class="card-title" id="hd-saved-tags">' . esc_html__( 'Placeholders', 'support-ticket-system-for-woocommerce' ) . '</h2></div>';
			foreach ( $tags as $tag ) {
				echo '<div class="row"><div><p class="row-title small"><span class="code">' . esc_html( $tag ) . '</span></p>' . ( isset( $help[ $tag ] ) ? '<div class="row-meta">' . esc_html( $help[ $tag ] ) . '</div>' : '' ) . '</div></div>';
			}
			echo '</section></div></div></div>';
		}

		/**
		 * Saves or deletes a saved reply.
		 */
		public function handle_saved_reply() {
			check_admin_referer( self::NONCE );
			if ( ! $this->core()->can_manage_tickets() ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}
			$delete = absint( $_POST['delete'] ?? 0 );
			if ( $delete ) {
				if ( WPFactory_WC_STS_Canned::POST_TYPE === get_post_type( $delete ) && current_user_can( 'delete_post', $delete ) ) {
					wp_trash_post( $delete );
				}
				wp_safe_redirect( self::url( 'saved', array( 'hd_msg' => 'deleted' ) ) );
				exit;
			}
			$id   = absint( $_POST['reply_id'] ?? 0 );
			$data = array(
				'post_type'    => WPFactory_WC_STS_Canned::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
				'post_content' => sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ),
			);
			if ( $id && WPFactory_WC_STS_Canned::POST_TYPE === get_post_type( $id ) ) {
				$data['ID'] = $id;
				$id         = wp_update_post( $data );
			} else {
				$id = wp_insert_post( $data );
			}
			wp_safe_redirect(
				self::url(
					'saved',
					array(
						'reply'  => (int) $id,
						'hd_msg' => 'saved',
					)
				)
			);
			exit;
		}

		/*
		|--------------------------------------------------------------------------
		| Setup wizard
		|--------------------------------------------------------------------------
		*/

		/**
		 * After activation on a store that never used the plugin, open the wizard once.
		 */
		public function maybe_redirect_to_wizard() {
			if ( ! get_transient( 'wpfactory_wc_sts_activation_redirect' ) || wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			delete_transient( 'wpfactory_wc_sts_activation_redirect' );
			if ( get_option( self::WIZARD_OPTION ) || ! current_user_can( 'manage_woocommerce' ) || ! $this->core()->is_new_store() ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				return;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '-setup' ) );
			exit;
		}

		/**
		 * Hidden admin page of the wizard (WordPress checks it exists before the wizard prints itself).
		 */
		public function register_wizard_page() {
			add_submenu_page( '', __( 'Helpdesk setup', 'support-ticket-system-for-woocommerce' ), '', 'manage_woocommerce', WPFACTORY_WC_STS_PAGE . '-setup', '__return_null' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		}

		/**
		 * Wizard steps.
		 *
		 * @return array key => label
		 */
		protected function wizard_steps() {
			return array(
				'welcome'  => __( 'Welcome', 'support-ticket-system-for-woocommerce' ),
				'access'   => __( 'Customer access', 'support-ticket-system-for-woocommerce' ),
				'subjects' => __( 'Ticket subjects', 'support-ticket-system-for-woocommerce' ),
				'messages' => __( 'Messages', 'support-ticket-system-for-woocommerce' ),
				'done'     => __( 'Done', 'support-ticket-system-for-woocommerce' ),
			);
		}

		/**
		 * Pages a support page can be: published pages that are not the shop, cart, checkout, My Account or the help
		 * center.
		 *
		 * @return array id => title
		 */
		public function support_page_choices() {
			$skip = array_filter( array_map( 'absint', array( get_option( 'woocommerce_myaccount_page_id' ), get_option( 'woocommerce_cart_page_id' ), get_option( 'woocommerce_checkout_page_id' ), get_option( 'woocommerce_shop_page_id' ), class_exists( 'WPFactory_WC_STS_KB' ) ? WPFactory_WC_STS_KB::get_instance()->get_page_id() : 0 ) ) );
			$out  = array();
			foreach ( get_pages(
				array(
					'post_status' => 'publish',
					'sort_column' => 'post_title',
					'number'      => 200,
				)
			) as $page ) { // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts
				if ( ! in_array( (int) $page->ID, $skip, true ) ) {
					$out[ (int) $page->ID ] = '' !== $page->post_title ? $page->post_title : '#' . $page->ID;
				}
			}
			return $out;
		}

		/**
		 * Puts the support form on a page (once), or creates a "Support" page with it.
		 *
		 * @param int|string $page Page ID, or `new`.
		 *
		 * @return int Page ID (0: none).
		 */
		public function setup_support_page( $page ) {
			$block = "<!-- wp:shortcode -->\n[stsw_user_tickets]\n<!-- /wp:shortcode -->";
			if ( 'new' === $page ) {
				$id = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => __( 'Support', 'support-ticket-system-for-woocommerce' ),
						'post_content' => $block,
					)
				);
				return is_wp_error( $id ) ? 0 : (int) $id;
			}
			$id = absint( $page );
			if ( ! $id || 'page' !== get_post_type( $id ) ) {
				return 0;
			}
			$content = (string) get_post_field( 'post_content', $id, 'raw' );
			if ( ! has_shortcode( $content, 'stsw_user_tickets' ) ) {
				wp_update_post(
					array(
						'ID'           => $id,
						'post_content' => rtrim( $content ) . ( '' !== trim( $content ) ? "\n\n" : '' ) . $block,
					)
				);
			}
			return $id;
		}

		/**
		 * The support page chosen in Settings gets the support form.
		 *
		 * @param mixed $old   Old value.
		 * @param mixed $value New value.
		 */
		public function on_support_page_change( $old, $value ) {
			if ( absint( $value ) && absint( $value ) !== absint( $old ) ) {
				$this->setup_support_page( absint( $value ) );
			}
		}

		/**
		 * The support page chosen the first time.
		 *
		 * @param string $option Option.
		 * @param mixed  $value  Value.
		 */
		public function on_support_page_add( $option, $value ) {
			$this->on_support_page_change( 0, $value );
		}

		/**
		 * The support page, when it is published and has the support form.
		 *
		 * @return int
		 */
		public static function support_page_id() {
			$id = absint( get_option( self::SUPPORT_PAGE, 0 ) );
			return $id && 'publish' === get_post_status( $id ) && has_shortcode( (string) get_post_field( 'post_content', $id, 'raw' ), 'stsw_user_tickets' ) ? $id : 0;
		}

		/**
		 * Settings > General: the support page.
		 *
		 * @param array $fields Fields.
		 *
		 * @return array
		 */
		public function support_page_field( $fields ) {
			$fields[ '@' . self::SUPPORT_PAGE ] = array(
				'type'        => 'select',
				'label'       => __( 'Support page', 'support-ticket-system-for-woocommerce' ),
				'options'     => array( '' => __( 'None: customers use My Account', 'support-ticket-system-for-woocommerce' ) ) + $this->support_page_choices(),
				'description' => __( 'Optional page where customers open and follow tickets, besides My Account. The support form is added to the page you choose; the rest of the page stays as it is.', 'support-ticket-system-for-woocommerce' ),
			);
			return $fields;
		}

		/**
		 * Suggested subjects.
		 *
		 * @return string[]
		 */
		protected function suggested_subjects() {
			return array(
				__( 'Order issue', 'support-ticket-system-for-woocommerce' ),
				__( 'Returns and exchanges', 'support-ticket-system-for-woocommerce' ),
				__( 'Shipping and delivery', 'support-ticket-system-for-woocommerce' ),
				__( 'Billing and invoices', 'support-ticket-system-for-woocommerce' ),
				__( 'Product question', 'support-ticket-system-for-woocommerce' ),
				__( 'Account and login', 'support-ticket-system-for-woocommerce' ),
			);
		}

		/**
		 * Full-screen wizard (no WordPress menus), printed before the admin page loads.
		 */
		public function maybe_render_wizard() {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( WPFACTORY_WC_STS_PAGE . '-setup' !== $page || wp_doing_ajax() ) {
				return;
			}
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}
			$steps = $this->wizard_steps();
			$step  = sanitize_key( wp_unslash( $_GET['step'] ?? 'welcome' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$step  = isset( $steps[ $step ] ) ? $step : 'welcome';
			$keys  = array_keys( $steps );
			$index = array_search( $step, $keys, true );
			$core  = $this->core();
			$p     = $core->plugin;

			wp_enqueue_style( 'wpfactory-wc-sts-admin-ui', plugins_url( 'assets/css/admin-ui.css', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION );
			wp_enqueue_script( 'wpfactory-wc-sts-admin-ui', plugins_url( 'assets/js/admin-ui.js', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION, true );
			?>
			<!doctype html>
			<html <?php language_attributes(); ?>>
			<head>
				<meta charset="<?php bloginfo( 'charset' ); ?>" />
				<meta name="viewport" content="width=device-width, initial-scale=1" />
				<title><?php esc_html_e( 'Helpdesk setup', 'support-ticket-system-for-woocommerce' ); ?></title>
				<?php wp_print_styles( 'wpfactory-wc-sts-admin-ui' ); ?>
			</head>
			<body class="wpf-hd wpf-hd-wizard hd-app">
				<a class="screen-reader-text hd-skip" href="#hd-wizard-main"><?php esc_html_e( 'Skip to the step', 'support-ticket-system-for-woocommerce' ); ?></a>
				<header class="topbar">
					<div class="left">
						<div class="brand"><span class="brand-mark" aria-hidden="true"><?php self::e_icon( 'chat', 18 ); ?></span><?php esc_html_e( 'Helpdesk setup', 'support-ticket-system-for-woocommerce' ); ?></div>
					</div>
					<?php if ( 'done' !== $step ) : ?>
						<div class="top-right"><a class="btn btn-text btn-sm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpfactory_wc_sts_wizard&skip=1' ), self::NONCE ) ); ?>"><?php esc_html_e( 'Skip, I\'ll set it up later', 'support-ticket-system-for-woocommerce' ); ?></a></div>
					<?php endif; ?>
				</header>
				<main id="hd-wizard-main" class="page narrow wizard" data-stsw-wizard="<?php echo esc_attr( $step ); ?>">
					<ol class="wizard-steps" aria-label="<?php esc_attr_e( 'Setup steps', 'support-ticket-system-for-woocommerce' ); ?>">
						<?php foreach ( $keys as $i => $key ) : ?>
							<li class="<?php echo $i < $index ? 'is-done' : ( $i === $index ? 'is-current' : '' ); ?>"<?php echo $i === $index ? ' aria-current="step"' : ''; ?>><span class="wizard-dot" aria-hidden="true"><?php echo (int) ( $i + 1 ); ?></span><span><?php echo esc_html( $steps[ $key ] ); ?></span><?php echo $i < $index ? '<span class="screen-reader-text"> ' . esc_html__( '(done)', 'support-ticket-system-for-woocommerce' ) . '</span>' : ''; ?></li>
						<?php endforeach; ?>
					</ol>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="card">
						<input type="hidden" name="action" value="wpfactory_wc_sts_wizard" />
						<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>" />
						<?php wp_nonce_field( self::NONCE ); ?>
						<?php $this->render_wizard_step( $step, $p ); ?>
						<div class="card-foot">
							<?php if ( $index > 0 && 'done' !== $step ) : ?>
								<a class="btn btn-text" href="<?php echo esc_url( add_query_arg( 'step', $keys[ $index - 1 ], admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '-setup' ) ) ); ?>"><?php esc_html_e( 'Back', 'support-ticket-system-for-woocommerce' ); ?></a>
							<?php else : ?>
								<span></span>
							<?php endif; ?>
							<?php if ( 'done' === $step ) : ?>
								<a class="btn btn-primary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Open the dashboard', 'support-ticket-system-for-woocommerce' ); ?></a>
							<?php else : ?>
								<span class="wizard-next"><span class="small muted"><?php echo esc_html( sprintf( /* translators: 1: step, 2: steps. */ __( 'Step %1$d of %2$d', 'support-ticket-system-for-woocommerce' ), $index + 1, count( $keys ) ) ); ?></span>
								<button type="submit" class="btn btn-primary"><?php echo esc_html( 'welcome' === $step ? __( 'Get started', 'support-ticket-system-for-woocommerce' ) : __( 'Continue', 'support-ticket-system-for-woocommerce' ) ); ?></button></span>
							<?php endif; ?>
						</div>
					</form>
				</main>
				<?php wp_print_scripts( 'wpfactory-wc-sts-admin-ui' ); ?>
			</body>
			</html>
			<?php
			exit;
		}

		/**
		 * One wizard step: the card body, then setting rows when the step has switches.
		 *
		 * @param string $step Step.
		 * @param string $p    Settings prefix.
		 */
		protected function render_wizard_step( $step, $p ) {
			switch ( $step ) {
				case 'welcome':
					echo '<div class="card-body"><h1>' . esc_html__( 'Let\'s set up support for your store', 'support-ticket-system-for-woocommerce' ) . '</h1><p class="lead">' . esc_html__( 'Four quick steps. You can change everything later in Settings.', 'support-ticket-system-for-woocommerce' ) . '</p></div><div class="wizard-features">';
					$features = array(
						array( 'user', __( 'Help from My Account', 'support-ticket-system-for-woocommerce' ), __( 'Customers open tickets from their account or straight from an order.', 'support-ticket-system-for-woocommerce' ) ),
						array( 'store', __( 'Orders next to every ticket', 'support-ticket-system-for-woocommerce' ), __( 'See the order and the customer without leaving the conversation.', 'support-ticket-system-for-woocommerce' ) ),
						array( 'inbox', __( 'One inbox for your team', 'support-ticket-system-for-woocommerce' ), __( 'Priorities, assignees, private notes and saved replies.', 'support-ticket-system-for-woocommerce' ) ),
					);
					foreach ( $features as $f ) {
						echo '<div class="tile">' . wp_kses( self::icon( $f[0], 20 ), self::svg_kses() ) . '<b>' . esc_html( $f[1] ) . '</b><span class="small muted">' . esc_html( $f[2] ) . '</span></div>';
					}
					echo '</div>';
					break;

				case 'access':
					$menu  = (string) get_option( $p . 'renameAccountTabLink', '' );
					$order = (string) get_option( $p . 'renameOrderButtonLink', '' );
					?>
					<div class="card-body">
						<h1><?php esc_html_e( 'Where customers ask for help', 'support-ticket-system-for-woocommerce' ); ?></h1>
						<p class="lead"><?php esc_html_e( 'These labels appear in your store\'s My Account area.', 'support-ticket-system-for-woocommerce' ); ?></p>
						<div class="wizard-two">
							<div>
								<div class="field">
									<label class="label" for="hd-w-menu"><?php esc_html_e( 'My Account menu label', 'support-ticket-system-for-woocommerce' ); ?></label>
									<input type="text" class="input" id="hd-w-menu" name="menu" value="<?php echo esc_attr( '' !== $menu ? $menu : __( 'Help', 'support-ticket-system-for-woocommerce' ) ); ?>" data-hd-preview="menu" />
								</div>
								<div class="field">
									<label class="label" for="hd-w-order"><?php esc_html_e( 'Button on each order', 'support-ticket-system-for-woocommerce' ); ?></label>
									<input type="text" class="input" id="hd-w-order" name="order" value="<?php echo esc_attr( '' !== $order ? $order : __( 'Get help', 'support-ticket-system-for-woocommerce' ) ); ?>" data-hd-preview="order" />
								</div>
								<div class="field">
									<label class="label" for="hd-w-page"><?php esc_html_e( 'Support page (optional)', 'support-ticket-system-for-woocommerce' ); ?></label>
									<select class="select" id="hd-w-page" name="support_page" aria-describedby="hd-w-page-help">
										<?php $current_page = self::support_page_id(); ?>
										<option value=""><?php esc_html_e( 'None: customers use My Account', 'support-ticket-system-for-woocommerce' ); ?></option>
										<option value="new"><?php esc_html_e( 'Create a new "Support" page', 'support-ticket-system-for-woocommerce' ); ?></option>
										<?php foreach ( $this->support_page_choices() as $page_id => $page_title ) : ?>
											<option value="<?php echo (int) $page_id; ?>" <?php selected( $current_page, $page_id ); ?>><?php echo esc_html( $page_title ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="hint" id="hd-w-page-help"><?php esc_html_e( 'A page of your site where customers open and follow tickets too. We add the support form to it, so it is never empty; the rest of the page stays as it is.', 'support-ticket-system-for-woocommerce' ); ?></p>
								</div>
							</div>
							<div class="wizard-preview" aria-hidden="true">
								<span class="eyebrow"><?php esc_html_e( 'Preview: your store', 'support-ticket-system-for-woocommerce' ); ?></span>
								<div class="wizard-store">
									<div class="wizard-store-nav"><span><?php esc_html_e( 'Dashboard', 'support-ticket-system-for-woocommerce' ); ?></span><span><?php esc_html_e( 'Orders', 'support-ticket-system-for-woocommerce' ); ?></span><span class="on" data-hd-preview-out="menu"><?php echo esc_html( '' !== $menu ? $menu : __( 'Help', 'support-ticket-system-for-woocommerce' ) ); ?></span><span><?php esc_html_e( 'Account details', 'support-ticket-system-for-woocommerce' ); ?></span></div>
									<div class="row"><div><p class="row-title"><?php esc_html_e( 'Order #1042', 'support-ticket-system-for-woocommerce' ); ?></p><div class="row-meta"><?php esc_html_e( '2 items, Completed', 'support-ticket-system-for-woocommerce' ); ?></div></div><span class="btn btn-secondary btn-sm" data-hd-preview-out="order"><?php echo esc_html( '' !== $order ? $order : __( 'Get help', 'support-ticket-system-for-woocommerce' ) ); ?></span></div>
								</div>
								<span class="hint"><?php esc_html_e( 'Your theme\'s colors and fonts apply on the live store.', 'support-ticket-system-for-woocommerce' ); ?></span>
							</div>
						</div>
					</div>
					<div class="setting">
						<div><p class="row-title" id="hd-w-hide-label"><?php esc_html_e( 'Hide closed tickets from customers', 'support-ticket-system-for-woocommerce' ); ?></p><div class="row-meta" id="hd-w-hide-help"><?php esc_html_e( 'Keeps their support list short and current.', 'support-ticket-system-for-woocommerce' ); ?></div></div>
						<div class="ctl">
							<?php
							echo wp_kses(
								self::toggle(
									'hide',
									'1' === (string) get_option( $p . 'hideClosed' ),
									array(
										'aria-labelledby'  => 'hd-w-hide-label',
										'aria-describedby' => 'hd-w-hide-help',
									)
								),
								self::toggle_kses()
							);
							?>
						</div>
					</div>
					<?php
					break;

				case 'subjects':
					$existing = wp_list_pluck( self::ordered_terms( 'stsw_tickets_subject' ), 'name' );
					$all      = array_unique( array_merge( $this->suggested_subjects(), $existing ) );
					?>
					<div class="card-body">
						<h1><?php esc_html_e( 'What do customers usually contact you about?', 'support-ticket-system-for-woocommerce' ); ?></h1>
						<p class="lead"><?php esc_html_e( 'Customers pick one of these subjects when they open a ticket. Choose the ones that fit your store.', 'support-ticket-system-for-woocommerce' ); ?></p>
						<fieldset class="choices wizard-subjects">
							<legend class="screen-reader-text"><?php esc_html_e( 'Subjects', 'support-ticket-system-for-woocommerce' ); ?></legend>
							<?php foreach ( $all as $name ) : ?>
								<label class="choice"><input type="checkbox" name="subjects[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( ! $existing || in_array( $name, $existing, true ) ); ?> /> <b><?php echo esc_html( $name ); ?></b></label>
							<?php endforeach; ?>
						</fieldset>
						<div class="field wizard-custom">
							<label class="label" for="hd-w-custom"><?php esc_html_e( 'Add your own subject', 'support-ticket-system-for-woocommerce' ); ?></label>
							<input type="text" class="input" id="hd-w-custom" name="custom" placeholder="<?php esc_attr_e( 'Add your own, e.g. Custom orders', 'support-ticket-system-for-woocommerce' ); ?>" />
						</div>
					</div>
					<?php
					break;

				case 'messages':
					$text     = trim( wp_strip_all_tags( (string) get_option( $p . 'textforTicketSave', '' ) ) );
					$switches = array(
						'alerts'   => array( __( 'Email me about new tickets and customer replies', 'support-ticket-system-for-woocommerce' ), __( 'More options under Settings, Emails.', 'support-ticket-system-for-woocommerce' ), '1' === (string) get_option( $p . 'mailToADmin' ) || false === get_option( $p . 'mailToADmin' ) ),
						'customer' => array( __( 'Email customers when you reply', 'support-ticket-system-for-woocommerce' ), __( 'They also get a confirmation when they open a ticket.', 'support-ticket-system-for-woocommerce' ), '1' === (string) get_option( $p . 'mailToCustomer' ) || false === get_option( $p . 'mailToCustomer' ) ),
					);
					?>
					<div class="card-body">
						<h1><?php esc_html_e( 'Messages and alerts', 'support-ticket-system-for-woocommerce' ); ?></h1>
						<p class="lead"><?php esc_html_e( 'What customers see after they write, and how your team hears about it.', 'support-ticket-system-for-woocommerce' ); ?></p>
						<div class="field wizard-first">
							<label class="label" for="hd-w-confirm"><?php esc_html_e( 'Message after a customer opens a ticket', 'support-ticket-system-for-woocommerce' ); ?></label>
							<textarea class="input" id="hd-w-confirm" name="confirm" rows="3" aria-describedby="hd-w-confirm-help"><?php echo esc_textarea( '' !== $text ? $text : __( 'Thanks for reaching out. We have received your request and will get back to you soon.', 'support-ticket-system-for-woocommerce' ) ); ?></textarea>
							<p class="hint" id="hd-w-confirm-help"><?php esc_html_e( 'Only promise a reply time your team can keep.', 'support-ticket-system-for-woocommerce' ); ?></p>
						</div>
						<div class="field">
							<label class="label" for="hd-w-email"><?php esc_html_e( 'Send new ticket alerts to', 'support-ticket-system-for-woocommerce' ); ?></label>
							<input type="email" class="input" id="hd-w-email" name="email" value="<?php echo esc_attr( WPFactory_WC_STS_Emails::get_instance()->get_store_email() ); ?>" />
						</div>
					</div>
					<?php foreach ( $switches as $name => $switch ) : ?>
						<div class="setting">
							<div><p class="row-title" id="hd-w-<?php echo esc_attr( $name ); ?>-label"><?php echo esc_html( $switch[0] ); ?></p><div class="row-meta" id="hd-w-<?php echo esc_attr( $name ); ?>-help"><?php echo esc_html( $switch[1] ); ?></div></div>
							<div class="ctl">
								<?php
								echo wp_kses(
									self::toggle(
										$name,
										$switch[2],
										array(
											'aria-labelledby'  => 'hd-w-' . $name . '-label',
											'aria-describedby' => 'hd-w-' . $name . '-help',
										)
									),
									self::toggle_kses()
								);
								?>
							</div>
						</div>
					<?php endforeach; ?>
					<?php
					break;

				case 'done':
					$menu    = (string) get_option( $p . 'renameAccountTabLink', '' );
					$credits = WPFactory_WC_STS_Credits::get_instance();
					$tab     = esc_html__( '(opens in a new tab)', 'support-ticket-system-for-woocommerce' );
					?>
					<div class="card-body">
						<div class="eyebrow"><?php esc_html_e( 'All set', 'support-ticket-system-for-woocommerce' ); ?></div>
						<h1><?php esc_html_e( 'Your helpdesk is live', 'support-ticket-system-for-woocommerce' ); ?></h1>
						<p class="lead"><?php echo esc_html( sprintf( /* translators: %s: menu label. */ __( 'Customers can now open tickets from My Account under "%s".', 'support-ticket-system-for-woocommerce' ), '' !== $menu ? $menu : __( 'Help', 'support-ticket-system-for-woocommerce' ) ) ); ?></p>
					</div>
					<div class="section-label"><?php esc_html_e( 'Next steps', 'support-ticket-system-for-woocommerce' ); ?></div>
					<?php if ( self::support_page_id() ) : ?>
						<div class="row" data-stsw-wizard-page><div><p class="row-title"><?php esc_html_e( 'Your support page', 'support-ticket-system-for-woocommerce' ); ?></p><div class="row-meta"><?php echo esc_html( get_the_title( self::support_page_id() ) ); ?></div></div><a class="btn btn-text btn-sm" href="<?php echo esc_url( get_permalink( self::support_page_id() ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View page', 'support-ticket-system-for-woocommerce' ); ?><span class="screen-reader-text"> <?php echo $tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></span></a></div>
					<?php endif; ?>
					<div class="row"><div><p class="row-title"><?php esc_html_e( 'Open a test ticket', 'support-ticket-system-for-woocommerce' ); ?></p><div class="row-meta"><?php esc_html_e( 'See what your customers see.', 'support-ticket-system-for-woocommerce' ); ?></div></div><a class="btn btn-text btn-sm" href="<?php echo esc_url( $this->core()->get_customer_tickets_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open store', 'support-ticket-system-for-woocommerce' ); ?><span class="screen-reader-text"> <?php echo $tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></span></a></div>
					<div class="row"><div><p class="row-title"><?php esc_html_e( 'Save your first saved reply', 'support-ticket-system-for-woocommerce' ); ?></p><div class="row-meta"><?php esc_html_e( 'Answer common questions in one click.', 'support-ticket-system-for-woocommerce' ); ?></div></div><a class="btn btn-text btn-sm" href="<?php echo esc_url( self::url( 'saved' ) ); ?>"><?php esc_html_e( 'Add reply', 'support-ticket-system-for-woocommerce' ); ?></a></div>
					<?php if ( $credits->library() ) : ?>
						<div class="row" data-stsw-wizard-ai><div><p class="row-title"><?php esc_html_e( 'Turn on AI replies', 'support-ticket-system-for-woocommerce' ); ?></p><div class="row-meta"><?php echo esc_html( sprintf( /* translators: %d: number of free credits. */ __( 'Draft replies and summaries with AI. A free WPFactory AI account gets %d free credits a month.', 'support-ticket-system-for-woocommerce' ), $credits->free_monthly() ) ); ?></div></div><a class="btn btn-text btn-sm" href="<?php echo esc_url( $credits->settings_url() ); ?>"><?php esc_html_e( 'Set up AI', 'support-ticket-system-for-woocommerce' ); ?></a></div>
					<?php endif; ?>
					<div class="row"><div><p class="row-title"><?php esc_html_e( 'Review all settings', 'support-ticket-system-for-woocommerce' ); ?></p><div class="row-meta"><?php esc_html_e( 'Assignment, notifications, ticket form and more.', 'support-ticket-system-for-woocommerce' ); ?></div></div><a class="btn btn-text btn-sm" href="<?php echo esc_url( self::url( 'settings' ) ); ?>"><?php esc_html_e( 'Settings', 'support-ticket-system-for-woocommerce' ); ?></a></div>
					<?php
					break;
			}
		}

		/**
		 * Saves a wizard step (same options as the Settings fields) and moves on.
		 */
		public function handle_wizard() {
			check_admin_referer( self::NONCE );
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
			}
			if ( ! empty( $_GET['skip'] ) ) {
				update_option( self::WIZARD_OPTION, 'skipped', false );
				wp_safe_redirect( self::url() );
				exit;
			}
			$p    = $this->core()->plugin;
			$step = sanitize_key( wp_unslash( $_POST['step'] ?? '' ) );
			switch ( $step ) {
				case 'access':
					update_option( $p . 'renameAccountTabLink', sanitize_text_field( wp_unslash( $_POST['menu'] ?? '' ) ) );
					update_option( $p . 'renameOrderButtonLink', sanitize_text_field( wp_unslash( $_POST['order'] ?? '' ) ) );
					update_option( $p . 'hideClosed', empty( $_POST['hide'] ) ? '' : '1' );
					$choice = sanitize_key( wp_unslash( $_POST['support_page'] ?? '' ) );
					if ( '' !== $choice ) {
						$page_id = $this->setup_support_page( 'new' === $choice ? 'new' : absint( $choice ) );
						if ( $page_id ) {
							update_option( self::SUPPORT_PAGE, $page_id );
						}
					}
					flush_rewrite_rules( false );
					break;
				case 'subjects':
					$names  = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['subjects'] ?? array() ) );
					$custom = sanitize_text_field( wp_unslash( $_POST['custom'] ?? '' ) );
					if ( '' !== $custom ) {
						$names[] = $custom;
					}
					foreach ( array_filter( $names ) as $name ) {
						if ( ! term_exists( $name, 'stsw_tickets_subject' ) ) {
							wp_insert_term( $name, 'stsw_tickets_subject' );
						}
					}
					break;
				case 'messages':
					$text = sanitize_textarea_field( wp_unslash( $_POST['confirm'] ?? '' ) );
					update_option( $p . 'textforTicketSave', '' !== $text ? '<p>' . esc_html( $text ) . '</p>' : '', false );
					$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
					if ( $email ) {
						update_option( $p . 'AdminEmailAddress', $email );
					}
					update_option( $p . 'mailToADmin', empty( $_POST['alerts'] ) ? '' : '1' );
					update_option( $p . 'mailToCustomer', empty( $_POST['customer'] ) ? '' : '1' );
					update_option( self::WIZARD_OPTION, 'done', false );
					/**
					 * The setup wizard finished (the knowledge base creates the Help center page).
					 */
					do_action( 'wpfactory_wc_sts_wizard_done' );
					break;
			}
			$keys = array_keys( $this->wizard_steps() );
			$next = $keys[ min( count( $keys ) - 1, (int) array_search( $step, $keys, true ) + 1 ) ];
			wp_safe_redirect( add_query_arg( 'step', $next, admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '-setup' ) ) );
			exit;
		}
	}

endif;

return WPFactory_WC_STS_UI::get_instance();
