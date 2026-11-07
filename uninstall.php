<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Uninstall.
 *
 * Deletes ticket data only when the merchant turned on "Delete all ticket data when the plugin is deleted"
 * (Settings > General), and only when the other edition (free or Pro) is not installed, because both
 * editions use the same tickets, replies and settings.
 *
 * @version 2.2.1
 * @since   2.2.1
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'wpfactory_wc_sts_uninstall_site' ) ) {
	/**
	 * Removes all ticket data of the current site, if the merchant asked for it.
	 *
	 * @param string $other_edition_file Main file name of the other edition.
	 */
	function wpfactory_wc_sts_uninstall_site( $other_edition_file ) {
		global $wpdb;

		if ( '1' !== (string) get_option( 'wpfactory_wc_sts_delete_data_on_uninstall' ) ) {
			return; // Default: keep everything, so a reinstall or the other edition finds the tickets.
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( array_keys( get_plugins() ) as $plugin ) {
			if ( substr( $plugin, -strlen( '/' . $other_edition_file ) ) === '/' . $other_edition_file ) {
				return; // The other edition still uses this data.
			}
		}

		// Tickets, their files and meta.
		$ticket_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'stsw_tickets'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $ticket_ids as $ticket_id ) {
			$attachments = get_children(
				array(
					'post_parent' => (int) $ticket_id,
					'post_type'   => 'attachment',
					'fields'      => 'ids',
				)
			);
			foreach ( $attachments as $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}
			wp_delete_post( (int) $ticket_id, true );
		}

		// Canned replies (2.3.0 / 3.1.0).
		$canned_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'stsw_canned_reply'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $canned_ids as $canned_id ) {
			wp_delete_post( (int) $canned_id, true );
		}

		// Status, priority and subject terms (taxonomies are not registered during uninstall).
		$taxonomies = array( 'stsw_tickets_status', 'stsw_tickets_priority', 'stsw_tickets_subject' );
		$term_ids   = $wpdb->get_col( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('" . implode( "','", $taxonomies ) . "')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('" . implode( "','", $taxonomies ) . "')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $term_ids as $term_id ) {
			$wpdb->delete( $wpdb->termmeta, array( 'term_id' => (int) $term_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term_id ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $wpdb->terms, array( 'term_id' => (int) $term_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
			delete_option( 'taxonomy_term_' . (int) $term_id );
		}

		// Replies and private notes.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}stsw_responses, {$wpdb->prefix}stsw_priv_responses" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange

		// Settings of both editions.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'STSWooCommerce%' OR option_name LIKE 'stswpro\\_%' OR option_name LIKE 'wpfactory\\_wc\\_sts\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Protected ticket files folder (Pro 3.0.5+): uploads/stsw-tickets.
		$uploads = wp_upload_dir( null, false );
		$folder  = trailingslashit( $uploads['basedir'] ) . 'stsw-tickets';
		if ( is_dir( $folder ) && 'stsw-tickets' === basename( $folder ) ) {
			$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $folder, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $items as $item ) {
				$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			@rmdir( $folder ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}

		// Ticket capability.
		foreach ( wp_roles()->role_objects as $role ) {
			$role->remove_cap( 'stsw_tickets' );
		}

		wp_cache_flush();
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $site_id ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- uninstall.php runs in its own scope.
		switch_to_blog( $site_id );
		wpfactory_wc_sts_uninstall_site( 'support-ticket-system-woocommerce-pro.php' );
		restore_current_blog();
	}
} else {
	wpfactory_wc_sts_uninstall_site( 'support-ticket-system-woocommerce-pro.php' );
}
