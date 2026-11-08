<?php
/**
 * Customer screen: help center (shortcode [stsw_knowledge_base]).
 *
 * Override: copy to yourtheme/woocommerce-support/help-center.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var string $query      Search words ('' when not searching).
 * @var array  $results    Articles found (list of { title, url, excerpt }), when searching.
 * @var array  $sections   Categories (list of { name, url, articles }), when not searching.
 * @var array  $articles   Latest articles when there are no categories.
 * @var string $ticket_url Link to open a ticket.
 */

defined( 'ABSPATH' ) || exit;

$wpfactory_wc_sts_list = static function ( $items, $none ) {
	if ( ! $items ) {
		if ( '' !== $none ) {
			echo '<p class="stsw-empty">' . esc_html( $none ) . '</p>';
		}
		return;
	}
	echo '<ul class="stsw-kb__list">';
	foreach ( $items as $item ) {
		echo '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a><span>' . esc_html( $item['excerpt'] ) . '</span></li>';
	}
	echo '</ul>';
};
?>
<div class="stsw stsw-kb">
	<form class="stsw-kb__search" role="search" method="get">
		<label for="stsw-kb-q" class="screen-reader-text"><?php esc_html_e( 'Search help articles', 'support-ticket-system-for-woocommerce' ); ?></label>
		<input type="search" id="stsw-kb-q" name="kb" value="<?php echo esc_attr( $query ); ?>" placeholder="<?php esc_attr_e( 'Search help articles', 'support-ticket-system-for-woocommerce' ); ?>" />
		<button type="submit" class="stsw-button button wp-element-button"><?php esc_html_e( 'Search', 'support-ticket-system-for-woocommerce' ); ?></button>
	</form>
	<?php if ( '' !== $query ) : ?>
		<h2 class="stsw-title">
			<?php
			/* translators: %s: search words. */
			echo esc_html( sprintf( __( 'Results for "%s"', 'support-ticket-system-for-woocommerce' ), $query ) );
			?>
		</h2>
		<?php $wpfactory_wc_sts_list( $results, __( 'No articles match. Try other words, or open a ticket.', 'support-ticket-system-for-woocommerce' ) ); ?>
	<?php elseif ( $sections ) : ?>
		<?php foreach ( $sections as $section ) : ?>
			<section class="stsw-kb__cat">
				<h2 class="stsw-title"><a href="<?php echo esc_url( $section['url'] ); ?>"><?php echo esc_html( $section['name'] ); ?></a></h2>
				<?php $wpfactory_wc_sts_list( $section['articles'], '' ); ?>
			</section>
		<?php endforeach; ?>
	<?php else : ?>
		<?php $wpfactory_wc_sts_list( $articles, __( 'No articles yet.', 'support-ticket-system-for-woocommerce' ) ); ?>
	<?php endif; ?>
	<p class="stsw-kb__more"><?php esc_html_e( 'Still need help?', 'support-ticket-system-for-woocommerce' ); ?> <a href="<?php echo esc_url( $ticket_url ); ?>"><?php esc_html_e( 'Open a ticket', 'support-ticket-system-for-woocommerce' ); ?></a></p>
</div>
