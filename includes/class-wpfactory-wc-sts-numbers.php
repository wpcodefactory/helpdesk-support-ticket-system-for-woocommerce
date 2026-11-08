<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Ticket numbers
 *
 * Public ticket numbers, separate from the post ID: an optional prefix and a sequential (with a start number) or random
 * number of a set length. Tickets created before the setting keep their ID as their number.
 *
 * @version 3.0.0
 * @since   3.0.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Numbers' ) ) :

	/**
	 * Ticket numbers.
	 */
	class WPFactory_WC_STS_Numbers {

		/**
		 * Ticket meta holding the public number.
		 */
		const META = '_stsw_number';

		/**
		 * Settings (options shared by both editions).
		 */
		const OPTION_FORMAT = 'wpfactory_wc_sts_number_format';
		const OPTION_PREFIX = 'wpfactory_wc_sts_number_prefix';
		const OPTION_START  = 'wpfactory_wc_sts_number_start';
		const OPTION_LENGTH = 'wpfactory_wc_sts_number_length';
		const OPTION_NEXT   = 'wpfactory_wc_sts_number_next';

		/**
		 * Instance.
		 *
		 * @var WPFactory_WC_STS_Numbers|null
		 */
		protected static $instance = null;

		/**
		 * Instance.
		 *
		 * @return WPFactory_WC_STS_Numbers
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
			// Before the emails (priority 10), so they show the new number.
			add_action( 'wpfactory_wc_sts_ticket_created', array( $this, 'assign' ), 1 );
			add_filter( 'wpfactory_wc_sts_ticket_number', array( $this, 'filter_number' ), 10, 2 );
			add_filter( 'wpfactory_wc_sts_settings_general', array( $this, 'settings' ), 20 );
			add_filter( 'wpfactory_wc_sts_find_ticket_by_number', array( $this, 'find' ), 10, 2 );
		}

		/**
		 * Settings rows (General > Ticket numbers).
		 *
		 * @param array $fields Fields.
		 *
		 * @return array
		 */
		public function settings( $fields ) {
			$fields[ '@' . self::OPTION_FORMAT ] = array(
				'type'        => 'select',
				'card'        => 'numbers',
				'label'       => __( 'Ticket numbers', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'Applies to new tickets. Existing tickets keep their current number.', 'support-ticket-system-for-woocommerce' ),
				'options'     => array(
					''           => __( 'Same as the ticket ID', 'support-ticket-system-for-woocommerce' ),
					'sequential' => __( 'Sequential, from a start number', 'support-ticket-system-for-woocommerce' ),
					'random'     => __( 'Random, with a set length', 'support-ticket-system-for-woocommerce' ),
				),
			);
			$fields[ '@' . self::OPTION_PREFIX ] = array(
				'type'        => 'text',
				'card'        => 'numbers',
				'label'       => __( 'Number prefix', 'support-ticket-system-for-woocommerce' ),
				'placeholder' => 'HD-',
				'description' => __( 'Optional, for example HD- gives HD-1001.', 'support-ticket-system-for-woocommerce' ),
			);
			$fields[ '@' . self::OPTION_START ]  = array(
				'type'        => 'number',
				'card'        => 'numbers',
				'label'       => __( 'Start number (sequential)', 'support-ticket-system-for-woocommerce' ),
				'placeholder' => '1000',
				'description' => __( 'Merchants moving from another helpdesk can continue their numbering here.', 'support-ticket-system-for-woocommerce' ),
			);
			$fields[ '@' . self::OPTION_LENGTH ] = array(
				'type'        => 'number',
				'card'        => 'numbers',
				'label'       => __( 'Number length (random)', 'support-ticket-system-for-woocommerce' ),
				'placeholder' => '6',
				'description' => __( 'Digits in a random number, from 4 to 12.', 'support-ticket-system-for-woocommerce' ),
			);
			return $fields;
		}

		/**
		 * Format in use: '', sequential or random.
		 *
		 * @return string
		 */
		public function get_format() {
			$format = (string) get_option( self::OPTION_FORMAT, '' );
			return in_array( $format, array( 'sequential', 'random' ), true ) ? $format : '';
		}

		/**
		 * Gives a new ticket its number (nothing when numbers follow the ID).
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function assign( $ticket_id ) {
			$format = $this->get_format();
			if ( '' === $format || '' !== (string) get_post_meta( $ticket_id, self::META, true ) ) {
				return;
			}
			$number = 'random' === $format ? $this->next_random() : $this->next_sequential();
			if ( '' === $number ) {
				return;
			}
			$prefix = sanitize_text_field( (string) get_option( self::OPTION_PREFIX, '' ) );
			update_post_meta( $ticket_id, self::META, $prefix . $number );
		}

		/**
		 * Next sequential number. The counter is raised in one query, so two tickets never get the same number.
		 *
		 * @return string
		 */
		protected function next_sequential() {
			global $wpdb;
			$start = absint( get_option( self::OPTION_START, 1000 ) );
			$start = $start ? $start : 1;
			if ( false === get_option( self::OPTION_NEXT, false ) ) {
				add_option( self::OPTION_NEXT, (string) $start, '', false );
			}
			// A start number raised after tickets were numbered moves the counter up, never down.
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = GREATEST( CAST( option_value AS UNSIGNED ), %d ) + 1 WHERE option_name = %s",
					$start,
					self::OPTION_NEXT
				)
			);
			wp_cache_delete( self::OPTION_NEXT, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			$next = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION_NEXT ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $next > 0 ? (string) ( $next - 1 ) : '';
		}

		/**
		 * Random number of the set length that no ticket has yet.
		 *
		 * @return string
		 */
		protected function next_random() {
			$length = absint( get_option( self::OPTION_LENGTH, 6 ) );
			$length = min( 12, max( 4, $length ? $length : 6 ) );
			$prefix = sanitize_text_field( (string) get_option( self::OPTION_PREFIX, '' ) );
			for ( $try = 0; $try < 20; $try++ ) {
				$number = (string) wp_rand( 1, 9 );
				for ( $digit = 1; $digit < $length; $digit++ ) {
					$number .= (string) wp_rand( 0, 9 );
				}
				if ( ! $this->find( 0, $prefix . $number ) ) {
					return $number;
				}
			}
			return '';
		}

		/**
		 * Public number of a ticket (without "#").
		 *
		 * @param string $number    Number so far (the ID).
		 * @param int    $ticket_id Ticket ID.
		 *
		 * @return string
		 */
		public function filter_number( $number, $ticket_id ) {
			$stored = (string) get_post_meta( $ticket_id, self::META, true );
			return '' !== $stored ? $stored : $number;
		}

		/**
		 * Ticket with a public number.
		 *
		 * @param int    $found  Ticket found so far.
		 * @param string $number Number as typed (a leading "#" is ignored).
		 *
		 * @return int
		 */
		public function find( $found, $number ) {
			if ( $found ) {
				return (int) $found;
			}
			$number = ltrim( trim( (string) $number ), '#' );
			if ( '' === $number ) {
				return 0;
			}
			$ids = get_posts(
				array(
					'post_type'      => 'stsw_tickets',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			return $ids ? (int) $ids[0] : 0;
		}
	}

endif;

return WPFactory_WC_STS_Numbers::get_instance();
