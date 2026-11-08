<?php
/**
 * Guest screen: every request of the email the guest confirmed with a code.
 *
 * Override: copy to yourtheme/woocommerce-support/guest-tickets.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var array  $otp   Code sign-in: email, tickets (list of { id, number, title, url, chip, state, at }).
 * @var string $nonce Nonce name and action.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="stsw-head">
	<div>
		<h2 class="stsw-title" id="stsw-guest-title"><?php esc_html_e( 'Your requests', 'support-ticket-system-for-woocommerce' ); ?></h2>
		<p class="stsw-intro">
			<?php
			/* translators: %s: email address. */
			echo esc_html( sprintf( __( 'Requests sent from %s.', 'support-ticket-system-for-woocommerce' ), $otp['email'] ) );
			?>
		</p>
	</div>
	<form method="post">
		<input type="hidden" name="stsw_otp_action" value="logout" />
		<?php wp_nonce_field( $nonce, $nonce ); ?>
		<button type="submit" class="stsw-button button"><?php esc_html_e( 'Sign out', 'support-ticket-system-for-woocommerce' ); ?></button>
	</form>
</div>
<?php if ( empty( $otp['tickets'] ) ) : ?>
	<div class="stsw-empty"><p><?php esc_html_e( 'There are no requests for this email address.', 'support-ticket-system-for-woocommerce' ); ?></p></div>
<?php else : ?>
	<ul class="stsw-cards" aria-labelledby="stsw-guest-title">
		<?php foreach ( $otp['tickets'] as $ticket ) : ?>
			<li class="stsw-card stsw-card--<?php echo esc_attr( $ticket['state'] ); ?>">
				<div class="stsw-card__main">
					<a class="stsw-card__link" href="<?php echo esc_url( $ticket['url'] ); ?>"><?php echo esc_html( $ticket['title'] ); ?></a>
					<p class="stsw-card__meta">
						<?php
						/* translators: %s: ticket number. */
						echo esc_html( sprintf( __( 'Ticket #%s', 'support-ticket-system-for-woocommerce' ), $ticket['number'] ) );
						?>
					</p>
				</div>
				<p class="stsw-card__chips"><?php echo wp_kses_post( $ticket['chip'] ); ?></p>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
<p class="stsw-help"><?php esc_html_e( 'Need help with another order? Sign out and enter its order number.', 'support-ticket-system-for-woocommerce' ); ?></p>
