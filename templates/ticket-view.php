<?php
/**
 * Customer screen: one ticket with its conversation and the reply, close or reopen form.
 *
 * Override: copy to yourtheme/woocommerce-support/ticket-view.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var WPFactory_WC_STS_Customer $view      Customer screens.
 * @var WPFactory_WC_STS_Inc      $core      Core.
 * @var int                       $ticket_id Ticket ID.
 * @var array                     $guest     Guest context: nonce, hidden fields, file link args (empty when signed in).
 * @var int                       $viewer    Viewer user ID (0 for a guest).
 * @var WC_Order|false            $order     Linked order.
 * @var bool                      $closed    The ticket is closed.
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( ! $guest ) : ?>
	<a class="stsw-back" href="<?php echo esc_url( $view->get_view_url() ); ?>"><span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Back to support', 'support-ticket-system-for-woocommerce' ); ?></a>
<?php endif; ?>
<div class="stsw-head stsw-head--ticket">
	<h2 class="stsw-title"><?php echo esc_html( get_the_title( $ticket_id ) ); ?></h2>
	<?php echo $view->status_chip( $ticket_id, $viewer && $core->get_ticket_user_id( $ticket_id ) !== $viewer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in status_chip(). ?>
</div>
<p class="stsw-card__meta">
	<?php
	/* translators: %s: ticket number. */
	echo '<span>' . esc_html( sprintf( __( 'Ticket #%s', 'support-ticket-system-for-woocommerce' ), $core->get_ticket_number( $ticket_id ) ) ) . '</span>';
	if ( $order ) {
		$url = ( $viewer && (int) $order->get_customer_id() === $viewer ) ? $order->get_view_order_url() : '';
		/* translators: %s: order number. */
		$text = sprintf( __( 'About order #%s', 'support-ticket-system-for-woocommerce' ), $order->get_order_number() );
		echo ' · <span>' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>' : esc_html( $text ) ) . '</span>';
	}
	?>
</p>
<?php $view->render_thread( $ticket_id, $viewer, $guest['file_args'] ?? array() ); ?>
<?php
/**
 * After the conversation on the customer screen (Pro: satisfaction rating).
 *
 * @param int   $ticket_id Ticket ID.
 * @param array $guest     Guest context.
 */
do_action( 'wpfactory_wc_sts_customer_ticket_after_thread', $ticket_id, $guest );
$hidden = array_merge( array( 'post_id' => $ticket_id ), $guest['hidden'] ?? array() );
$nonce  = $guest['nonce'] ?? 'stswresponsefrontend';
if ( ! $guest ) {
	$hidden['stsw_return'] = $view->get_view_url( 'ticket', array( 'ticket' => $ticket_id ) );
}
if ( $closed ) {
	?>
	<form class="stsw-reopen" method="post">
		<p><?php esc_html_e( 'This ticket is closed. Reopen it if you still need help.', 'support-ticket-system-for-woocommerce' ); ?></p>
		<?php $view->hidden_fields( $hidden ); ?>
		<?php wp_nonce_field( $nonce, $nonce ); ?>
		<button type="submit" class="stsw-button" name="stsw_ticket_action" value="reopen"><?php esc_html_e( 'Reopen ticket', 'support-ticket-system-for-woocommerce' ); ?></button>
	</form>
	<?php
	return;
}
?>
<form class="stsw-reply <?php echo esc_attr( $core->plugin ); ?>new_response" method="post" enctype="multipart/form-data">
	<div class="stsw-field">
		<label for="stsw-reply-<?php echo (int) $ticket_id; ?>"><?php esc_html_e( 'Your reply', 'support-ticket-system-for-woocommerce' ); ?></label>
		<textarea id="stsw-reply-<?php echo (int) $ticket_id; ?>" name="response_content" rows="5" required></textarea>
	</div>
	<?php $view->render_file_input( 'stsw-reply-files-' . (int) $ticket_id ); ?>
	<?php $view->hidden_fields( $hidden ); ?>
	<?php wp_nonce_field( $nonce, $nonce ); ?>
	<div class="stsw-actions">
		<button type="submit" class="stsw-button stsw-button--primary button wp-element-button" name="stsw_ticket_action" value="reply"><?php esc_html_e( 'Send reply', 'support-ticket-system-for-woocommerce' ); ?></button>
		<button type="submit" class="stsw-button" name="stsw_ticket_action" value="close" formnovalidate><?php esc_html_e( 'Close ticket', 'support-ticket-system-for-woocommerce' ); ?></button>
	</div>
</form>
<?php
