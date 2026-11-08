<?php
/**
 * Guest screen: enter the code sent by email.
 *
 * Override: copy to yourtheme/woocommerce-support/guest-code.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var array  $otp   Code sign-in: state { email, error, notice }.
 * @var string $nonce Nonce name and action.
 */

defined( 'ABSPATH' ) || exit;
$state = $otp['state'];
?>
<h2 id="stsw-guest-title"><?php esc_html_e( 'Check your email', 'support-ticket-system-for-woocommerce' ); ?></h2>
<?php if ( ! empty( $state['notice'] ) ) : ?>
	<div class="woocommerce-message" role="status"><?php echo esc_html( $state['notice'] ); ?></div>
<?php endif; ?>
<?php if ( ! empty( $state['error'] ) ) : ?>
	<div class="woocommerce-error" role="alert"><?php echo esc_html( $state['error'] ); ?></div>
<?php endif; ?>
<form method="post" class="stsw-otp__verify">
	<p>
		<label for="stsw-otp-code"><?php esc_html_e( 'Code from the email', 'support-ticket-system-for-woocommerce' ); ?></label><br />
		<input type="text" id="stsw-otp-code" name="stsw_otp_code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required aria-describedby="stsw-otp-help" />
		<small id="stsw-otp-help" class="stsw-help"><?php esc_html_e( 'Six digits. The code works for 15 minutes.', 'support-ticket-system-for-woocommerce' ); ?></small>
	</p>
	<input type="hidden" name="stsw_otp_email" value="<?php echo esc_attr( $state['email'] ?? '' ); ?>" />
	<input type="hidden" name="stsw_otp_action" value="verify" />
	<?php wp_nonce_field( $nonce, $nonce ); ?>
	<p><button type="submit" class="button wp-element-button"><?php esc_html_e( 'See my requests', 'support-ticket-system-for-woocommerce' ); ?></button></p>
</form>
<form method="post" class="stsw-otp__again">
	<input type="hidden" name="stsw_otp_email" value="<?php echo esc_attr( $state['email'] ?? '' ); ?>" />
	<input type="hidden" name="stsw_otp_action" value="send" />
	<?php wp_nonce_field( $nonce, $nonce ); ?>
	<button type="submit" class="button stsw-link-button"><?php esc_html_e( 'Send a new code', 'support-ticket-system-for-woocommerce' ); ?></button>
</form>
