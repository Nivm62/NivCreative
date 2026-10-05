<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
delete_option( 'nivc_conn_settings' );
delete_option( 'nivc_conn_queue' );
wp_clear_scheduled_hook( 'nivc_conn_heartbeat' );
wp_clear_scheduled_hook( 'nivc_conn_flush_queue' );
