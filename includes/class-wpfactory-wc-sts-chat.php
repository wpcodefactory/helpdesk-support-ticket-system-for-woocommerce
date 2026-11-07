<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: live chat.
 *
 * A self-hosted chat widget. Each chat is a ticket (channel `chat`), so it shows in the inbox and stays a ticket when
 * the visitor leaves. Visitors see the chat when a team member is available (the inbox "Available" switch is on and
 * they were active in the last 5 minutes); otherwise they get an offline form that opens a ticket. The widget polls
 * every 3 seconds while visible, every 15 seconds while the tab is hidden, and stops when the chat is closed.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Chat' ) ) :

	/**
	 * WPFactory_WC_STS_Chat class.
	 */
	class WPFactory_WC_STS_Chat {

		/**
		 * Option: chat on ('1').
		 */
		const OPTION = 'wpfactory_wc_sts_chat';

		/**
		 * Option: greeting.
		 */
		const OPTION_GREETING = 'wpfactory_wc_sts_chat_greeting';

		/**
		 * Ticket meta: channel (`chat`).
		 */
		const META_CHANNEL = '_stsw_channel';

		/**
		 * Ticket meta: secret of the visitor's chat session.
		 */
		const META_KEY = '_stsw_chat_key';

		/**
		 * User meta: available for chats ('1').
		 */
		const META_AVAILABLE = '_stsw_available';

		/**
		 * User meta: last activity in the inbox (timestamp).
		 */
		const META_SEEN = '_stsw_last_seen';

		/**
		 * Seconds an agent counts as active.
		 */
		const ONLINE_WINDOW = 300;

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Chat|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Chat
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
			foreach ( array( 'status', 'start', 'send', 'poll' ) as $endpoint ) {
				add_action( 'wp_ajax_wpfactory_wc_sts_chat_' . $endpoint, array( $this, 'ajax_' . $endpoint ) );
				add_action( 'wp_ajax_nopriv_wpfactory_wc_sts_chat_' . $endpoint, array( $this, 'ajax_' . $endpoint ) );
			}
			add_action( 'wp_ajax_wpfactory_wc_sts_available', array( $this, 'ajax_available' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( 'wp_footer', array( $this, 'render_widget' ) );
			add_action( 'admin_init', array( $this, 'mark_seen' ) );
			add_action( 'wpfactory_wc_sts_inbox_rail', array( $this, 'render_switch' ), 5 );
			add_action( 'wpfactory_wc_sts_inbox_row_meta', array( $this, 'row_badge' ), 5 );
			add_filter( 'wpfactory_wc_sts_inbox_views', array( $this, 'view' ) );
			add_filter( 'wpfactory_wc_sts_settings_general', array( $this, 'settings' ) );
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
		 * Whether the chat is on.
		 *
		 * @return bool
		 */
		public function is_enabled() {
			return '1' === (string) get_option( self::OPTION );
		}

		/**
		 * Settings fields (General tab).
		 *
		 * @param array $fields Fields.
		 *
		 * @return array
		 */
		public function settings( $fields ) {
			$fields[ '@' . self::OPTION ]          = array(
				'type'        => 'checkbox',
				'label'       => __( 'Live chat on your store', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'A chat button on every page. Visitors can chat while a team member has "Available" on in the inbox; otherwise they leave a message that becomes a ticket. Runs on your own site, no outside service.', 'support-ticket-system-for-woocommerce' ),
			);
			$fields[ '@' . self::OPTION_GREETING ] = array(
				'type'        => 'text',
				'label'       => __( 'Chat greeting', 'support-ticket-system-for-woocommerce' ),
				'placeholder' => __( 'Hi! How can we help?', 'support-ticket-system-for-woocommerce' ),
			);
			return $fields;
		}

		/*
		|--------------------------------------------------------------------------
		| Availability
		|--------------------------------------------------------------------------
		*/

		/**
		 * Records activity of staff on the plugin screens.
		 */
		public function mark_seen() {
			if ( wp_doing_ajax() || ! WPFactory_WC_STS_UI::is_screen() || ! $this->core()->can_manage_tickets() ) {
				return;
			}
			update_user_meta( get_current_user_id(), self::META_SEEN, time() );
		}

		/**
		 * Staff who can take chats now.
		 *
		 * @return int[] User IDs.
		 */
		public function get_online_agents() {
			$users  = get_users(
				array(
					'meta_key'   => self::META_AVAILABLE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'     => 'ID',
					'number'     => 50,
				)
			);
			$online = array();
			foreach ( $users as $id ) {
				$seen = (int) get_user_meta( (int) $id, self::META_SEEN, true );
				if ( $seen >= time() - self::ONLINE_WINDOW && $this->core()->can_manage_tickets( (int) $id ) ) {
					$online[] = (int) $id;
				}
			}
			return $online;
		}

		/**
		 * Whether a visitor can chat now.
		 *
		 * @return bool
		 */
		public function is_online() {
			return (bool) $this->get_online_agents();
		}

		/**
		 * "Available for chat" switch at the top of the inbox.
		 */
		public function render_switch() {
			if ( ! $this->is_enabled() ) {
				return;
			}
			$on = '1' === (string) get_user_meta( get_current_user_id(), self::META_AVAILABLE, true );
			echo '<label class="hd-switchrow hd-available"><input type="checkbox" role="switch" class="hd-switch" data-hd-available' . checked( $on, true, false ) . ' /> <span><strong>' . esc_html__( 'Available for chat', 'support-ticket-system-for-woocommerce' ) . '</strong><span class="hd-help">' . esc_html__( 'Visitors can chat while this is on and you are active.', 'support-ticket-system-for-woocommerce' ) . '</span></span></label>';
		}

		/**
		 * Saves the switch and keeps the agent active (called by the inbox every minute).
		 */
		public function ajax_available() {
			check_ajax_referer( WPFactory_WC_STS_Inbox::NONCE );
			if ( ! $this->core()->can_manage_tickets() ) {
				wp_send_json_error( null, 403 );
			}
			$user = get_current_user_id();
			if ( isset( $_POST['available'] ) ) {
				$on = sanitize_text_field( wp_unslash( $_POST['available'] ) );
				update_user_meta( $user, self::META_AVAILABLE, '1' === $on ? '1' : '' );
			}
			update_user_meta( $user, self::META_SEEN, time() );
			wp_send_json_success( array( 'online' => $this->is_online() ) );
		}

		/*
		|--------------------------------------------------------------------------
		| Inbox
		|--------------------------------------------------------------------------
		*/

		/**
		 * "Live chats" view.
		 *
		 * @param array $views Views.
		 *
		 * @return array
		 */
		public function view( $views ) {
			if ( ! $this->is_enabled() ) {
				return $views;
			}
			$chats = array(
				'chats' => array(
					'label' => __( 'Live chats', 'support-ticket-system-for-woocommerce' ),
					'args'  => array(
						'tax_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => 'stsw_tickets_status',
								'field'    => 'slug',
								'terms'    => array( 'closed' ),
								'operator' => 'NOT IN',
							),
						),
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'   => self::META_CHANNEL,
								'value' => 'chat',
							),
						),
					),
				),
			);
			$pos   = array_search( 'waiting', array_keys( $views ), true );
			return false === $pos ? $views + $chats : array_slice( $views, 0, $pos, true ) + $chats + array_slice( $views, $pos, null, true );
		}

		/**
		 * "Chat" tag on list rows.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function row_badge( $ticket_id ) {
			if ( 'chat' === get_post_meta( $ticket_id, self::META_CHANNEL, true ) ) {
				echo '<span class="hd-tag">' . esc_html__( 'Chat', 'support-ticket-system-for-woocommerce' ) . '</span>';
			}
		}

		/*
		|--------------------------------------------------------------------------
		| Widget
		|--------------------------------------------------------------------------
		*/

		/**
		 * Widget assets.
		 */
		public function enqueue() {
			if ( ! $this->is_enabled() || is_admin() ) {
				return;
			}
			wp_enqueue_style( 'wpfactory-wc-sts-chat', plugins_url( 'assets/css/chat.css', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION );
			wp_enqueue_script( 'wpfactory-wc-sts-chat', plugins_url( 'assets/js/chat.js', WPFACTORY_WC_STS_FILE ), array(), WPFACTORY_WC_STS_VERSION, true );
			$user = wp_get_current_user();
			wp_localize_script(
				'wpfactory-wc-sts-chat',
				'WPFactory_WC_STS_Chat',
				array(
					'ajaxurl'  => admin_url( 'admin-ajax.php' ),
					'name'     => $user->exists() ? $this->core()->get_user_name( $user->ID ) : '',
					'email'    => $user->exists() ? $user->user_email : '',
					'you'      => __( 'You', 'support-ticket-system-for-woocommerce' ),
					'sending'  => __( 'Sending...', 'support-ticket-system-for-woocommerce' ),
					'error'    => __( 'Your message was not sent. Please try again.', 'support-ticket-system-for-woocommerce' ),
					'closed'   => __( 'This chat has ended. Start a new one any time.', 'support-ticket-system-for-woocommerce' ),
					'received' => __( 'Thanks, we received your message and will reply by email.', 'support-ticket-system-for-woocommerce' ),
					'newChat'  => __( 'New message from support', 'support-ticket-system-for-woocommerce' ),
				)
			);
		}

		/**
		 * The chat button and panel (filled by the script).
		 */
		public function render_widget() {
			if ( ! $this->is_enabled() || is_admin() ) {
				return;
			}
			$greeting = trim( (string) get_option( self::OPTION_GREETING, '' ) );
			$greeting = '' !== $greeting ? $greeting : __( 'Hi! How can we help?', 'support-ticket-system-for-woocommerce' );
			?>
			<div class="stsw-chat" data-stsw-chat>
				<button type="button" class="stsw-chat__launcher" aria-expanded="false" aria-controls="stsw-chat-panel">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
					<span><?php esc_html_e( 'Chat with us', 'support-ticket-system-for-woocommerce' ); ?></span>
				</button>
				<section class="stsw-chat__panel" id="stsw-chat-panel" role="dialog" aria-modal="false" aria-labelledby="stsw-chat-title" hidden>
					<header class="stsw-chat__head">
						<h2 class="stsw-chat__title" id="stsw-chat-title"><?php esc_html_e( 'Chat with us', 'support-ticket-system-for-woocommerce' ); ?></h2>
						<p class="stsw-chat__state" data-stsw-chat-state></p>
						<button type="button" class="stsw-chat__close" data-stsw-chat-close aria-label="<?php esc_attr_e( 'Close the chat window', 'support-ticket-system-for-woocommerce' ); ?>">&times;</button>
					</header>
					<ol class="stsw-chat__log" data-stsw-chat-log aria-live="polite" aria-label="<?php esc_attr_e( 'Messages', 'support-ticket-system-for-woocommerce' ); ?>">
						<li class="stsw-chat__msg stsw-chat__msg--store"><?php echo esc_html( $greeting ); ?></li>
					</ol>
					<form class="stsw-chat__start" data-stsw-chat-start novalidate>
						<p class="stsw-chat__offline" data-stsw-chat-offline hidden><?php esc_html_e( 'We are not online right now. Leave a message and we will reply by email.', 'support-ticket-system-for-woocommerce' ); ?></p>
						<label for="stsw-chat-name"><?php esc_html_e( 'Your name', 'support-ticket-system-for-woocommerce' ); ?></label>
						<input type="text" id="stsw-chat-name" name="name" autocomplete="name" required />
						<label for="stsw-chat-email"><?php esc_html_e( 'Email', 'support-ticket-system-for-woocommerce' ); ?></label>
						<input type="email" id="stsw-chat-email" name="email" autocomplete="email" required />
						<label for="stsw-chat-first"><?php esc_html_e( 'Message', 'support-ticket-system-for-woocommerce' ); ?></label>
						<textarea id="stsw-chat-first" name="message" rows="3" required></textarea>
						<div class="stsw-chat__hp" aria-hidden="true"><label for="stsw-chat-website">Website</label><input type="text" id="stsw-chat-website" name="website" tabindex="-1" autocomplete="off" /></div>
						<p class="stsw-chat__error" role="alert" data-stsw-chat-error></p>
						<button type="submit" class="button wp-element-button"><?php esc_html_e( 'Start chat', 'support-ticket-system-for-woocommerce' ); ?></button>
					</form>
					<form class="stsw-chat__send" data-stsw-chat-send hidden>
						<label for="stsw-chat-message" class="screen-reader-text"><?php esc_html_e( 'Your message', 'support-ticket-system-for-woocommerce' ); ?></label>
						<textarea id="stsw-chat-message" name="message" rows="2" placeholder="<?php esc_attr_e( 'Write a message', 'support-ticket-system-for-woocommerce' ); ?>" required></textarea>
						<button type="submit" class="button wp-element-button"><?php esc_html_e( 'Send', 'support-ticket-system-for-woocommerce' ); ?></button>
					</form>
				</section>
			</div>
			<?php
		}

		/*
		|--------------------------------------------------------------------------
		| Endpoints
		|--------------------------------------------------------------------------
		*/

		/**
		 * Too many requests from this address (per minute window).
		 *
		 * @param string $what  Bucket.
		 * @param int    $limit Allowed per 10 minutes.
		 *
		 * @return bool
		 */
		protected function limited( $what, $limit ) {
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$key = 'stsw_chat_' . $what . '_' . md5( $ip );
			$n   = (int) get_transient( $key );
			if ( $n >= $limit ) {
				return true;
			}
			set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
			return false;
		}

		/**
		 * Checks the visitor's session.
		 *
		 * @return int Ticket ID (ends the request when invalid).
		 */
		protected function session_ticket() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- the chat key is the visitor's secret.
			$ticket = absint( $_POST['ticket'] ?? 0 );
			$key    = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$stored = $ticket ? (string) get_post_meta( $ticket, self::META_KEY, true ) : '';
			if ( ! $this->is_enabled() || '' === $stored || '' === $key || ! hash_equals( $stored, $key ) || 'stsw_tickets' !== get_post_type( $ticket ) ) {
				wp_send_json_error( array( 'message' => 'session' ), 403 );
			}
			return $ticket;
		}

		/**
		 * Messages after an ID, as the visitor sees them.
		 *
		 * @param int $ticket_id Ticket ID.
		 * @param int $after     Last reply ID the visitor has (-1: include the first message).
		 *
		 * @return array
		 */
		public function messages( $ticket_id, $after ) {
			$core  = $this->core();
			$out   = array();
			$store = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			if ( $after < 0 ) {
				$out[] = array(
					'id'   => 0,
					'by'   => 'you',
					'text' => (string) get_post_field( 'post_content', $ticket_id, 'raw' ),
				);
			}
			foreach ( $core->get_replies( $ticket_id ) as $row ) {
				if ( (int) $row->id <= $after ) {
					continue;
				}
				$out[] = array(
					'id'   => (int) $row->id,
					'by'   => (int) $row->agent > 0 ? 'store' : 'you',
					'name' => (int) $row->agent > 0 ? $store : '',
					'text' => (string) $row->content,
				);
			}
			return $out;
		}

		/**
		 * Online state and a fresh nonce (pages may be cached).
		 */
		public function ajax_status() {
			if ( ! $this->is_enabled() ) {
				wp_send_json_error( null, 404 );
			}
			wp_send_json_success(
				array(
					'online' => $this->is_online(),
					'nonce'  => wp_create_nonce( 'wpfactory_wc_sts_chat' ),
				)
			);
		}

		/**
		 * Starts a chat (online) or leaves a message (offline): both open a ticket.
		 */
		public function ajax_start() {
			check_ajax_referer( 'wpfactory_wc_sts_chat' );
			if ( ! $this->is_enabled() ) {
				wp_send_json_error( null, 404 );
			}
			$user    = wp_get_current_user();
			$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
			$email   = $user->exists() ? $user->user_email : sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
			$message = trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) );
			if ( '' !== (string) wp_unslash( $_POST['website'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- honeypot, only checked for emptiness.
				wp_send_json_error( array( 'message' => __( 'Your message was not sent. Please try again.', 'support-ticket-system-for-woocommerce' ) ), 400 );
			}
			if ( ! is_email( $email ) || '' === $message ) {
				wp_send_json_error( array( 'message' => __( 'Please enter your email and a message.', 'support-ticket-system-for-woocommerce' ) ), 400 );
			}
			if ( $this->limited( 'start', 5 ) ) {
				wp_send_json_error( array( 'message' => __( 'Too many chats from your connection. Please try again in a few minutes.', 'support-ticket-system-for-woocommerce' ) ), 429 );
			}
			$online = $this->is_online();
			$title  = wp_html_excerpt( preg_replace( '/\s+/', ' ', $message ), 60, '...' );
			$ticket = $this->core()->create_ticket(
				array(
					'title'       => $title,
					'content'     => function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 5000 ) : substr( $message, 0, 5000 ),
					'user_id'     => $user->exists() ? (int) $user->ID : 0,
					'guest_email' => $user->exists() ? '' : $email,
					'reason'      => 'other',
				)
			);
			if ( is_wp_error( $ticket ) ) {
				wp_send_json_error( array( 'message' => __( 'Your message was not sent. Please try again.', 'support-ticket-system-for-woocommerce' ) ), 500 );
			}
			$key = wp_generate_password( 32, false );
			update_post_meta( $ticket, self::META_CHANNEL, 'chat' );
			update_post_meta( $ticket, self::META_KEY, $key );
			if ( '' !== $name && ! $user->exists() ) {
				update_post_meta( $ticket, '_stsw_guest_name', $name );
			}
			/**
			 * A chat started (Pro: the AI assistant may answer from the knowledge base).
			 *
			 * @param int  $ticket Ticket ID.
			 * @param bool $online A team member is available.
			 */
			do_action( 'wpfactory_wc_sts_chat_started', $ticket, $online );
			wp_send_json_success(
				array(
					'ticket'   => $ticket,
					'key'      => $key,
					'online'   => $online,
					'messages' => $online ? $this->messages( $ticket, -1 ) : array(),
				)
			);
		}

		/**
		 * A visitor message in a running chat.
		 */
		public function ajax_send() {
			$ticket  = $this->session_ticket();
			$message = trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in session_ticket().
			if ( '' === $message ) {
				wp_send_json_error( array( 'message' => __( 'Write a message first.', 'support-ticket-system-for-woocommerce' ) ), 400 );
			}
			if ( $this->limited( 'send', 60 ) ) {
				wp_send_json_error( array( 'message' => __( 'You are sending messages too fast. Please wait a moment.', 'support-ticket-system-for-woocommerce' ) ), 429 );
			}
			$author = $this->core()->get_ticket_user_id( $ticket );
			// Staff are in the chat: no email for every line. Offline, the store is emailed as for any reply.
			$id = $this->core()->add_reply( $ticket, $message, $author, false, array( 'notify' => ! $this->is_online() ) );
			if ( ! $id ) {
				wp_send_json_error( array( 'message' => __( 'Your message was not sent. Please try again.', 'support-ticket-system-for-woocommerce' ) ), 500 );
			}
			/**
			 * A visitor wrote in a chat (Pro: the AI assistant may answer).
			 *
			 * @param int $ticket Ticket ID.
			 * @param int $id     Reply ID.
			 */
			do_action( 'wpfactory_wc_sts_chat_message', $ticket, $id );
			wp_send_json_success( array( 'id' => $id ) );
		}

		/**
		 * New messages for the visitor.
		 */
		public function ajax_poll() {
			$ticket = $this->session_ticket();
			$after  = (int) ( $_POST['after'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in session_ticket().
			wp_send_json_success(
				array(
					'messages' => $this->messages( $ticket, max( -1, $after ) ),
					'closed'   => 'closed' === $this->core()->get_ticket_status_slug( $ticket ),
					'online'   => $this->is_online(),
				)
			);
		}
	}

endif;

return WPFactory_WC_STS_Chat::get_instance();
