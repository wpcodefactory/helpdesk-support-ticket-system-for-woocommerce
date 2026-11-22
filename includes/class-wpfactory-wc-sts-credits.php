<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: AI with WPFactory AI credits.
 *
 * "Draft a reply with AI" and "Summarize" in the inbox, paid with WPFactory AI credits through the bundled
 * wpfactory/wpfactory-ai library (one site connection and one wallet for every WPFactory plugin). Works in the free
 * edition; Pro uses the same connection as one of its AI services. Nothing is sent to WPFactory until an
 * administrator connects the site on the AI Credits screen, and nothing is sent until a team member clicks a button.
 * Drafts land in the reply box for review and are never sent on their own.
 *
 * @version 3.0.0
 * @since   3.0.0
 *
 * @author  WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Credits' ) ) :

	/**
	 * WPFactory_WC_STS_Credits class.
	 */
	class WPFactory_WC_STS_Credits {

		/**
		 * Product id on the WPFactory AI server.
		 */
		const PRODUCT = 'helpdesk';

		/**
		 * Characters of conversation sent at most (the latest messages are kept).
		 */
		const MAX_CHARS = 12000;

		/**
		 * Models: fast and cheap (default), or stronger.
		 */
		const MODEL_FAST = 'claude-haiku-5-5';
		const MODEL_BEST = 'claude-sonnet-5-5';

		/**
		 * Free credits each WPFactory AI account gets per plugin and month (set on the server; can change).
		 */
		const FREE_MONTHLY = 100;

		/**
		 * User meta: the AI suggestion in the reply box was hidden.
		 */
		const META_PROMO_HIDDEN = 'wpfactory_wc_sts_ai_promo_hidden';

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Credits|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Credits
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
			add_action( 'init', array( $this, 'register_product' ), 5 );
			add_filter( 'wpfactory_wc_sts_settings_sections', array( $this, 'settings' ) );
			add_action( 'wpfactory_wc_sts_inbox_reply_tools', array( $this, 'render_buttons' ), 20 );
			add_action( 'wp_ajax_wpfactory_wc_sts_credits_ai', array( $this, 'ajax' ) );
			add_action( 'wpfactory_wc_sts_header_actions', array( $this, 'header_pill' ), 5 );
			add_action( 'admin_post_wpfactory_wc_sts_ai_promo_hide', array( $this, 'hide_promo' ) );
		}

		/**
		 * Free credits a month, for texts.
		 *
		 * @return int
		 */
		public function free_monthly() {
			/**
			 * Free WPFactory AI credits each account gets per month for this plugin (as set on the server).
			 *
			 * @param int $credits Credits.
			 */
			return (int) apply_filters( 'wpfactory_wc_sts_ai_free_monthly', self::FREE_MONTHLY );
		}

		/**
		 * Settings > AI (Pro has its own section).
		 *
		 * @return string
		 */
		public function settings_url() {
			return WPFactory_WC_STS_UI::url( 'settings' ) . ( $this->handled_elsewhere() ? '#pro_ai' : '#ai' );
		}

		/**
		 * A short suggestion in the reply box while AI is not set up: what it does, the free credits, one link to set
		 * it up, and Hide. Only for people who can change settings.
		 *
		 * @param bool $on AI is turned on but the site is not connected yet.
		 */
		public function render_promo( $on = false ) {
			if ( ! $this->library() || ! current_user_can( 'manage_woocommerce' ) || ( ! $on && get_user_meta( get_current_user_id(), self::META_PROMO_HIDDEN, true ) ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				return;
			}
			echo '<p class="hd-ai-promo" data-stsw-ai-promo>';
			if ( $on ) {
				echo '<span>' . esc_html__( 'AI replies are on, but this site is not connected to WPFactory AI yet.', 'support-ticket-system-for-woocommerce' ) . '</span> ';
				echo '<a href="' . esc_url( $this->screen_url() ) . '">' . esc_html__( 'Connect', 'support-ticket-system-for-woocommerce' ) . '</a>';
			} else {
				/* translators: %d: number of free credits. */
				echo '<span>' . esc_html( sprintf( __( 'Draft replies and summaries with AI. Create a free WPFactory AI account and get %d free credits a month (about one credit per draft).', 'support-ticket-system-for-woocommerce' ), $this->free_monthly() ) ) . '</span> ';
				echo '<a href="' . esc_url( $this->settings_url() ) . '" data-stsw-ai-promo-setup>' . esc_html__( 'Set up AI', 'support-ticket-system-for-woocommerce' ) . '</a> ';
				echo '<a class="hd-ai-promo__hide" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpfactory_wc_sts_ai_promo_hide' ), 'wpfactory_wc_sts_ai_promo_hide' ) ) . '">' . esc_html__( 'Hide', 'support-ticket-system-for-woocommerce' ) . '</a>';
			}
			echo '</p>';
		}

		/**
		 * Hides the AI suggestion for the current user.
		 */
		public function hide_promo() {
			check_admin_referer( 'wpfactory_wc_sts_ai_promo_hide' );
			update_user_meta( get_current_user_id(), self::META_PROMO_HIDDEN, '1' );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : WPFactory_WC_STS_UI::url() );
			exit;
		}

		/**
		 * The WPFactory AI library, when bundled.
		 *
		 * @return object|null
		 */
		public function library() {
			return function_exists( 'wpfactory_ai' ) ? wpfactory_ai() : null;
		}

		/**
		 * Registers Helpdesk with the library, so it shows on the AI Credits screen.
		 */
		public function register_product() {
			if ( $this->library() ) {
				$this->library()->register_product( self::PRODUCT, array( 'name' => __( 'Helpdesk', 'support-ticket-system-for-woocommerce' ) ) );
			}
		}

		/**
		 * Whether Pro shows the AI buttons and settings itself (WPFactory AI credits are one of its AI services).
		 *
		 * @return bool
		 */
		public function handled_elsewhere() {
			/**
			 * True when another module (Pro) provides the AI buttons and settings.
			 *
			 * @param bool $handled Handled.
			 */
			return (bool) apply_filters( 'wpfactory_wc_sts_ai_handled', false );
		}

		/**
		 * Whether the site is connected to WPFactory AI.
		 *
		 * @return bool
		 */
		public function is_connected() {
			return $this->library() && $this->library()->is_connected();
		}

		/**
		 * Whether the AI buttons show (free edition).
		 *
		 * @return bool
		 */
		public function is_enabled() {
			return '1' === (string) get_option( 'wpfactory_wc_sts_ai_enabled', '' ) && null !== $this->library();
		}

		/**
		 * The AI Credits screen (connect, balance, buy).
		 *
		 * @return string
		 */
		public function screen_url() {
			return $this->library() ? $this->library()->admin_url() : admin_url();
		}

		/**
		 * Credits the site can spend now, from the library's cache (null: unknown).
		 *
		 * @param bool $fresh Ask the server now.
		 *
		 * @return int|null
		 */
		public function balance( $fresh = false ) {
			if ( ! $this->is_connected() ) {
				return null;
			}
			$b = $this->library()->balance( self::PRODUCT, $fresh );
			return is_array( $b ) && isset( $b['balance'] ) ? (int) $b['balance'] : null;
		}

		/**
		 * The header credits link text for a balance ("123 credits"), or '' when unknown.
		 *
		 * @param int|null $balance Credits.
		 *
		 * @return string
		 */
		public function credits_label( $balance ) {
			if ( null === $balance ) {
				return '';
			}
			/* translators: %s: number of credits. */
			return sprintf( _n( '%s credit', '%s credits', (int) $balance, 'support-ticket-system-for-woocommerce' ), number_format_i18n( (int) $balance ) );
		}

		/**
		 * Asks the AI with credits.
		 *
		 * @param string $system     System prompt.
		 * @param string $prompt     User prompt.
		 * @param string $task       Task shown in usage by task.
		 * @param string $model      Model ('' : the quality setting).
		 * @param int    $max_tokens Most output tokens (the server reserves the most a call can cost).
		 *
		 * @return string|WP_Error Plain, ready-to-show messages on errors. Nothing is charged on errors.
		 */
		public function complete( $system, $prompt, $task = 'ask', $model = '', $max_tokens = 1000 ) {
			$ai = $this->library();
			if ( ! $ai ) {
				return new WP_Error( 'stsw_credits_missing', __( 'WPFactory AI is not available in this copy of the plugin.', 'support-ticket-system-for-woocommerce' ) );
			}
			if ( ! $ai->is_connected() ) {
				/* translators: %s: address of the AI Credits screen. */
				return new WP_Error( 'stsw_credits_not_connected', sprintf( __( 'Connect this site to WPFactory AI first: %s', 'support-ticket-system-for-woocommerce' ), $this->screen_url() ) );
			}
			$model = '' !== $model ? $model : ( 'best' === get_option( 'wpfactory_wc_sts_ai_quality', 'fast' ) ? self::MODEL_BEST : self::MODEL_FAST );
			$body  = array(
				'model'      => $model,
				'max_tokens' => (int) $max_tokens,
				'system'     => $system,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
			);
			if ( preg_match( '/^claude-(opus|sonnet|haiku|fable)-5/', $model ) ) {
				$body['output_config'] = array( 'effort' => 'low' ); // Short support texts: quick and cheap.
			}
			$r = $ai->call( self::PRODUCT, $body, array( 'task' => $task ) );
			if ( is_wp_error( $r ) ) {
				if ( 'wpfactory_ai_insufficient_credits' === $r->get_error_code() ) {
					$data = (array) $r->get_error_data();
					/* translators: 1: credits needed, 2: credits left, 3: address of the AI Credits screen. */
					return new WP_Error( 'stsw_credits_low', sprintf( __( 'Not enough AI credits: this needs about %1$d and %2$d are left. Buy credits: %3$s', 'support-ticket-system-for-woocommerce' ), (int) ( $data['estimate'] ?? 0 ), (int) ( $data['balance'] ?? 0 ), $this->library()->buy_url() ) );
				}
				return $r;
			}
			$text = '';
			foreach ( (array) ( $r['message']['content'] ?? array() ) as $block ) {
				if ( isset( $block['text'] ) && ( ! isset( $block['type'] ) || 'text' === $block['type'] ) ) {
					$text .= $block['text'];
				}
			}
			$text = trim( $text );
			return '' !== $text ? $text : new WP_Error( 'stsw_credits_empty', __( 'The AI sent an empty answer.', 'support-ticket-system-for-woocommerce' ) );
		}

		/**
		 * The ticket as plain text for the model: order, conversation (latest kept), private notes for summaries.
		 *
		 * @param int  $ticket_id Ticket ID.
		 * @param bool $notes     Include private notes.
		 *
		 * @return string
		 */
		public function build_context( $ticket_id, $notes ) {
			$core    = WPFactory_WC_STS_Inc::get_main_instance();
			$contact = $core->get_ticket_contact( $ticket_id );
			$lines   = array();
			$lines[] = sprintf( 'Ticket #%1$d: %2$s', $ticket_id, get_the_title( $ticket_id ) );
			$lines[] = 'Customer: ' . $contact['display_name'];
			$order   = ( $core->get_ticket_order_id( $ticket_id ) && function_exists( 'wc_get_order' ) ) ? wc_get_order( $core->get_ticket_order_id( $ticket_id ) ) : false;
			if ( $order ) {
				$items = array();
				foreach ( $order->get_items() as $item ) {
					$items[] = $item->get_name() . ' x ' . $item->get_quantity();
				}
				$lines[] = sprintf( 'Order #%s, status %s, placed %s, total %s %s, items: %s, shipping: %s', $order->get_order_number(), wc_get_order_status_name( $order->get_status() ), wc_format_datetime( $order->get_date_created() ), wc_format_decimal( $order->get_total(), 2 ), $order->get_currency(), implode( '; ', $items ), $order->get_shipping_method() );
			}
			$messages   = array();
			$messages[] = 'Customer: ' . get_post_field( 'post_content', $ticket_id, 'raw' );
			foreach ( $core->get_replies( $ticket_id ) as $row ) {
				$messages[] = ( (int) $row->agent > 0 ? 'Store' : 'Customer' ) . ': ' . $row->content;
			}
			if ( $notes ) {
				foreach ( $core->get_notes( $ticket_id ) as $row ) {
					$messages[] = 'Private staff note: ' . $row->content;
				}
			}
			$text = implode( "\n\n", $messages );
			if ( strlen( $text ) > self::MAX_CHARS ) {
				$text = '[earlier messages left out]' . "\n" . substr( $text, -self::MAX_CHARS );
			}
			return implode( "\n", $lines ) . "\n\nConversation:\n" . $text;
		}

		/**
		 * Instructions for the model.
		 *
		 * @param string $task  `draft` or `summary`.
		 * @param string $extra Store guidelines.
		 *
		 * @return string
		 */
		public function system_prompt( $task, $extra = '' ) {
			$store = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$base  = 'You help the customer support team of the online store "' . $store . '". Use only facts from the ticket and order below; if something is unknown, say a team member will check. Never promise a response time, delivery date or refund that is not stated in the data. Plain text only, no markdown.';
			if ( 'summary' === $task ) {
				$base .= ' Summarize the ticket for a colleague in 3 to 5 short lines: the problem, what was done, what is still open.';
			} else {
				$base .= ' Write the next reply from the store to the customer, in the customer\'s language, friendly and short. No subject line. Do not sign with a person\'s name.';
			}
			$extra = trim( (string) $extra );
			return '' !== $extra ? $base . "\n\nStore guidelines:\n" . $extra : $base;
		}

		/**
		 * Settings section (free edition).
		 *
		 * @param array $sections Sections.
		 *
		 * @return array
		 */
		public function settings( $sections ) {
			if ( $this->handled_elsewhere() || ! $this->library() ) {
				return $sections;
			}
			$sections['ai'] = array(
				'label'  => __( 'AI', 'support-ticket-system-for-woocommerce' ),
				'group'  => 'advanced',
				'intro'  => __( 'When a team member clicks "Draft a reply" or "Summarize", the ticket conversation and the order details are sent to WPFactory AI and paid with WPFactory AI credits. Nothing is sent otherwise, and drafts are never sent to customers without a person reviewing them.', 'support-ticket-system-for-woocommerce' ),
				'fields' => array(
					'@wpfactory_wc_sts_ai_enabled'      => array(
						'type'  => 'checkbox',
						'label' => __( 'Show AI buttons in the reply box', 'support-ticket-system-for-woocommerce' ),
					),
					'@wpfactory_wc_sts_ai_connection'   => array(
						'type'   => 'html',
						'label'  => __( 'WPFactory AI credits', 'support-ticket-system-for-woocommerce' ),
						'render' => array( $this, 'render_connection' ),
					),
					'@wpfactory_wc_sts_ai_quality'      => array(
						'type'    => 'select',
						'label'   => __( 'Quality', 'support-ticket-system-for-woocommerce' ),
						'options' => array(
							'fast' => __( 'Fast: about 1 credit per draft or summary', 'support-ticket-system-for-woocommerce' ),
							'best' => __( 'Best: about 11 credits per draft or summary', 'support-ticket-system-for-woocommerce' ),
						),
					),
					'@wpfactory_wc_sts_ai_instructions' => array(
						'type'        => 'textarea',
						'label'       => __( 'Store guidelines for drafts', 'support-ticket-system-for-woocommerce' ),
						'description' => __( 'For example tone, sign-off, return policy. Added to every request.', 'support-ticket-system-for-woocommerce' ),
					),
				),
			);
			return $sections;
		}

		/**
		 * Connection status: connect, or credits left and where to buy more.
		 */
		public function render_connection() {
			echo '<div class="hd-credits" data-stsw-credits>';
			if ( ! $this->library() ) {
				echo '<p class="hd-help">' . esc_html__( 'WPFactory AI is not available in this copy of the plugin.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
				return;
			}
			if ( ! $this->is_connected() ) {
				/* translators: %d: number of free credits. */
				echo '<p class="hd-help">' . esc_html( sprintf( __( 'Create a free WPFactory AI account with your email address (we send you a 6-digit code) and this site is connected. Each account gets %d free credits a month for Helpdesk on a live site (https); buy more when you need them (1 credit = 1 cent). Test and local sites can use bought credits.', 'support-ticket-system-for-woocommerce' ), $this->free_monthly() ) ) . '</p>';
				if ( current_user_can( 'manage_options' ) ) {
					echo '<p><a class="hd-btn hd-btn--primary" data-stsw-credits-connect href="' . esc_url( $this->screen_url() ) . '">' . esc_html__( 'Create a free account and connect', 'support-ticket-system-for-woocommerce' ) . '</a></p>';
				} else {
					echo '<p class="hd-help">' . esc_html__( 'Ask a site administrator to connect the site.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				}
				echo '</div>';
				return;
			}
			$balance = $this->balance();
			echo '<p data-stsw-credits-balance>';
			if ( null === $balance ) {
				esc_html_e( 'Connected. The balance could not be read right now.', 'support-ticket-system-for-woocommerce' );
			} else {
				/* translators: %s: number of credits. */
				echo esc_html( sprintf( _n( 'Connected: %s credit available.', 'Connected: %s credits available.', $balance, 'support-ticket-system-for-woocommerce' ), number_format_i18n( $balance ) ) );
			}
			echo '</p><p><a class="hd-btn" href="' . esc_url( $this->library()->buy_url() ) . '">' . esc_html__( 'Buy credits', 'support-ticket-system-for-woocommerce' ) . '</a> <a class="hd-btn" href="' . esc_url( $this->screen_url() ) . '">' . esc_html__( 'Usage and account', 'support-ticket-system-for-woocommerce' ) . '</a></p></div>';
		}

		/**
		 * Credits link in the plugin header, while AI with credits is on.
		 */
		public function header_pill() {
			if ( ! $this->library() || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			/**
			 * Whether AI with WPFactory credits is in use (Pro answers for its own settings).
			 *
			 * @param bool $on In use.
			 */
			$on = (bool) apply_filters( 'wpfactory_wc_sts_ai_uses_credits', ! $this->handled_elsewhere() && $this->is_enabled() );
			if ( $on ) {
				echo wp_kses_post( $this->library()->pill( self::PRODUCT ) );
			}
		}

		/**
		 * Buttons in the inbox reply box (free edition).
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function render_buttons( $ticket_id ) {
			if ( $this->handled_elsewhere() ) {
				return;
			}
			if ( ! $this->is_enabled() || ! $this->is_connected() ) {
				$this->render_promo( $this->is_enabled() );
				return;
			}
			$url   = admin_url( 'admin-ajax.php' );
			$nonce = wp_create_nonce( 'wpfactory_wc_sts_credits_ai' );
			echo '<p class="stsw-credits-ai">';
			foreach ( array(
				'draft'   => __( 'Draft a reply with AI', 'support-ticket-system-for-woocommerce' ),
				'summary' => __( 'Summarize', 'support-ticket-system-for-woocommerce' ),
			) as $task => $label ) {
				echo '<button type="button" class="hd-btn hd-btn--sm" data-stsw-cai="' . esc_attr( $task ) . '" data-ticket="' . (int) $ticket_id . '" data-url="' . esc_url( $url ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-working="' . esc_attr__( 'Asking the AI...', 'support-ticket-system-for-woocommerce' ) . '" data-replace="' . esc_attr__( 'Replace the text in the reply box with the AI draft?', 'support-ticket-system-for-woocommerce' ) . '">' . esc_html( $label ) . '</button> ';
			}
			echo '</p><div id="stsw-credits-ai-out" class="hd-help" aria-live="polite"></div>';
		}

		/**
		 * AJAX: draft or summary for a ticket.
		 */
		public function ajax() {
			check_ajax_referer( 'wpfactory_wc_sts_credits_ai', 'nonce' );
			$ticket_id = absint( $_POST['ticket'] ?? 0 );
			$task      = 'summary' === sanitize_key( wp_unslash( $_POST['task'] ?? '' ) ) ? 'summary' : 'draft';
			if ( ! $this->is_enabled() || 'stsw_tickets' !== get_post_type( $ticket_id ) || ! WPFactory_WC_STS_Inc::get_main_instance()->can_manage_tickets() || ! current_user_can( 'edit_post', $ticket_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ) ), 403 );
			}
			$text = $this->complete( $this->system_prompt( $task, (string) get_option( 'wpfactory_wc_sts_ai_instructions', '' ) ), $this->build_context( $ticket_id, 'summary' === $task ), 'summary' === $task ? 'ticket-summary' : 'reply-draft' );
			if ( is_wp_error( $text ) ) {
				wp_send_json_error( array( 'message' => $text->get_error_message() ) );
			}
			$balance = $this->balance();
			wp_send_json_success(
				array(
					'text'    => sanitize_textarea_field( $text ),
					'balance' => $balance,
					'pill'    => $this->credits_label( $balance ),
				)
			);
		}
	}

endif;

return WPFactory_WC_STS_Credits::get_instance();
