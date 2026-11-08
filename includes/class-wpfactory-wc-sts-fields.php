<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: custom fields on the ticket form.
 *
 * Text, select and checkbox fields set up in Settings > Ticket form. Definitions in the option
 * `wpfactory_wc_sts_custom_fields`, answers in ticket meta `_stsw_field_<key>` (shared by both editions).
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Fields' ) ) :

	/**
	 * WPFactory_WC_STS_Fields class.
	 *
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	class WPFactory_WC_STS_Fields {

		/**
		 * Field definitions option.
		 */
		const OPTION = 'wpfactory_wc_sts_custom_fields';

		/**
		 * Meta key prefix of the answers.
		 */
		const META_PREFIX = '_stsw_field_';

		/**
		 * Upload field name prefix of file fields.
		 */
		const FILE_PREFIX = 'stsw_field_file_';

		/**
		 * Posted value of a file field until the file is saved.
		 */
		const FILE_PENDING = 'file:pending';

		/**
		 * Field types.
		 */
		const TYPES = array( 'text', 'select', 'checkbox', 'date', 'number', 'multiselect', 'file' );

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Fields|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Fields
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
		}

		/**
		 * Field definitions.
		 *
		 * @return array List of { key, label, type, options[], required }.
		 */
		public function get_fields() {
			$fields = get_option( self::OPTION, array() );
			return is_array( $fields ) ? array_values( array_filter( $fields, 'is_array' ) ) : array();
		}

		/**
		 * Cleans field definitions posted from the settings screen. Keys never change once created
		 * (answers are stored under them); removing a field keeps the answers already given.
		 *
		 * @param array $rows Posted rows: key, label, type, options (comma separated), required.
		 *
		 * @return array
		 */
		public function sanitize_fields( $rows ) {
			$clean = array();
			$keys  = array();
			foreach ( (array) $rows as $row ) {
				$label = sanitize_text_field( wp_unslash( $row['label'] ?? '' ) );
				if ( '' === $label ) {
					continue;
				}
				$type = sanitize_key( $row['type'] ?? 'text' );
				$type = in_array( $type, self::TYPES, true ) ? $type : 'text';
				$key  = sanitize_key( $row['key'] ?? '' );
				if ( '' === $key ) {
					$key = substr( sanitize_key( str_replace( ' ', '_', remove_accents( $label ) ) ), 0, 40 );
					$key = '' !== $key ? $key : 'field';
				}
				$base = $key;
				$i    = 2;
				while ( in_array( $key, $keys, true ) ) {
					$key = $base . '_' . $i;
					++$i;
				}
				$keys[]  = $key;
				$options = array();
				if ( in_array( $type, array( 'select', 'multiselect' ), true ) ) {
					$options = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', wp_unslash( (string) ( $row['options'] ?? '' ) ) ) ) ) ) );
				}
				$clean[] = array(
					'key'      => $key,
					'label'    => $label,
					'type'     => $type,
					'options'  => $options,
					'required' => ! empty( $row['required'] ),
				);
			}
			return $clean;
		}

		/**
		 * Inputs on the new ticket forms.
		 */
		public function render_inputs() {
			foreach ( $this->get_fields() as $field ) {
				$id       = 'stsw-field-' . $field['key'];
				$name     = 'stsw_fields[' . $field['key'] . ']';
				$required = $field['required'] ? ' required' : '';
				$mark     = $field['required'] ? ' <span class="required" aria-hidden="true">*</span>' : '';
				if ( 'multiselect' === $field['type'] ) {
					echo '<fieldset class="stsw-field stsw-field--multi"><legend>' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</legend>';
					foreach ( $field['options'] as $option ) {
						echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $option ) . '" /> ' . esc_html( $option ) . '</label> ';
					}
					echo '</fieldset>';
					continue;
				}
				echo '<p>';
				if ( 'file' === $field['type'] ) {
					$files = WPFactory_WC_STS_Files::get_instance();
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</label><br />';
					echo '<input type="file" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::FILE_PREFIX . $field['key'] ) . '" accept="' . esc_attr( $files->get_accept_attribute() ) . '"' . esc_attr( $required ) . ' aria-describedby="' . esc_attr( $id ) . '-help" />';
					echo '<br /><small class="stsw-help" id="' . esc_attr( $id ) . '-help">' . esc_html( $files->get_rules_text() ) . '</small>';
				} elseif ( in_array( $field['type'], array( 'date', 'number' ), true ) ) {
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</label><br />';
					echo '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( 'number' === $field['type'] ? ' step="any"' : '' ) . esc_attr( $required ) . ' />';
				} elseif ( 'checkbox' === $field['type'] ) {
					echo '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . esc_attr( $required ) . ' /> ' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</label>';
				} elseif ( 'select' === $field['type'] ) {
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</label><br />';
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . esc_attr( $required ) . '><option value="">' . esc_html__( 'Choose...', 'support-ticket-system-for-woocommerce' ) . '</option>';
					foreach ( $field['options'] as $option ) {
						echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
					}
					echo '</select>';
				} else {
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</label><br />';
					echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . esc_attr( $required ) . ' />';
				}
				echo '</p>';
			}
		}

		/**
		 * Answers posted with a ticket form (the form's nonce is verified by the caller).
		 *
		 * @return array key => value
		 */
		public function get_posted_values() {
			$posted = isset( $_POST['stsw_fields'] ) && is_array( $_POST['stsw_fields'] ) ? wp_unslash( $_POST['stsw_fields'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized below.
			$values = array();
			foreach ( $this->get_fields() as $field ) {
				if ( 'multiselect' === $field['type'] ) {
					$chosen                  = isset( $posted[ $field['key'] ] ) ? array_map( 'sanitize_text_field', array_map( 'strval', (array) $posted[ $field['key'] ] ) ) : array();
					$values[ $field['key'] ] = implode( ', ', array_values( array_intersect( $field['options'], $chosen ) ) );
					continue;
				}
				if ( 'file' === $field['type'] ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the calling form verified its nonce.
					$values[ $field['key'] ] = empty( $_FILES[ self::FILE_PREFIX . $field['key'] ]['name'] ) ? '' : self::FILE_PENDING;
					continue;
				}
				$value = isset( $posted[ $field['key'] ] ) && ! is_array( $posted[ $field['key'] ] ) ? sanitize_text_field( (string) $posted[ $field['key'] ] ) : '';
				if ( 'date' === $field['type'] ) {
					$value = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && wp_checkdate( (int) substr( $value, 5, 2 ), (int) substr( $value, 8, 2 ), (int) substr( $value, 0, 4 ), $value ) ? $value : '';
				} elseif ( 'number' === $field['type'] ) {
					$value = is_numeric( $value ) ? (string) ( $value + 0 ) : '';
				}
				if ( 'checkbox' === $field['type'] ) {
					$value = '' !== $value ? '1' : '';
				} elseif ( 'select' === $field['type'] && ! in_array( $value, $field['options'], true ) ) {
					$value = '';
				}
				$values[ $field['key'] ] = $value;
			}
			return $values;
		}

		/**
		 * Message for the first required field left empty, or an empty string.
		 *
		 * @param array $values Posted values.
		 *
		 * @return string
		 */
		public function validate( $values ) {
			foreach ( $this->get_fields() as $field ) {
				if ( $field['required'] && '' === ( $values[ $field['key'] ] ?? '' ) ) {
					/* translators: %s: field label. */
					return sprintf( __( 'Please fill in "%s".', 'support-ticket-system-for-woocommerce' ), $field['label'] );
				}
			}
			return '';
		}

		/**
		 * Saves answers on a ticket.
		 *
		 * @param int   $ticket_id Ticket ID.
		 * @param array $values    key => value.
		 */
		public function save_values( $ticket_id, $values ) {
			$types = wp_list_pluck( $this->get_fields(), 'type', 'key' );
			foreach ( $values as $key => $value ) {
				if ( 'file' === ( $types[ $key ] ?? '' ) ) {
					if ( self::FILE_PENDING === $value ) {
						$this->save_file( $ticket_id, $key );
					}
					continue;
				}
				if ( '' !== (string) $value ) {
					update_post_meta( $ticket_id, self::META_PREFIX . sanitize_key( $key ), sanitize_text_field( (string) $value ) );
				}
			}
		}

		/**
		 * Stores the file of a file field: a private ticket file (same rules as other attachments), listed with the
		 * ticket's files. The answer is the attachment ID.
		 *
		 * @param int    $ticket_id Ticket ID.
		 * @param string $key       Field key.
		 */
		protected function save_file( $ticket_id, $key ) {
			$files = WPFactory_WC_STS_Files::get_instance();
			$saved = $files->handle_upload( self::FILE_PREFIX . $key, $ticket_id, 'ids' );
			if ( ! $saved ) {
				return;
			}
			$id = (int) $saved[0];
			update_post_meta( $ticket_id, self::META_PREFIX . sanitize_key( $key ), 'file:' . $id );
			$list   = array_filter( explode( ',', (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FILES, true ) ) );
			$list[] = $files->get_protected_download_url( $id );
			update_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FILES, implode( ',', $list ) );
		}

		/**
		 * Answers of a ticket for display, with the current labels (answers of removed fields show their key).
		 *
		 * @param int $ticket_id Ticket ID.
		 *
		 * @return array label => value
		 */
		public function get_display_values( $ticket_id ) {
			$labels = array();
			$types  = array();
			foreach ( $this->get_fields() as $field ) {
				$labels[ $field['key'] ] = $field['label'];
				$types[ $field['key'] ]  = $field['type'];
			}
			$display = array();
			foreach ( (array) get_post_meta( $ticket_id ) as $meta_key => $values ) {
				if ( 0 !== strpos( (string) $meta_key, self::META_PREFIX ) ) {
					continue;
				}
				$key   = substr( (string) $meta_key, strlen( self::META_PREFIX ) );
				$value = (string) ( $values[0] ?? '' );
				if ( 'checkbox' === ( $types[ $key ] ?? '' ) ) {
					$value = '1' === $value ? __( 'Yes', 'support-ticket-system-for-woocommerce' ) : $value;
				} elseif ( 'file' === ( $types[ $key ] ?? '' ) && 0 === strpos( $value, 'file:' ) && (int) wp_get_post_parent_id( (int) substr( $value, 5 ) ) === (int) $ticket_id ) {
					$attachment = (int) substr( $value, 5 );
					$file_name  = (string) get_post_meta( $attachment, '_stsw_original_name', true );
					$value      = 'attachment' === get_post_type( $attachment )
						/* translators: %s: file name. */
						? sprintf( __( '%s (with the files of this ticket)', 'support-ticket-system-for-woocommerce' ), '' !== $file_name ? $file_name : get_the_title( $attachment ) )
						: __( 'File removed', 'support-ticket-system-for-woocommerce' );
				} elseif ( 'date' === ( $types[ $key ] ?? '' ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
					$value = date_i18n( get_option( 'date_format' ), strtotime( $value . ' 12:00:00' ) );
				}
				$display[ $labels[ $key ] ?? $key ] = $value;
			}
			return $display;
		}

		/**
		 * Removes all answers from a ticket (privacy erase).
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function delete_values( $ticket_id ) {
			foreach ( array_keys( (array) get_post_meta( $ticket_id ) ) as $meta_key ) {
				if ( 0 === strpos( (string) $meta_key, self::META_PREFIX ) ) {
					delete_post_meta( $ticket_id, $meta_key );
				}
			}
		}

		/**
		 * Field editor for the settings screen.
		 */
		public function render_settings() {
			$fields   = $this->get_fields();
			$fields[] = array(
				'key'      => '',
				'label'    => '',
				'type'     => 'text',
				'options'  => array(),
				'required' => false,
			);
			$types    = array(
				'text'        => __( 'Text', 'support-ticket-system-for-woocommerce' ),
				'select'      => __( 'Dropdown', 'support-ticket-system-for-woocommerce' ),
				'checkbox'    => __( 'Checkbox', 'support-ticket-system-for-woocommerce' ),
				'date'        => __( 'Date', 'support-ticket-system-for-woocommerce' ),
				'number'      => __( 'Number', 'support-ticket-system-for-woocommerce' ),
				'multiselect' => __( 'Several choices', 'support-ticket-system-for-woocommerce' ),
				'file'        => __( 'File', 'support-ticket-system-for-woocommerce' ),
			);
			echo '<p>' . esc_html__( 'Extra questions on the new ticket form. Leave the label empty to remove a field; answers already given stay on their tickets.', 'support-ticket-system-for-woocommerce' ) . '</p>';
			echo '<table class="widefat striped wpfactory-wc-sts-fields"><thead><tr>' .
				'<th scope="col">' . esc_html__( 'Label', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th scope="col">' . esc_html__( 'Type', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th scope="col">' . esc_html__( 'Choices (dropdown and several choices, comma separated)', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th scope="col">' . esc_html__( 'Required', 'support-ticket-system-for-woocommerce' ) . '</th></tr></thead><tbody>';
			foreach ( $fields as $i => $field ) {
				$name = 'wpfactory_wc_sts_custom_fields[' . (int) $i . ']';
				echo '<tr>';
				echo '<td><input type="hidden" name="' . esc_attr( $name . '[key]' ) . '" value="' . esc_attr( $field['key'] ) . '" />';
				/* translators: %d: row number. */
				echo '<label class="screen-reader-text" for="stsw-cf-label-' . (int) $i . '">' . esc_html( sprintf( __( 'Label of field %d', 'support-ticket-system-for-woocommerce' ), $i + 1 ) ) . '</label>';
				echo '<input type="text" id="stsw-cf-label-' . (int) $i . '" name="' . esc_attr( $name . '[label]' ) . '" value="' . esc_attr( $field['label'] ) . '" placeholder="' . esc_attr__( 'New field', 'support-ticket-system-for-woocommerce' ) . '" /></td>';
				echo '<td><label class="screen-reader-text" for="stsw-cf-type-' . (int) $i . '">' . esc_html__( 'Type', 'support-ticket-system-for-woocommerce' ) . '</label><select id="stsw-cf-type-' . (int) $i . '" name="' . esc_attr( $name . '[type]' ) . '">';
				foreach ( $types as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '"' . selected( $field['type'], $value, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select></td>';
				echo '<td><label class="screen-reader-text" for="stsw-cf-options-' . (int) $i . '">' . esc_html__( 'Choices', 'support-ticket-system-for-woocommerce' ) . '</label><input type="text" id="stsw-cf-options-' . (int) $i . '" name="' . esc_attr( $name . '[options]' ) . '" value="' . esc_attr( implode( ', ', (array) $field['options'] ) ) . '" /></td>';
				echo '<td><label><input type="checkbox" name="' . esc_attr( $name . '[required]' ) . '" value="1"' . checked( ! empty( $field['required'] ), true, false ) . ' /> <span class="screen-reader-text">' . esc_html__( 'Required', 'support-ticket-system-for-woocommerce' ) . '</span></label></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">' . esc_html__( 'Save to get another empty row.', 'support-ticket-system-for-woocommerce' ) . '</p>';
		}
	}

endif;

return WPFactory_WC_STS_Fields::get_instance();
