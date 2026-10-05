<?php
// Deleting the plugin keeps all panel data (clients, leads, billing). To wipe it on purpose define
// NIVP_DELETE_DATA as true in wp-config.php before deleting the plugin; this drops the nivp_* tables.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
wp_clear_scheduled_hook( 'nivp_maintenance' );
delete_option( 'nivp_ready' );
delete_option( 'nivp_version' );
if ( defined( 'NIVP_DELETE_DATA' ) && NIVP_DELETE_DATA ) {
	global $wpdb;
	foreach ( array( 'rate_limits', 'api_logs', 'settings', 'notifications', 'page_views', 'lead_activity', 'lead_notes', 'leads', 'landing_pages', 'websites', 'subscriptions', 'password_resets', 'remember_tokens', 'users', 'clients' ) as $t ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `nivp_' . $t . '`' ); // phpcs:ignore WordPress.DB
	}
	delete_option( 'nivp_app_key' );
}
