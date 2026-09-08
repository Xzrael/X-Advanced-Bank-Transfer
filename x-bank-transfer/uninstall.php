<?php
/**
 * Uninstall routine for X Bank Transfer.
 *
 * This file runs only when the plugin is deleted from the Plugins screen,
 * and only when WP_UNINSTALL_PLUGIN is defined.
 *
 * @package X_Bank_Transfer
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all uploaded receipt attachments created by this plugin.
 *
 * We look for attachments whose title matches the prefix this plugin uses,
 * and remove them along with their files.
 *
 * Note: This intentionally removes only the data created by this plugin,
 * not other posts or options.
 */
function xbt_uninstall_delete_receipts() {
	$query = new WP_Query(
		array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_xbt_receipt',
					'value' => '1',
				),
			),
		)
	);

	$ids = $query->posts;

	foreach ( $ids as $id ) {
		wp_delete_attachment( $id, true );
	}

	wp_reset_postdata();
}
xbt_uninstall_delete_receipts();

// Remove plugin options.
delete_option( 'xbt_version' );

// Remove plugin transients.
delete_transient( 'xbt_gateway_loaded' );
