<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: email notifications.
 *
 * Store, assignee and customer emails for new tickets and replies, with the merchant's templates and placeholders,
 * and the "Send test email" button. Shared by the free and Pro editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Emails' ) ) :

	/**
	 * WPFactory_WC_STS_Emails class.
	 *
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	class WPFactory_WC_STS_Emails {

		/**
		 * Core instance.
		 *
		 * @var WPFactory_WC_STS_Inc
		 */
		protected $core;

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Emails|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Emails
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self( WPFactory_WC_STS_Inc::get_main_instance() );
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @param WPFactory_WC_STS_Inc $core Core instance.
		 */
		public function __construct( $core ) {
			$this->core     = $core;
			self::$instance = $this;
			add_action( 'wpfactory_wc_sts_ticket_created', array( $this, 'on_ticket_created' ) );
			add_action( 'wpfactory_wc_sts_reply_added', array( $this, 'on_reply_added' ), 10, 3 );
			add_action( 'admin_post_wpfactory_wc_sts_test_email', array( $this, 'send_test_email' ) );
		}

		/**
		 * Whether a notification setting is on.
		 *
		 * @param string $key Option suffix.
		 *
		 * @return bool
		 */
		protected function is_on( $key ) {
			return '1' === (string) get_option( $this->core->plugin . $key );
		}

		/**
		 * Text option.
		 *
		 * @param string $key Option suffix.
		 *
		 * @return string
		 */
		protected function text( $key ) {
			return trim( (string) get_option( $this->core->plugin . $key ) );
		}

		/**
		 * Store email used as sender and for store notifications (setting, or the site admin email).
		 *
		 * @return string
		 */
		public function get_store_email() {
			$email = sanitize_email( $this->text( 'AdminEmailAddress' ) );
			return $email ? $email : sanitize_email( get_bloginfo( 'admin_email' ) );
		}

		/**
		 * Placeholder values for a ticket event. Body values are escaped HTML, subject values plain text.
		 *
		 * Placeholders: {ticketId}, {responseId}, {title}, {content}, {responseContent}, {toEmail}, {toFirstName},
		 * {toLastName}, {ticketLink}. {content} is the new message (the ticket text on a new ticket, the reply on a
		 * customer reply, the ticket text on a store reply, as in earlier versions).
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param int    $reply_id  Reply ID (0 for a new ticket).
		 * @param string $content   Value of {content}.
		 * @param string $reply     Value of {responseContent}.
		 *
		 * @return array { plain: array, html: array }
		 */
		protected function placeholders( $ticket_id, $reply_id, $content, $reply ) {
			$contact = $this->core->get_ticket_contact( $ticket_id );
			$title   = wp_specialchars_decode( get_the_title( $ticket_id ), ENT_QUOTES );
			$link    = $this->core->get_ticket_customer_url( $ticket_id );
			$plain   = array(
				'{ticketId}'        => (string) $ticket_id,
				'{responseId}'      => $reply_id ? (string) $reply_id : '',
				'{title}'           => $title,
				'{content}'         => wp_specialchars_decode( (string) $content, ENT_QUOTES ),
				'{responseContent}' => wp_specialchars_decode( (string) $reply, ENT_QUOTES ),
				'{toEmail}'         => (string) $contact['email'],
				'{toFirstName}'     => (string) $contact['first_name'],
				'{toLastName}'      => (string) $contact['last_name'],
				'{ticketLink}'      => $link,
			);
			$html    = array();
			foreach ( $plain as $key => $value ) {
				$html[ $key ] = in_array( $key, array( '{content}', '{responseContent}' ), true ) ? nl2br( esc_html( $value ) ) : esc_html( $value );
			}
			$html['{ticketLink}'] = '<a href="' . esc_url( $link ) . '">' . esc_html__( 'View your request', 'support-ticket-system-for-woocommerce' ) . '</a>';
			return array(
				'plain' => $plain,
				'html'  => $html,
			);
		}

		/**
		 * Fills a merchant subject template.
		 *
		 * @param string $template Template.
		 * @param array  $values   Placeholder values.
		 *
		 * @return string
		 */
		protected function fill_subject( $template, $values ) {
			return wp_strip_all_tags( strtr( $template, $values['plain'] ) );
		}

		/**
		 * Fills a merchant body template (paragraphs from the editor, allowed tags only).
		 *
		 * @param string $template Template.
		 * @param array  $values   Placeholder values.
		 *
		 * @return string
		 */
		protected function fill_body( $template, $values ) {
			return wp_kses( strtr( wpautop( $template ), $values['html'] ), WPFactory_WC_STS_Init::$allowed_html );
		}

		/**
		 * Link to the ticket in wp-admin.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string
		 */
		protected function admin_link( $ticket_id ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&ticket=' . (int) $ticket_id ) ) . '">' . esc_html__( 'Open the ticket', 'support-ticket-system-for-woocommerce' ) . '</a>';
		}

		/**
		 * Emails for a new ticket: store, assignee, customer.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function on_ticket_created( $ticket_id ) {
			if ( get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_STARTED_BY, true ) ) {
				$this->on_ticket_started_by_store( $ticket_id );
				return;
			}
			$content = (string) get_post_field( 'post_content', $ticket_id, 'raw' );
			$values  = $this->placeholders( $ticket_id, 0, $content, '' );
			$contact = $this->core->get_ticket_contact( $ticket_id );
			$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$title   = $values['plain']['{title}'];
			$fields  = $this->custom_fields_html( $ticket_id );

			/* translators: 1: store name, 2: ticket ID, 3: ticket title. */
			$staff_subject = sprintf( __( 'New ticket on %1$s - #%2$d %3$s', 'support-ticket-system-for-woocommerce' ), $site, (int) $ticket_id, $title );
			$staff_body    = '<strong>' . esc_html( $title ) . '</strong><br/>' . $values['html']['{content}'] . $fields . '<br/><br/>' . $this->admin_link( $ticket_id );
			$this->send_staff( $ticket_id, $values, $staff_subject, $staff_body, $contact['email'] );

			if ( $this->is_on( 'mailToCustomer' ) && is_email( $contact['email'] ) ) {
				$subject = '' !== $this->text( 'mailIt_subjectToCust' )
					? $this->fill_subject( $this->text( 'mailIt_subjectToCust' ), $values )
					/* translators: 1: store name, 2: ticket ID, 3: ticket title. */
					: sprintf( __( 'We received your request on %1$s - #%2$d %3$s', 'support-ticket-system-for-woocommerce' ), $site, (int) $ticket_id, $title );
				$body = '' !== $this->text( 'mailIt_contentToCust' )
					? $this->fill_body( $this->text( 'mailIt_contentToCust' ), $values )
					: '<strong>' . esc_html( $title ) . '</strong><br/>' . $values['html']['{content}'] . '<br/><br/>' . $values['html']['{ticketLink}'];
				$body = $this->with_guest_link( $ticket_id, $body, $values );
				$this->notify_users( $contact['email'], $subject, $body );
			}
		}

		/**
		 * A staff member opened a ticket for the customer: the customer gets the message and the link.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		protected function on_ticket_started_by_store( $ticket_id ) {
			$contact = $this->core->get_ticket_contact( $ticket_id );
			if ( ! is_email( $contact['email'] ) ) {
				return;
			}
			$content = (string) get_post_field( 'post_content', $ticket_id, 'raw' );
			$values  = $this->placeholders( $ticket_id, 0, $content, $content );
			$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			/* translators: 1: store name, 2: ticket ID, 3: ticket title. */
			$subject = sprintf( __( 'A message from %1$s - #%2$d %3$s', 'support-ticket-system-for-woocommerce' ), $site, (int) $ticket_id, $values['plain']['{title}'] );
			$body    = $values['html']['{content}'] . '<br/><br/>' . $values['html']['{ticketLink}'];
			$this->notify_users( $contact['email'], $subject, $this->with_guest_link( $ticket_id, $body, $values ) );
		}

		/**
		 * Emails for a reply.
		 *
		 * @param int  $ticket_id Ticket ID.
		 * @param int  $reply_id  Reply ID.
		 * @param bool $by_staff  Whether the store replied.
		 */
		public function on_reply_added( $ticket_id, $reply_id, $by_staff ) {
			global $wpdb;
			$reply   = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT content FROM %i WHERE id = %d', $wpdb->prefix . $this->core->table_name, (int) $reply_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$contact = $this->core->get_ticket_contact( $ticket_id );
			$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$title   = wp_specialchars_decode( get_the_title( $ticket_id ), ENT_QUOTES );

			if ( $by_staff ) {
				$values = $this->placeholders( $ticket_id, $reply_id, (string) get_post_field( 'post_content', $ticket_id, 'raw' ), $reply );

				if ( $this->is_on( 'mailToCustomer' ) && is_email( $contact['email'] ) ) {
					$subject = '' !== $this->text( 'mailIt_subjectToCust' )
						? $this->fill_subject( $this->text( 'mailIt_subjectToCust' ), $values )
						/* translators: 1: ticket ID, 2: ticket title. */
						: sprintf( __( 'New reply on your request #%1$d %2$s', 'support-ticket-system-for-woocommerce' ), (int) $ticket_id, $title );
					$template = $this->text( 'mailIt_contentToCust' );
					if ( '' === $template ) {
						$body = '<strong>' . esc_html( $title ) . '</strong><br/>' . $values['html']['{responseContent}'] . '<br/><br/>' . $values['html']['{ticketLink}'];
					} elseif ( false !== strpos( $template, '{responseContent}' ) ) {
						$body = $this->fill_body( $template, $values );
					} else {
						// Templates without {responseContent} get the reply after the merchant's text (as before 2.3.0).
						$body = $this->fill_body( $template, $values ) . '<br/>' . $values['html']['{responseContent}'] . '<br/>' . $values['html']['{ticketLink}'];
					}
					$this->notify_users( $contact['email'], $subject, $this->with_guest_link( $ticket_id, $body, $values ) );
				}

				// Assignee, when someone else answered.
				$assignee = $this->core->get_ticket_assignee_id( $ticket_id );
				if ( $this->is_on( 'mailToAssignee' ) && $assignee && get_current_user_id() !== $assignee ) {
					/* translators: 1: store name, 2: ticket ID, 3: ticket title. */
					$subject = sprintf( __( 'Ticket updated on %1$s - #%2$d %3$s', 'support-ticket-system-for-woocommerce' ), $site, (int) $ticket_id, $title );
					$body    = $values['html']['{responseContent}'] . '<br/><br/>' . $this->admin_link( $ticket_id );
					$this->send_assignee( $ticket_id, $values, $subject, $body );
				}
				return;
			}

			// Customer reply.
			$values = $this->placeholders( $ticket_id, $reply_id, $reply, $reply );
			/* translators: 1: ticket ID, 2: ticket title. */
			$staff_subject = sprintf( __( 'New reply on ticket #%1$d %2$s', 'support-ticket-system-for-woocommerce' ), (int) $ticket_id, $title );
			$staff_body    = $values['html']['{content}'] . '<br/><br/>' . $this->admin_link( $ticket_id );
			$this->send_staff( $ticket_id, $values, $staff_subject, $staff_body, $contact['email'] );

			if ( $this->is_on( 'mailToCustomer' ) && is_email( $contact['email'] ) ) {
				$subject = '' !== $this->text( 'mailIt_subjectToCust' )
					? $this->fill_subject( $this->text( 'mailIt_subjectToCust' ), $values )
					/* translators: 1: store name, 2: ticket ID. */
					: sprintf( __( '%1$s received your reply on ticket #%2$d', 'support-ticket-system-for-woocommerce' ), $site, (int) $ticket_id );
				$body = '' !== $this->text( 'mailIt_contentToCust' )
					? $this->fill_body( $this->text( 'mailIt_contentToCust' ), $values )
					: $values['html']['{content}'] . '<br/><br/>' . $values['html']['{ticketLink}'];
				$this->notify_users( $contact['email'], $subject, $this->with_guest_link( $ticket_id, $body, $values ) );
			}
		}

		/**
		 * Guests can only reach their ticket through the link: add it when the merchant's template has none.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $body      Body.
		 * @param array  $values    Placeholder values.
		 *
		 * @return string
		 */
		protected function with_guest_link( $ticket_id, $body, $values ) {
			if ( $this->core->get_ticket_user_id( $ticket_id ) || false !== strpos( $body, esc_url( $values['plain']['{ticketLink}'] ) ) ) {
				return $body;
			}
			return $body . '<br/><br/>' . $values['html']['{ticketLink}'];
		}

		/**
		 * Store and assignee emails. The merchant's staff template ("Assignee email") is used for both when set.
		 *
		 * @param int    $ticket_id       Ticket ID.
		 * @param array  $values          Placeholder values.
		 * @param string $default_subject Default subject.
		 * @param string $default_body    Default body.
		 * @param string $reply_to        Customer email (Reply-To on store emails).
		 */
		protected function send_staff( $ticket_id, $values, $default_subject, $default_body, $reply_to ) {
			$subject = '' !== $this->text( 'mailIt_subjectToAs' ) ? $this->fill_subject( $this->text( 'mailIt_subjectToAs' ), $values ) : $default_subject;
			$body    = '' !== $this->text( 'mailIt_contentToAs' ) ? $this->fill_body( $this->text( 'mailIt_contentToAs' ), $values ) : $default_body;
			if ( $this->is_on( 'mailToADmin' ) ) {
				$this->notify_users( $this->get_store_email(), $subject, $body, $reply_to );
			}
			if ( $this->is_on( 'mailToAssignee' ) ) {
				$this->send_assignee( $ticket_id, $values, $default_subject, $default_body );
			}
		}

		/**
		 * Email to the assignee (agent, or the default assignee), unless it is the store address.
		 *
		 * @param int    $ticket_id       Ticket ID.
		 * @param array  $values          Placeholder values.
		 * @param string $default_subject Default subject.
		 * @param string $default_body    Default body.
		 */
		protected function send_assignee( $ticket_id, $values, $default_subject, $default_body ) {
			$assignee = get_user_by( 'id', $this->core->get_ticket_assignee_id( $ticket_id ) );
			if ( ! $assignee || ! is_email( $assignee->user_email ) ) {
				return;
			}
			if ( $this->is_on( 'mailToADmin' ) && sanitize_email( $assignee->user_email ) === $this->get_store_email() ) {
				return; // Already emailed as the store.
			}
			$subject = '' !== $this->text( 'mailIt_subjectToAs' ) ? $this->fill_subject( $this->text( 'mailIt_subjectToAs' ), $values ) : $default_subject;
			$body    = '' !== $this->text( 'mailIt_contentToAs' ) ? $this->fill_body( $this->text( 'mailIt_contentToAs' ), $values ) : $default_body;
			$this->notify_users( $assignee->user_email, $subject, $body );
		}

		/**
		 * Custom field answers for staff emails.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string
		 */
		protected function custom_fields_html( $ticket_id ) {
			if ( ! class_exists( 'WPFactory_WC_STS_Fields' ) ) {
				return '';
			}
			$html = '';
			foreach ( WPFactory_WC_STS_Fields::get_instance()->get_display_values( $ticket_id ) as $label => $value ) {
				$html .= '<br/><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value );
			}
			$order_id = $this->core->get_ticket_order_id( $ticket_id );
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			if ( $order ) {
				/* translators: %s: order number. */
				$html .= '<br/><strong>' . esc_html( sprintf( __( 'Order #%s', 'support-ticket-system-for-woocommerce' ), $order->get_order_number() ) ) . '</strong>';
			}
			return $html ? '<br/>' . $html : '';
		}

		/**
		 * Sends an HTML email from the store. Reply-To: the customer on store emails, the store on customer emails.
		 *
		 * @param string $to       Recipient.
		 * @param string $subject  Subject.
		 * @param string $message  HTML body.
		 * @param string $reply_to Reply-To address.
		 *
		 * @return bool
		 */
		public function notify_users( $to, $subject, $message, $reply_to = '' ) {
			$store    = $this->get_store_email();
			$reply_to = sanitize_email( $reply_to );
			if ( ! $reply_to || sanitize_email( $to ) === $reply_to ) {
				$reply_to = $store;
			}
			$headers = array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' <' . $store . '>',
			);
			if ( sanitize_email( $to ) !== $reply_to ) {
				$headers[] = 'Reply-To: ' . $reply_to;
			}
			/**
			 * Headers of ticket emails (Pro: email piping adds Reply-To and loop guards).
			 *
			 * @param array  $headers Headers.
			 * @param string $to      Recipient.
			 * @param string $subject Subject.
			 */
			$headers = (array) apply_filters( 'wpfactory_wc_sts_email_headers', $headers, $to, $subject );
			/**
			 * Subject of ticket emails (Pro: email piping adds the ticket tag).
			 *
			 * @param string $subject Subject.
			 */
			$subject = (string) apply_filters( 'wpfactory_wc_sts_email_subject', $subject );
			add_action( 'wp_mail_failed', array( $this, 'record_failure' ) );
			$sent = (bool) wp_mail( $to, $subject, $message, $headers );
			remove_action( 'wp_mail_failed', array( $this, 'record_failure' ) );
			return $sent;
		}

		/**
		 * Remembers the last error WordPress reported for a ticket email (shown in Email health).
		 *
		 * @param WP_Error $error Error.
		 */
		public function record_failure( $error ) {
			update_option(
				'wpfactory_wc_sts_mail_error',
				array(
					'time'    => time(),
					'message' => is_wp_error( $error ) ? $error->get_error_message() : '',
				),
				false
			);
		}

		/**
		 * Email health: checks that store emails can leave the site and reach inboxes.
		 *
		 * @return array List of { ok: bool, label: string, fix: string }.
		 */
		public function get_email_health() {
			$store  = $this->get_store_email();
			$domain = strtolower( (string) substr( strrchr( $store, '@' ), 1 ) );
			$site   = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			$site   = preg_replace( '/^www\./', '', $site );
			$free   = array( 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com', 'aol.com', 'icloud.com', 'gmx.com', 'gmx.de', 'yandex.com', 'proton.me', 'protonmail.com' );
			$smtp   = has_action( 'phpmailer_init' ) || has_filter( 'pre_wp_mail' );
			$test   = (array) get_option( 'wpfactory_wc_sts_email_test', array() );
			$error  = (array) get_option( 'wpfactory_wc_sts_mail_error', array() );
			$checks = array(
				array(
					'ok'    => $this->is_on( 'mailToADmin' ) && $this->is_on( 'mailToCustomer' ),
					'label' => __( 'Store and customer emails are on', 'support-ticket-system-for-woocommerce' ),
					'fix'   => __( 'Turn on both email settings above, or customers and staff miss replies.', 'support-ticket-system-for-woocommerce' ),
				),
				array(
					'ok'    => (bool) is_email( $store ),
					/* translators: %s: email address. */
					'label' => sprintf( __( 'Sender address: %s', 'support-ticket-system-for-woocommerce' ), $store ),
					'fix'   => __( 'Enter a valid store email address.', 'support-ticket-system-for-woocommerce' ),
				),
				array(
					'ok'    => '' !== $domain && ! in_array( $domain, $free, true ) && ( $domain === $site || str_ends_with( $site, '.' . $domain ) || str_ends_with( $domain, '.' . $site ) || 'localhost' === $site || preg_match( '/^[0-9.]+$/', $site ) ),
					'label' => __( 'Sender address uses your shop domain', 'support-ticket-system-for-woocommerce' ),
					'fix'   => __( 'Emails sent from a free mailbox or another domain often land in spam. Use an address on your shop domain.', 'support-ticket-system-for-woocommerce' ),
				),
				array(
					'ok'    => (bool) $smtp,
					'label' => __( 'An email sending plugin (SMTP) is active', 'support-ticket-system-for-woocommerce' ),
					'fix'   => __( 'Without one, many hosts drop or delay email. Install an SMTP plugin and connect it to your mail provider.', 'support-ticket-system-for-woocommerce' ),
				),
				array(
					'ok'    => ! empty( $test['ok'] ),
					'label' => empty( $test['time'] )
						? __( 'Test email not sent yet', 'support-ticket-system-for-woocommerce' )
						/* translators: %s: time since the test, e.g. "2 hours". */
						: sprintf( __( 'Last test email: %s ago', 'support-ticket-system-for-woocommerce' ), human_time_diff( (int) $test['time'] ) ),
					'fix'   => __( 'Send a test email and check it arrives (also in the spam folder).', 'support-ticket-system-for-woocommerce' ),
				),
			);
			if ( ! empty( $error['time'] ) && ( empty( $test['time'] ) || (int) $error['time'] > (int) $test['time'] ) ) {
				$checks[] = array(
					'ok'    => false,
					/* translators: 1: time since the error, 2: error message. */
					'label' => sprintf( __( 'A ticket email failed %1$s ago: %2$s', 'support-ticket-system-for-woocommerce' ), human_time_diff( (int) $error['time'] ), $error['message'] ),
					'fix'   => __( 'Check your SMTP plugin\'s log, then send a test email.', 'support-ticket-system-for-woocommerce' ),
				);
			}
			return $checks;
		}

		/**
		 * Email health box (Settings > Notifications).
		 */
		public function render_email_health() {
			echo '<h2 id="stsw-email-health">' . esc_html__( 'Email health', 'support-ticket-system-for-woocommerce' ) . '</h2><ul class="stsw-health">';
			foreach ( $this->get_email_health() as $check ) {
				echo '<li class="' . ( $check['ok'] ? 'is-ok' : 'is-warning' ) . '"><span class="dashicons ' . ( $check['ok'] ? 'dashicons-yes-alt' : 'dashicons-warning' ) . '" aria-hidden="true"></span> ';
				echo '<strong>' . esc_html( $check['ok'] ? __( 'OK', 'support-ticket-system-for-woocommerce' ) : __( 'Check', 'support-ticket-system-for-woocommerce' ) ) . ':</strong> ' . esc_html( $check['label'] );
				if ( ! $check['ok'] ) {
					echo '<br/><span class="description">' . esc_html( $check['fix'] ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}

		/**
		 * Sends a test email to the store address ("Send test email" button in Settings > Notifications).
		 */
		public function send_test_email() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				wp_die( esc_html__( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ) );
			}
			check_admin_referer( 'wpfactory_wc_sts_test_email' );
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$sent = $this->notify_users(
				$this->get_store_email(),
				/* translators: %s: store name. */
				sprintf( __( 'Test email from %s support tickets', 'support-ticket-system-for-woocommerce' ), $site ),
				esc_html__( 'This is a test. If you received it, ticket notifications from your store can reach this address.', 'support-ticket-system-for-woocommerce' )
			);
			update_option(
				'wpfactory_wc_sts_email_test',
				array(
					'time' => time(),
					'ok'   => $sent,
				),
				false
			);
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'                        => WPFACTORY_WC_STS_PAGE,
						'tab'                         => 'settings',
						'wpfactory_wc_sts_test_email' => $sent ? 'sent' : 'failed',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		/**
		 * "Send test email" button and the result of the last test.
		 */
		public function render_test_email_button() {
			$result = isset( $_GET['wpfactory_wc_sts_test_email'] ) ? sanitize_key( wp_unslash( $_GET['wpfactory_wc_sts_test_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			if ( 'sent' === $result ) {
				echo '<div class="notice notice-success inline" role="status"><p>' . esc_html(
					sprintf(
						/* translators: %s: email address. */
						__( 'Test email sent to %s. If it does not arrive, check the spam folder or set up an SMTP plugin.', 'support-ticket-system-for-woocommerce' ),
						$this->get_store_email()
					)
				) . '</p></div>';
			} elseif ( 'failed' === $result ) {
				echo '<div class="notice notice-error inline" role="alert"><p>' . esc_html__( 'WordPress could not send the test email. Your site probably needs an SMTP plugin to send email.', 'support-ticket-system-for-woocommerce' ) . '</p></div>';
			}
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=wpfactory_wc_sts_test_email' ), 'wpfactory_wc_sts_test_email' );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Send test email', 'support-ticket-system-for-woocommerce' ) . '</a> <span class="description">' .
				esc_html(
					sprintf(
						/* translators: %s: email address. */
						__( 'Sends a test to %s (saved store email address).', 'support-ticket-system-for-woocommerce' ),
						$this->get_store_email()
					)
				) . '</span></p>';
		}
	}

endif;

return WPFactory_WC_STS_Emails::get_instance();
