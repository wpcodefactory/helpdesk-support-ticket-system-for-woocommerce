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
			add_action( 'load-edit-tags.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-term.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-edit.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-post-new.php', array( $this, 'redirect_old_screens' ) );
			add_action( 'load-post.php', array( $this, 'redirect_old_screens' ) );
			add_filter( 'admin_body_class', array( $this, 'body_class' ) );
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
				wp_safe_redirect( self::url( $map[ $taxonomy ] ) );
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
			);
			$path  = $paths[ $name ] ?? $paths['chat'];
			return '<svg class="hd-icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
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
		 * @param string $size `sm`, `md` or `lg`.
		 *
		 * @return string HTML.
		 */
		public static function avatar( $name, $size = 'md' ) {
			$tones = array( 'a', 'b', 'c', 'd', 'e', 'f' );
			$tone  = $tones[ abs( crc32( (string) $name ) ) % count( $tones ) ];
			return '<span class="hd-avatar hd-avatar--' . esc_attr( $size ) . ' hd-tone-' . esc_attr( $tone ) . '" aria-hidden="true">' . esc_html( self::initials( $name ) ) . '</span>';
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
			$color = sanitize_hex_color( (string) get_term_meta( $term->term_id, WPFactory_WC_STS_Inc::META_COLOR, true ) );
			$known = in_array( $term->slug, array( 'low', 'normal', 'high' ), true ) ? ' hd-prio--' . $term->slug : '';
			$style = $known ? '' : ( $color ? ' style="--hd-prio-dot:' . esc_attr( $color ) . '"' : '' );
			return '<span class="hd-prio' . esc_attr( $known ) . '"' . $style . '><span class="hd-dot" aria-hidden="true"></span>' . esc_html( $term->name ) . '</span>';
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
			$tabs = array(
				''           => __( 'Inbox', 'support-ticket-system-for-woocommerce' ),
				'priorities' => __( 'Priorities', 'support-ticket-system-for-woocommerce' ),
				'subjects'   => __( 'Subjects', 'support-ticket-system-for-woocommerce' ),
				'saved'      => __( 'Saved replies', 'support-ticket-system-for-woocommerce' ),
			);
			/**
			 * Header tabs (the knowledge base adds its own).
			 *
			 * @param array $tabs key => label.
			 */
			$tabs             = (array) apply_filters( 'wpfactory_wc_sts_header_tabs', $tabs );
			$tabs['settings'] = __( 'Settings', 'support-ticket-system-for-woocommerce' );
			$search           = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<header class="hd-header">
				<div class="hd-brand">
					<span class="hd-logo"><?php self::e_icon( 'chat', 18 ); ?></span>
					<span class="hd-brand__name"><?php esc_html_e( 'Helpdesk', 'support-ticket-system-for-woocommerce' ); ?></span>
					<?php
					/**
					 * After the product name (Pro prints its badge).
					 */
					do_action( 'wpfactory_wc_sts_header_badge' );
					?>
				</div>
				<nav class="hd-tabs" aria-label="<?php esc_attr_e( 'Helpdesk sections', 'support-ticket-system-for-woocommerce' ); ?>">
					<?php foreach ( $tabs as $key => $label ) : ?>
						<a class="hd-tab" href="<?php echo esc_url( self::url( $key ) ); ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
				<div class="hd-header__spacer"></div>
				<div class="hd-header__actions">
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" role="search" class="hd-search">
						<input type="hidden" name="page" value="<?php echo esc_attr( WPFACTORY_WC_STS_PAGE ); ?>" />
						<input type="hidden" name="view" value="all" />
						<label class="screen-reader-text" for="hd-search"><?php esc_html_e( 'Search tickets', 'support-ticket-system-for-woocommerce' ); ?></label>
						<?php self::e_icon( 'search', 16 ); ?>
						<input type="search" id="hd-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email or #number', 'support-ticket-system-for-woocommerce' ); ?>" />
						<kbd aria-hidden="true">/</kbd>
					</form>
					<?php
					/**
					 * Header actions (Pro: New ticket).
					 */
					do_action( 'wpfactory_wc_sts_header_actions' );
					?>
				</div>
			</header>
			<?php
		}

		/**
		 * Page title block.
		 *
		 * @param string $title  Title.
		 * @param string $intro  Intro line.
		 * @param string $action Action HTML (escaped by the caller).
		 */
		public static function page_head( $title, $intro, $action = '' ) {
			echo '<div class="hd-pagehead"><div><h1 class="hd-h1">' . esc_html( $title ) . '</h1>' . ( $intro ? '<p class="hd-muted">' . esc_html( $intro ) . '</p>' : '' ) . '</div>' . $action . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- action escaped by callers.
		}

		/**
		 * Result notice.
		 *
		 * @param string $text  Text.
		 * @param bool   $error Error.
		 */
		public static function notice( $text, $error = false ) {
			echo '<div class="hd-toast ' . ( $error ? 'is-error' : 'is-success' ) . '" role="' . ( $error ? 'alert' : 'status' ) . '">' . wp_kses( self::icon( $error ? 'warn' : 'check', 16 ), self::svg_kses() ) . ' <span>' . esc_html( $text ) . '</span></div>';
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
		 * Priority, subject or status screen: add form left, ordered list right.
		 *
		 * @param string $tab Tab.
		 */
		public function render_terms( $tab ) {
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
			echo '<div class="hd-page">';
			if ( isset( $texts[ $message ] ) ) {
				self::notice( $texts[ $message ], 'error' === $message );
			}
			self::page_head( $screen['title'], $screen['intro'] );
			?>
			<div class="hd-split">
				<section class="hd-card hd-card--form" aria-labelledby="hd-term-form">
					<h2 id="hd-term-form" class="hd-h2"><?php echo esc_html( $editing ? $screen['edit'] : $screen['add'] ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hd-form">
						<input type="hidden" name="action" value="wpfactory_wc_sts_term" />
						<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
						<input type="hidden" name="term_id" value="<?php echo $editing ? (int) $editing->term_id : 0; ?>" />
						<?php wp_nonce_field( self::NONCE ); ?>
						<div class="hd-field">
							<label for="hd-term-name"><?php esc_html_e( 'Name', 'support-ticket-system-for-woocommerce' ); ?></label>
							<input type="text" id="hd-term-name" name="name" required value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" placeholder="<?php echo esc_attr( $screen['example'] ); ?>" />
						</div>
						<?php if ( ! empty( $screen['colors'] ) ) : ?>
							<fieldset class="hd-field">
								<legend><?php esc_html_e( 'Color', 'support-ticket-system-for-woocommerce' ); ?></legend>
								<div class="hd-swatches">
									<?php foreach ( $swatch as $hex => $label ) : ?>
										<label class="hd-swatch" style="--hd-swatch:<?php echo esc_attr( $hex ); ?>">
											<input type="radio" name="color" value="<?php echo esc_attr( $hex ); ?>" <?php checked( strtolower( $current_color ), $hex ); ?> />
											<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<span class="hd-help"><?php esc_html_e( 'Shown as a dot and chip on every ticket.', 'support-ticket-system-for-woocommerce' ); ?></span>
							</fieldset>
						<?php endif; ?>
						<?php if ( ! empty( $screen['assignee'] ) ) : ?>
							<div class="hd-field">
								<label for="hd-term-assignee"><?php esc_html_e( 'Send tickets with this subject to', 'support-ticket-system-for-woocommerce' ); ?></label>
								<?php WPFactory_WC_STS_Admin::get_instance()->render_user_select( 'assignee', $editing ? (int) get_term_meta( $editing->term_id, WPFactory_WC_STS_Inc::META_SUBJECT_ASSIGNEE, true ) : 0, __( 'Nobody in particular', 'support-ticket-system-for-woocommerce' ), 'hd-term-assignee' ); ?>
							</div>
						<?php endif; ?>
						<div class="hd-field">
							<label for="hd-term-desc"><?php esc_html_e( 'Description', 'support-ticket-system-for-woocommerce' ); ?> <span class="hd-muted"><?php esc_html_e( '(optional)', 'support-ticket-system-for-woocommerce' ); ?></span></label>
							<textarea id="hd-term-desc" name="description" rows="3"><?php echo esc_textarea( $editing ? $editing->description : '' ); ?></textarea>
						</div>
						<details class="hd-details">
							<summary><?php esc_html_e( 'Advanced', 'support-ticket-system-for-woocommerce' ); ?></summary>
							<div class="hd-field">
								<label for="hd-term-slug"><?php esc_html_e( 'Slug', 'support-ticket-system-for-woocommerce' ); ?></label>
								<input type="text" id="hd-term-slug" name="slug" value="<?php echo esc_attr( $editing ? $editing->slug : '' ); ?>" placeholder="<?php esc_attr_e( 'Created from the name', 'support-ticket-system-for-woocommerce' ); ?>" />
							</div>
						</details>
						<div class="hd-actions">
							<?php if ( $editing ) : ?>
								<a class="hd-btn" href="<?php echo esc_url( self::url( $tab ) ); ?>"><?php esc_html_e( 'Cancel', 'support-ticket-system-for-woocommerce' ); ?></a>
							<?php endif; ?>
							<button type="submit" class="hd-btn hd-btn--primary"><?php echo esc_html( $editing ? __( 'Save changes', 'support-ticket-system-for-woocommerce' ) : $screen['add'] ); ?></button>
						</div>
					</form>
				</section>

				<section class="hd-card hd-card--list" aria-label="<?php echo esc_attr( $screen['title'] ); ?>">
					<div class="hd-list-head" aria-hidden="true">
						<span></span><span><?php esc_html_e( 'Name', 'support-ticket-system-for-woocommerce' ); ?></span><span><?php esc_html_e( 'Tickets', 'support-ticket-system-for-woocommerce' ); ?></span><span><?php esc_html_e( 'Actions', 'support-ticket-system-for-woocommerce' ); ?></span>
					</div>
					<ol class="hd-sortable" data-taxonomy="<?php echo esc_attr( $screen['taxonomy'] ); ?>">
						<?php
						$terms = self::ordered_terms( $screen['taxonomy'] );
						if ( ! $terms ) {
							echo '<li class="hd-empty">' . esc_html__( 'Nothing here yet.', 'support-ticket-system-for-woocommerce' ) . '</li>';
						}
						foreach ( $terms as $term ) :
							$filter = 'stsw_tickets_priority' === $screen['taxonomy'] ? array( 'prio' => $term->slug ) : ( 'stsw_tickets_subject' === $screen['taxonomy'] ? array( 'subject' => $term->slug ) : array( 'status' => $term->slug ) );
							?>
							<li class="hd-sortable__row" data-id="<?php echo (int) $term->term_id; ?>" draggable="true">
								<button type="button" class="hd-icon-btn hd-drag" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name. */ __( 'Move %s. Use the arrow keys.', 'support-ticket-system-for-woocommerce' ), $term->name ) ); ?>"><?php self::e_icon( 'grip', 16 ); ?></button>
								<span class="hd-sortable__name">
									<?php
									if ( 'stsw_tickets_priority' === $screen['taxonomy'] ) {
										echo self::priority_chip( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in priority_chip().
									} else {
										echo '<strong>' . esc_html( $term->name ) . '</strong>';
									}
									if ( $term->description ) {
										echo '<span class="hd-muted">' . esc_html( $term->description ) . '</span>';
									}
									?>
								</span>
								<a href="<?php echo esc_url( self::url( '', array_merge( array( 'view' => 'all' ), $filter ) ) ); ?>"><?php echo (int) $term->count; ?></a>
								<span class="hd-row-actions">
									<a class="hd-icon-btn" href="<?php echo esc_url( self::url( $tab, array( 'edit' => $term->term_id ) ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name. */ __( 'Edit %s', 'support-ticket-system-for-woocommerce' ), $term->name ) ); ?>"><?php self::e_icon( 'edit', 16 ); ?></a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hd-inline" data-hd-confirm="term">
										<input type="hidden" name="action" value="wpfactory_wc_sts_term" />
										<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
										<input type="hidden" name="delete" value="<?php echo (int) $term->term_id; ?>" />
										<?php wp_nonce_field( self::NONCE ); ?>
										<button type="submit" class="hd-icon-btn hd-danger" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name. */ __( 'Delete %s', 'support-ticket-system-for-woocommerce' ), $term->name ) ); ?>"><?php self::e_icon( 'trash', 16 ); ?></button>
									</form>
								</span>
							</li>
						<?php endforeach; ?>
					</ol>
					<p class="hd-live screen-reader-text" aria-live="polite"></p>
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
				wp_safe_redirect( self::url( $tab, array( 'hd_msg' => $ok ? 'deleted' : 'error' ) ) );
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
				wp_safe_redirect( self::url( $tab, array( 'hd_msg' => 'error' ) ) );
				exit;
			}
			if ( $term_id ) {
				$args['name'] = $name;
				$result       = wp_update_term( $term_id, $taxonomy, $args );
			} else {
				$result = wp_insert_term( $name, $taxonomy, $args );
			}
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( self::url( $tab, array( 'hd_msg' => 'error' ) ) );
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
			wp_safe_redirect( self::url( $tab, array( 'hd_msg' => 'saved' ) ) );
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
		 * Saved replies screen: empty state with starters, or list and editor.
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
			$action   = '<a class="hd-btn hd-btn--primary" href="' . esc_url( self::url( 'saved', array( 'new' => 1 ) ) ) . '">' . wp_kses( self::icon( 'plus', 16 ), self::svg_kses() ) . ' ' . esc_html__( 'New saved reply', 'support-ticket-system-for-woocommerce' ) . '</a>';
			echo '<div class="hd-page">';
			if ( 'saved' === $message ) {
				self::notice( __( 'Saved reply saved.', 'support-ticket-system-for-woocommerce' ) );
			} elseif ( 'deleted' === $message ) {
				self::notice( __( 'Saved reply deleted.', 'support-ticket-system-for-woocommerce' ) );
			}
			self::page_head( __( 'Saved replies', 'support-ticket-system-for-woocommerce' ), __( 'Save answers you send often, then insert them from any ticket with one click.', 'support-ticket-system-for-woocommerce' ), $action );

			if ( ! $replies && ! $new && ! $current ) {
				echo '<section class="hd-card hd-emptystate"><span class="hd-emptystate__icon">' . wp_kses( self::icon( 'lines', 22 ), self::svg_kses() ) . '</span><h2 class="hd-h2">' . esc_html__( 'No saved replies yet', 'support-ticket-system-for-woocommerce' ) . '</h2><p class="hd-muted">' . esc_html__( 'Start with one of these common answers, or write your own.', 'support-ticket-system-for-woocommerce' ) . '</p><div class="hd-chips">';
				foreach ( array_keys( $starters ) as $name ) {
					echo '<a class="hd-chip-btn" href="' . esc_url( self::url( 'saved', array( 'starter' => $name ) ) ) . '">' . esc_html( $name ) . '</a>';
				}
				echo '</div></section></div>';
				return;
			}
			?>
			<div class="hd-split hd-split--wide">
				<section class="hd-card hd-card--list" aria-label="<?php esc_attr_e( 'Saved replies', 'support-ticket-system-for-woocommerce' ); ?>">
					<form method="get" class="hd-search hd-search--block" role="search">
						<input type="hidden" name="page" value="<?php echo esc_attr( WPFACTORY_WC_STS_PAGE ); ?>" />
						<input type="hidden" name="tab" value="saved" />
						<label class="screen-reader-text" for="hd-saved-q"><?php esc_html_e( 'Search saved replies', 'support-ticket-system-for-woocommerce' ); ?></label>
						<?php self::e_icon( 'search', 16 ); ?>
						<input type="search" id="hd-saved-q" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search replies', 'support-ticket-system-for-woocommerce' ); ?>" />
					</form>
					<ul class="hd-pick-list">
						<?php
						foreach ( $replies as $reply ) :
							$text = wp_strip_all_tags( $reply->post_content );
							if ( '' !== $search && false === stripos( $reply->post_title . ' ' . $text, $search ) ) {
								continue;
							}
							?>
							<li><a href="<?php echo esc_url( self::url( 'saved', array( 'reply' => $reply->ID ) ) ); ?>"<?php echo $current && $current->ID === $reply->ID ? ' aria-current="true"' : ''; ?>><strong><?php echo esc_html( get_the_title( $reply ) ); ?></strong><span class="hd-muted"><?php echo esc_html( wp_trim_words( $text, 12 ) ); ?></span></a></li>
						<?php endforeach; ?>
						<?php if ( ! $replies ) : ?>
							<li class="hd-empty"><?php esc_html_e( 'Your saved replies will show here.', 'support-ticket-system-for-woocommerce' ); ?></li>
						<?php endif; ?>
					</ul>
				</section>
				<section class="hd-card" aria-labelledby="hd-editor-h">
					<?php if ( ! $current && ! $new ) : ?>
						<p class="hd-muted"><?php esc_html_e( 'Pick a saved reply to edit it, or create a new one.', 'support-ticket-system-for-woocommerce' ); ?></p>
					<?php else : ?>
						<div class="hd-card__head">
							<h2 id="hd-editor-h" class="hd-h2"><?php echo esc_html( $current ? __( 'Edit saved reply', 'support-ticket-system-for-woocommerce' ) : __( 'New saved reply', 'support-ticket-system-for-woocommerce' ) ); ?></h2>
							<?php if ( $current ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-hd-confirm="reply" class="hd-inline">
									<input type="hidden" name="action" value="wpfactory_wc_sts_saved_reply" />
									<input type="hidden" name="delete" value="<?php echo (int) $current->ID; ?>" />
									<?php wp_nonce_field( self::NONCE ); ?>
									<button type="submit" class="hd-btn hd-btn--danger-ghost"><?php esc_html_e( 'Delete', 'support-ticket-system-for-woocommerce' ); ?></button>
								</form>
							<?php endif; ?>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hd-form">
							<input type="hidden" name="action" value="wpfactory_wc_sts_saved_reply" />
							<input type="hidden" name="reply_id" value="<?php echo $current ? (int) $current->ID : 0; ?>" />
							<?php wp_nonce_field( self::NONCE ); ?>
							<div class="hd-field">
								<label for="hd-reply-title"><?php esc_html_e( 'Title', 'support-ticket-system-for-woocommerce' ); ?></label>
								<input type="text" id="hd-reply-title" name="title" required value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Return instructions', 'support-ticket-system-for-woocommerce' ); ?>" aria-describedby="hd-reply-title-help" />
								<span class="hd-help" id="hd-reply-title-help"><?php esc_html_e( 'Only your team sees the title. Use one you can find fast.', 'support-ticket-system-for-woocommerce' ); ?></span>
							</div>
							<div class="hd-field">
								<label for="hd-reply-body"><?php esc_html_e( 'Message', 'support-ticket-system-for-woocommerce' ); ?></label>
								<textarea id="hd-reply-body" name="body" rows="9" required><?php echo esc_textarea( $body ); ?></textarea>
							</div>
							<div class="hd-field">
								<span id="hd-placeholders"><?php esc_html_e( 'Insert customer details', 'support-ticket-system-for-woocommerce' ); ?></span>
								<div class="hd-chips" role="group" aria-labelledby="hd-placeholders">
									<?php foreach ( array( '{customer_first_name}', '{order_number}', '{ticket_number}', '{store_name}' ) as $tag ) : ?>
										<button type="button" class="hd-chip-btn hd-mono" data-hd-insert="<?php echo esc_attr( $tag ); ?>" data-hd-target="hd-reply-body"><?php echo esc_html( $tag ); ?></button>
									<?php endforeach; ?>
								</div>
								<span class="hd-help"><?php esc_html_e( 'Replaced with the ticket\'s details when you insert the reply.', 'support-ticket-system-for-woocommerce' ); ?></span>
							</div>
							<div class="hd-actions">
								<a class="hd-btn" href="<?php echo esc_url( self::url( 'saved' ) ); ?>"><?php esc_html_e( 'Cancel', 'support-ticket-system-for-woocommerce' ); ?></a>
								<button type="submit" class="hd-btn hd-btn--primary"><?php esc_html_e( 'Save reply', 'support-ticket-system-for-woocommerce' ); ?></button>
							</div>
						</form>
					<?php endif; ?>
				</section>
			</div>
			<?php
			echo '</div>';
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
			<body class="wpf-hd wpf-hd-wizard">
				<a class="screen-reader-text hd-skip" href="#hd-wizard-main"><?php esc_html_e( 'Skip to the step', 'support-ticket-system-for-woocommerce' ); ?></a>
				<div class="hd-wizard__top">
					<span class="hd-logo"><?php self::e_icon( 'chat', 18 ); ?></span>
					<span class="hd-brand__name"><?php esc_html_e( 'Helpdesk setup', 'support-ticket-system-for-woocommerce' ); ?></span>
					<span class="hd-header__spacer"></span>
					<?php if ( 'done' !== $step ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpfactory_wc_sts_wizard&skip=1' ), self::NONCE ) ); ?>"><?php esc_html_e( 'Skip, I\'ll set it up later', 'support-ticket-system-for-woocommerce' ); ?></a>
					<?php endif; ?>
				</div>
				<ol class="hd-stepper" aria-label="<?php esc_attr_e( 'Setup steps', 'support-ticket-system-for-woocommerce' ); ?>">
					<?php foreach ( $keys as $i => $key ) : ?>
						<li class="<?php echo $i < $index ? 'is-done' : ( $i === $index ? 'is-current' : '' ); ?>"<?php echo $i === $index ? ' aria-current="step"' : ''; ?>><span class="hd-stepper__dot" aria-hidden="true"></span><span><?php echo esc_html( ( $i + 1 ) . '. ' . $steps[ $key ] ); ?></span></li>
					<?php endforeach; ?>
				</ol>
				<main id="hd-wizard-main" class="hd-wizard__card">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wpfactory_wc_sts_wizard" />
						<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>" />
						<?php wp_nonce_field( self::NONCE ); ?>
						<div class="hd-wizard__body">
							<?php $this->render_wizard_step( $step, $p ); ?>
						</div>
						<div class="hd-wizard__foot">
							<?php if ( $index > 0 && 'done' !== $step ) : ?>
								<a class="hd-btn" href="<?php echo esc_url( add_query_arg( 'step', $keys[ $index - 1 ], admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '-setup' ) ) ); ?>"><?php esc_html_e( 'Back', 'support-ticket-system-for-woocommerce' ); ?></a>
							<?php endif; ?>
							<span class="hd-header__spacer"></span>
							<?php if ( 'done' === $step ) : ?>
								<a class="hd-btn hd-btn--primary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Go to inbox', 'support-ticket-system-for-woocommerce' ); ?></a>
							<?php else : ?>
								<span class="hd-muted"><?php echo esc_html( sprintf( /* translators: 1: step, 2: steps. */ __( 'Step %1$d of %2$d', 'support-ticket-system-for-woocommerce' ), $index + 1, count( $keys ) ) ); ?></span>
								<button type="submit" class="hd-btn hd-btn--primary"><?php echo esc_html( 'welcome' === $step ? __( 'Get started', 'support-ticket-system-for-woocommerce' ) : __( 'Continue', 'support-ticket-system-for-woocommerce' ) ); ?></button>
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
		 * One wizard step.
		 *
		 * @param string $step Step.
		 * @param string $p    Settings prefix.
		 */
		protected function render_wizard_step( $step, $p ) {
			switch ( $step ) {
				case 'welcome':
					echo '<h1 class="hd-h1">' . esc_html__( 'Let\'s set up support for your store', 'support-ticket-system-for-woocommerce' ) . '</h1><p class="hd-muted">' . esc_html__( 'Four quick steps. You can change everything later in Settings.', 'support-ticket-system-for-woocommerce' ) . '</p><div class="hd-features">';
					$features = array(
						array( 'user', __( 'Help from My Account', 'support-ticket-system-for-woocommerce' ), __( 'Customers open tickets from their account or straight from an order.', 'support-ticket-system-for-woocommerce' ) ),
						array( 'store', __( 'Orders next to every ticket', 'support-ticket-system-for-woocommerce' ), __( 'See the order and the customer without leaving the conversation.', 'support-ticket-system-for-woocommerce' ) ),
						array( 'inbox', __( 'One inbox for your team', 'support-ticket-system-for-woocommerce' ), __( 'Priorities, assignees, private notes and saved replies.', 'support-ticket-system-for-woocommerce' ) ),
					);
					foreach ( $features as $f ) {
						echo '<div class="hd-feature"><span class="hd-feature__icon">' . wp_kses( self::icon( $f[0], 20 ), self::svg_kses() ) . '</span><strong>' . esc_html( $f[1] ) . '</strong><span class="hd-muted">' . esc_html( $f[2] ) . '</span></div>';
					}
					echo '</div>';
					break;

				case 'access':
					$menu  = (string) get_option( $p . 'renameAccountTabLink', '' );
					$order = (string) get_option( $p . 'renameOrderButtonLink', '' );
					?>
					<div class="hd-wizard__two">
						<div>
							<h1 class="hd-h1"><?php esc_html_e( 'Where customers ask for help', 'support-ticket-system-for-woocommerce' ); ?></h1>
							<p class="hd-muted"><?php esc_html_e( 'These labels appear in your store\'s My Account area.', 'support-ticket-system-for-woocommerce' ); ?></p>
							<div class="hd-field">
								<label for="hd-w-menu"><?php esc_html_e( 'My Account menu label', 'support-ticket-system-for-woocommerce' ); ?></label>
								<input type="text" id="hd-w-menu" name="menu" value="<?php echo esc_attr( '' !== $menu ? $menu : __( 'Help', 'support-ticket-system-for-woocommerce' ) ); ?>" data-hd-preview="menu" />
							</div>
							<div class="hd-field">
								<label for="hd-w-order"><?php esc_html_e( 'Button on each order', 'support-ticket-system-for-woocommerce' ); ?></label>
								<input type="text" id="hd-w-order" name="order" value="<?php echo esc_attr( '' !== $order ? $order : __( 'Get Help', 'support-ticket-system-for-woocommerce' ) ); ?>" data-hd-preview="order" />
							</div>
							<label class="hd-switchrow">
								<input type="checkbox" role="switch" class="hd-switch" name="hide" value="1" <?php checked( '1', (string) get_option( $p . 'hideClosed' ) ); ?> />
								<span><strong><?php esc_html_e( 'Hide closed tickets from customers', 'support-ticket-system-for-woocommerce' ); ?></strong><span class="hd-help"><?php esc_html_e( 'Keeps their support list short and current.', 'support-ticket-system-for-woocommerce' ); ?></span></span>
							</label>
						</div>
						<div class="hd-preview" aria-hidden="true">
							<span class="hd-help"><?php esc_html_e( 'Preview: your store', 'support-ticket-system-for-woocommerce' ); ?></span>
							<div class="hd-preview__store">
								<div class="hd-preview__nav"><span><?php esc_html_e( 'Dashboard', 'support-ticket-system-for-woocommerce' ); ?></span><span><?php esc_html_e( 'Orders', 'support-ticket-system-for-woocommerce' ); ?></span><span class="is-on" data-hd-preview-out="menu"><?php echo esc_html( '' !== $menu ? $menu : __( 'Help', 'support-ticket-system-for-woocommerce' ) ); ?></span><span><?php esc_html_e( 'Account details', 'support-ticket-system-for-woocommerce' ); ?></span></div>
								<div class="hd-preview__order"><span><?php esc_html_e( 'Order #1042', 'support-ticket-system-for-woocommerce' ); ?></span><span class="hd-help"><?php esc_html_e( '2 items, Completed', 'support-ticket-system-for-woocommerce' ); ?></span><span class="hd-btn hd-btn--sm" data-hd-preview-out="order"><?php echo esc_html( '' !== $order ? $order : __( 'Get Help', 'support-ticket-system-for-woocommerce' ) ); ?></span></div>
							</div>
							<span class="hd-help"><?php esc_html_e( 'Your theme\'s colors and fonts apply on the live store.', 'support-ticket-system-for-woocommerce' ); ?></span>
						</div>
					</div>
					<?php
					break;

				case 'subjects':
					$existing = wp_list_pluck( self::ordered_terms( 'stsw_tickets_subject' ), 'name' );
					$all      = array_unique( array_merge( $this->suggested_subjects(), $existing ) );
					?>
					<h1 class="hd-h1"><?php esc_html_e( 'What do customers usually contact you about?', 'support-ticket-system-for-woocommerce' ); ?></h1>
					<p class="hd-muted"><?php esc_html_e( 'Customers pick one of these subjects when they open a ticket. Choose the ones that fit your store.', 'support-ticket-system-for-woocommerce' ); ?></p>
					<fieldset class="hd-togglechips">
						<legend class="screen-reader-text"><?php esc_html_e( 'Subjects', 'support-ticket-system-for-woocommerce' ); ?></legend>
						<?php foreach ( $all as $i => $name ) : ?>
							<label class="hd-togglechip"><input type="checkbox" name="subjects[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( ! $existing || in_array( $name, $existing, true ) ); ?> /> <span><?php echo esc_html( $name ); ?></span></label>
						<?php endforeach; ?>
					</fieldset>
					<div class="hd-field">
						<label for="hd-w-custom"><?php esc_html_e( 'Add your own subject', 'support-ticket-system-for-woocommerce' ); ?></label>
						<input type="text" id="hd-w-custom" name="custom" placeholder="<?php esc_attr_e( 'Add your own, e.g. Custom orders', 'support-ticket-system-for-woocommerce' ); ?>" />
					</div>
					<?php
					break;

				case 'messages':
					$text = trim( wp_strip_all_tags( (string) get_option( $p . 'textforTicketSave', '' ) ) );
					?>
					<h1 class="hd-h1"><?php esc_html_e( 'Messages and alerts', 'support-ticket-system-for-woocommerce' ); ?></h1>
					<p class="hd-muted"><?php esc_html_e( 'What customers see after they write, and how your team hears about it.', 'support-ticket-system-for-woocommerce' ); ?></p>
					<div class="hd-field">
						<label for="hd-w-confirm"><?php esc_html_e( 'Message after a customer opens a ticket', 'support-ticket-system-for-woocommerce' ); ?></label>
						<textarea id="hd-w-confirm" name="confirm" rows="3" aria-describedby="hd-w-confirm-help"><?php echo esc_textarea( '' !== $text ? $text : __( 'Thanks for reaching out. We have received your request and will get back to you soon.', 'support-ticket-system-for-woocommerce' ) ); ?></textarea>
						<span class="hd-help" id="hd-w-confirm-help"><?php esc_html_e( 'Only promise a reply time your team can keep.', 'support-ticket-system-for-woocommerce' ); ?></span>
					</div>
					<div class="hd-field">
						<label for="hd-w-email"><?php esc_html_e( 'Send new ticket alerts to', 'support-ticket-system-for-woocommerce' ); ?></label>
						<input type="email" id="hd-w-email" name="email" value="<?php echo esc_attr( WPFactory_WC_STS_Emails::get_instance()->get_store_email() ); ?>" />
					</div>
					<label class="hd-switchrow">
						<input type="checkbox" role="switch" class="hd-switch" name="alerts" value="1" <?php checked( '1' === (string) get_option( $p . 'mailToADmin' ) || false === get_option( $p . 'mailToADmin' ) ); ?> />
						<span><strong><?php esc_html_e( 'Email me about new tickets and customer replies', 'support-ticket-system-for-woocommerce' ); ?></strong><span class="hd-help"><?php esc_html_e( 'More options under Settings, Notifications.', 'support-ticket-system-for-woocommerce' ); ?></span></span>
					</label>
					<label class="hd-switchrow">
						<input type="checkbox" role="switch" class="hd-switch" name="customer" value="1" <?php checked( '1' === (string) get_option( $p . 'mailToCustomer' ) || false === get_option( $p . 'mailToCustomer' ) ); ?> />
						<span><strong><?php esc_html_e( 'Email customers when you reply', 'support-ticket-system-for-woocommerce' ); ?></strong><span class="hd-help"><?php esc_html_e( 'They also get a confirmation when they open a ticket.', 'support-ticket-system-for-woocommerce' ); ?></span></span>
					</label>
					<?php
					break;

				case 'done':
					$menu = (string) get_option( $p . 'renameAccountTabLink', '' );
					?>
					<div class="hd-done">
						<span class="hd-emptystate__icon is-success"><?php self::e_icon( 'check', 24 ); ?></span>
						<h1 class="hd-h1"><?php esc_html_e( 'Your helpdesk is live', 'support-ticket-system-for-woocommerce' ); ?></h1>
						<p class="hd-muted"><?php echo esc_html( sprintf( /* translators: %s: menu label. */ __( 'Customers can now open tickets from My Account under "%s".', 'support-ticket-system-for-woocommerce' ), '' !== $menu ? $menu : __( 'Help', 'support-ticket-system-for-woocommerce' ) ) ); ?></p>
						<ol class="hd-nextsteps">
							<li><span><strong><?php esc_html_e( 'Open a test ticket', 'support-ticket-system-for-woocommerce' ); ?></strong><span class="hd-help"><?php esc_html_e( 'See what your customers see.', 'support-ticket-system-for-woocommerce' ); ?></span></span><a href="<?php echo esc_url( $this->core()->get_customer_tickets_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open store', 'support-ticket-system-for-woocommerce' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'support-ticket-system-for-woocommerce' ); ?></span></a></li>
							<li><span><strong><?php esc_html_e( 'Save your first saved reply', 'support-ticket-system-for-woocommerce' ); ?></strong><span class="hd-help"><?php esc_html_e( 'Answer common questions in one click.', 'support-ticket-system-for-woocommerce' ); ?></span></span><a href="<?php echo esc_url( self::url( 'saved' ) ); ?>"><?php esc_html_e( 'Add reply', 'support-ticket-system-for-woocommerce' ); ?></a></li>
							<li><span><strong><?php esc_html_e( 'Review all settings', 'support-ticket-system-for-woocommerce' ); ?></strong><span class="hd-help"><?php esc_html_e( 'Assignment, notifications, ticket form and more.', 'support-ticket-system-for-woocommerce' ); ?></span></span><a href="<?php echo esc_url( self::url( 'settings' ) ); ?>"><?php esc_html_e( 'Settings', 'support-ticket-system-for-woocommerce' ); ?></a></li>
						</ol>
					</div>
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
