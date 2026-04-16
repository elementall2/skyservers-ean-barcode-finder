<?php
/**
 * Uninstall handler for SkyServers EAN Barcode Finder.
 *
 * Fired when the plugin is deleted (not deactivated).
 * Removes plugin options from the database.
 * Does NOT remove barcode data from products as that belongs to WooCommerce.
 *
 * @package SkyServersEanBarcodeFinder
 * @since   1.1.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'skyean_upcitemdb_api_key' );
