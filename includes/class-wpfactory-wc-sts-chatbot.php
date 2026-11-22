<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: AI assistant in the live chat.
 *
 * When it is on (and AI is set up), the assistant answers chat messages using only the store's
 * knowledge base articles. When the articles do not cover the question it hands the chat to the team: the ticket stays
 * in "Needs reply" and the store is emailed. Every AI answer is logged as a private note naming the articles used.
 *
 * @version 3.1.0
 * @since   3.1.0 (Pro 3.1.0; in the free edition since 3.1.0)
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Chatbot' ) ) :

	/**
	 * WPFactory_WC_STS_Chatbot class.
	 */
	class WPFactory_WC_STS_Chatbot {

		/**
		 * Answer the model gives when the articles do not cover the question.
		 */
		const HANDOFF = 'HANDOFF';

		/**
		 * Ticket meta: the chat was handed to the team.
		 */
		const META_HANDOFF = '_stsw_ai_handoff';

		/**
		 * Ticket meta: IDs of replies written by the assistant.
		 */
		const META_REPLIES = '_stsw_ai_replies';

		/**
		 * Most AI answers in one chat.
		 */
		const MAX_ANSWERS = 8;

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Chatbot|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Chatbot
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
			add_filter( 'wpfactory_wc_sts_settings_sections', array( $this, 'settings' ), 20 );
			add_filter( 'wpfactory_wc_sts_chat_online', array( $this, 'online' ) );
			add_action( 'wpfactory_wc_sts_chat_started', array( $this, 'on_start' ), 10, 2 );
			add_action( 'wpfactory_wc_sts_chat_message', array( $this, 'on_message' ), 10, 2 );
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
		 * AI tools (WPFactory AI credits, or Pro's AI service).
		 *
		 * @return WPFactory_WC_STS_Credits
		 */
		protected function ai() {
			return WPFactory_WC_STS_Credits::get_instance();
		}

		/**
		 * A setting; the names Pro used before 4.1.0 still count until the setting is saved.
		 *
		 * @param string $key `chat` or `chat_user`.
		 *
		 * @return string
		 */
		protected function opt( $key ) {
			$value = get_option( 'wpfactory_wc_sts_ai_' . $key, null );
			return (string) ( null === $value ? get_option( 'wpfactory_wc_sts_pro_ai_' . $key, '' ) : $value );
		}

		/**
		 * Whether the assistant answers chats: turned on, AI ready (and, with WPFactory AI credits, connected).
		 *
		 * @return bool
		 */
		public function is_enabled() {
			$ai = $this->ai();
			if ( '1' !== $this->opt( 'chat' ) || ! $ai->tools_on() || ! WPFactory_WC_STS_Chat::get_instance()->is_enabled() ) {
				return false;
			}
			return $ai->handled_elsewhere() || $ai->is_connected();
		}

		/**
		 * Fields in the AI tab.
		 *
		 * @param array $sections Sections.
		 *
		 * @return array
		 */
		public function settings( $sections ) {
			$key = isset( $sections['pro_ai'] ) ? 'pro_ai' : ( isset( $sections['ai'] ) ? 'ai' : '' );
			if ( '' === $key ) {
				return $sections;
			}
			$sections[ $key ]['fields']['@wpfactory_wc_sts_ai_chat']      = array(
				'type'        => 'checkbox',
				'label'       => __( 'Answer live chats from the knowledge base', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'The assistant replies in the chat using only your published help articles, also when nobody is available. When the articles do not cover a question, it hands the chat to your team and the store is emailed. Chat messages and matching articles are sent to the AI service above.', 'support-ticket-system-for-woocommerce' ),
				'default'     => (string) get_option( 'wpfactory_wc_sts_pro_ai_chat', '' ),
			);
			$sections[ $key ]['fields']['@wpfactory_wc_sts_ai_chat_user'] = array(
				'type'        => 'user',
				'label'       => __( 'Assistant answers as', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'Team member shown as the author of AI answers in the inbox. Customers see the store name.', 'support-ticket-system-for-woocommerce' ),
				'default'     => (string) get_option( 'wpfactory_wc_sts_pro_ai_chat_user', '' ),
			);
			return $sections;
		}

		/**
		 * The chat stays open while the assistant answers.
		 *
		 * @param bool $online A team member is available.
		 *
		 * @return bool
		 */
		public function online( $online ) {
			return $online || $this->is_enabled();
		}

		/**
		 * Author of assistant replies.
		 *
		 * @return int
		 */
		protected function author() {
			$user = (int) $this->opt( 'chat_user' );
			if ( $user && get_userdata( $user ) ) {
				return $user;
			}
			$admins = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			);
			return $admins ? (int) $admins[0] : 0;
		}

		/**
		 * A team member answered in the last 10 minutes: the assistant stays quiet.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return bool
		 */
		protected function human_active( $ticket_id ) {
			$mine = array_map( 'intval', (array) get_post_meta( $ticket_id, self::META_REPLIES, true ) );
			foreach ( array_reverse( $this->core()->get_replies( $ticket_id ) ) as $row ) {
				if ( (int) $row->agent > 0 && ! in_array( (int) $row->id, $mine, true ) ) {
					return strtotime( $row->creationdate . ' UTC' ) > time() - 10 * MINUTE_IN_SECONDS;
				}
			}
			return false;
		}

		/**
		 * First message of a chat.
		 *
		 * @param int  $ticket_id Ticket ID.
		 * @param bool $online    Chat open.
		 */
		public function on_start( $ticket_id, $online ) {
			if ( $online ) {
				$this->answer( $ticket_id, (string) get_post_field( 'post_content', $ticket_id, 'raw' ) );
			}
		}

		/**
		 * Later messages of a chat.
		 *
		 * @param int $ticket_id Ticket ID.
		 * @param int $reply_id  Reply ID.
		 */
		public function on_message( $ticket_id, $reply_id ) {
			global $wpdb;
			$text = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT content FROM %i WHERE id = %d', $wpdb->prefix . $this->core()->table_name, $reply_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->answer( $ticket_id, $text );
		}

		/**
		 * Answers one visitor message from the knowledge base, or hands the chat to the team.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $question  Visitor message.
		 *
		 * @return string `answered`, `handoff`, or why it did nothing.
		 */
		public function answer( $ticket_id, $question ) {
			if ( ! $this->is_enabled() ) {
				return 'off';
			}
			if ( get_post_meta( $ticket_id, self::META_HANDOFF, true ) || $this->human_active( $ticket_id ) ) {
				return 'team';
			}
			$mine = array_map( 'intval', (array) get_post_meta( $ticket_id, self::META_REPLIES, true ) );
			if ( count( $mine ) >= self::MAX_ANSWERS ) {
				return $this->handoff( $ticket_id, __( 'The assistant reached its answer limit for this chat.', 'support-ticket-system-for-woocommerce' ) );
			}
			$articles = WPFactory_WC_STS_KB::get_instance()->search( $question, 3 );
			if ( ! $articles ) {
				return $this->handoff( $ticket_id, __( 'No help article matched the question.', 'support-ticket-system-for-woocommerce' ) );
			}
			$sources = '';
			foreach ( $articles as $article ) {
				$body     = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $article->post_content ) ) ) );
				$sources .= '## ' . get_the_title( $article ) . ' (' . get_permalink( $article ) . ")\n" . ( function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 2500 ) : substr( $body, 0, 2500 ) ) . "\n\n";
			}
			$history = '';
			foreach ( array_slice( $this->core()->get_replies( $ticket_id ), -6 ) as $row ) {
				$history .= ( (int) $row->agent > 0 ? 'Store: ' : 'Visitor: ' ) . $row->content . "\n";
			}
			$store  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$system = 'You are the chat assistant of the online store "' . $store . '". Answer the visitor using ONLY the help articles provided. Reply in the visitor\'s language, in 1 to 4 short sentences, plain text, and include the link of the article you used. Never invent prices, dates, policies or order details, and never promise a response time or a refund. If the articles do not answer the question, or the visitor asks about a specific order or wants a person, reply with exactly ' . self::HANDOFF . ' and nothing else.';
			$prompt = "Help articles:\n\n" . $sources . ( '' !== $history ? "Recent chat:\n" . $history . "\n" : '' ) . 'Visitor message: ' . $question;
			$result = $this->ai()->ai_complete( $system, $prompt, 'chat-answer' );
			if ( is_wp_error( $result ) ) {
				return $this->handoff( $ticket_id, $result->get_error_message() );
			}
			$text = trim( $result );
			if ( '' === $text || false !== strpos( $text, self::HANDOFF ) ) {
				return $this->handoff( $ticket_id, __( 'The help articles do not cover the question.', 'support-ticket-system-for-woocommerce' ) );
			}
			$reply = $this->core()->add_reply( $ticket_id, $text, $this->author(), true, array( 'notify' => false ) );
			if ( $reply ) {
				$mine[] = $reply;
				update_post_meta( $ticket_id, self::META_REPLIES, $mine );
			}
			$this->core()->add_note(
				$ticket_id,
				/* translators: %s: article titles. */
				sprintf( __( 'AI assistant answered in the chat using: %s', 'support-ticket-system-for-woocommerce' ), implode( ', ', array_map( 'get_the_title', $articles ) ) ),
				0
			);
			return 'answered';
		}

		/**
		 * Hands the chat to the team: tells the visitor, keeps the ticket in Needs reply, emails the store.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $why       Reason for the private note.
		 *
		 * @return string
		 */
		protected function handoff( $ticket_id, $why ) {
			update_post_meta( $ticket_id, self::META_HANDOFF, current_time( 'mysql', 1 ) );
			$reply = $this->core()->add_reply( $ticket_id, __( 'Thanks for your question. A member of our team will follow up here, and by email if you leave the chat.', 'support-ticket-system-for-woocommerce' ), $this->author(), true, array( 'notify' => false ) );
			$mine  = array_map( 'intval', (array) get_post_meta( $ticket_id, self::META_REPLIES, true ) );
			if ( $reply ) {
				$mine[] = $reply;
				update_post_meta( $ticket_id, self::META_REPLIES, $mine );
			}
			// The visitor still waits for a person.
			$this->core()->touch_activity( $ticket_id, 'customer' );
			$this->core()->add_note(
				$ticket_id,
				/* translators: %s: reason. */
				sprintf( __( 'AI assistant handed this chat to the team: %s', 'support-ticket-system-for-woocommerce' ), $why ),
				0
			);
			// Chat lines are not emailed while the chat is open: alert the store now with the visitor's last message
			// (the first message was emailed when the chat started).
			foreach ( array_reverse( $this->core()->get_replies( $ticket_id ) ) as $row ) {
				if ( 0 === (int) $row->agent ) {
					/** This action is documented in the core (class Inc, add_reply()). */
					do_action( 'wpfactory_wc_sts_reply_added', $ticket_id, (int) $row->id, false );
					break;
				}
			}
			return 'handoff';
		}
	}

endif;

return WPFactory_WC_STS_Chatbot::get_instance();
