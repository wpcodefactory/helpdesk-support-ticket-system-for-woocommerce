<?php
/**
 * Guest screen: order number and billing email to open a ticket, and the email code form to see earlier tickets.
 *
 * Override: copy to yourtheme/woocommerce-support/guest-lookup.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var string $error Error of the last form ('' when none).
 * @var string $nonce Nonce name and action.
 * @var array  $otp   Code sign-in: enabled, state { error, notice }.
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 id="stsw-guest-title"><?php esc_html_e( 'Need help with an order?', 'support-ticket-system-for-woocommerce' ); ?></h2>
<p><?php esc_html_e( 'No account needed: enter your order number and the email address used for the order.', 'support-ticket-system-for-woocommerce' ); ?></p>
<?php if ( '' !== $error ) : ?>
	<div class="woocommerce-error" role="alert"><?php echo esc_html( $error ); ?></div>
<?php endif; ?>
<form method="post" class="wpfactory-wc-sts-guest-lookup">
	<p>
		<label for="stsw-guest-order"><?php esc_html_e( 'Order number', 'support-ticket-system-for-woocommerce' ); ?></label><br />
		<input type="text" id="stsw-guest-order" name="stsw_order_number" required autocomplete="off" value="<?php echo esc_attr( $order_number ?? '' ); ?>" />
	</p>
	<p>
		<label for="stsw-guest-email"><?php esc_html_e( 'Billing email', 'support-ticket-system-for-woocommerce' ); ?></label><br />
		<input type="email" id="stsw-guest-email" name="stsw_email" required autocomplete="email" />
	</p>
	<?php
	/**
	 * Inside the guest order form, before the button (human check).
	 */
	do_action( 'wpfactory_wc_sts_guest_form_fields' );
	?>
	<input type="hidden" name="stsw_guest_action" value="lookup" />
	<?php wp_nonce_field( $nonce, $nonce ); ?>
	<p><button type="submit" class="button wp-element-button"><?php esc_html_e( 'Continue', 'support-ticket-system-for-woocommerce' ); ?></button></p>
</form>
<?php if ( ! empty( $otp['enabled'] ) ) : ?>
	<section class="stsw-otp" aria-labelledby="stsw-otp-title">
		<h3 id="stsw-otp-title"><?php esc_html_e( 'Wrote to us before?', 'support-ticket-system-for-woocommerce' ); ?></h3>
		<p><?php esc_html_e( 'Enter your email and we will send you a code to see all your requests.', 'support-ticket-system-for-woocommerce' ); ?></p>
		<?php if ( ! empty( $otp['state']['error'] ) && empty( $otp['state']['step'] ) ) : ?>
			<div class="woocommerce-error" role="alert"><?php echo esc_html( $otp['state']['error'] ); ?></div>
		<?php endif; ?>
		<form method="post" class="stsw-otp__send">
			<p>
				<label for="stsw-otp-email"><?php esc_html_e( 'Email', 'support-ticket-system-for-woocommerce' ); ?></label><br />
				<input type="email" id="stsw-otp-email" name="stsw_otp_email" required autocomplete="email" />
			</p>
			<input type="hidden" name="stsw_otp_action" value="send" />
			<?php wp_nonce_field( $nonce, $nonce ); ?>
			<p><button type="submit" class="button wp-element-button"><?php esc_html_e( 'Email me a code', 'support-ticket-system-for-woocommerce' ); ?></button></p>
		</form>
	</section>
<?php endif; ?>
