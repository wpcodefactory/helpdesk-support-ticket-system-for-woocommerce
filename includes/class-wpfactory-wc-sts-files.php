<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: ticket attachments.
 *
 * Uploads go to uploads/stsw-tickets/<random>/ under a random name, are hidden from the Media Library and are served
 * only through a checked download link (the ticket's customer, a guest with the ticket link, or store staff).
 * Shared by the free and Pro editions; meta names `_stsw_protected` and `_stsw_original_name` unchanged.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Files' ) ) :

	/**
	 * WPFactory_WC_STS_Files class.
	 *
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	class WPFactory_WC_STS_Files {

		/**
		 * File types allowed when the setting is empty.
		 */
		const DEFAULT_EXTENSIONS = 'jpg,jpeg,png,gif,pdf';

		/**
		 * Problems found in the last upload, for the customer.
		 *
		 * @var string[]
		 */
		public $errors = array();

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Files|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Files
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
			add_action( 'template_redirect', array( $this, 'maybe_serve_protected_file' ), 1 );
			add_filter( 'wp_get_attachment_url', array( $this, 'protected_attachment_url' ), 10, 2 );
			add_filter( 'ajax_query_attachments_args', array( $this, 'hide_protected_from_media_modal' ) );
			add_action( 'pre_get_posts', array( $this, 'hide_protected_from_media_list' ) );
			add_action( 'admin_init', array( $this, 'maybe_mark_old_ticket_files' ) );
			add_filter( 'wpfactory_wc_sts_settings_general', array( $this, 'settings' ), 31 );
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
		 * Allowed extensions (lower case).
		 *
		 * @return string[]
		 */
		public function get_allowed_extensions() {
			$setting = (string) get_option( $this->core()->plugin . 'allowedExtensions' );
			$setting = '' !== trim( $setting ) ? $setting : self::DEFAULT_EXTENSIONS;
			return array_values( array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', str_replace( '.', '', $setting ) ) ) ) ) );
		}

		/**
		 * Maximum file size in bytes (setting in MB, never above the server limit).
		 *
		 * @return int
		 */
		public function get_max_size() {
			$mb     = (float) get_option( $this->core()->plugin . 'allowedSize' );
			$server = (int) wp_max_upload_size();
			return $mb > 0 ? (int) min( $mb * MB_IN_BYTES, $server ) : $server;
		}

		/**
		 * Maximum number of files per message.
		 *
		 * @return int
		 */
		public function get_max_files() {
			$max = absint( get_option( $this->core()->plugin . 'allowedAttachNum' ) );
			return $max ? $max : 5;
		}

		/**
		 * `accept` attribute for the file input.
		 *
		 * @return string
		 */
		public function get_accept_attribute() {
			return implode(
				',',
				array_map(
					function ( $ext ) {
						return '.' . $ext;
					},
					$this->get_allowed_extensions()
				)
			);
		}

		/**
		 * Rules shown under the file input.
		 *
		 * @return string
		 */
		public function get_rules_text() {
			return sprintf(
				/* translators: 1: number of files, 2: file types, 3: maximum size. */
				__( 'Up to %1$d files: %2$s, %3$s each at most.', 'support-ticket-system-for-woocommerce' ),
				$this->get_max_files(),
				implode( ', ', $this->get_allowed_extensions() ),
				size_format( $this->get_max_size() )
			);
		}

		/**
		 * Stores uploaded files of a form field as protected attachments of the ticket.
		 *
		 * @version 2.3.0
		 *
		 * @param string $field     `$_FILES` key.
		 * @param int    $ticket_id Ticket ID (attachment parent).
		 * @param string $format    `ids` or `urls` (download links).
		 *
		 * @return array
		 */
		public function handle_upload( $field, $ticket_id, $format = 'ids' ) {
			$this->errors = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the calling form verified its nonce; file arrays are checked below.
			$files = isset( $_FILES[ $field ] ) ? $_FILES[ $field ] : null;
			if ( ! $files || empty( $files['name'] ) ) {
				return array();
			}
			if ( ! is_array( $files['name'] ) ) {
				$files = array(
					'name'     => array( $files['name'] ),
					'type'     => array( $files['type'] ),
					'tmp_name' => array( $files['tmp_name'] ),
					'error'    => array( $files['error'] ),
					'size'     => array( $files['size'] ),
				);
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$allowed = $this->get_allowed_extensions();
			$max     = $this->get_max_size();
			$limit   = $this->get_max_files();
			$result  = array();
			$count   = 0;

			foreach ( array_keys( (array) $files['name'] ) as $key ) {
				$name = sanitize_file_name( (string) $files['name'][ $key ] );
				if ( '' === $name || UPLOAD_ERR_NO_FILE === (int) $files['error'][ $key ] ) {
					continue;
				}
				if ( $count >= $limit ) {
					/* translators: %d: maximum number of files. */
					$this->errors[] = sprintf( __( 'Only %d files can be attached; the others were not saved.', 'support-ticket-system-for-woocommerce' ), $limit );
					break;
				}
				$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, $allowed, true ) ) {
					/* translators: %s: file name. */
					$this->errors[] = sprintf( __( '%s: this file type is not allowed.', 'support-ticket-system-for-woocommerce' ), $name );
					continue;
				}
				if ( (int) $files['size'][ $key ] > $max ) {
					/* translators: 1: file name, 2: maximum size. */
					$this->errors[] = sprintf( __( '%1$s is larger than %2$s.', 'support-ticket-system-for-woocommerce' ), $name, size_format( $max ) );
					continue;
				}

				$file = array(
					'name'     => strtolower( wp_generate_password( 16, false, false ) ) . '-' . $name,
					'type'     => (string) $files['type'][ $key ],
					'tmp_name' => (string) $files['tmp_name'][ $key ],
					'error'    => (int) $files['error'][ $key ],
					'size'     => (int) $files['size'][ $key ],
				);
				add_filter( 'upload_dir', array( $this, 'protected_upload_dir' ) );
				// `wp_handle_upload()` checks the real file type against WordPress's allowed types.
				$moved = wp_handle_upload( $file, array( 'test_form' => false ) );
				remove_filter( 'upload_dir', array( $this, 'protected_upload_dir' ) );
				if ( ! $moved || isset( $moved['error'] ) ) {
					/* translators: %s: file name. */
					$this->errors[] = sprintf( __( '%s could not be uploaded.', 'support-ticket-system-for-woocommerce' ), $name );
					continue;
				}

				$id = wp_insert_attachment(
					array(
						'post_mime_type' => $moved['type'],
						'guid'           => $moved['url'],
						'post_parent'    => (int) $ticket_id,
						'post_title'     => preg_replace( '/\.[^.]+$/', '', $name ),
						'post_content'   => '',
					),
					$moved['file'],
					(int) $ticket_id
				);
				if ( ! $id || is_wp_error( $id ) ) {
					continue;
				}
				// No thumbnails: they would be extra public copies. The file is only served through the download link.
				update_post_meta( $id, '_stsw_protected', 1 );
				update_post_meta( $id, '_stsw_ticket_file', 1 );
				update_post_meta( $id, '_stsw_original_name', $name );
				$result[] = 'urls' === $format ? $this->get_protected_download_url( $id ) : (int) $id;
				++$count;
			}
			return $result;
		}

		/**
		 * Saves files that did not come from a form (for example email attachments) as ticket files, with the same
		 * rules as uploads: allowed types, maximum size and number of files, real type checked, protected folder.
		 *
		 * @param array $files     Each `name` and `data` (file contents).
		 * @param int   $ticket_id Ticket ID.
		 *
		 * @return int[] Attachment IDs.
		 */
		public function save_files( $files, $ticket_id ) {
			$this->errors = array();
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$allowed = $this->get_allowed_extensions();
			$max     = $this->get_max_size();
			$limit   = $this->get_max_files();
			$result  = array();
			foreach ( (array) $files as $file ) {
				$name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
				$data = (string) ( $file['data'] ?? '' );
				if ( '' === $name || '' === $data ) {
					continue;
				}
				if ( count( $result ) >= $limit ) {
					/* translators: %d: maximum number of files. */
					$this->errors[] = sprintf( __( 'Only %d files can be attached; the others were not saved.', 'support-ticket-system-for-woocommerce' ), $limit );
					break;
				}
				$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, $allowed, true ) ) {
					/* translators: %s: file name. */
					$this->errors[] = sprintf( __( '%s: this file type is not allowed.', 'support-ticket-system-for-woocommerce' ), $name );
					continue;
				}
				if ( strlen( $data ) > $max ) {
					/* translators: 1: file name, 2: maximum size. */
					$this->errors[] = sprintf( __( '%1$s is larger than %2$s.', 'support-ticket-system-for-woocommerce' ), $name, size_format( $max ) );
					continue;
				}
				$tmp = wp_tempnam( $name );
				if ( ! $tmp || false === file_put_contents( $tmp, $data ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary file for wp_handle_sideload().
					continue;
				}
				$sideload = array(
					'name'     => strtolower( wp_generate_password( 16, false, false ) ) . '-' . $name,
					'tmp_name' => $tmp,
					'error'    => 0,
					'size'     => strlen( $data ),
				);
				add_filter( 'upload_dir', array( $this, 'protected_upload_dir' ) );
				// `wp_handle_sideload()` checks the real file type against WordPress's allowed types.
				$moved = wp_handle_sideload( $sideload, array( 'test_form' => false ) );
				remove_filter( 'upload_dir', array( $this, 'protected_upload_dir' ) );
				if ( file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
				if ( ! $moved || isset( $moved['error'] ) ) {
					/* translators: %s: file name. */
					$this->errors[] = sprintf( __( '%s could not be uploaded.', 'support-ticket-system-for-woocommerce' ), $name );
					continue;
				}
				$id = wp_insert_attachment(
					array(
						'post_mime_type' => $moved['type'],
						'guid'           => $moved['url'],
						'post_parent'    => (int) $ticket_id,
						'post_title'     => preg_replace( '/\.[^.]+$/', '', $name ),
						'post_content'   => '',
					),
					$moved['file'],
					(int) $ticket_id
				);
				if ( ! $id || is_wp_error( $id ) ) {
					continue;
				}
				update_post_meta( $id, '_stsw_protected', 1 );
				update_post_meta( $id, '_stsw_ticket_file', 1 );
				update_post_meta( $id, '_stsw_original_name', $name );
				$result[] = (int) $id;
			}
			return $result;
		}

		/**
		 * Upload folder for ticket files: uploads/stsw-tickets/<random>/, protected with .htaccess and index.php.
		 *
		 * @param array $dirs Upload dir data.
		 *
		 * @return array
		 */
		public function protected_upload_dir( $dirs ) {
			$base = trailingslashit( $dirs['basedir'] ) . 'stsw-tickets';
			if ( ! is_dir( $base ) ) {
				wp_mkdir_p( $base );
			}
			if ( ! file_exists( $base . '/.htaccess' ) ) {
				// Apache and LiteSpeed. Nginx ignores this file: there the random folder and file names protect the files.
				@file_put_contents( $base . '/.htaccess', "# Ticket files are served by the plugin after an access check.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
			if ( ! file_exists( $base . '/index.php' ) ) {
				@file_put_contents( $base . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
			$sub            = '/stsw-tickets/' . strtolower( wp_generate_password( 12, false, false ) );
			$dirs['subdir'] = $sub;
			$dirs['path']   = $dirs['basedir'] . $sub;
			$dirs['url']    = $dirs['baseurl'] . $sub;
			return $dirs;
		}

		/**
		 * Download link of a protected ticket file.
		 *
		 * @param int   $attachment_id Attachment ID.
		 * @param array $extra         Extra query args (guest access).
		 *
		 * @return string
		 */
		public function get_protected_download_url( $attachment_id, $extra = array() ) {
			return add_query_arg( array_merge( array( 'stsw_file' => (int) $attachment_id ), $extra ), home_url( '/' ) );
		}

		/**
		 * Protected ticket files: every `wp_get_attachment_url()` returns the checked download link.
		 *
		 * @param string $url           URL.
		 * @param int    $attachment_id Attachment ID.
		 *
		 * @return string
		 */
		public function protected_attachment_url( $url, $attachment_id ) {
			return get_post_meta( $attachment_id, '_stsw_protected', true ) ? $this->get_protected_download_url( $attachment_id ) : $url;
		}

		/**
		 * File name shown for an attachment.
		 *
		 * @param int $attachment_id Attachment ID.
		 *
		 * @return string
		 */
		protected function get_display_name( $attachment_id ) {
			$name = (string) get_post_meta( $attachment_id, '_stsw_original_name', true );
			return '' !== $name ? $name : get_the_title( $attachment_id );
		}

		/**
		 * Prints a link to a reply attachment.
		 *
		 * @param int   $attachment_id Attachment ID.
		 * @param array $extra         Extra query args (guest access).
		 */
		public function print_attachment_link( $attachment_id, $extra = array() ) {
			if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
				return;
			}
			$url = get_post_meta( $attachment_id, '_stsw_protected', true ) ? $this->get_protected_download_url( $attachment_id, $extra ) : wp_get_attachment_url( $attachment_id );
			echo '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><i class="fa fa-file-o" aria-hidden="true"></i> ' . esc_html( $this->get_display_name( $attachment_id ) ) . '</a></p>';
		}

		/**
		 * Prints a ticket file link (stored as a URL in the `images` ticket meta).
		 *
		 * @param string $url   Stored URL: a download link (3.0.5+ / 2.3.0+) or a Media Library URL (older tickets).
		 * @param array  $extra Extra query args (guest access).
		 */
		public function print_ticket_file_link( $url, $extra = array() ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				return;
			}
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );
			$id   = ! empty( $args['stsw_file'] ) ? absint( $args['stsw_file'] ) : attachment_url_to_postid( $url );
			$name = $id ? $this->get_display_name( $id ) : basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			if ( $id && $extra && get_post_meta( $id, '_stsw_protected', true ) ) {
				$url = $this->get_protected_download_url( $id, $extra );
			}
			echo '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><i class="fa fa-file-o" aria-hidden="true"></i> ' . esc_html( $name ) . '</a></p>';
		}

		/**
		 * Serves a protected ticket file to the ticket's customer, a guest with a valid ticket link, or store staff.
		 */
		public function maybe_serve_protected_file() {
			if ( empty( $_GET['stsw_file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- access is checked below.
				return;
			}
			$id = absint( $_GET['stsw_file'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! $id || 'attachment' !== get_post_type( $id ) || ! get_post_meta( $id, '_stsw_protected', true ) ) {
				wp_die( esc_html__( 'File not found.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 404 ) );
			}
			$ticket_id = (int) wp_get_post_parent_id( $id );
			$guest_ok  = $ticket_id && class_exists( 'WPFactory_WC_STS_Guest' ) && WPFactory_WC_STS_Guest::get_instance()->request_has_ticket_access( $ticket_id );
			if ( ! $guest_ok ) {
				if ( ! is_user_logged_in() ) {
					wp_safe_redirect( wp_login_url( $this->get_protected_download_url( $id ) ) );
					exit;
				}
				if ( ! $ticket_id || ! $this->core()->user_can_access_ticket( $ticket_id ) ) {
					wp_die( esc_html__( 'You do not have access to this file.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 403 ) );
				}
			}
			$path = get_attached_file( $id );
			if ( ! $path || ! is_readable( $path ) ) {
				wp_die( esc_html__( 'File not found.', 'support-ticket-system-for-woocommerce' ), '', array( 'response' => 404 ) );
			}
			$mime   = (string) get_post_mime_type( $id );
			$name   = $this->get_display_name( $id );
			$inline = in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf' ), true );
			while ( ob_get_level() ) {
				ob_end_clean();
			}
			nocache_headers();
			header( 'Content-Type: ' . ( $mime ? $mime : 'application/octet-stream' ) );
			header( 'Content-Length: ' . filesize( $path ) );
			header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . str_replace( array( '"', "\r", "\n" ), '', $name ) . '"' );
			header( 'X-Content-Type-Options: nosniff' );
			header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox" );
			readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			exit;
		}

		/**
		 * Hides protected ticket files from the media modal.
		 *
		 * @param array $query Query args.
		 *
		 * @return array
		 */
		public function hide_protected_from_media_modal( $query ) {
			$query['meta_query'] = isset( $query['meta_query'] ) ? (array) $query['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			foreach ( $this->hidden_keys() as $key ) {
				$query['meta_query'][] = array(
					'key'     => $key,
					'compare' => 'NOT EXISTS',
				);
			}
			return $query;
		}

		/**
		 * Meta keys of files kept out of the Media Library: protected files always, older ticket files when the setting
		 * is on (default).
		 *
		 * @return string[]
		 */
		protected function hidden_keys() {
			return '1' === (string) get_option( 'wpfactory_wc_sts_hide_media', '1' ) ? array( '_stsw_protected', '_stsw_ticket_file' ) : array( '_stsw_protected' );
		}

		/**
		 * Setting row (General > Data).
		 *
		 * @param array $fields Fields.
		 *
		 * @return array
		 */
		public function settings( $fields ) {
			$fields['@wpfactory_wc_sts_hide_media'] = array(
				'type'        => 'checkbox',
				'card'        => 'data',
				'default'     => '1',
				'label'       => __( 'Hide ticket files from the Media Library', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'Files customers attach never show in Media. This also hides files from tickets created before version 2.3, which were stored as normal media.', 'support-ticket-system-for-woocommerce' ),
			);
			return $fields;
		}

		/**
		 * Marks files attached to tickets before 2.3 once (they sit in the normal uploads folder).
		 */
		public function maybe_mark_old_ticket_files() {
			global $wpdb;
			if ( 'done' === get_option( 'wpfactory_wc_sts_ticket_files_marked' ) ) {
				return;
			}
			$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"SELECT a.ID FROM {$wpdb->posts} a INNER JOIN {$wpdb->posts} t ON t.ID = a.post_parent AND t.post_type = 'stsw_tickets' LEFT JOIN {$wpdb->postmeta} m ON m.post_id = a.ID AND m.meta_key = '_stsw_ticket_file' WHERE a.post_type = 'attachment' AND m.meta_id IS NULL LIMIT 500"
			);
			foreach ( $ids as $id ) {
				update_post_meta( (int) $id, '_stsw_ticket_file', 1 );
			}
			if ( count( $ids ) < 500 ) {
				update_option( 'wpfactory_wc_sts_ticket_files_marked', 'done', false );
			}
		}

		/**
		 * Hides protected ticket files from Media > Library (list view).
		 *
		 * @param WP_Query $query Query.
		 */
		public function hide_protected_from_media_list( $query ) {
			if ( ! is_admin() || ! $query->is_main_query() || 'attachment' !== $query->get( 'post_type' ) ) {
				return;
			}
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || 'upload' !== $screen->id ) {
				return;
			}
			$meta_query = (array) $query->get( 'meta_query' );
			foreach ( $this->hidden_keys() as $key ) {
				$meta_query[] = array(
					'key'     => $key,
					'compare' => 'NOT EXISTS',
				);
			}
			$query->set( 'meta_query', $meta_query );
		}
	}

endif;

return WPFactory_WC_STS_Files::get_instance();
