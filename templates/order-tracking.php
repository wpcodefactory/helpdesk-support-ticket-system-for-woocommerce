<?php
/**
 * Customer screen part: "Where is my order?" (tracking numbers, or the order's status timeline).
 *
 * Override: copy to yourtheme/woocommerce-support/order-tracking.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var WC_Order $order    Order.
 * @var array    $tracking Tracking items (list of { provider, number, url, date }).
 * @var array    $timeline Status steps when there is no tracking (list of { label, date, done }).
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="stsw-track__status">
	<strong>
		<?php
		/* translators: %s: order number. */
		echo esc_html( sprintf( __( 'Order #%s', 'support-ticket-system-for-woocommerce' ), $order->get_order_number() ) );
		?>
	</strong>
	<span class="stsw-chip"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
</p>
<?php if ( $tracking ) : ?>
	<ul class="stsw-track__list">
		<?php foreach ( $tracking as $item ) : ?>
			<?php $label = trim( $item['provider'] . ' ' . $item['number'] ); ?>
			<li>
				<?php if ( '' !== $item['url'] ) : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $label ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'support-ticket-system-for-woocommerce' ); ?></span></a>
				<?php else : ?>
					<?php echo esc_html( $label ); ?>
				<?php endif; ?>
				<?php if ( '' !== $item['date'] ) : ?>
					<span class="stsw-help">
						<?php
						/* translators: %s: date. */
						echo esc_html( sprintf( __( 'shipped %s', 'support-ticket-system-for-woocommerce' ), $item['date'] ) );
						?>
					</span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php else : ?>
	<ol class="stsw-track__timeline">
		<?php foreach ( $timeline as $step ) : ?>
			<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
				<span><?php echo esc_html( $step['label'] ); ?></span>
				<?php if ( '' !== $step['date'] ) : ?>
					<span class="stsw-help"><?php echo esc_html( $step['date'] ); ?></span>
				<?php endif; ?>
				<span class="screen-reader-text"> <?php echo esc_html( $step['done'] ? __( '(done)', 'support-ticket-system-for-woocommerce' ) : __( '(not yet)', 'support-ticket-system-for-woocommerce' ) ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>
