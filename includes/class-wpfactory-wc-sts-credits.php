<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: AI with WPFactory AI credits.
 *
 * AI tools in the inbox (draft a reply, summarize, improve my reply, translate the customer's message), paid with
 * WPFactory AI credits through the bundled wpfactory/wpfactory-ai library (one site connection and one wallet for
 * every WPFactory plugin). Works in the free edition; Pro shows the same tools and can answer them with its other AI
 * services (filters `wpfactory_wc_sts_ai_ready` and `wpfactory_wc_sts_ai_complete`). Nothing is sent to WPFactory
 * until an administrator connects the site on the AI Credits screen, and nothing is sent until a team member clicks a
 * button. Drafts land in the reply box for review and are never sent on their own.
 *
 * @version 3.1.0
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
			add_action( 'wpfactory_wc_sts_settings_before_save', array( $this, 'map_choice' ) );
			add_action( 'wpfactory_wc_sts_inbox_ai', array( $this, 'render_buttons' ), 20 );
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
			echo '<div class="menu-note ai-promo" data-stsw-ai-promo>';
			if ( $on ) {
				echo '<p class="small">' . esc_html__( 'AI replies are on, but this site is not connected to WPFactory AI yet.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				echo '<a class="btn btn-text btn-sm" href="' . esc_url( $this->screen_url() ) . '">' . esc_html__( 'Connect', 'support-ticket-system-for-woocommerce' ) . '</a>';
			} else {
				/* translators: %d: number of free credits. */
				echo '<p class="small">' . esc_html( sprintf( __( 'Draft, improve, summarize and translate replies with AI. Create a free WPFactory AI account and get %d free credits a month (about one credit per draft).', 'support-ticket-system-for-woocommerce' ), $this->free_monthly() ) ) . '</p>';
				echo '<a class="btn btn-text btn-sm" href="' . esc_url( $this->settings_url() ) . '" data-stsw-ai-promo-setup>' . esc_html__( 'Set up AI', 'support-ticket-system-for-woocommerce' ) . '</a>';
				echo '<a class="btn btn-text btn-sm ai-promo-hide" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpfactory_wc_sts_ai_promo_hide' ), 'wpfactory_wc_sts_ai_promo_hide' ) ) . '">' . esc_html__( 'Hide', 'support-ticket-system-for-woocommerce' ) . '</a>';
			}
			echo '</div>';
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
		 * Whether the AI tools are on: in the free edition when AI is turned on (a click before the site is connected
		 * explains how to connect); in Pro as its AI settings say.
		 *
		 * @return bool
		 */
		public function tools_on() {
			if ( ! $this->handled_elsewhere() ) {
				return $this->is_enabled();
			}
			/**
			 * Whether Pro's AI answers the inbox tools and the chat assistant now.
			 *
			 * @param bool $ready Ready.
			 */
			return (bool) apply_filters( 'wpfactory_wc_sts_ai_ready', false );
		}

		/**
		 * Asks the AI used by this edition: WPFactory AI credits, or the service chosen in Pro.
		 *
		 * @param string $system System prompt.
		 * @param string $prompt User prompt.
		 * @param string $task   Task shown in usage by task.
		 *
		 * @return string|WP_Error
		 */
		public function ai_complete( $system, $prompt, $task = 'ask' ) {
			if ( ! $this->handled_elsewhere() ) {
				return $this->complete( $system, $prompt, $task );
			}
			/**
			 * Pro's answer to an AI request (string or WP_Error).
			 *
			 * @param string|WP_Error $result Result.
			 * @param string          $system System prompt.
			 * @param string          $prompt User prompt.
			 * @param string          $task   Task.
			 */
			$result = apply_filters( 'wpfactory_wc_sts_ai_complete', new WP_Error( 'stsw_ai', __( 'AI is not set up.', 'support-ticket-system-for-woocommerce' ) ), $system, $prompt, $task );
			return is_wp_error( $result ) ? $result : (string) $result;
		}

		/**
		 * Store guidelines added to every request.
		 *
		 * @return string
		 */
		public function instructions() {
			$text = (string) get_option( 'wpfactory_wc_sts_ai_instructions', '' );
			/**
			 * Store guidelines for AI requests (Pro answers from its own setting).
			 *
			 * @param string $text Guidelines.
			 */
			return (string) apply_filters( 'wpfactory_wc_sts_ai_instructions', $text );
		}

		/**
		 * The inbox AI tools.
		 *
		 * @return array task => array( label, help )
		 */
		public function tools() {
			return array(
				'draft'     => array( __( 'Draft a reply', 'support-ticket-system-for-woocommerce' ), __( 'Writes the next reply into the reply box from the conversation and the order. You review it before sending.', 'support-ticket-system-for-woocommerce' ) ),
				'improve'   => array( __( 'Improve my reply', 'support-ticket-system-for-woocommerce' ), __( 'Fixes spelling and grammar and makes the text in the reply box clearer and friendlier, in the same language.', 'support-ticket-system-for-woocommerce' ) ),
				'summary'   => array( __( 'Summarize', 'support-ticket-system-for-woocommerce' ), __( 'The problem, what was done and what is still open, in a few lines.', 'support-ticket-system-for-woocommerce' ) ),
				'translate' => array( __( 'Translate', 'support-ticket-system-for-woocommerce' ), __( 'Shows the customer\'s latest message in your language. Drafts are written in the customer\'s language.', 'support-ticket-system-for-woocommerce' ) ),
			);
		}

		/**
		 * Language of the current user, for translations.
		 *
		 * @return string
		 */
		protected function user_language() {
			$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			if ( function_exists( 'locale_get_display_language' ) ) {
				$name = locale_get_display_language( $locale, 'en' );
				if ( $name && $name !== $locale ) {
					return $name;
				}
			}
			return 'the language of the locale ' . $locale;
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
			} elseif ( 'improve' === $task ) {
				$base .= ' Improve the store\'s draft reply given at the end: fix spelling and grammar, make it clear, friendly and short. Keep its language, meaning and facts, add no new promises or facts. Answer with the improved reply only.';
			} elseif ( 'translate' === $task ) {
				$base .= ' Translate the customer\'s latest message into ' . $this->user_language() . ' for a team member. Answer with the translation only. If it is already in that language, answer with a one-line summary of it instead.';
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
				'intro'  => __( 'When a team member clicks an AI button, the ticket conversation and the order details are sent to WPFactory AI and paid with WPFactory AI credits. Nothing is sent otherwise, and drafts are never sent to customers without a person reviewing them.', 'support-ticket-system-for-woocommerce' ),
				'render' => array( $this, 'render_settings' ),
				'fields' => array(
					'@wpfactory_wc_sts_ai_features'     => array(
						'type'   => 'html',
						'label'  => __( 'What AI does for your support', 'support-ticket-system-for-woocommerce' ),
						'render' => array( $this, 'render_features' ),
					),
					'@wpfactory_wc_sts_ai_enabled'      => array(
						'type'  => 'checkbox',
						'label' => __( 'Turn on AI tools in the reply box', 'support-ticket-system-for-woocommerce' ),
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
		 * Settings > AI (free edition): how AI connects (WPFactory AI credits; your own key is a Pro choice), limits,
		 * and what AI does.
		 *
		 * @param WPFactory_WC_STS_Init $init Settings page.
		 * @param string                $id   Section ID.
		 */
		public function render_settings( $init, $id ) {
			$enabled = '1' === (string) get_option( 'wpfactory_wc_sts_ai_enabled', '' );
			foreach ( array( '@wpfactory_wc_sts_ai_enabled', '@wpfactory_wc_sts_ai_connection', '@wpfactory_wc_sts_ai_features' ) as $key ) {
				$init->mark( $id, $key );
			}
			$balance = $this->is_connected() ? $this->balance() : null;
			/* translators: %s: number of credits, for example "95 credits". */
			$credits = null !== $balance ? sprintf( __( '%s left. Nothing to set up.', 'support-ticket-system-for-woocommerce' ), $this->credits_label( $balance ) ) : sprintf( /* translators: %d: free credits a month. */ __( 'Free account with %d free credits a month. Nothing else to set up.', 'support-ticket-system-for-woocommerce' ), $this->free_monthly() );
			$foot    = $enabled
				? '<span class="small muted">' . esc_html__( 'AI is on with WPFactory AI credits.', 'support-ticket-system-for-woocommerce' ) . '</span><button type="submit" class="btn btn-text btn-sm" name="stsw_ai_mode" value="off">' . esc_html__( 'Turn AI off', 'support-ticket-system-for-woocommerce' ) . '</button>'
				: '<span class="small muted">' . esc_html__( 'AI stays off until you choose and save.', 'support-ticket-system-for-woocommerce' ) . '</span>';
			$init->card_start(
				__( 'How should AI connect?', 'support-ticket-system-for-woocommerce' ),
				array(
					'id'     => $id,
					'action' => '',
					'intro'  => __( 'Used for reply drafts, summaries, translations and the chat assistant. Off until you choose.', 'support-ticket-system-for-woocommerce' ),
				)
			);
			echo '<div class="card-body stack">';
			$this->render_features( 'notice' );
			echo '<input type="hidden" name="stsw_ai_mode" value="" />';
			echo '<div class="choices" role="radiogroup" aria-label="' . esc_attr__( 'How AI connects', 'support-ticket-system-for-woocommerce' ) . '">';
			echo '<label class="choice"><input type="radio" name="stsw_ai_mode" value="credits"' . checked( $enabled, true, false ) . ' /><span><b>' . esc_html__( 'WPFactory AI credits', 'support-ticket-system-for-woocommerce' ) . '</b><span class="small muted">' . esc_html( $credits ) . '</span></span></label>';
			echo '<label class="choice is-locked"><input type="radio" name="stsw_ai_mode" value="key" disabled /><span><b>' . esc_html__( 'Your own API key', 'support-ticket-system-for-woocommerce' ) . ' ' . WPFactory_WC_STS_UI::chip( __( 'Pro', 'support-ticket-system-for-woocommerce' ), 'info' ) . '</b><span class="small muted">' . esc_html__( 'OpenAI, Anthropic or Google Gemini with your own key, in Helpdesk Pro.', 'support-ticket-system-for-woocommerce' ) . '</span></span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in chip().
			echo '</div><div class="choice-detail" data-hd-when="stsw_ai_mode=credits">';
			$this->render_connection();
			echo '</div></div>';
			$init->card_end( $foot );

			$foot = '';
			if ( null !== $balance ) {
				/* translators: %s: number of credits, for example "95 credits". */
				$foot = '<span class="small muted">' . esc_html( sprintf( __( 'Credits left: %s', 'support-ticket-system-for-woocommerce' ), $this->credits_label( $balance ) ) ) . '</span><a class="btn btn-text btn-sm" href="' . esc_url( $this->screen_url() ) . '">' . esc_html__( 'Usage and account', 'support-ticket-system-for-woocommerce' ) . '</a>';
			}
			$init->card( $id, __( 'Limits', 'support-ticket-system-for-woocommerce' ), array( '@wpfactory_wc_sts_ai_chat', '@wpfactory_wc_sts_ai_chat_user' ), array( 'foot' => $foot ) );

			$init->card_start( __( 'What AI does', 'support-ticket-system-for-woocommerce' ) );
			$this->render_features( 'list' );
			$init->rows( $id, array( '@wpfactory_wc_sts_ai_quality', '@wpfactory_wc_sts_ai_instructions' ) );
			$init->card_end();
			$init->card( $id, __( 'More AI settings', 'support-ticket-system-for-woocommerce' ) );
		}

		/**
		 * Settings > AI: the connection choice becomes the posted "AI on" value (the nonce is checked by the core).
		 *
		 * @param string[] $tabs Sections saved.
		 */
		public function map_choice( $tabs ) {
			if ( $this->handled_elsewhere() || ! isset( $_POST['stsw_ai_mode'] ) || ! array_intersect( (array) $tabs, array( '', 'ai' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the core.
				return;
			}
			$mode                                 = sanitize_key( wp_unslash( $_POST['stsw_ai_mode'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the core.
			$_POST['wpfactory_wc_sts_ai_enabled'] = 'credits' === $mode ? '1' : '';
		}

		/**
		 * Connection status: connect, or credits left and where to buy more.
		 */
		public function render_connection() {
			echo '<div class="credits-box" data-stsw-credits>';
			if ( ! $this->library() ) {
				echo '<p class="hint">' . esc_html__( 'WPFactory AI is not available in this copy of the plugin.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
				return;
			}
			if ( ! $this->is_connected() ) {
				/* translators: %d: number of free credits. */
				echo '<p class="hint">' . esc_html( sprintf( __( 'Create a free WPFactory AI account with your email address (we send you a 6-digit code) and this site is connected. Each account gets %d free credits a month for Helpdesk on a live site (https); buy more when you need them (1 credit = 1 cent). Test and local sites can use bought credits.', 'support-ticket-system-for-woocommerce' ), $this->free_monthly() ) ) . '</p>';
				if ( current_user_can( 'manage_options' ) ) {
					echo '<p><a class="btn btn-primary btn-sm" data-stsw-credits-connect href="' . esc_url( $this->screen_url() ) . '">' . esc_html__( 'Create a free account and connect', 'support-ticket-system-for-woocommerce' ) . '</a></p>';
				} else {
					echo '<p class="hint">' . esc_html__( 'Ask a site administrator to connect the site.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				}
				echo '</div>';
				return;
			}
			$balance = $this->balance();
			echo '<p class="row-title" data-stsw-credits-balance>';
			if ( null === $balance ) {
				esc_html_e( 'Connected. The balance could not be read right now.', 'support-ticket-system-for-woocommerce' );
			} else {
				/* translators: %s: number of credits. */
				echo esc_html( sprintf( _n( 'Connected: %s credit available.', 'Connected: %s credits available.', $balance, 'support-ticket-system-for-woocommerce' ), number_format_i18n( $balance ) ) );
			}
			echo '</p><p class="credits-actions"><a class="btn btn-secondary btn-sm" href="' . esc_url( $this->library()->buy_url() ) . '">' . esc_html__( 'Buy credits', 'support-ticket-system-for-woocommerce' ) . '</a><a class="btn btn-text btn-sm" href="' . esc_url( $this->screen_url() ) . '">' . esc_html__( 'Usage and account', 'support-ticket-system-for-woocommerce' ) . '</a></p></div>';
		}

		/**
		 * What the AI does (tools and chat assistant), and a notice while AI is on but the site is not connected.
		 *
		 * @param string $part `all`, `notice` (the not connected notice only) or `list` (the list only).
		 */
		public function render_features( $part = 'all' ) {
			if ( 'list' !== $part && ! $this->handled_elsewhere() && ! $this->is_connected() ) {
				$hidden = '1' === (string) get_option( 'wpfactory_wc_sts_ai_enabled', '' ) ? '' : ' hidden';
				echo '<div class="banner banner-warn" data-stsw-ai-notconnected' . esc_attr( $hidden ) . '><span>' . esc_html__( 'AI replies are on, but this site is not connected to WPFactory AI yet.', 'support-ticket-system-for-woocommerce' ) . '</span><a class="btn btn-text btn-sm" href="' . esc_url( $this->screen_url() ) . '">' . esc_html__( 'Connect', 'support-ticket-system-for-woocommerce' ) . '</a></div>';
			}
			if ( 'notice' === $part ) {
				return;
			}
			$items         = $this->tools();
			$items['chat'] = array( __( 'Chat assistant', 'support-ticket-system-for-woocommerce' ), __( 'Answers live chat questions from your help articles, day and night, and hands the chat to your team when the articles do not cover it.', 'support-ticket-system-for-woocommerce' ) );
			echo '<ul class="ai-features" data-stsw-ai-features>';
			foreach ( $items as $task => $item ) {
				echo '<li class="row" data-task="' . esc_attr( $task ) . '"><div><p class="row-title">' . esc_html( $item[0] ) . '</p><div class="row-meta">' . esc_html( $item[1] ) . '</div></div></li>';
			}
			echo '</ul>';
			if ( $this->handled_elsewhere() ) {
				return;
			}
			/* translators: %d: number of free credits. */
			echo '<p class="card-intro">' . esc_html( sprintf( __( 'Included in the free plugin. A free WPFactory AI account gets %d credits a month on a live site, about one credit per draft.', 'support-ticket-system-for-woocommerce' ), $this->free_monthly() ) ) . '</p>';
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
			if ( ! $on ) {
				return;
			}
			$balance = $this->balance();
			/* translators: %s: number of credits, for example "95 credits". */
			$text = null === $balance ? __( 'AI credits', 'support-ticket-system-for-woocommerce' ) : sprintf( __( 'AI: %s', 'support-ticket-system-for-woocommerce' ), $this->credits_label( $balance ) );
			echo '<a class="btn btn-secondary btn-sm" href="' . esc_url( $this->screen_url() ) . '" data-stsw-credits-pill>' . esc_html( $text ) . '</a>';
		}

		/**
		 * Buttons in the inbox reply box (free edition).
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function render_buttons( $ticket_id ) {
			if ( $this->handled_elsewhere() ) {
				return; // Pro decides what shows, then calls render_tools().
			}
			ob_start();
			if ( ! $this->is_enabled() ) {
				$this->render_promo();
				$promo = (string) ob_get_clean();
				if ( '' !== $promo ) {
					$this->render_tools( $ticket_id, $promo, false );
				}
				return;
			}
			if ( ! $this->is_connected() ) {
				$this->render_promo( true );
			}
			$this->render_tools( $ticket_id, (string) ob_get_clean() );
		}

		/**
		 * The "Draft with AI" menu of the composer: the four AI tasks (their answers show under the reply box), more
		 * items from other modules, and a notice when AI still needs setting up.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $notice    Notice at the top of the menu (HTML).
		 * @param bool   $tasks     Show the AI tasks.
		 */
		public function render_tools( $ticket_id, $notice = '', $tasks = true ) {
			$url   = admin_url( 'admin-ajax.php' );
			$nonce = wp_create_nonce( 'wpfactory_wc_sts_credits_ai' );
			echo '<details class="menu ai-menu stsw-credits-ai" data-stsw-ai-menu><summary class="btn btn-secondary btn-sm ai-button">' . wp_kses( WPFactory_WC_STS_UI::icon( 'sparkle', 16 ), WPFactory_WC_STS_UI::svg_kses() ) . esc_html__( 'Draft with AI', 'support-ticket-system-for-woocommerce' ) . '</summary><div class="menu-panel end" role="group" aria-label="' . esc_attr__( 'AI tools', 'support-ticket-system-for-woocommerce' ) . '">';
			echo wp_kses_post( $notice );
			if ( $tasks ) {
				foreach ( $this->tools() as $task => $tool ) {
					echo '<button type="button" class="menu-item" data-stsw-cai="' . esc_attr( $task ) . '" data-ticket="' . (int) $ticket_id . '" data-url="' . esc_url( $url ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-working="' . esc_attr__( 'Asking the AI...', 'support-ticket-system-for-woocommerce' ) . '" data-replace="' . esc_attr__( 'Replace the text in the reply box with the AI draft?', 'support-ticket-system-for-woocommerce' ) . '" data-empty="' . esc_attr__( 'Write a reply first, then click Improve my reply.', 'support-ticket-system-for-woocommerce' ) . '"><b>' . esc_html( $tool[0] ) . '</b><span>' . esc_html( $tool[1] ) . '</span></button>';
				}
				/**
				 * More items in the "Draft with AI" menu (Pro: Suggest a resolution).
				 *
				 * @param int $ticket_id Ticket ID.
				 */
				do_action( 'wpfactory_wc_sts_inbox_ai_items', $ticket_id );
			}
			echo '</div></details>';
		}

		/**
		 * AJAX: draft or summary for a ticket.
		 */
		public function ajax() {
			check_ajax_referer( 'wpfactory_wc_sts_credits_ai', 'nonce' );
			$ticket_id = absint( $_POST['ticket'] ?? 0 );
			$task      = sanitize_key( wp_unslash( $_POST['task'] ?? '' ) );
			$task      = isset( $this->tools()[ $task ] ) ? $task : 'draft';
			if ( ! $this->tools_on() || 'stsw_tickets' !== get_post_type( $ticket_id ) || ! WPFactory_WC_STS_Inc::get_main_instance()->can_manage_tickets() || ! current_user_can( 'edit_post', $ticket_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ) ), 403 );
			}
			if ( ! $this->handled_elsewhere() && ! $this->is_connected() ) {
				wp_send_json_error(
					array(
						'message' => __( 'AI replies are on, but this site is not connected to WPFactory AI yet.', 'support-ticket-system-for-woocommerce' ),
						'link'    => $this->screen_url(),
						'label'   => __( 'Connect', 'support-ticket-system-for-woocommerce' ),
					)
				);
			}
			$prompt = $this->build_context( $ticket_id, 'summary' === $task );
			if ( 'improve' === $task ) {
				$draft = trim( sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) ) );
				if ( '' === $draft ) {
					wp_send_json_error( array( 'message' => __( 'Write a reply first, then click Improve my reply.', 'support-ticket-system-for-woocommerce' ) ) );
				}
				$prompt .= "\n\nDraft reply to improve:\n" . $draft;
			}
			$names = array(
				'draft'     => 'reply-draft',
				'summary'   => 'ticket-summary',
				'improve'   => 'reply-improve',
				'translate' => 'translate',
			);
			$text  = $this->ai_complete( $this->system_prompt( $task, $this->instructions() ), $prompt, $names[ $task ] );
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
