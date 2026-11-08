<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Guest sign-in with a code by email
 *
 * A guest types their email and gets a six digit code. With the code they see every ticket of that email address, not
 * only the one in a ticket link. The session is a signed cookie that lasts one day.
 *
 * @version 3.0.0
 * @since   3.0.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_OTP' ) ) :

	/**
	 * Guest codes.
	 */
	class WPFactory_WC_STS_OTP {

		/**
		 * Setting (shared by both editions), on unless turned off.
		 */
		const OPTION = 'wpfactory_wc_sts_guest_otp';

		/**
		 * Session cookie.
		 */
		const COOKIE = 'stsw_guest_session';

		/**
		 * Code lifetime.
		 */
		const CODE_LIFETIME = 15 * MINUTE_IN_SECONDS;

		/**
		 * Session lifetime.
		 */
		const SESSION_LIFETIME = DAY_IN_SECONDS;

		/**
		 * Wrong codes allowed before a new code is needed.
		 */
		const MAX_TRIES = 5;

		/**
		 * Codes sent per IP address and per email in 15 minutes.
		 */
		const MAX_SENDS = 3;

		/**
		 * Tickets listed.
		 */
		const LIST_LIMIT = 50;

		/**
		 * Instance.
		 *
		 * @var WPFactory_WC_STS_OTP|null
		 */
		protected static $instance = null;

		/**
		 * State of the last form: step (`verify`), email, error, notice.
		 *
		 * @var array
		 */
		public $state = array();

		/**
		 * Email of a session started in this request (the cookie is only readable on the next one).
		 *
		 * @var string
		 */
		protected $fresh_email = '';

		/**
		 * Instance.
		 *
		 * @return WPFactory_WC_STS_OTP
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
			add_action( 'template_redirect', array( $this, 'handle_forms' ), 4 );
		}

		/**
		 * Core.
		 *
		 * @return WPFactory_WC_STS_Inc
		 */
		protected function core() {
			return WPFactory_WC_STS_Inc::get_main_instance();
		}

		/**
		 * Whether guests can sign in with a code (needs guest tickets).
		 *
		 * @return bool
		 */
		public function is_enabled() {
			return class_exists( 'WPFactory_WC_STS_Guest' ) && WPFactory_WC_STS_Guest::get_instance()->is_enabled() && '1' === (string) get_option( self::OPTION, '1' );
		}

		/**
		 * HMAC with a site secret.
		 *
		 * @param string $data Data.
		 *
		 * @return string
		 */
		protected function sign( $data ) {
			return hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
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
			$key   = 'wpfactory_wc_sts_otp_' . md5( $key );
			$count = (int) get_transient( $key );
			if ( $count >= $limit ) {
				return false;
			}
			set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
			return true;
		}

		/**
		 * Email of the signed-in guest, or an empty string.
		 *
		 * @return string
		 */
		public function get_session_email() {
			if ( '' !== $this->fresh_email ) {
				return $this->fresh_email;
			}
			// The value is email|expires|signature. Read it as it is: sanitize_text_field() would strip parts of it.
			$raw = isset( $_COOKIE[ self::COOKIE ] ) ? (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each part is validated below and the signature is checked.
			if ( ! preg_match( '/^([^|\s]{3,254})\|(\d{9,11})\|([a-f0-9]{64})$/', $raw, $m ) ) {
				return '';
			}
			list( , $email, $expires, $key ) = $m;
			$email                          = strtolower( sanitize_email( $email ) );
			if ( ! is_email( $email ) || (int) $expires < time() || ! hash_equals( $this->sign( 'session|' . $email . '|' . (int) $expires ), $key ) ) {
				return '';
			}
			return $email;
		}

		/**
		 * Stores the session cookie.
		 *
		 * @param string $email Email ('' signs out).
		 */
		protected function set_session( $email ) {
			$expires = time() + self::SESSION_LIFETIME;
			$value   = '' === $email ? '' : $email . '|' . $expires . '|' . $this->sign( 'session|' . $email . '|' . $expires );
			if ( ! headers_sent() ) {
				setcookie(
					self::COOKIE,
					$value,
					array(
						'expires'  => '' === $email ? time() - HOUR_IN_SECONDS : $expires,
						'path'     => COOKIEPATH ? COOKIEPATH : '/',
						'domain'   => COOKIE_DOMAIN,
						'secure'   => is_ssl(),
						'httponly' => true,
						'samesite' => 'Lax',
					)
				);
			}
			$this->fresh_email = $email;
			if ( '' === $email ) {
				unset( $_COOKIE[ self::COOKIE ] );
			}
		}

		/**
		 * Tickets of a guest email (the email that opened them), newest activity first.
		 *
		 * @param string $email Email.
		 *
		 * @return int[]
		 */
		public function get_ticket_ids( $email ) {
			if ( ! is_email( $email ) ) {
				return array();
			}
			return array_map(
				'intval',
				get_posts(
					array(
						'post_type'      => 'stsw_tickets',
						'post_status'    => 'publish',
						'posts_per_page' => self::LIST_LIMIT,
						'fields'         => 'ids',
						'orderby'        => 'modified',
						'order'          => 'DESC',
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'   => WPFactory_WC_STS_Inc::META_GUEST_EMAIL,
								'value' => strtolower( $email ),
							),
							array(
								'key'     => '_stsw_internal',
								'compare' => 'NOT EXISTS',
							),
						),
					)
				)
			);
		}

		/**
		 * Sends a code. Says the same whether or not the email has tickets (no way to test which emails exist).
		 *
		 * @param string $email Email.
		 *
		 * @return bool Whether a code was sent.
		 */
		public function send_code( $email ) {
			$email = strtolower( sanitize_email( $email ) );
			if ( ! is_email( $email ) || ! $this->get_ticket_ids( $email ) ) {
				return false;
			}
			$code = (string) wp_rand( 100000, 999999 );
			set_transient(
				'wpfactory_wc_sts_otp_code_' . md5( $email ),
				array(
					'hash'  => $this->sign( 'code|' . $email . '|' . $code ),
					'tries' => 0,
				),
				self::CODE_LIFETIME
			);
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			/* translators: %s: store name. */
			$subject = sprintf( __( 'Your code for %s support', 'support-ticket-system-for-woocommerce' ), $site );
			$body    = '<p>' . esc_html__( 'Use this code to see your support requests:', 'support-ticket-system-for-woocommerce' ) . '</p>' .
				'<p style="font-size:24px;letter-spacing:4px"><strong>' . esc_html( $code ) . '</strong></p>' .
				'<p>' . esc_html__( 'The code works for 15 minutes. If you did not ask for it, you can ignore this email.', 'support-ticket-system-for-woocommerce' ) . '</p>';
			if ( class_exists( 'WPFactory_WC_STS_Emails' ) && WPFactory_WC_STS_Emails::get_instance() ) {
				return (bool) WPFactory_WC_STS_Emails::get_instance()->notify_users( $email, $subject, $body );
			}
			return (bool) wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		}

		/**
		 * Checks a code; a good code is used once.
		 *
		 * @param string $email Email.
		 * @param string $code  Code.
		 *
		 * @return string `ok`, `wrong` (tries left), `locked` (too many wrong codes) or `expired` (no code waiting).
		 */
		public function code_status( $email, $code ) {
			$email  = strtolower( sanitize_email( $email ) );
			$key    = 'wpfactory_wc_sts_otp_code_' . md5( $email );
			$stored = get_transient( $key );
			$code   = preg_replace( '/\D/', '', (string) $code );
			if ( ! is_array( $stored ) ) {
				return 'expired';
			}
			if ( 6 === strlen( $code ) && hash_equals( (string) $stored['hash'], $this->sign( 'code|' . $email . '|' . $code ) ) ) {
				delete_transient( $key );
				return 'ok';
			}
			++$stored['tries'];
			if ( $stored['tries'] >= self::MAX_TRIES ) {
				delete_transient( $key );
				return 'locked';
			}
			set_transient( $key, $stored, self::CODE_LIFETIME );
			return 'wrong';
		}

		/**
		 * Whether a code is right (kept for add-ons; see code_status()).
		 *
		 * @param string $email Email.
		 * @param string $code  Code.
		 *
		 * @return bool
		 */
		public function check_code( $email, $code ) {
			return 'ok' === $this->code_status( $email, $code );
		}

		/**
		 * Code forms: send, verify, sign out.
		 */
		public function handle_forms() {
			if ( ! $this->is_enabled() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['stsw_otp_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
				return;
			}
			if ( ! isset( $_POST[ WPFactory_WC_STS_Guest::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ WPFactory_WC_STS_Guest::NONCE ] ) ), WPFactory_WC_STS_Guest::NONCE ) ) {
				$this->state = array( 'error' => __( 'This form expired. Please refresh the page and try again.', 'support-ticket-system-for-woocommerce' ) );
				return;
			}
			$action = sanitize_key( wp_unslash( $_POST['stsw_otp_action'] ) );
			$email  = strtolower( sanitize_email( wp_unslash( $_POST['stsw_otp_email'] ?? '' ) ) );

			if ( 'logout' === $action ) {
				$this->set_session( '' );
				return;
			}
			if ( ! is_email( $email ) ) {
				$this->state = array( 'error' => __( 'Please enter a valid email address.', 'support-ticket-system-for-woocommerce' ) );
				return;
			}
			if ( 'send' === $action ) {
				/**
				 * Error message to refuse a code request, or an empty string (blocked senders, spam checks).
				 *
				 * @param string $error  Error.
				 * @param string $email  Email.
				 * @param string $source Form: `otp`.
				 */
				$error = (string) apply_filters( 'wpfactory_wc_sts_check_sender', '', $email, 'otp' );
				if ( '' === $error && ( ! $this->allowed( 'ip|' . $this->ip(), self::MAX_SENDS * 3 ) || ! $this->allowed( 'email|' . $email, self::MAX_SENDS ) ) ) {
					$error = __( 'Too many attempts. Please wait a few minutes and try again.', 'support-ticket-system-for-woocommerce' );
				}
				if ( '' !== $error ) {
					$this->state = array( 'error' => $error );
					return;
				}
				$this->send_code( $email );
				$this->state = array(
					'step'   => 'verify',
					'email'  => $email,
					'notice' => __( 'If we have requests for this email address, we sent a code to it. Enter the code below.', 'support-ticket-system-for-woocommerce' ),
				);
				return;
			}
			if ( 'verify' === $action ) {
				if ( ! $this->allowed( 'verify|' . $this->ip(), 20 ) ) {
					$this->state = array(
						'step'  => 'verify',
						'email' => $email,
						'error' => __( 'Too many attempts. Please wait a few minutes and try again.', 'support-ticket-system-for-woocommerce' ),
					);
					return;
				}
				$status = $this->code_status( $email, sanitize_text_field( wp_unslash( $_POST['stsw_otp_code'] ?? '' ) ) );
				if ( 'ok' === $status ) {
					$this->set_session( $email );
					$this->state = array();
					wp_safe_redirect( remove_query_arg( array( 'stsw_ticket', 'stsw_key', 'stsw_expires', 'stsw_notice' ) ) );
					exit;
				}
				$errors      = array(
					'wrong'   => __( 'This code is not right. Check the six digits in the email and try again.', 'support-ticket-system-for-woocommerce' ),
					'locked'  => __( 'Too many wrong codes. Ask for a new code below.', 'support-ticket-system-for-woocommerce' ),
					'expired' => __( 'This code has expired or was already used. Ask for a new code below.', 'support-ticket-system-for-woocommerce' ),
				);
				$this->state = array(
					'step'  => 'verify',
					'email' => $email,
					'error' => $errors[ $status ],
				);
			}
		}

		/**
		 * Template data for the guest screens.
		 *
		 * @return array
		 */
		public function get_view_data() {
			$email = $this->get_session_email();
			$data  = array(
				'enabled' => $this->is_enabled(),
				'email'   => $email,
				'state'   => $this->state,
				'nonce'   => WPFactory_WC_STS_Guest::NONCE,
				'tickets' => array(),
			);
			if ( '' === $email ) {
				return $data;
			}
			$core     = $this->core();
			$customer = WPFactory_WC_STS_Customer::get_instance();
			$guest    = WPFactory_WC_STS_Guest::get_instance();
			foreach ( $this->get_ticket_ids( $email ) as $id ) {
				$activity          = $core->get_activity( $id );
				$data['tickets'][] = array(
					'id'     => $id,
					'number' => $core->get_ticket_number( $id ),
					'title'  => get_the_title( $id ),
					'url'    => $guest->get_ticket_link( $id ),
					'chip'   => $customer->status_chip( $id ),
					'state'  => $customer->get_state( $id ),
					'at'     => $activity['at'],
				);
			}
			return $data;
		}
	}

endif;

return WPFactory_WC_STS_OTP::get_instance();
