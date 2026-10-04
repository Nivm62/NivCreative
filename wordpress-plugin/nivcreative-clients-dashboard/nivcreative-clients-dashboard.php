<?php
/**
 * Plugin Name:       NivCreative – לוח ניהול לקוחות
 * Description:       לוח ניהול לקוחות פרטי (מנהלים בלבד) לסטודיו NivCreative. שורטקוד: [nivcreative_clients_dashboard]
 * Version:           1.0.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            NivCreative
 * Text Domain:       nivcreative-clients
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'NIVC_VERSION', '1.0.1' );
define( 'NIVC_DB_VERSION', '1' );
define( 'NIVC_FILE', __FILE__ );
define( 'NIVC_DIR', plugin_dir_path( __FILE__ ) );
define( 'NIVC_URL', plugin_dir_url( __FILE__ ) );
define( 'NIVC_SHORTCODE', 'nivcreative_clients_dashboard' );
define( 'NIVC_REST_NS', 'nivcreative/v1' );
define( 'NIVC_CAP', 'manage_options' );

require_once NIVC_DIR . 'includes/class-nivc-helpers.php';
require_once NIVC_DIR . 'includes/class-nivc-db.php';
require_once NIVC_DIR . 'includes/class-nivc-settings.php';
require_once NIVC_DIR . 'includes/class-nivc-repository.php';
require_once NIVC_DIR . 'includes/class-nivc-tracker.php';
require_once NIVC_DIR . 'includes/class-nivc-rest.php';
require_once NIVC_DIR . 'includes/class-nivc-shortcode.php';

// Activation creates/updates the tables. Deactivation and updates never touch client data.
register_activation_hook( __FILE__, array( 'NIVC_DB', 'install' ) );

add_action(
	'plugins_loaded',
	static function () {
		NIVC_DB::maybe_upgrade();
		NIVC_Tracker::init();
		NIVC_REST::init();
		NIVC_Shortcode::init();
	}
);
