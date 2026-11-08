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
			add_action( 'wpfactory_wc_sts_status_changed', array( $this, 'on_status_changed' ), 30, 3 );
			add_action( 'wpfactory_wc_sts_reply_added', array( $this, 'remember_reply' ), 1, 3 );
			add_action( 'shutdown', array( $this, 'send_status_emails' ) );
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
			$order   = $this->core->get_ticket_order_id( $ticket_id ) ? wc_get_order( $this->core->get_ticket_order_id( $ticket_id ) ) : false;
			$status  = $this->core->get_ticket_term( $ticket_id, 'stsw_tickets_status' );
			// Names used by saved replies and newer templates.
			$plain += array(
				'{ticketLinkPlain}'     => $link,
				'{customer_first_name}' => (string) $contact['first_name'],
				'{customer_last_name}'  => (string) $contact['last_name'],
				'{customer_name}'       => (string) $contact['display_name'],
				'{customer_email}'      => (string) $contact['email'],
				'{ticket_number}'       => $this->core->get_ticket_number( $ticket_id ),
				'{ticket_title}'        => $title,
				'{ticket_status}'       => $status ? $status->name : '',
				'{ticket_url}'          => $link,
				'{ticket_url_plain}'    => $link,
				'{order_number}'        => $order ? (string) $order->get_order_number() : '',
				'{store_name}'          => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			);
			$html   = array();
			foreach ( $plain as $key => $value ) {
				$html[ $key ] = in_array( $key, array( '{content}', '{responseContent}' ), true ) ? nl2br( esc_html( $value ) ) : esc_html( $value );
			}
			$html['{ticketLink}']       = '<a href="' . esc_url( $link ) . '">' . esc_html__( 'View your request', 'support-ticket-system-for-woocommerce' ) . '</a>';
			$html['{ticket_url}']       = $html['{ticketLink}'];
			$html['{ticketLinkPlain}']  = esc_url( $link );
			$html['{ticket_url_plain}'] = esc_url( $link );
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
			return wp_strip_all_tags( strtr( self::fallbacks( $template, $values['plain'], false ), $values['plain'] ) );
		}

		/**
		 * Smartcodes with a fallback, as {customer_first_name|there}: the value when it is not empty, else the fallback.
		 *
		 * @param string $text   Text.
		 * @param array  $values Placeholder => value.
		 * @param bool   $html   Escape the fallback for HTML.
		 *
		 * @return string
		 */
		public static function fallbacks( $text, $values, $html ) {
			return (string) preg_replace_callback(
				'/\{([A-Za-z_]+)\|([^{}]*)\}/',
				function ( $m ) use ( $values, $html ) {
					$value = $values[ '{' . $m[1] . '}' ] ?? '';
					if ( '' !== trim( wp_strip_all_tags( (string) $value ) ) ) {
						return (string) $value;
					}
					return $html ? esc_html( $m[2] ) : $m[2];
				},
				(string) $text
			);
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
			return wp_kses( strtr( self::fallbacks( wpautop( $template ), $values['html'], true ), $values['html'] ), WPFactory_WC_STS_Init::$allowed_html );
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
		 * One look for every ticket email: greeting, request number and title, a short line, the message, a button and
		 * a footer. Values in `intro`, `message` and `after` must already be safe HTML.
		 *
		 * @param int   $ticket_id Ticket ID (0: no ticket heading).
		 * @param array $args {
		 *     @type string|null $greet   First name for "Hi name," ('' gives "Hi there,"; null: no greeting).
		 *     @type string      $intro   Line under the title.
		 *     @type string      $message Message, shown in a box.
		 *     @type string      $url     Button link.
		 *     @type string      $button  Button text.
		 *     @type string      $after   HTML after the button.
		 *     @type bool        $staff   An email for the team (Ticket #, no greeting).
		 * }
		 *
		 * @return string
		 */
		public function layout( $ticket_id, $args ) {
			$args  = wp_parse_args(
				$args,
				array(
					'greet'   => '',
					'intro'   => '',
					'message' => '',
					'url'     => '',
					'button'  => '',
					'after'   => '',
					'staff'   => false,
				)
			);
			$muted = 'color:#50575e;font-size:13px;';
			$html  = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1d2327;font-size:15px;line-height:1.6">';
			if ( null !== $args['greet'] && ! $args['staff'] ) {
				/* translators: %s: first name, or "there". */
				$html .= '<p style="margin:0 0 16px">' . esc_html( sprintf( __( 'Hi %s,', 'support-ticket-system-for-woocommerce' ), '' !== trim( (string) $args['greet'] ) ? trim( (string) $args['greet'] ) : __( 'there', 'support-ticket-system-for-woocommerce' ) ) ) . '</p>';
			}
			if ( $ticket_id ) {
				$number = $this->core->get_ticket_number( $ticket_id );
				$html  .= '<p style="margin:0;' . $muted . '">' . esc_html(
					$args['staff']
						/* translators: %s: ticket number. */
						? sprintf( __( 'Ticket #%s', 'support-ticket-system-for-woocommerce' ), $number )
						/* translators: %s: ticket number. */
						: sprintf( __( 'Request #%s', 'support-ticket-system-for-woocommerce' ), $number )
				) . '</p>';
				$html .= '<p style="margin:0 0 16px;font-size:18px;font-weight:600;line-height:1.4">' . esc_html( wp_specialchars_decode( get_the_title( $ticket_id ), ENT_QUOTES ) ) . '</p>';
			}
			if ( '' !== $args['intro'] ) {
				$html .= '<p style="margin:0 0 16px">' . $args['intro'] . '</p>';
			}
			if ( '' !== trim( wp_strip_all_tags( (string) $args['message'] ) ) ) {
				$html .= '<div style="margin:0 0 20px;padding:12px 16px;background:#f6f7f7;border-radius:6px">' . $args['message'] . '</div>';
			}
			if ( '' !== $args['url'] && '' !== $args['button'] ) {
				$html .= '<p style="margin:0 0 20px"><a href="' . esc_url( $args['url'] ) . '" style="display:inline-block;padding:10px 18px;background:#2271b1;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600">' . esc_html( $args['button'] ) . '</a></p>';
			}
			$html .= $args['after'];
			$html .= '<p style="margin:24px 0 0;padding-top:12px;border-top:1px solid #dcdcde;' . $muted . '">' . esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . '</p>';
			return $html . '</div>';
		}

		/**
		 * Whether a message already starts with a greeting ("Hi Omar,"), so the email does not add a second one.
		 *
		 * @param string $text Plain text.
		 *
		 * @return bool
		 */
		protected function has_greeting( $text ) {
			return (bool) preg_match( '/^\s*(hi|hello|hey|dear|good (morning|afternoon|evening))\b/i', wp_strip_all_tags( (string) $text ) );
		}

		/**
		 * Whether customers can answer ticket emails by replying (Pro email piping on).
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return bool
		 */
		protected function reply_by_email( $ticket_id ) {
			/**
			 * Whether a reply to a ticket email reaches the ticket (Pro: email piping).
			 *
			 * @param bool $on        Default false.
			 * @param int  $ticket_id Ticket ID.
			 */
			return (bool) apply_filters( 'wpfactory_wc_sts_reply_by_email', false, (int) $ticket_id );
		}

		/**
		 * Subject and button for the next store reply email (Pro: store credit and payment links have their own).
		 *
		 * @var array
		 */
		protected $next_reply = array();

		/**
		 * Gives the next store reply email its own subject, line and button.
		 *
		 * @param array $args { subject, intro, url, button }.
		 */
		public function set_next_reply( $args ) {
			$this->next_reply = (array) $args;
		}

		/**
		 * Plain words for a status, for customers.
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return string HTML.
		 */
		protected function status_words( $ticket_id ) {
			$slug = $this->core->get_ticket_status_slug( $ticket_id );
			$term = $this->core->get_ticket_term( $ticket_id, 'stsw_tickets_status' );
			$map  = array(
				'open'        => __( 'Your request is open again and waiting for our team.', 'support-ticket-system-for-woocommerce' ),
				'in-progress' => __( 'We are working on your request.', 'support-ticket-system-for-woocommerce' ),
				'closed'      => __( 'Your request is closed.', 'support-ticket-system-for-woocommerce' ),
			);
			if ( isset( $map[ $slug ] ) ) {
				return esc_html( $map[ $slug ] );
			}
			/* translators: %s: status name. */
			return esc_html( sprintf( __( 'Your request is now: %s.', 'support-ticket-system-for-woocommerce' ), $term ? $term->name : $slug ) );
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
			$inside  = (bool) get_post_meta( $ticket_id, '_stsw_internal', true );
			// A guest chat gives an email nobody confirmed: no store email to that address until the team replies.
			if ( 'chat' === get_post_meta( $ticket_id, '_stsw_channel', true ) && ! $this->core->get_ticket_user_id( $ticket_id ) ) {
				$inside = true;
			}

			/* translators: 1: ticket number, 2: ticket title. */
			$staff_subject = sprintf( __( 'New ticket #%1$s: %2$s', 'support-ticket-system-for-woocommerce' ), $this->core->get_ticket_number( $ticket_id ), $title );
			$staff_body    = $this->layout(
				$ticket_id,
				array(
					'staff'   => true,
					'intro'   => $this->staff_facts( $ticket_id, $contact ),
					'message' => $values['html']['{content}'] . $fields,
					'url'     => admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&ticket=' . (int) $ticket_id ),
					'button'  => __( 'Open the ticket', 'support-ticket-system-for-woocommerce' ),
				)
			);
			$this->send_staff( $ticket_id, $values, $staff_subject, $staff_body, $contact['email'] );

			if ( ! $inside && $this->is_on( 'mailToCustomer' ) && is_email( $contact['email'] ) ) {
				$subject = '' !== $this->text( 'mailIt_subjectToCust' )
					? $this->fill_subject( $this->text( 'mailIt_subjectToCust' ), $values )
					/* translators: 1: ticket number, 2: ticket title. */
					: sprintf( __( 'We received your request #%1$s: %2$s', 'support-ticket-system-for-woocommerce' ), $this->core->get_ticket_number( $ticket_id ), $title );
				$body = '' !== $this->text( 'mailIt_contentToCust' )
					? $this->layout(
						0,
						array(
							'greet' => null,
							'after' => $this->fill_body( $this->text( 'mailIt_contentToCust' ), $values ),
						)
					)
					: $this->layout(
						$ticket_id,
						array(
							'greet'   => $contact['first_name'],
							/* translators: %s: store name. */
							'intro'   => esc_html( sprintf( __( 'Thank you for contacting %s. We received your request and will reply by email.', 'support-ticket-system-for-woocommerce' ), $site ) ),
							'message' => $values['html']['{content}'],
							'url'     => $values['plain']['{ticketLink}'],
							'button'  => __( 'View your request', 'support-ticket-system-for-woocommerce' ),
						)
					);
				$body = $this->with_guest_link( $ticket_id, $body, $values );
				$this->notify_users( $contact['email'], $subject, $body, '', $ticket_id );
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
			/* translators: 1: store name, 2: ticket number, 3: ticket title. */
			$subject = sprintf( __( 'A message from %1$s - #%2$s %3$s', 'support-ticket-system-for-woocommerce' ), $site, $this->core->get_ticket_number( $ticket_id ), $values['plain']['{title}'] );
			$body    = $this->layout(
				$ticket_id,
				array(
					'greet'   => $this->has_greeting( $content ) ? null : $contact['first_name'],
					/* translators: %s: store name. */
					'intro'   => esc_html( sprintf( __( '%s sent you a message about your request.', 'support-ticket-system-for-woocommerce' ), $site ) ),
					'message' => $values['html']['{content}'],
					'url'     => $values['plain']['{ticketLink}'],
					'button'  => __( 'View your request', 'support-ticket-system-for-woocommerce' ),
				)
			);
			$this->notify_users( $contact['email'], $subject, $this->with_guest_link( $ticket_id, $body, $values ), '', $ticket_id );
		}

		/**
		 * Who asked and about what, for the team: name, email, order, subject, attachments.
		 *
		 * @param int   $ticket_id Ticket ID.
		 * @param array $contact   Ticket contact.
		 *
		 * @return string HTML.
		 */
		protected function staff_facts( $ticket_id, $contact ) {
			$lines    = array();
			$lines[]  = '<strong>' . esc_html__( 'From:', 'support-ticket-system-for-woocommerce' ) . '</strong> ' . esc_html( trim( $contact['display_name'] ) ) . ( is_email( $contact['email'] ) ? ' &lt;<a href="mailto:' . esc_attr( $contact['email'] ) . '">' . esc_html( $contact['email'] ) . '</a>&gt;' : '' );
			$order_id = $this->core->get_ticket_order_id( $ticket_id );
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			if ( $order ) {
				$lines[] = '<strong>' . esc_html__( 'Order:', 'support-ticket-system-for-woocommerce' ) . '</strong> <a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
			}
			$subject = $this->core->get_ticket_term( $ticket_id, 'stsw_tickets_subject' );
			if ( $subject ) {
				$lines[] = '<strong>' . esc_html__( 'Subject:', 'support-ticket-system-for-woocommerce' ) . '</strong> ' . esc_html( $subject->name );
			}
			$files   = array_filter( explode( ',', (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FILES, true ) ) );
			$lines[] = '<strong>' . esc_html__( 'Attachments:', 'support-ticket-system-for-woocommerce' ) . '</strong> ' . (int) count( $files );
			return implode( '<br/>', $lines );
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

				$next             = $this->next_reply;
				$this->next_reply = array();
				if ( $this->is_on( 'mailToCustomer' ) && is_email( $contact['email'] ) ) {
					$subject = '' !== $this->text( 'mailIt_subjectToCust' ) && empty( $next['subject'] )
						? $this->fill_subject( $this->text( 'mailIt_subjectToCust' ), $values )
						/* translators: 1: ticket number, 2: ticket title. */
						: ( $next['subject'] ?? sprintf( __( 'New reply on your request #%1$s: %2$s', 'support-ticket-system-for-woocommerce' ), $this->core->get_ticket_number( $ticket_id ), $title ) );
					$template = $next ? '' : $this->text( 'mailIt_contentToCust' );
					if ( '' === $template ) {
						$body = $this->layout(
							$ticket_id,
							array(
								'greet'   => $this->has_greeting( $reply ) ? null : $contact['first_name'],
								'intro'   => esc_html( $next['intro'] ?? __( 'We replied to your request:', 'support-ticket-system-for-woocommerce' ) ),
								'message' => $values['html']['{responseContent}'],
								'url'     => $next['url'] ?? $values['plain']['{ticketLink}'],
								'button'  => $next['button'] ?? __( 'View your request', 'support-ticket-system-for-woocommerce' ),
								'after'   => empty( $next['url'] ) ? '' : '<p style="margin:0 0 20px"><a href="' . esc_url( $values['plain']['{ticketLink}'] ) . '">' . esc_html__( 'View your request', 'support-ticket-system-for-woocommerce' ) . '</a></p>',
							)
						);
					} elseif ( false !== strpos( $template, '{responseContent}' ) ) {
						$body = $this->layout(
							0,
							array(
								'greet' => null,
								'after' => $this->fill_body( $template, $values ),
							)
						);
					} else {
						// Templates without {responseContent} get the reply after the merchant's text (as before 2.3.0).
						$body = $this->layout(
							0,
							array(
								'greet' => null,
								'after' => $this->fill_body( $template, $values ) . '<br/>' . $values['html']['{responseContent}'] . '<br/>' . $values['html']['{ticketLink}'],
							)
						);
					}
					$this->notify_users( $contact['email'], $subject, $this->with_guest_link( $ticket_id, $body, $values ), '', $ticket_id );
				}

				// Assignee, when someone else answered.
				$assignee = $this->core->get_ticket_assignee_id( $ticket_id );
				if ( $this->is_on( 'mailToAssignee' ) && $assignee && get_current_user_id() !== $assignee ) {
					/* translators: 1: store name, 2: ticket number, 3: ticket title. */
					$subject = sprintf( __( 'Ticket updated on %1$s - #%2$s %3$s', 'support-ticket-system-for-woocommerce' ), $site, $this->core->get_ticket_number( $ticket_id ), $title );
					$body    = $this->layout(
						$ticket_id,
						array(
							'staff'   => true,
							/* translators: %s: staff name. */
							'intro'   => esc_html( sprintf( __( '%s replied to the customer:', 'support-ticket-system-for-woocommerce' ), $this->core->get_user_name( get_current_user_id() ) ) ),
							'message' => $values['html']['{responseContent}'],
							'url'     => admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&ticket=' . (int) $ticket_id ),
							'button'  => __( 'Open the ticket', 'support-ticket-system-for-woocommerce' ),
						)
					);
					$this->send_assignee( $ticket_id, $values, $subject, $body );
				}
				return;
			}

			// Customer reply.
			$values = $this->placeholders( $ticket_id, $reply_id, $reply, $reply );
			/* translators: 1: ticket number, 2: ticket title. */
			$staff_subject = sprintf( __( 'New reply on ticket #%1$s: %2$s', 'support-ticket-system-for-woocommerce' ), $this->core->get_ticket_number( $ticket_id ), $title );
			$staff_body    = $this->layout(
				$ticket_id,
				array(
					'staff'   => true,
					'intro'   => $this->staff_facts( $ticket_id, $contact ),
					'message' => $values['html']['{content}'],
					'url'     => admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&ticket=' . (int) $ticket_id ),
					'button'  => __( 'Open the ticket', 'support-ticket-system-for-woocommerce' ),
				)
			);
			$this->send_staff( $ticket_id, $values, $staff_subject, $staff_body, $contact['email'] );

			// "We received your reply": an option, off unless the store turns it on.
			if ( $this->is_on( 'mailToCustomer' ) && '1' === (string) get_option( 'wpfactory_wc_sts_mail_reply_received' ) && is_email( $contact['email'] ) ) {
				/* translators: 1: ticket number, 2: ticket title. */
				$subject = sprintf( __( 'We received your reply on request #%1$s: %2$s', 'support-ticket-system-for-woocommerce' ), $this->core->get_ticket_number( $ticket_id ), $title );
				$body    = $this->layout(
					$ticket_id,
					array(
						'greet'   => $contact['first_name'],
						'intro'   => esc_html__( 'We received your reply and added it to your request:', 'support-ticket-system-for-woocommerce' ),
						'message' => $values['html']['{content}'],
						'url'     => $values['plain']['{ticketLink}'],
						'button'  => __( 'View your request', 'support-ticket-system-for-woocommerce' ),
					)
				);
				$this->notify_users( $contact['email'], $subject, $this->with_guest_link( $ticket_id, $body, $values ), '', $ticket_id );
			}
		}

		/**
		 * Tickets that got a store reply in this request (their status email is not needed).
		 *
		 * @var int[]
		 */
		protected $replied = array();

		/**
		 * Status emails waiting for the end of the request: ticket => status slug.
		 *
		 * @var array
		 */
		protected $pending_status = array();

		/**
		 * Remembers a store reply (the reply email already tells the customer).
		 *
		 * @param int  $ticket_id Ticket ID.
		 * @param int  $reply_id  Reply ID.
		 * @param bool $by_staff  Whether the store replied.
		 */
		public function remember_reply( $ticket_id, $reply_id, $by_staff ) {
			if ( $by_staff ) {
				$this->replied[] = (int) $ticket_id;
			}
		}

		/**
		 * Status change: queue the closed or status email when the store (or an automatic task) changed it.
		 * Customers who close or reopen their own ticket get no email about it.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $slug      New status.
		 * @param string $old       Old status.
		 */
		public function on_status_changed( $ticket_id, $slug, $old ) {
			if ( get_post_meta( $ticket_id, '_stsw_internal', true ) || '' === $old ) {
				return;
			}
			$by_store = $this->core->can_manage_tickets() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST && $this->core->can_manage_tickets() );
			if ( ! $by_store ) {
				return;
			}
			$this->pending_status[ (int) $ticket_id ] = $slug;
		}

		/**
		 * Sends the queued status emails (end of the request).
		 */
		public function send_status_emails() {
			$pending              = $this->pending_status;
			$this->pending_status = array();
			foreach ( $pending as $ticket_id => $slug ) {
				$closed = 'closed' === $slug;
				$prefix = $closed ? 'wpfactory_wc_sts_mail_closed' : 'wpfactory_wc_sts_mail_status';
				if ( '1' !== (string) get_option( $prefix ) || ( ! $closed && in_array( (int) $ticket_id, $this->replied, true ) ) ) {
					continue;
				}
				$contact = $this->core->get_ticket_contact( $ticket_id );
				if ( ! is_email( $contact['email'] ) ) {
					continue;
				}
				$values  = $this->placeholders( $ticket_id, 0, (string) get_post_field( 'post_content', $ticket_id, 'raw' ), '' );
				$subject = trim( (string) get_option( $prefix . '_subject', '' ) );
				$body    = trim( (string) get_option( $prefix . '_body', '' ) );
				if ( '' === $subject ) {
					$subject = $closed
						/* translators: 1: ticket number, 2: ticket title. */
						? sprintf( __( 'Your request #%1$s is closed: %2$s', 'support-ticket-system-for-woocommerce' ), $values['plain']['{ticket_number}'], $values['plain']['{title}'] )
						/* translators: 1: ticket number, 2: new status. */
						: sprintf( __( 'Update on your request #%1$s: %2$s', 'support-ticket-system-for-woocommerce' ), $values['plain']['{ticket_number}'], $values['plain']['{ticket_status}'] );
				} else {
					$subject = $this->fill_subject( $subject, $values );
				}
				if ( '' === $body ) {
					$pipe = $this->reply_by_email( (int) $ticket_id );
					$body = $this->layout(
						(int) $ticket_id,
						array(
							'greet'  => $contact['first_name'],
							'intro'  => $closed
								? esc_html__( 'We closed your request.', 'support-ticket-system-for-woocommerce' ) . ' ' . ( $pipe
									? esc_html__( 'If you still need help, reply to this email to reopen it.', 'support-ticket-system-for-woocommerce' )
									: esc_html__( 'If you still need help, use this link to reopen it.', 'support-ticket-system-for-woocommerce' ) )
								: $this->status_words( (int) $ticket_id ),
							'url'    => $values['plain']['{ticketLink}'],
							'button' => $closed && ! $pipe ? __( 'Reopen your request', 'support-ticket-system-for-woocommerce' ) : __( 'View your request', 'support-ticket-system-for-woocommerce' ),
						)
					);
				} else {
					$body = $this->layout(
						0,
						array(
							'greet' => null,
							'after' => $this->fill_body( $body, $values ),
						)
					);
				}
				$this->notify_users( $contact['email'], $subject, $this->with_guest_link( $ticket_id, $body, $values ), '', (int) $ticket_id );
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
				$this->notify_users( $this->get_store_email(), $subject, $body, $reply_to, $ticket_id );
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
			$this->notify_users( $assignee->user_email, $subject, $body, '', $ticket_id );
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
		 * @param string $reply_to  Reply-To address.
		 * @param int    $ticket_id Ticket the email is about (0: none).
		 *
		 * @return bool
		 */
		public function notify_users( $to, $subject, $message, $reply_to = '', $ticket_id = 0 ) {
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
			 * @param array  $headers   Headers.
			 * @param string $to        Recipient.
			 * @param string $subject   Subject.
			 * @param int    $ticket_id Ticket ID (0: none).
			 */
			$headers = (array) apply_filters( 'wpfactory_wc_sts_email_headers', $headers, $to, $subject, (int) $ticket_id );
			/**
			 * Subject of ticket emails (Pro: email piping adds the ticket tag).
			 *
			 * @param string $subject   Subject.
			 * @param int    $ticket_id Ticket ID (0: none).
			 * @param string $to        Recipient.
			 */
			$subject = (string) apply_filters( 'wpfactory_wc_sts_email_subject', $subject, (int) $ticket_id, $to );
			/**
			 * Body of ticket emails (Pro: email piping adds the reply line).
			 *
			 * @param string $message   HTML body.
			 * @param int    $ticket_id Ticket ID (0: none).
			 * @param string $to        Recipient.
			 */
			$message = (string) apply_filters( 'wpfactory_wc_sts_email_message', $message, (int) $ticket_id, $to );
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
			$sender = $this->get_sender();
			$store  = $sender['email'];
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
					'label' => '' !== $sender['by']
						/* translators: 1: email address, 2: plugin or filter name. */
						? sprintf( __( 'Sender address: %1$s (set by %2$s)', 'support-ticket-system-for-woocommerce' ), $store, $sender['by'] )
						/* translators: %s: email address. */
						: sprintf( __( 'Sender address: %s', 'support-ticket-system-for-woocommerce' ), $store ),
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
			$late   = 0;
			foreach ( (array) _get_cron_array() as $time => $hooks ) {
				if ( (int) $time < time() - 15 * MINUTE_IN_SECONDS ) {
					$late += count( (array) $hooks );
				}
			}
			$by_server = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
			$checks[]  = array(
				'ok'    => 0 === $late,
				'label' => 0 === $late
					? ( $by_server ? __( 'Scheduled tasks run on time (started by your server)', 'support-ticket-system-for-woocommerce' ) : __( 'Scheduled tasks run on time', 'support-ticket-system-for-woocommerce' ) )
					/* translators: %d: number of late tasks. */
					: sprintf( _n( '%d scheduled task is more than 15 minutes late', '%d scheduled tasks are more than 15 minutes late', $late, 'support-ticket-system-for-woocommerce' ), $late ),
				'fix'   => $by_server
					? __( 'WordPress scheduled tasks are turned off (DISABLE_WP_CRON) and nothing seems to run them. Ask your host to run wp-cron.php every 5 minutes. Email piping, automatic closing and data retention depend on it.', 'support-ticket-system-for-woocommerce' )
					: __( 'Scheduled tasks only run when someone visits the site. On a quiet site, ask your host to run wp-cron.php every 5 minutes. Email piping, automatic closing and data retention depend on it.', 'support-ticket-system-for-woocommerce' ),
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
			$to   = sanitize_email( wp_unslash( $_POST['stsw_test_to'] ?? '' ) );
			$to   = is_email( $to ) ? $to : $this->get_store_email();
			$sent = $this->notify_users(
				$to,
				/* translators: %s: store name. */
				sprintf( __( 'Test email from %s support tickets', 'support-ticket-system-for-woocommerce' ), $site ),
				$this->layout(
					0,
					array(
						'greet' => null,
						'intro' => esc_html__( 'This is a test. If you received it, ticket emails from your store can reach this address.', 'support-ticket-system-for-woocommerce' ),
					)
				)
			);
			$error = (array) get_option( 'wpfactory_wc_sts_mail_error', array() );
			update_option(
				'wpfactory_wc_sts_email_test',
				array(
					'time'  => time(),
					'ok'    => $sent,
					'to'    => $to,
					'error' => $sent ? '' : (string) ( $error['message'] ?? '' ),
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
				) . '#notifications'
			);
			exit;
		}

		/**
		 * "Send test email": a recipient (the admin email by default), the result of the last test, on the Emails tab.
		 * The settings screen is one big form, so the test is sent by a small form placed after it (see `form`).
		 */
		public function render_test_email_button() {
			$result = isset( $_GET['wpfactory_wc_sts_test_email'] ) ? sanitize_key( wp_unslash( $_GET['wpfactory_wc_sts_test_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			$test   = (array) get_option( 'wpfactory_wc_sts_email_test', array() );
			$to     = ! empty( $test['to'] ) ? $test['to'] : sanitize_email( get_bloginfo( 'admin_email' ) );
			if ( 'sent' === $result ) {
				echo '<div class="notice notice-success inline stsw-test-result" role="status"><p>' . esc_html(
					sprintf(
						/* translators: %s: email address. */
						__( 'Test email sent to %s. If it does not arrive in a few minutes, check the spam folder and your SMTP plugin.', 'support-ticket-system-for-woocommerce' ),
						$to
					)
				) . '</p></div>';
			} elseif ( 'failed' === $result ) {
				echo '<div class="notice notice-error inline stsw-test-result" role="alert"><p>' . esc_html(
					sprintf(
						/* translators: 1: email address, 2: error message. */
						__( 'The test email to %1$s was not sent. %2$s', 'support-ticket-system-for-woocommerce' ),
						$to,
						! empty( $test['error'] ) ? $test['error'] : __( 'Your site probably needs an SMTP plugin to send email.', 'support-ticket-system-for-woocommerce' )
					)
				) . '</p></div>';
			}
			echo '<div class="stsw-test-email"><label for="stsw-test-to">' . esc_html__( 'Send a test email to', 'support-ticket-system-for-woocommerce' ) . '</label> ';
			echo '<input type="email" id="stsw-test-to" name="stsw_test_to" form="stsw-test-email-form" value="' . esc_attr( $to ) . '" /> ';
			echo '<button type="submit" class="button" form="stsw-test-email-form">' . esc_html__( 'Send test email', 'support-ticket-system-for-woocommerce' ) . '</button></div>';
			add_action( 'admin_footer', array( $this, 'render_test_email_form' ) );
		}

		/**
		 * The small form the test email button submits (outside the settings form).
		 */
		public function render_test_email_form() {
			echo '<form id="stsw-test-email-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" hidden><input type="hidden" name="action" value="wpfactory_wc_sts_test_email" />';
			wp_nonce_field( 'wpfactory_wc_sts_test_email' );
			echo '</form>';
		}

		/**
		 * The address emails really leave from: an SMTP plugin can force its own sender.
		 *
		 * @return array { email: string, by: string } `by` names the plugin that sets it ('' when it is the store email).
		 */
		public function get_sender() {
			$email  = $this->get_store_email();
			$by     = '';
			$fluent = get_option( 'fluentmail-settings' );
			if ( is_array( $fluent ) && ! empty( $fluent['connections'] ) && defined( 'FLUENTMAIL' ) ) {
				$default = $fluent['misc']['default_connection'] ?? '';
				$conn    = $default && isset( $fluent['connections'][ $default ] ) ? $fluent['connections'][ $default ] : reset( $fluent['connections'] );
				$set     = (array) ( $conn['provider_settings'] ?? array() );
				if ( ! empty( $set['sender_email'] ) && is_email( $set['sender_email'] ) ) {
					$email = $set['sender_email'];
					$by    = 'FluentSMTP';
				}
			}
			$wpms = get_option( 'wp_mail_smtp' );
			if ( '' === $by && is_array( $wpms ) && function_exists( 'wp_mail_smtp' ) && ! empty( $wpms['mail']['from_email'] ) && ! empty( $wpms['mail']['from_email_force'] ) ) {
				$email = $wpms['mail']['from_email'];
				$by    = 'WP Mail SMTP';
			}
			/** This filter is documented in wp-includes/pluggable.php */
			$filtered = (string) apply_filters( 'wp_mail_from', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the WordPress filter.
			if ( is_email( $filtered ) && strtolower( $filtered ) !== strtolower( $email ) ) {
				$email = $filtered;
				$by    = '' !== $by ? $by : 'wp_mail_from';
			}
			return array(
				'email' => sanitize_email( $email ),
				'by'    => $by,
			);
		}
	}

endif;

return WPFactory_WC_STS_Emails::get_instance();
