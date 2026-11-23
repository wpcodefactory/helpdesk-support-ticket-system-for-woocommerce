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
				$show = array(
					'products'   => array(),
					'categories' => array_values( array_filter( array_map( 'absint', (array) ( $row['show']['categories'] ?? array() ) ) ) ),
					'subjects'   => array_values( array_filter( array_map( 'absint', (array) ( $row['show']['subjects'] ?? array() ) ) ) ),
					'reasons'    => array_values( array_filter( array_map( 'sanitize_key', (array) ( $row['show']['reasons'] ?? array() ) ) ) ),
				);
				// Products: IDs or SKUs, comma separated.
				foreach ( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( (string) ( $row['show']['products'] ?? '' ) ) ) ) ) ) as $ref ) {
					$id = ctype_digit( $ref ) ? (int) $ref : (int) wc_get_product_id_by_sku( $ref );
					if ( $id && wc_get_product( $id ) ) {
						$show['products'][] = $id;
					}
				}
				$clean[] = array(
					'key'      => $key,
					'label'    => $label,
					'type'     => $type,
					'options'  => $options,
					'required' => ! empty( $row['required'] ),
					'show'     => $show,
				);
			}
			return $clean;
		}

		/**
		 * What an order item matches in field conditions: "p<product id>" and "c<category id>" tokens.
		 *
		 * @param WC_Order_Item_Product $item Item.
		 *
		 * @return string[]
		 */
		public function item_match( $item ) {
			$out = array();
			if ( ! is_callable( array( $item, 'get_product_id' ) ) ) {
				return $out;
			}
			foreach ( array_filter( array( (int) $item->get_product_id(), (int) $item->get_variation_id() ) ) as $id ) {
				$out[] = 'p' . $id;
			}
			$cats = wc_get_product_term_ids( (int) $item->get_product_id(), 'product_cat' );
			foreach ( $cats as $cat ) {
				$out[] = 'c' . (int) $cat;
				foreach ( get_ancestors( (int) $cat, 'product_cat', 'taxonomy' ) as $parent ) {
					$out[] = 'c' . (int) $parent;
				}
			}
			return array_values( array_unique( $out ) );
		}

		/**
		 * What a new ticket is about, for field conditions.
		 *
		 * @param WC_Order|false $order   Order.
		 * @param int[]          $items   Chosen item IDs (none: all items of the order).
		 * @param int            $subject Topic term ID.
		 * @param string         $reason  Reason slug.
		 *
		 * @return array { match: string[], subject: int, reason: string }
		 */
		public function context( $order, $items, $subject, $reason ) {
			$match = array();
			if ( $order ) {
				foreach ( $order->get_items() as $item_id => $item ) {
					if ( ! $items || in_array( (int) $item_id, array_map( 'intval', (array) $items ), true ) ) {
						$match = array_merge( $match, $this->item_match( $item ) );
					}
				}
			}
			return array(
				'match'   => array_values( array_unique( $match ) ),
				'subject' => (int) $subject,
				'reason'  => (string) $reason,
			);
		}

		/**
		 * Whether a field is asked for this ticket. Each condition set must match (any of its values); no conditions:
		 * always asked.
		 *
		 * @param array      $field   Field.
		 * @param array|null $context Context from context() (null: always asked).
		 *
		 * @return bool
		 */
		public function applies( $field, $context ) {
			$show = (array) ( $field['show'] ?? array() );
			if ( null === $context ) {
				return true;
			}
			$things = array();
			foreach ( (array) ( $show['products'] ?? array() ) as $id ) {
				$things[] = 'p' . (int) $id;
			}
			foreach ( (array) ( $show['categories'] ?? array() ) as $id ) {
				$things[] = 'c' . (int) $id;
			}
			if ( $things && ! array_intersect( $things, $context['match'] ) ) {
				return false;
			}
			if ( ! empty( $show['subjects'] ) && ! in_array( (int) $context['subject'], array_map( 'intval', $show['subjects'] ), true ) ) {
				return false;
			}
			if ( ! empty( $show['reasons'] ) && ! in_array( (string) $context['reason'], (array) $show['reasons'], true ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Conditions of a field for the form script, as data attributes.
		 *
		 * @param array $field Field.
		 *
		 * @return string
		 */
		protected function condition_attributes( $field ) {
			$show   = (array) ( $field['show'] ?? array() );
			$things = array();
			foreach ( (array) ( $show['products'] ?? array() ) as $id ) {
				$things[] = 'p' . (int) $id;
			}
			foreach ( (array) ( $show['categories'] ?? array() ) as $id ) {
				$things[] = 'c' . (int) $id;
			}
			$out = '';
			if ( $things ) {
				$out .= ' data-stsw-if-items="' . esc_attr( implode( ' ', $things ) ) . '"';
			}
			if ( ! empty( $show['subjects'] ) ) {
				$out .= ' data-stsw-if-subject="' . esc_attr( implode( ' ', array_map( 'intval', $show['subjects'] ) ) ) . '"';
			}
			if ( ! empty( $show['reasons'] ) ) {
				$out .= ' data-stsw-if-reason="' . esc_attr( implode( ' ', $show['reasons'] ) ) . '"';
			}
			return $out;
		}

		/**
		 * Inputs on the new ticket forms. Fields with conditions show only when they match (the script hides the others;
		 * the server checks them again).
		 */
		public function render_inputs() {
			foreach ( $this->get_fields() as $field ) {
				$if       = $this->condition_attributes( $field );
				$id       = 'stsw-field-' . $field['key'];
				$name     = 'stsw_fields[' . $field['key'] . ']';
				$required = $field['required'] ? ' required' : '';
				$mark     = $field['required'] ? ' <span class="required" aria-hidden="true">*</span>' : '';
				if ( 'multiselect' === $field['type'] ) {
					echo '<fieldset class="stsw-field stsw-field--multi"' . $if . '><legend>' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</legend>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $if escaped in condition_attributes().
					foreach ( $field['options'] as $option ) {
						echo '<label class="stsw-choice"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $option ) . '" /> <span>' . esc_html( $option ) . '</span></label>';
					}
					echo '</fieldset>';
					continue;
				}
				echo '<div class="stsw-field"' . $if . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in condition_attributes().
				if ( 'file' === $field['type'] ) {
					$files = WPFactory_WC_STS_Files::get_instance();
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</label>';
					echo '<input type="file" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::FILE_PREFIX . $field['key'] ) . '" accept="' . esc_attr( $files->get_accept_attribute() ) . '"' . esc_attr( $required ) . ' aria-describedby="' . esc_attr( $id ) . '-help" />';
					echo '<small class="stsw-help" id="' . esc_attr( $id ) . '-help">' . esc_html( $files->get_rules_text() ) . '</small>';
				} elseif ( in_array( $field['type'], array( 'date', 'number' ), true ) ) {
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</label>';
					echo '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( 'number' === $field['type'] ? ' step="any"' : '' ) . esc_attr( $required ) . ' />';
				} elseif ( 'checkbox' === $field['type'] ) {
					echo '<label class="stsw-choice" for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . esc_attr( $required ) . ' /> <span>' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</span></label>';
				} elseif ( 'select' === $field['type'] ) {
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</label>';
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . esc_attr( $required ) . '><option value="">' . esc_html__( 'Choose...', 'support-ticket-system-for-woocommerce' ) . '</option>';
					foreach ( $field['options'] as $option ) {
						echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
					}
					echo '</select>';
				} else {
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . wp_kses_post( $mark ) . '</label>';
					echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . esc_attr( $required ) . ' />';
				}
				echo '</div>';
			}
		}

		/**
		 * Answers posted with a ticket form (the form's nonce is verified by the caller).
		 *
		 * @param array|null $context Ticket context (see context()); null: every field is asked.
		 *
		 * @return array key => value
		 */
		public function get_posted_values( $context = null ) {
			$posted = isset( $_POST['stsw_fields'] ) && is_array( $_POST['stsw_fields'] ) ? wp_unslash( $_POST['stsw_fields'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized below.
			$values = array();
			foreach ( $this->get_fields() as $field ) {
				if ( ! $this->applies( $field, $context ) ) {
					continue; // Not asked for this ticket.
				}
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
		 * @param array      $values  Posted values.
		 * @param array|null $context Ticket context (see context()); null: every field is asked.
		 *
		 * @return string
		 */
		public function validate( $values, $context = null ) {
			foreach ( $this->get_fields() as $field ) {
				if ( $field['required'] && $this->applies( $field, $context ) && '' === ( $values[ $field['key'] ] ?? '' ) ) {
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
				'show'     => array(),
			);
			$cats     = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);
			$cats     = is_wp_error( $cats ) ? array() : $cats;
			$topics   = WPFactory_WC_STS_UI::ordered_terms( 'stsw_tickets_subject' );
			$reasons  = WPFactory_WC_STS_Inc::get_main_instance()->get_reasons();
			$types    = array(
				'text'        => __( 'Text', 'support-ticket-system-for-woocommerce' ),
				'select'      => __( 'Dropdown', 'support-ticket-system-for-woocommerce' ),
				'checkbox'    => __( 'Checkbox', 'support-ticket-system-for-woocommerce' ),
				'date'        => __( 'Date', 'support-ticket-system-for-woocommerce' ),
				'number'      => __( 'Number', 'support-ticket-system-for-woocommerce' ),
				'multiselect' => __( 'Several choices', 'support-ticket-system-for-woocommerce' ),
				'file'        => __( 'File', 'support-ticket-system-for-woocommerce' ),
			);
			echo '<p class="hint expand-hint">' . esc_html__( 'Extra questions on the new ticket form. Leave the label empty to remove a field; answers already given stay on their tickets. Save to get another empty row.', 'support-ticket-system-for-woocommerce' ) . '</p>';
			echo '<div class="table-wrap"><table class="table wpfactory-wc-sts-fields"><thead><tr>' .
				'<th scope="col">' . esc_html__( 'Label', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th scope="col">' . esc_html__( 'Type', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th scope="col">' . esc_html__( 'Choices (comma separated)', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th scope="col">' . esc_html__( 'Required', 'support-ticket-system-for-woocommerce' ) . '</th>' .
				'<th scope="col">' . esc_html__( 'Ask only for', 'support-ticket-system-for-woocommerce' ) . '</th></tr></thead><tbody>';
			foreach ( $fields as $i => $field ) {
				$name = 'wpfactory_wc_sts_custom_fields[' . (int) $i . ']';
				echo '<tr>';
				echo '<td><input type="hidden" name="' . esc_attr( $name . '[key]' ) . '" value="' . esc_attr( $field['key'] ) . '" />';
				/* translators: %d: row number. */
				echo '<label class="screen-reader-text" for="stsw-cf-label-' . (int) $i . '">' . esc_html( sprintf( __( 'Label of field %d', 'support-ticket-system-for-woocommerce' ), $i + 1 ) ) . '</label>';
				echo '<input type="text" class="input" id="stsw-cf-label-' . (int) $i . '" name="' . esc_attr( $name . '[label]' ) . '" value="' . esc_attr( $field['label'] ) . '" placeholder="' . esc_attr__( 'New field', 'support-ticket-system-for-woocommerce' ) . '" /></td>';
				echo '<td><label class="screen-reader-text" for="stsw-cf-type-' . (int) $i . '">' . esc_html__( 'Type', 'support-ticket-system-for-woocommerce' ) . '</label><select class="select" id="stsw-cf-type-' . (int) $i . '" name="' . esc_attr( $name . '[type]' ) . '">';
				foreach ( $types as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '"' . selected( $field['type'], $value, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select></td>';
				echo '<td><label class="screen-reader-text" for="stsw-cf-options-' . (int) $i . '">' . esc_html__( 'Choices', 'support-ticket-system-for-woocommerce' ) . '</label><input type="text" class="input" id="stsw-cf-options-' . (int) $i . '" name="' . esc_attr( $name . '[options]' ) . '" value="' . esc_attr( implode( ', ', (array) $field['options'] ) ) . '" /></td>';
				echo '<td><label><input type="checkbox" name="' . esc_attr( $name . '[required]' ) . '" value="1"' . checked( ! empty( $field['required'] ), true, false ) . ' /> <span class="screen-reader-text">' . esc_html__( 'Required', 'support-ticket-system-for-woocommerce' ) . '</span></label></td>';
				$show  = (array) ( $field['show'] ?? array() );
				$count = count( (array) ( $show['products'] ?? array() ) ) + count( (array) ( $show['categories'] ?? array() ) ) + count( (array) ( $show['subjects'] ?? array() ) ) + count( (array) ( $show['reasons'] ?? array() ) );
				echo '<td><details class="stsw-cf-show"><summary>' . esc_html( $count ? __( 'Some tickets', 'support-ticket-system-for-woocommerce' ) : __( 'Every ticket', 'support-ticket-system-for-woocommerce' ) ) . '</summary>';
				echo '<p class="hint">' . esc_html__( 'Leave all empty to ask on every ticket. When you fill more than one, all must match.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				$skus = array();
				foreach ( (array) ( $show['products'] ?? array() ) as $pid ) {
					$product = wc_get_product( $pid );
					$skus[]  = $product && $product->get_sku() ? $product->get_sku() : (string) (int) $pid;
				}
				echo '<div class="field"><label class="label" for="stsw-cf-products-' . (int) $i . '">' . esc_html__( 'Products in the order (IDs or SKUs, comma separated)', 'support-ticket-system-for-woocommerce' ) . '</label><input type="text" class="input" id="stsw-cf-products-' . (int) $i . '" name="' . esc_attr( $name . '[show][products]' ) . '" value="' . esc_attr( implode( ', ', $skus ) ) . '" /></div>';
				$this->render_multi( $name . '[show][categories][]', 'stsw-cf-cats-' . (int) $i, __( 'Product categories', 'support-ticket-system-for-woocommerce' ), wp_list_pluck( $cats, 'name', 'term_id' ), (array) ( $show['categories'] ?? array() ) );
				$this->render_multi( $name . '[show][subjects][]', 'stsw-cf-topics-' . (int) $i, __( 'Topics', 'support-ticket-system-for-woocommerce' ), wp_list_pluck( $topics, 'name', 'term_id' ), (array) ( $show['subjects'] ?? array() ) );
				$this->render_multi( $name . '[show][reasons][]', 'stsw-cf-reasons-' . (int) $i, __( 'Reasons', 'support-ticket-system-for-woocommerce' ), $reasons, (array) ( $show['reasons'] ?? array() ) );
				echo '</details></td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}

		/**
		 * A list of checkboxes (keyboard friendly in place of a multiple select).
		 *
		 * @param string $name    Input name.
		 * @param string $id      ID prefix.
		 * @param string $label   Group label.
		 * @param array  $choices value => label.
		 * @param array  $chosen  Chosen values.
		 */
		protected function render_multi( $name, $id, $label, $choices, $chosen ) {
			if ( ! $choices ) {
				return;
			}
			$chosen = array_map( 'strval', $chosen );
			echo '<fieldset id="' . esc_attr( $id ) . '" class="checks"><legend class="label">' . esc_html( $label ) . '</legend>';
			foreach ( $choices as $value => $text ) {
				echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . checked( in_array( (string) $value, $chosen, true ), true, false ) . ' /> ' . esc_html( $text ) . '</label> ';
			}
			echo '</fieldset>';
		}
	}

endif;

return WPFactory_WC_STS_Fields::get_instance();
