<?php
/**
 * Customer screen part: the conversation, oldest first.
 *
 * Override: copy to yourtheme/woocommerce-support/ticket-thread.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var WPFactory_WC_STS_Customer $view      Customer screens.
 * @var WPFactory_WC_STS_Inc      $core      Core.
 * @var int                       $ticket_id Ticket ID.
 * @var int                       $viewer_id Viewer (0 for a guest).
 * @var int                       $owner     Customer user ID.
 * @var bool                      $staff     The viewer is store staff.
 * @var string                    $you       Label of the viewer's messages.
 * @var string                    $store     Label of store messages.
 * @var WPFactory_WC_STS_Files|null $files   Files helper.
 * @var array                     $file_args Extra query args for file links (guest access).
 */

defined( 'ABSPATH' ) || exit;
echo '<ol class="stsw-thread">';
// First message.
$started  = (int) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_STARTED_BY, true );
$first_by = ( ! $viewer_id || $viewer_id === $owner ) ? $you : $core->get_ticket_contact( $ticket_id )['display_name'];
if ( $started ) {
	$first_by = $staff ? $core->get_user_name( $started ) : $store;
}
echo '<li class="stsw-msg stsw-msg--' . ( $started ? 'store' : 'customer' ) . '"><p class="stsw-msg__head"><strong>' . esc_html( $first_by ) . '</strong> <time datetime="' . esc_attr( get_post_time( 'c', true, $ticket_id ) ) . '">' . esc_html( $core->format_response_date( get_post_field( 'post_date_gmt', $ticket_id ) ) ) . '</time></p>';
echo '<div class="stsw-msg__body">' . $core->format_message( get_post_field( 'post_content', $ticket_id, 'raw' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
$details = $view->get_ticket_details( $ticket_id );
if ( $details ) {
	echo '<dl class="stsw-details">';
	foreach ( $details as $label => $value ) {
		echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
	}
	echo '</dl>';
}
$ticket_files = (string) get_post_meta( $ticket_id, WPFactory_WC_STS_Inc::META_FILES, true );
if ( '' !== $ticket_files && $files ) {
	echo '<div class="stsw-files">';
	foreach ( explode( ',', $ticket_files ) as $url ) {
		$files->print_ticket_file_link( $url, $file_args );
	}
	echo '</div>';
}
echo '</li>';

foreach ( $core->get_replies( $ticket_id ) as $res ) {
	$by_store = (int) $res->agent > 0;
	if ( $by_store ) {
		$who = $staff ? $core->get_response_author_label( $res ) : $store;
	} else {
		$who = ( ! $viewer_id || $viewer_id === $owner ) ? $you : $core->get_ticket_contact( $ticket_id )['display_name'];
	}
	echo '<li class="stsw-msg stsw-msg--' . ( $by_store ? 'store' : 'customer' ) . '"><p class="stsw-msg__head"><strong>' . esc_html( $who ) . '</strong> <time datetime="' . esc_attr( mysql2date( 'c', $res->creationdate . ' +0000' ) ) . '">' . esc_html( $core->format_response_date( $res->creationdate ) ) . '</time></p>';
	echo '<div class="stsw-msg__body">' . $core->format_message( $res->content ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in format_message().
	if ( ! empty( $res->attachments ) && $files ) {
		echo '<div class="stsw-files">';
		foreach ( explode( ',', (string) $res->attachments ) as $attachment_id ) {
			$files->print_attachment_link( (int) $attachment_id, $file_args );
		}
		echo '</div>';
	}
	echo '</li>';
}
echo '</ol>';
