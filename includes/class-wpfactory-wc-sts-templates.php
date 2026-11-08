<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Template loader
 *
 * Every customer screen is a template in the plugin's `templates/` folder. A theme overrides one by copying it to
 * `yourtheme/woocommerce-support/<name>.php` (child theme first, then parent theme).
 *
 * @version 3.0.0
 * @since   3.0.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Templates' ) ) :

	/**
	 * Templates.
	 */
	class WPFactory_WC_STS_Templates {

		/**
		 * Folder in the theme.
		 */
		const THEME_FOLDER = 'woocommerce-support';

		/**
		 * Full path of a template: theme override or the plugin's copy.
		 *
		 * @param string $name Template name without `.php`, e.g. `ticket-list`.
		 *
		 * @return string
		 */
		public static function locate( $name ) {
			$name = sanitize_file_name( $name ) . '.php';
			/**
			 * Folder in the theme for template overrides.
			 *
			 * @param string $folder Folder name.
			 */
			$folder = trim( (string) apply_filters( 'wpfactory_wc_sts_template_folder', self::THEME_FOLDER ), '/' );
			$found  = locate_template( array( $folder . '/' . $name ) );
			if ( ! $found ) {
				$found = plugin_dir_path( WPFACTORY_WC_STS_FILE ) . 'templates/' . $name;
			}
			/**
			 * Path of a customer screen template.
			 *
			 * @param string $found Path.
			 * @param string $name  File name.
			 */
			return (string) apply_filters( 'wpfactory_wc_sts_template', $found, $name );
		}

		/**
		 * Prints a template. Variables in `$args` are available by name; `$view` is the customer screens object.
		 *
		 * @param string $name Template name.
		 * @param array  $args Variables.
		 */
		public static function render( $name, $args = array() ) {
			$file = self::locate( $name );
			if ( ! is_readable( $file ) ) {
				return;
			}
			/**
			 * Before a customer screen template.
			 *
			 * @param string $name Template name.
			 * @param array  $args Variables.
			 */
			do_action( 'wpfactory_wc_sts_before_template', $name, $args );
			( static function ( $wpfactory_wc_sts_file, $args ) {
				extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables, as WooCommerce templates.
				include $wpfactory_wc_sts_file;
			} )( $file, $args );
			/**
			 * After a customer screen template.
			 *
			 * @param string $name Template name.
			 * @param array  $args Variables.
			 */
			do_action( 'wpfactory_wc_sts_after_template', $name, $args );
		}

		/**
		 * Template output as a string.
		 *
		 * @param string $name Template name.
		 * @param array  $args Variables.
		 *
		 * @return string
		 */
		public static function get( $name, $args = array() ) {
			ob_start();
			self::render( $name, $args );
			return (string) ob_get_clean();
		}
	}

endif;
