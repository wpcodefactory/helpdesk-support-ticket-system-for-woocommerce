<?php
/**
 * Customer screen: the list of tickets (My Account > Support, shortcode and block).
 *
 * Override: copy to yourtheme/woocommerce-support/ticket-list.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var WPFactory_WC_STS_Customer $view    Customer screens.
 * @var WPFactory_WC_STS_Inc      $core    Core.
 * @var WP_Query                  $query   Tickets on this page.
 * @var int                       $user    Current user ID.
 * @var int                       $page    Current page.
 * @var int                       $pages   Number of pages.
 * @var bool                      $closed_hidden Closed tickets are hidden.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="stsw-head">
	<div>
		<?php // My Account already shows the page title: no second "Support" heading there. ?>
		<h2 class="stsw-title<?php echo function_exists( 'is_account_page' ) && is_account_page() ? ' screen-reader-text' : ''; ?>" id="stsw-list-title"><?php esc_html_e( 'Support', 'support-ticket-system-for-woocommerce' ); ?></h2>
		<p class="stsw-intro"><?php esc_html_e( 'Questions about an order or a product? Open a ticket and follow the answers here.', 'support-ticket-system-for-woocommerce' ); ?></p>
	</div>
	<a class="stsw-button stsw-button--primary button wp-element-button" href="<?php echo esc_url( $view->get_view_url( 'new' ) ); ?>"><?php esc_html_e( 'Open a ticket', 'support-ticket-system-for-woocommerce' ); ?></a>
</div>
<?php
/**
 * Above the ticket list (Pro: support credits and priority support).
 *
 * @param int $user User ID.
 */
do_action( 'wpfactory_wc_sts_customer_list_top', $user );

if ( ! $query->have_posts() ) :
	?>
	<div class="stsw-empty"><p><strong><?php esc_html_e( 'You have no tickets yet.', 'support-ticket-system-for-woocommerce' ); ?></strong></p><p><?php esc_html_e( 'Questions about an order or a product? We reply here and by email.', 'support-ticket-system-for-woocommerce' ); ?></p><p><a class="stsw-button stsw-button--primary button wp-element-button" href="<?php echo esc_url( $view->get_view_url( 'new' ) ); ?>"><?php esc_html_e( 'Ask for help', 'support-ticket-system-for-woocommerce' ); ?></a></p></div>
	<?php
else :
	echo '<ul class="stsw-cards" aria-labelledby="stsw-list-title">';
	foreach ( $query->posts as $wpfactory_wc_sts_post ) {
		$row_id    = (int) $wpfactory_wc_sts_post->ID;
		$state     = $view->get_state( $row_id );
		$activity  = $core->get_activity( $row_id );
		$row_order = $core->get_ticket_order_id( $row_id ) ? wc_get_order( $core->get_ticket_order_id( $row_id ) ) : false;
		$is_agent  = $core->get_ticket_user_id( $row_id ) !== $user;
		/* translators: %s: ticket number. */
		$meta = array( sprintf( __( 'Ticket #%s', 'support-ticket-system-for-woocommerce' ), $core->get_ticket_number( $row_id ) ) );
		if ( $row_order ) {
			/* translators: %s: order number. */
			$meta[] = sprintf( __( 'Order #%s', 'support-ticket-system-for-woocommerce' ), $row_order->get_order_number() );
		}
		/* translators: %s: time since the last message, e.g. "2 hours ago". */
		$meta[] = sprintf( __( 'updated %s', 'support-ticket-system-for-woocommerce' ), $view->ago( $activity['at'] ) );
		echo '<li class="stsw-card stsw-card--' . esc_attr( $state ) . '"><div class="stsw-card__main">';
		echo '<a class="stsw-card__link" href="' . esc_url( $view->get_view_url( 'ticket', array( 'ticket' => $row_id ) ) ) . '">' . esc_html( get_the_title( $row_id ) ) . '</a>';
		echo '<p class="stsw-card__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p></div><p class="stsw-card__chips">';
		echo $view->status_chip( $row_id, $is_agent ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in status_chip().
		if ( $is_agent && $core->can_manage_tickets( $user ) ) {
			echo ' <span class="stsw-chip">' . esc_html__( 'Assigned to you', 'support-ticket-system-for-woocommerce' ) . '</span>';
		} elseif ( $is_agent ) {
			/* translators: %s: name. */
			echo ' <span class="stsw-chip">' . esc_html( sprintf( __( 'Opened by %s', 'support-ticket-system-for-woocommerce' ), $core->get_ticket_contact( $row_id )['display_name'] ) ) . '</span>';
		}
		echo '</p></li>';
	}
	echo '</ul>';
endif;

if ( $pages > 1 ) :
	?>
	<nav class="stsw-pages" aria-label="<?php esc_attr_e( 'Ticket pages', 'support-ticket-system-for-woocommerce' ); ?>">
		<?php if ( $page > 1 ) : ?>
			<a class="stsw-button" href="<?php echo esc_url( $view->get_view_url( '', $page > 2 ? array( 'stsw_page' => $page - 1 ) : array() ) ); ?>" rel="prev"><span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Newer tickets', 'support-ticket-system-for-woocommerce' ); ?></a>
		<?php endif; ?>
		<span class="stsw-pages__current">
			<?php
			/* translators: 1: current page, 2: number of pages. */
			echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'support-ticket-system-for-woocommerce' ), $page, $pages ) );
			?>
		</span>
		<?php if ( $page < $pages ) : ?>
			<a class="stsw-button" href="<?php echo esc_url( $view->get_view_url( '', array( 'stsw_page' => $page + 1 ) ) ); ?>" rel="next"><?php esc_html_e( 'Older tickets', 'support-ticket-system-for-woocommerce' ); ?> <span aria-hidden="true">&rarr;</span></a>
		<?php endif; ?>
	</nav>
	<?php
endif;

if ( $closed_hidden ) {
	echo '<p class="stsw-help stsw-hidden-note">' . esc_html__( 'Closed tickets are not shown here.', 'support-ticket-system-for-woocommerce' ) . '</p>';
}
