<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: guest tickets.
 *
 * A customer without an account (or not logged in) enters the order number and billing email, opens a ticket for that
 * order and follows it through a signed link that expires. Shown on the My Account login screen and on pages with the
 * [stsw_user_tickets] shortcode. Off by default. Shared by the free and Pro editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Guest' ) ) :

	/**
	 * WPFactory_WC_STS_Guest class.
	 *
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	class WPFactory_WC_STS_Guest {

		/**
		 * Setting (shared by both editions).
		 */
		const OPTION = 'wpfactory_wc_sts_guest_tickets';

		/**
		 * Lifetime of a ticket link.
		 */
		const LINK_LIFETIME = 30 * DAY_IN_SECONDS;

		/**
		 * Lifetime of the order check (time to fill in the form).
		 */
		const ORDER_LIFETIME = HOUR_IN_SECONDS;

		/**
		 * Order lookups allowed per IP address in 15 minutes.
		 */
		const MAX_LOOKUPS = 10;

		/**
		 * Nonce action and field.
		 */
		const NONCE = 'wpfactory_wc_sts_guest';

		/**
		 * Result of a lookup in this request: order ID and email, or an error message.
		 *
		 * @var array
		 */
		protected $lookup = array();

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Guest|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Guest
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
			add_action( 'template_redirect', array( $this, 'handle_forms' ), 5 );
			add_action( 'woocommerce_before_customer_login_form', array( $this, 'render_on_login_screen' ) );
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
		 * Whether guest tickets are on.
		 *
		 * @return bool
		 */
		public function is_enabled() {
			return '1' === (string) get_option( self::OPTION );
		}

		/**
		 * HMAC of a value with a site secret.
		 *
		 * @param string $data Data.
		 *
		 * @return string
		 */
		protected function sign( $data ) {
			return hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
		}

		/**
		 * Signed link to a guest ticket.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string
		 */
		public function get_ticket_link( $ticket_id ) {
			$email   = strtolower( (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_GUEST_EMAIL, true ) );
			$expires = time() + self::LINK_LIFETIME;
			return add_query_arg( $this->get_ticket_args( $ticket_id, $email, $expires ), $this->get_base_url() );
		}

		/**
		 * Query args of a signed ticket link.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $email     Guest email.
		 * @param int    $expires   Expiry timestamp.
		 *
		 * @return array
		 */
		protected function get_ticket_args( $ticket_id, $email, $expires ) {
			return array(
				'stsw_ticket'  => (int) $ticket_id,
				'stsw_expires' => (int) $expires,
				'stsw_key'     => $this->sign( 'ticket|' . (int) $ticket_id . '|' . $email . '|' . (int) $expires ),
			);
		}

		/**
		 * Page guests use: the My Account page.
		 *
		 * @return string
		 */
		protected function get_base_url() {
			return wc_get_page_permalink( 'myaccount' );
		}

		/**
		 * Ticket ID from a valid signed link in this request (GET or POST), or 0.
		 *
		 * @return int
		 */
		public function get_requested_ticket_id() {
			// phpcs:disable WordPress.Security.NonceVerification -- the HMAC signature is the access check.
			$ticket_id = absint( $_REQUEST['stsw_ticket'] ?? 0 );
			$expires   = absint( $_REQUEST['stsw_expires'] ?? 0 );
			$key       = sanitize_text_field( wp_unslash( $_REQUEST['stsw_key'] ?? '' ) );
			// phpcs:enable WordPress.Security.NonceVerification
			if ( ! $ticket_id || ! $key || $expires < time() || 'stsw_tickets' !== get_post_type( $ticket_id ) ) {
				return 0;
			}
			$email = strtolower( (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_GUEST_EMAIL, true ) );
			if ( '' === $email ) {
				return 0;
			}
			return hash_equals( $this->sign( 'ticket|' . $ticket_id . '|' . $email . '|' . $expires ), $key ) ? $ticket_id : 0;
		}

		/**
		 * Whether this request carries a valid link for the ticket (used for file downloads).
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return bool
		 */
		public function request_has_ticket_access( $ticket_id ) {
			return (int) $ticket_id === $this->get_requested_ticket_id();
		}

		/**
		 * Client IP address (REMOTE_ADDR only: forwarded headers can be faked).
		 *
		 * @return string
		 */
		protected function ip() {
			return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}

		/**
		 * Counts an event in a 15 minute window.
		 *
		 * @param string $key   Key.
		 * @param int    $limit Limit.
		 *
		 * @return bool Whether it is still allowed.
		 */
		protected function allowed( $key, $limit ) {
			$bucket = 'guest:' . strtok( $key, '|' );
			$key    = 'wpfactory_wc_sts_guest_' . md5( $key );
			$count  = (int) get_transient( $key );
			if ( $count >= $limit ) {
			/**
			 * A rate limit refused a request (for logging; the request is refused either way).
			 *
			 * @param string $bucket What was limited.
			 * @param int    $limit  Limit in the window.
			 */
			do_action( 'wpfactory_wc_sts_rate_limited', $bucket, (int) $limit );
				return false;
			}
			set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
			return true;
		}

		/**
		 * Rate limit for order lookups, per IP address.
		 *
		 * @return bool Whether another lookup is allowed.
		 */
		protected function lookup_allowed() {
			$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$key   = 'wpfactory_wc_sts_guest_' . md5( $ip );
			$count = (int) get_transient( $key );
			$limit = self::MAX_LOOKUPS;
			if ( $count >= $limit ) {
			/**
			 * A rate limit refused a request (for logging; the request is refused either way).
			 *
			 * @param string $bucket What was limited.
			 * @param int    $limit  Limit in the window.
			 */
			do_action( 'wpfactory_wc_sts_rate_limited', 'guest:lookup', (int) $limit );
				return false;
			}
			set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
			return true;
		}

		/**
		 * Order that matches an order number and billing email.
		 *
		 * @param string $number Order number.
		 * @param string $email  Billing email.
		 *
		 * @return WC_Order|false
		 */
		public function find_order( $number, $email ) {
			$order_id = $this->core()->resolve_order_number( $number );
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			if ( ! $order || ! is_a( $order, 'WC_Order' ) || '' === $email ) {
				return false;
			}
			return strtolower( $order->get_billing_email() ) === strtolower( $email ) ? $order : false;
		}

		/**
		 * Valid order check posted with the new ticket form: order ID and email, or empty.
		 *
		 * @return array
		 */
		protected function get_posted_order() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle_forms().
			$order_id = absint( $_POST['stsw_order'] ?? 0 );
			$email    = strtolower( sanitize_email( wp_unslash( $_POST['stsw_email'] ?? '' ) ) );
			$expires  = absint( $_POST['stsw_order_expires'] ?? 0 );
			$key      = sanitize_text_field( wp_unslash( $_POST['stsw_order_key'] ?? '' ) );
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			if ( ! $order_id || ! $email || $expires < time() || ! hash_equals( $this->sign( 'order|' . $order_id . '|' . $email . '|' . $expires ), $key ) ) {
				return array();
			}
			return array(
				'order_id' => $order_id,
				'email'    => $email,
			);
		}

		/**
		 * Handles the guest forms (order lookup, new ticket, reply), then redirects (Post/Redirect/Get).
		 */
		public function handle_forms() {
			if ( ! $this->is_enabled() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['stsw_guest_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
				return;
			}
			$action = sanitize_key( wp_unslash( $_POST['stsw_guest_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
				$this->lookup = array( 'error' => __( 'This form expired. Please refresh the page and try again.', 'support-ticket-system-for-woocommerce' ) );
				return;
			}

			if ( 'lookup' === $action ) {
				if ( ! $this->lookup_allowed() ) {
					$this->lookup = array( 'error' => __( 'Too many attempts. Please wait a few minutes and try again.', 'support-ticket-system-for-woocommerce' ) );
					return;
				}
				$guard = class_exists( 'WPFactory_WC_STS_Guard' ) ? WPFactory_WC_STS_Guard::get_instance() : null;
				if ( $guard && ! $guard->verify_captcha() ) {
					$this->lookup = array( 'error' => $guard->captcha_error() );
					return;
				}
				$blocked = (string) apply_filters( 'wpfactory_wc_sts_check_sender', '', sanitize_email( wp_unslash( $_POST['stsw_email'] ?? '' ) ), 'guest' );
				if ( '' !== $blocked ) {
					$this->lookup = array( 'error' => $blocked );
					return;
				}
				$order        = $this->find_order( sanitize_text_field( wp_unslash( $_POST['stsw_order_number'] ?? '' ) ), sanitize_email( wp_unslash( $_POST['stsw_email'] ?? '' ) ) );
				$this->lookup = $order
					? array(
						'order_id' => (int) $order->get_id(),
						'email'    => strtolower( $order->get_billing_email() ),
					)
					: array( 'error' => __( 'We could not find an order with this number and email address.', 'support-ticket-system-for-woocommerce' ) );
				return; // The form for the new ticket is shown on this same request.
			}

			if ( 'create' === $action ) {
				$checked = $this->get_posted_order();
				if ( ! $checked ) {
					$this->lookup = array( 'error' => __( 'This form expired. Please enter your order number again.', 'support-ticket-system-for-woocommerce' ) );
					return;
				}
				$this->lookup = $checked;
				if ( ! $this->allowed( 'create|' . $this->ip(), 5 ) || ! $this->allowed( 'create-order|' . $checked['order_id'], 3 ) ) {
					$this->lookup['error'] = __( 'Too many attempts. Please wait a few minutes and try again.', 'support-ticket-system-for-woocommerce' );
					return;
				}
				$blocked = (string) apply_filters( 'wpfactory_wc_sts_check_sender', '', $checked['email'], 'guest' );
				if ( '' !== $blocked ) {
					$this->lookup['error'] = $blocked;
					return;
				}
				$error = $this->create_from_post( $checked );
				if ( is_int( $error ) ) {
					wp_safe_redirect( add_query_arg( 'stsw_notice', 'created', $this->get_ticket_link( $error ) ) );
					exit;
				}
				$this->lookup['error'] = $error;
				return;
			}

			if ( 'reply' === $action ) {
				$ticket_id = $this->get_requested_ticket_id();
				$content   = sanitize_textarea_field( wp_unslash( $_POST['response_content'] ?? '' ) );
				$step      = sanitize_key( wp_unslash( $_POST['stsw_ticket_action'] ?? 'reply' ) );
				$close     = 'close' === $step || ! empty( $_POST['closeTicket'] );
				if ( ! $ticket_id ) {
					return;
				}
				if ( ! $this->allowed( 'reply|' . $this->ip(), 20 ) || ! $this->allowed( 'reply-ticket|' . $ticket_id, 10 ) ) {
					wp_safe_redirect( add_query_arg( 'stsw_notice', 'slow', $this->get_ticket_link( $ticket_id ) ) );
					exit;
				}
				$core   = $this->core();
				$notice = 'replied';
				if ( 'reopen' === $step ) {
					$core->set_ticket_status( $ticket_id, 'open' );
					$notice = 'reopened';
				} elseif ( '' === trim( $content ) ) {
					if ( ! $close ) {
						return;
					}
					$core->set_ticket_status( $ticket_id, 'closed' );
					$notice = 'closed';
				} else {
					$core->add_reply(
						$ticket_id,
						$content,
						0,
						false,
						array(
							'close'       => $close,
							'files_field' => $core->attachments_enabled() ? 'my_file_upload' : '',
						)
					);
				}
				wp_safe_redirect( add_query_arg( 'stsw_notice', $notice, $this->get_ticket_link( $ticket_id ) ) );
				exit;
			}
		}

		/**
		 * Creates the guest ticket from the posted form.
		 *
		 * @param array $checked Order ID and email from the signed order check.
		 *
		 * @return int|string Ticket ID, or an error message.
		 */
		protected function create_from_post( $checked ) {
			$core    = $this->core();
			$order   = wc_get_order( $checked['order_id'] );
			$request = $core->get_posted_request( $order );
			if ( ! is_array( $request ) ) {
				return $request;
			}
			// An order of a registered customer stays linked to their account, so the ticket also shows there.
			$request['user_id']     = $order ? (int) $order->get_customer_id() : 0;
			$request['guest_email'] = $checked['email'];
			$ticket_id              = $core->create_ticket( $request );
			if ( is_wp_error( $ticket_id ) ) {
				return __( 'Your request could not be saved. Please try again.', 'support-ticket-system-for-woocommerce' );
			}
			// The signed link checks this email, also for tickets linked to an account.
			update_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_GUEST_EMAIL, $checked['email'] );
			return (int) $ticket_id;
		}

		/**
		 * Guest box above the My Account login form.
		 */
		public function render_on_login_screen() {
			if ( ! $this->is_enabled() ) {
				return;
			}
			echo '<section class="wpfactory-wc-sts-guest" aria-labelledby="stsw-guest-title">';
			$this->render();
			echo '</section>';
		}

		/**
		 * Guest screen: a ticket from a signed link, the new ticket form after a lookup, or the lookup form.
		 */
		public function render() {
			if ( class_exists( 'WPFactory_WC_STS_Init' ) ) {
				WPFactory_WC_STS_Init::enqueue_frontend_assets();
			}
			$ticket_id = $this->get_requested_ticket_id();
			if ( $ticket_id ) {
				$this->render_ticket( $ticket_id );
				return;
			}
			if ( ! empty( $_REQUEST['stsw_ticket'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
				echo '<div class="woocommerce-error" role="alert">' . esc_html__( 'This link has expired. Enter your order number and email below to get a new one.', 'support-ticket-system-for-woocommerce' ) . '</div>';
			}
			if ( ! empty( $this->lookup['order_id'] ) ) {
				$this->render_new_ticket_form();
				return;
			}
			$otp = class_exists( 'WPFactory_WC_STS_OTP' ) ? WPFactory_WC_STS_OTP::get_instance()->get_view_data() : array();
			if ( ! empty( $otp['enabled'] ) && 'verify' === ( $otp['state']['step'] ?? '' ) ) {
				WPFactory_WC_STS_Templates::render(
					'guest-code',
					array(
						'otp'   => $otp,
						'nonce' => self::NONCE,
					)
				);
				return;
			}
			if ( ! empty( $otp['enabled'] ) && '' !== $otp['email'] ) {
				WPFactory_WC_STS_Templates::render(
					'guest-tickets',
					array(
						'otp'   => $otp,
						'nonce' => self::NONCE,
					)
				);
				return;
			}
			$this->render_lookup_form();
		}

		/**
		 * Error of the last form, if any.
		 */
		protected function render_error() {
			if ( ! empty( $this->lookup['error'] ) ) {
				echo '<div class="woocommerce-error" role="alert">' . esc_html( $this->lookup['error'] ) . '</div>';
			}
		}

		/**
		 * Order number and email form.
		 */
		protected function render_lookup_form() {
			WPFactory_WC_STS_Templates::render(
				'guest-lookup',
				array(
					// "Get help" on an order page fills in the order number.
					'order_number' => preg_replace( '/[^A-Za-z0-9_-]/', '', sanitize_text_field( wp_unslash( $_GET['order'] ?? '' ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pre-fill only.
					'error'        => (string) ( $this->lookup['error'] ?? '' ),
					'nonce'        => self::NONCE,
					'otp'          => class_exists( 'WPFactory_WC_STS_OTP' ) ? WPFactory_WC_STS_OTP::get_instance()->get_view_data() : array(),
				)
			);
		}

		/**
		 * New ticket form for a checked order.
		 */
		protected function render_new_ticket_form() {
			$order_id = (int) $this->lookup['order_id'];
			$email    = (string) $this->lookup['email'];
			$expires  = time() + self::ORDER_LIFETIME;
			echo '<div class="stsw" id="stsw-guest-title">';
			$this->render_error();
			WPFactory_WC_STS_Customer::get_instance()->render_ask(
				$order_id,
				array(
					'nonce'  => self::NONCE,
					'hidden' => array(
						'stsw_guest_action'  => 'create',
						'stsw_order'         => $order_id,
						'stsw_email'         => $email,
						'stsw_order_expires' => $expires,
						'stsw_order_key'     => $this->sign( 'order|' . $order_id . '|' . $email . '|' . $expires ),
					),
				)
			);
			echo '</div>';
		}

		/**
		 * A guest ticket with its conversation and the reply form.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		protected function render_ticket( $ticket_id ) {
			$args   = array(
				'stsw_ticket'  => $ticket_id,
				'stsw_expires' => absint( $_REQUEST['stsw_expires'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed link, checked.
				'stsw_key'     => sanitize_text_field( wp_unslash( $_REQUEST['stsw_key'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
			$notice = sanitize_key( wp_unslash( $_GET['stsw_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			$texts  = array(
				/* translators: %s: request number. */
				'created'  => sprintf( __( 'Thanks, we received your request #%s. We will reply by email. Keep the link we sent you to follow this request.', 'support-ticket-system-for-woocommerce' ), $this->core()->get_ticket_number( $ticket_id ) ),
				'replied'  => __( 'Thanks, your message was sent. We will reply by email.', 'support-ticket-system-for-woocommerce' ),
				'closed'   => __( 'Your request is closed. You can reopen it at any time.', 'support-ticket-system-for-woocommerce' ),
				'reopened' => __( 'Your request is open again. Add a reply to tell us what you still need.', 'support-ticket-system-for-woocommerce' ),
				'slow'     => __( 'Too many messages in a short time. Please wait a few minutes and try again.', 'support-ticket-system-for-woocommerce' ),
			);
			echo '<div class="stsw" id="stsw-guest-title">';
			if ( isset( $texts[ $notice ] ) ) {
				echo '<div class="woocommerce-message" role="status">' . esc_html( $texts[ $notice ] ) . '</div>';
			}
			WPFactory_WC_STS_Customer::get_instance()->render_ticket(
				$ticket_id,
				array(
					'nonce'     => self::NONCE,
					'file_args' => $args,
					'hidden'    => array_merge( $args, array( 'stsw_guest_action' => 'reply' ) ),
				)
			);
			echo '</div>';
		}
	}

endif;

return WPFactory_WC_STS_Guest::get_instance();
