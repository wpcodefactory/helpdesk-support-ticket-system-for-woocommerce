<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Spam protection
 *
 * Blocked senders (emails and domains) for the ticket forms, chat, guest codes and email piping, and an optional
 * Cloudflare Turnstile or Google reCAPTCHA check on the guest form and the chat form when nobody is online.
 *
 * @version 3.0.0
 * @since   3.0.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Guard' ) ) :

	/**
	 * Spam protection.
	 */
	class WPFactory_WC_STS_Guard {

		/**
		 * Settings (shared by both editions).
		 */
		const OPTION_BLOCKED = 'wpfactory_wc_sts_blocked_senders';
		const OPTION_CAPTCHA = 'wpfactory_wc_sts_captcha';
		const OPTION_SITE    = 'wpfactory_wc_sts_captcha_site_key';
		const OPTION_SECRET  = 'wpfactory_wc_sts_captcha_secret';

		/**
		 * Verification endpoints.
		 */
		const VERIFY = array(
			'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
		);

		/**
		 * Widget scripts.
		 */
		const SCRIPTS = array(
			'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
			'recaptcha' => 'https://www.google.com/recaptcha/api.js',
		);

		/**
		 * Response field each service posts.
		 */
		const FIELDS = array(
			'turnstile' => 'cf-turnstile-response',
			'recaptcha' => 'g-recaptcha-response',
		);

		/**
		 * Instance.
		 *
		 * @var WPFactory_WC_STS_Guard|null
		 */
		protected static $instance = null;

		/**
		 * Instance.
		 *
		 * @return WPFactory_WC_STS_Guard
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
			add_filter( 'wpfactory_wc_sts_check_sender', array( $this, 'check_sender' ), 10, 2 );
			add_filter( 'wpfactory_wc_sts_settings_form', array( $this, 'settings' ) );
			add_action( 'wpfactory_wc_sts_guest_form_fields', array( $this, 'render_captcha' ) );
			add_action(
				'wpfactory_wc_sts_chat_form_fields',
				function () {
					$this->render_captcha( 'chat' );
				}
			);
		}

		/**
		 * Settings rows (Ticket form > Spam protection).
		 *
		 * @param array $fields Fields.
		 *
		 * @return array
		 */
		public function settings( $fields ) {
			$fields[ '@' . self::OPTION_BLOCKED ] = array(
				'type'        => 'textarea',
				'card'        => 'spam',
				'label'       => __( 'Blocked senders', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'One per line: a full email address (name@example.com) or a whole domain (example.com). They cannot open tickets, start chats, ask for guest codes or reach you through email piping.', 'support-ticket-system-for-woocommerce' ),
			);
			$fields[ '@' . self::OPTION_CAPTCHA ] = array(
				'type'        => 'select',
				'card'        => 'spam',
				'label'       => __( 'Human check', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'Shown on the guest form and on the chat form for visitors who are not signed in (for customers, only when nobody is online). Off by default. When on, the visitor\'s browser loads the check from Cloudflare or Google.', 'support-ticket-system-for-woocommerce' ),
				'options'     => array(
					''          => __( 'Off', 'support-ticket-system-for-woocommerce' ),
					'turnstile' => __( 'Cloudflare Turnstile', 'support-ticket-system-for-woocommerce' ),
					'recaptcha' => __( 'Google reCAPTCHA v2 (checkbox)', 'support-ticket-system-for-woocommerce' ),
				),
			);
			$fields[ '@' . self::OPTION_SITE ]    = array(
				'type'  => 'text',
				'card'  => 'spam',
				'label' => __( 'Site key', 'support-ticket-system-for-woocommerce' ),
			);
			$fields[ '@' . self::OPTION_SECRET ]  = array(
				'type'  => 'password',
				'card'  => 'spam',
				'label' => __( 'Secret key', 'support-ticket-system-for-woocommerce' ),
			);
			return $fields;
		}

		/**
		 * Blocked entries, lower case.
		 *
		 * @return string[]
		 */
		public function get_blocked() {
			$lines = preg_split( '/[\r\n,]+/', strtolower( (string) get_option( self::OPTION_BLOCKED, '' ) ) );
			return array_values( array_filter( array_map( 'trim', (array) $lines ) ) );
		}

		/**
		 * Whether an email address or its domain is blocked.
		 *
		 * @param string $email Email.
		 *
		 * @return bool
		 */
		public function is_blocked( $email ) {
			$email = strtolower( trim( (string) $email ) );
			if ( '' === $email ) {
				return false;
			}
			$domain = substr( (string) strrchr( $email, '@' ), 1 );
			foreach ( $this->get_blocked() as $entry ) {
				$entry = ltrim( $entry, '@' );
				if ( $entry === $email || ( false === strpos( $entry, '@' ) && '' !== $domain && ( $domain === $entry || substr( $domain, -strlen( '.' . $entry ) ) === '.' . $entry ) ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Refuses blocked senders (same neutral message everywhere).
		 *
		 * @param string $error Error so far.
		 * @param string $email Email.
		 *
		 * @return string
		 */
		public function check_sender( $error, $email ) {
			if ( '' !== $error ) {
				return $error;
			}
			return $this->is_blocked( $email ) ? __( 'We could not accept this request. Please contact the store another way.', 'support-ticket-system-for-woocommerce' ) : '';
		}

		/**
		 * Service in use ('' when off or keys are missing).
		 *
		 * @return string
		 */
		public function captcha_service() {
			$service = (string) get_option( self::OPTION_CAPTCHA, '' );
			if ( ! isset( self::VERIFY[ $service ] ) || '' === trim( (string) get_option( self::OPTION_SITE, '' ) ) || '' === trim( (string) get_option( self::OPTION_SECRET, '' ) ) ) {
				return '';
			}
			return $service;
		}

		/**
		 * Prints the check widget (and loads its script).
		 *
		 * @param string $context Where: `guest` or `chat`.
		 */
		public function render_captcha( $context = 'guest' ) {
			$context = 'chat' === $context ? 'chat' : 'guest';
			$service = $this->captcha_service();
			if ( '' === $service ) {
				return;
			}
			wp_enqueue_script( 'wpfactory-wc-sts-captcha', self::SCRIPTS[ $service ], array(), null, array( 'strategy' => 'defer' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the service serves its current version.
			$class = 'turnstile' === $service ? 'cf-turnstile' : 'g-recaptcha';
			// Signed-in customers only need it in the chat when nobody is online (the chat script shows it then).
			$toggle = 'chat' === $context && is_user_logged_in();
			echo '<div class="stsw-captcha stsw-captcha--' . esc_attr( $context ) . '"' . ( $toggle ? ' data-stsw-chat-captcha hidden' : '' ) . '><div class="' . esc_attr( $class ) . '" data-sitekey="' . esc_attr( trim( (string) get_option( self::OPTION_SITE ) ) ) . '"></div></div>';
		}

		/**
		 * Checks the posted response with the service. Passes when the check is off.
		 *
		 * @return bool
		 */
		public function verify_captcha() {
			$service = $this->captcha_service();
			if ( '' === $service ) {
				return true;
			}
			$token = sanitize_text_field( wp_unslash( $_POST[ self::FIELDS[ $service ] ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the calling form verified its nonce.
			if ( '' === $token ) {
				return false;
			}
			$response = wp_remote_post(
				self::VERIFY[ $service ],
				array(
					'timeout' => 10,
					'body'    => array(
						'secret'   => trim( (string) get_option( self::OPTION_SECRET ) ),
						'response' => $token,
						'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					),
				)
			);
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			return is_array( $body ) && ! empty( $body['success'] );
		}

		/**
		 * Message when the check fails.
		 *
		 * @return string
		 */
		public function captcha_error() {
			return __( 'Please confirm you are not a robot and try again.', 'support-ticket-system-for-woocommerce' );
		}
	}

endif;

return WPFactory_WC_STS_Guard::get_instance();
