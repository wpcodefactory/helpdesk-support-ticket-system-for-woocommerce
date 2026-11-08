<?php
/**
 * Customer screen: open a ticket (three steps; two for guests, whose order is fixed).
 *
 * Override: copy to yourtheme/woocommerce-support/ticket-new.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var WPFactory_WC_STS_Customer $view     Customer screens.
 * @var WPFactory_WC_STS_Inc      $core     Core.
 * @var int                       $order_id Pre-selected order.
 * @var array                     $guest    Guest context: nonce and hidden fields (empty when signed in).
 * @var array                     $draft    What the customer typed before an error.
 * @var WC_Order[]                $orders   Orders offered.
 * @var int                       $steps    Number of steps.
 */

defined( 'ABSPATH' ) || exit;
$step = 0;
?>
<div class="stsw-head">
	<div>
		<?php if ( ! $guest ) : ?>
			<a class="stsw-back" href="<?php echo esc_url( $view->get_view_url() ); ?>"><span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Back to support', 'support-ticket-system-for-woocommerce' ); ?></a>
		<?php endif; ?>
		<h2 class="stsw-title" id="stsw-ask-title"><?php esc_html_e( 'Open a ticket', 'support-ticket-system-for-woocommerce' ); ?></h2>
	</div>
</div>
<form class="stsw-ask <?php echo esc_attr( $core->plugin ); ?>new_ticket" method="post" enctype="multipart/form-data" aria-labelledby="stsw-ask-title" data-stsw-steps="<?php echo (int) $steps; ?>">
	<p class="screen-reader-text stsw-live" aria-live="polite"></p>

	<?php if ( ! $guest ) : ?>
		<fieldset class="stsw-step" data-step="<?php echo (int) ++$step; ?>">
			<legend class="stsw-step__title">
				<?php
				/* translators: 1: step number, 2: number of steps. */
				echo esc_html( sprintf( __( 'Step %1$d of %2$d: which order is it about?', 'support-ticket-system-for-woocommerce' ), $step, $steps ) );
				?>
			</legend>
			<div class="stsw-choices">
				<?php foreach ( $orders as $choice ) : ?>
					<label class="stsw-choice">
						<input type="radio" name="ticketorder" value="<?php echo (int) $choice->get_id(); ?>" <?php checked( (int) $order_id, (int) $choice->get_id() ); ?> />
						<span><?php echo esc_html( $view->order_label( $choice ) ); ?></span>
					</label>
				<?php endforeach; ?>
				<label class="stsw-choice">
					<input type="radio" name="ticketorder" value="" <?php checked( ! $order_id ); ?> />
					<span><?php esc_html_e( 'Not about an order', 'support-ticket-system-for-woocommerce' ); ?></span>
				</label>
			</div>
		</fieldset>
	<?php else : ?>
		<input type="hidden" name="ticketorder" value="<?php echo (int) $order_id; ?>" />
	<?php endif; ?>

	<fieldset class="stsw-step" data-step="<?php echo (int) ++$step; ?>">
		<legend class="stsw-step__title">
			<?php
			/* translators: 1: step number, 2: number of steps. */
			echo esc_html( sprintf( __( 'Step %1$d of %2$d: what is it about?', 'support-ticket-system-for-woocommerce' ), $step, $steps ) );
			?>
		</legend>
		<?php $view->render_items( $orders ); ?>
		<div class="stsw-field">
			<label for="stsw-reason"><?php esc_html_e( 'Reason', 'support-ticket-system-for-woocommerce' ); ?></label>
			<select id="stsw-reason" name="stsw_reason">
				<?php foreach ( $core->get_reasons() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $draft['reason'] ?? '', $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
		/**
		 * After the reason in the ticket form (Pro: return or exchange choice).
		 */
		do_action( 'wpfactory_wc_sts_ask_after_reason' );
		$view->render_term_select( 'stsw_tickets_subject', __( 'Topic', 'support-ticket-system-for-woocommerce' ), 'term_id' );
		if ( '1' === (string) get_option( $core->plugin . 'enablePriority' ) ) {
			$view->render_term_select( 'stsw_tickets_priority', __( 'How urgent is it?', 'support-ticket-system-for-woocommerce' ), 'name' );
		}
		?>
	</fieldset>

	<fieldset class="stsw-step" data-step="<?php echo (int) ++$step; ?>">
		<legend class="stsw-step__title">
			<?php
			/* translators: 1: step number, 2: number of steps. */
			echo esc_html( sprintf( __( 'Step %1$d of %2$d: tell us more', 'support-ticket-system-for-woocommerce' ), $step, $steps ) );
			?>
		</legend>
		<?php
		/**
		 * Top of the message step (order tracking for "Where is my order?").
		 *
		 * @param WC_Order[] $orders Orders offered.
		 */
		do_action( 'wpfactory_wc_sts_ask_before_message', $orders );
		?>
		<div class="stsw-field">
			<label for="stsw-ticket-title"><?php esc_html_e( 'Short summary (optional)', 'support-ticket-system-for-woocommerce' ); ?></label>
			<input type="text" id="stsw-ticket-title" name="title" maxlength="150" value="<?php echo esc_attr( $draft['title'] ?? '' ); ?>" />
		</div>
		<?php
		if ( class_exists( 'WPFactory_WC_STS_Fields' ) ) {
			WPFactory_WC_STS_Fields::get_instance()->render_inputs();
		}
		?>
		<div class="stsw-field">
			<label for="stsw-ticket-content"><?php esc_html_e( 'Message', 'support-ticket-system-for-woocommerce' ); ?> <span aria-hidden="true">*</span></label>
			<textarea id="stsw-ticket-content" name="content" rows="6" required><?php echo esc_textarea( $draft['content'] ?? '' ); ?></textarea>
		</div>
		<?php
		/**
		 * After the message field of the ticket form (knowledge base suggestions).
		 */
		do_action( 'wpfactory_wc_sts_ask_after_message' );
		?>
		<?php $view->render_file_input( 'stsw-ticket-files' ); ?>
	</fieldset>

	<?php
	foreach ( ( $guest['hidden'] ?? array() ) as $name => $value ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
	}
	if ( $guest ) {
		wp_nonce_field( $guest['nonce'], $guest['nonce'] );
	} else {
		wp_nonce_field( 'stswticketfrontend', 'stswticketfrontend' );
		echo '<input type="hidden" name="stsw_return" value="' . esc_attr( $view->get_view_url() ) . '" />';
	}
	?>
	<div class="stsw-actions">
		<button type="button" class="stsw-button stsw-js-only" data-stsw-back hidden><?php esc_html_e( 'Back', 'support-ticket-system-for-woocommerce' ); ?></button>
		<button type="button" class="stsw-button stsw-button--primary button wp-element-button stsw-js-only" data-stsw-next hidden><?php esc_html_e( 'Next', 'support-ticket-system-for-woocommerce' ); ?></button>
		<button type="submit" class="stsw-button stsw-button--primary button wp-element-button" data-stsw-submit><?php esc_html_e( 'Send', 'support-ticket-system-for-woocommerce' ); ?></button>
	</div>
</form>
<?php
