<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: saved replies.
 *
 * Saved answers (private post type `stsw_canned_reply`) that staff insert into the reply box with one click.
 * Shared by the free and Pro editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Canned' ) ) :

	/**
	 * WPFactory_WC_STS_Canned class.
	 *
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	class WPFactory_WC_STS_Canned {

		/**
		 * Post type.
		 */
		const POST_TYPE = 'stsw_canned_reply';

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Canned|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Canned
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
			add_action( 'init', array( $this, 'register_post_type' ) );
			add_filter( 'parent_file', array( $this, 'highlight_menu' ) );
		}

		/**
		 * Registers the private post type. The reply text is the post content.
		 */
		public function register_post_type() {
			register_post_type(
				self::POST_TYPE,
				array(
					'labels'              => array(
						'name'          => __( 'Saved replies', 'support-ticket-system-for-woocommerce' ),
						'singular_name' => __( 'Saved reply', 'support-ticket-system-for-woocommerce' ),
						'add_new'       => __( 'Add saved reply', 'support-ticket-system-for-woocommerce' ),
						'add_new_item'  => __( 'Add saved reply', 'support-ticket-system-for-woocommerce' ),
						'edit_item'     => __( 'Edit saved reply', 'support-ticket-system-for-woocommerce' ),
						'not_found'     => __( 'No saved replies yet.', 'support-ticket-system-for-woocommerce' ),
						'search_items'  => __( 'Search saved replies', 'support-ticket-system-for-woocommerce' ),
					),
					'public'              => false,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'show_ui'             => true,
					'show_in_menu'        => false,
					'show_in_rest'        => false,
					'supports'            => array( 'title', 'editor' ),
					'capability_type'     => 'page',
					'capabilities'        => WPFactory_WC_STS_Inc::get_main_instance()->get_ticket_capabilities( 'stsw_tickets' ),
					'map_meta_cap'        => true,
					'rewrite'             => false,
					'query_var'           => false,
				)
			);
		}

		/**
		 * Keeps the Support Tickets menu open on the saved replies screens.
		 *
		 * @param string $parent_file Parent menu file.
		 *
		 * @return string
		 */
		public function highlight_menu( $parent_file ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			return ( $screen && self::POST_TYPE === $screen->post_type ) ? WPFACTORY_WC_STS_PAGE : $parent_file;
		}

		/**
		 * Saved replies, by title.
		 *
		 * @return WP_Post[]
		 */
		public function get_replies() {
			return get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
		}

		/**
		 * Placeholder values of a ticket.
		 *
		 * @param int $ticket_id Ticket ID (0: none).
		 *
		 * @return array placeholder => value
		 */
		public function get_placeholders( $ticket_id ) {
			$core   = WPFactory_WC_STS_Inc::get_main_instance();
			$values = array(
				'{customer_first_name}' => '',
				'{order_number}'        => '',
				'{ticket_number}'       => $ticket_id ? $core->get_ticket_number( $ticket_id ) : '',
				'{store_name}'          => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			);
			if ( $ticket_id ) {
				$contact = $core->get_ticket_contact( $ticket_id );
				$user_id = $core->get_ticket_user_id( $ticket_id );
				$first   = $user_id ? (string) get_user_meta( $user_id, 'first_name', true ) : '';
				$order   = $core->get_ticket_order_id( $ticket_id ) ? wc_get_order( $core->get_ticket_order_id( $ticket_id ) ) : false;
				if ( '' === $first && $order ) {
					$first = $order->get_billing_first_name();
				}
				if ( '' === $first ) {
					$parts = explode( ' ', trim( (string) $contact['display_name'] ) );
					$first = $parts[0];
				}
				$values['{customer_first_name}'] = $first;
				$values['{order_number}']        = $order ? (string) $order->get_order_number() : '';
			}
			return $values;
		}

		/**
		 * "Saved reply" picker in a composer toolbar (the script copies the text into the box).
		 *
		 * @param string $target    ID of the textarea.
		 * @param int    $ticket_id Ticket whose details fill the placeholders.
		 */
		public function render_select( $target, $ticket_id = 0 ) {
			$replies = $this->get_replies();
			$add_url = admin_url( 'admin.php?page=' . WPFACTORY_WC_STS_PAGE . '&tab=saved' );
			if ( empty( $replies ) ) {
				echo '<a class="btn btn-text btn-sm" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add saved replies', 'support-ticket-system-for-woocommerce' ) . '</a>';
				return;
			}
			$values = $this->get_placeholders( $ticket_id );
			echo '<label class="screen-reader-text" for="stsw-canned">' . esc_html__( 'Insert a saved reply', 'support-ticket-system-for-woocommerce' ) . '</label>';
			echo '<select id="stsw-canned" class="wpfactory-wc-sts-canned" data-target="' . esc_attr( $target ) . '"><option value="">' . esc_html__( 'Saved reply', 'support-ticket-system-for-woocommerce' ) . '</option>';
			foreach ( $replies as $reply ) {
				$text = trim( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br />', '<br/>' ), "\n", (string) $reply->post_content ) ) );
				$text = strtr( WPFactory_WC_STS_Emails::fallbacks( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ), $values, false ), $values );
				echo '<option value="' . (int) $reply->ID . '" data-text="' . esc_attr( $text ) . '">' . esc_html( get_the_title( $reply ) ) . '</option>';
			}
			echo '</select>';
		}
	}

endif;

return WPFactory_WC_STS_Canned::get_instance();
