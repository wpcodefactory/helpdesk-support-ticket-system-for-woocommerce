<?php
/**
 * Customer screen: confirmation after a new ticket (the ticket list follows).
 *
 * Override: copy to yourtheme/woocommerce-support/ticket-sent.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var WPFactory_WC_STS_Customer $view      Customer screens.
 * @var int                       $ticket_id Ticket ID.
 * @var string                    $message   Merchant text (safe HTML).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="stsw-sent" role="status" tabindex="-1">
	<p class="stsw-sent__title"><strong><?php esc_html_e( 'Thanks, we received your request', 'support-ticket-system-for-woocommerce' ); ?></strong></p>
	<?php if ( '' !== trim( wp_strip_all_tags( (string) $message ) ) ) : ?>
		<div class="stsw-sent__text"><?php echo wp_kses_post( $message ); ?></div>
	<?php endif; ?>
	<p>
		<?php
		/* translators: %s: ticket number. */
		echo esc_html( sprintf( __( 'Your ticket number is #%s.', 'support-ticket-system-for-woocommerce' ), $core->get_ticket_number( $ticket_id ) ) );
		?>
		<a href="<?php echo esc_url( $view->get_view_url( 'ticket', array( 'ticket' => $ticket_id ) ) ); ?>"><?php esc_html_e( 'View your ticket', 'support-ticket-system-for-woocommerce' ); ?></a>
	</p>
</div>
