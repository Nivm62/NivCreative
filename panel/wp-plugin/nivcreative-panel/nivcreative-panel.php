<?php
/**
 * Plugin Name:       NivCreative Panel
 * Description:       Client & lead management panel (admin + client dashboards, Hebrew/English) served from /app on this WordPress site.
 * Version:           1.1.0
 * Requires at least: 5.9
 * Requires PHP:      8.1
 * Author:            NivCreative
 * License:           GPL-2.0-or-later
 * Text Domain:       nivcreative-panel
 */

defined( 'ABSPATH' ) || exit;

define( 'NIVP_VERSION', '1.1.0' );
define( 'NIVP_FILE', __FILE__ );
define( 'NIVP_DIR', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'NIVP_PATH' ) ) {
	define( 'NIVP_PATH', 'app' ); // the panel lives at https://your-site/app  (override in wp-config.php if ever needed)
}

/**
 * Thin WordPress wrapper around the standalone NivCreative Panel (folder /app of this plugin).
 *  - intercepts requests to /app/* very early (plugins_loaded, priority 0) and runs the panel instead of WordPress;
 *  - uses this site's database (own tables, prefix "nivp_"), its own users/sessions — WordPress logins grant nothing in the panel;
 *  - keeps sessions/logs outside the plugin folder (wp-content/nivcreative-panel-data) so plugin updates never touch them.
 */
final class NivP {

	private static $booted = false;

	/* ---------------------------------------------------------------- paths */

	public static function base_path() {
		return rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' ) . '/' . NIVP_PATH;
	}

	public static function storage_dir() {
		return WP_CONTENT_DIR . '/nivcreative-panel-data';
	}

	/** Splits WordPress' DB_HOST ("host", "host:3307", "localhost:/path/to.sock"). */
	private static function db_config() {
		$host = DB_HOST;
		$port = 3306;
		$sock = '';
		if ( false !== strpos( $host, ':' ) && substr_count( $host, ':' ) === 1 ) {
			list( $h, $rest ) = explode( ':', $host, 2 );
			if ( ctype_digit( $rest ) ) {
				$host = $h;
				$port = (int) $rest;
			} elseif ( '' !== $rest && '/' === $rest[0] ) {
				$host = $h;
				$sock = $rest;
			}
		}
		return array( 'host' => $host, 'port' => $port, 'socket' => $sock, 'name' => DB_NAME, 'user' => DB_USER, 'pass' => DB_PASSWORD, 'charset' => 'utf8mb4', 'prefix' => 'nivp_' );
	}

	/** Loads the panel code and injects the WordPress-derived configuration (idempotent). */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		if ( ! defined( 'NIVC_PANEL_VERSION' ) ) {
			define( 'NIVC_PANEL_VERSION', NIVP_VERSION );
		}
		if ( ! defined( 'NIVC_ASSET_URL' ) ) {
			define( 'NIVC_ASSET_URL', plugins_url( 'app/assets', NIVP_FILE ) ); // static files are served straight by the web server
		}
		require_once NIVP_DIR . 'app/src/autoload.php';

		$tz = (string) get_option( 'timezone_string' );
		$cfg = array(
			'env'            => defined( 'NIVP_ENV' ) ? NIVP_ENV : 'production',
			'timezone'       => '' !== $tz ? $tz : 'Asia/Jerusalem',
			'default_locale' => 'he',
			'app_key'        => (string) get_option( 'nivp_app_key' ),
			'base_url'       => home_url( '/' . NIVP_PATH ),
			'mail_from'      => (string) get_option( 'admin_email' ),
			'support_email'  => (string) get_option( 'admin_email' ),
			'db'             => self::db_config(),
		);
		\Nivc\Core\Config::embedded( apply_filters( 'nivp_config', $cfg ), self::storage_dir() );
		self::$booted = true;
	}

	/* ----------------------------------------------------- request handling */

	public static function maybe_serve() {
		$uri  = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH ); // phpcs:ignore WordPress.Security
		$base = self::base_path();
		if ( $uri !== $base && 0 !== strpos( $uri, $base . '/' ) ) {
			return;
		}
		// From here on WordPress is out of the picture: drop anything other plugins buffered and run the panel.
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		if ( '1' !== (string) get_option( 'nivp_ready' ) ) {
			status_header( 503 );
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Cache-Control: no-store' );
			echo '<!doctype html><meta charset="utf-8"><title>NivCreative Panel</title><body style="font-family:sans-serif;max-width:520px;margin:15vh auto;text-align:center"><h2>הפאנל עדיין לא הופעל</h2><p>כנסו ל-WordPress ← NivCreative Panel והשלימו את ההגדרה.</p></body>';
			exit;
		}
		@ini_set( 'display_errors', '0' );
		self::boot();
		\Nivc\Core\App::run();
		exit;
	}

	/* ------------------------------------------------ install / upgrade / cron */

	public static function activate() {
		if ( PHP_VERSION_ID < 80100 ) {
			wp_die( 'NivCreative Panel requires PHP 8.1 or newer (current: ' . esc_html( PHP_VERSION ) . '). Change the PHP version in hPanel → Advanced → PHP Configuration.', 'NivCreative Panel', array( 'back_link' => true ) );
		}
		// pdo_mysql is the only hard requirement besides an encryption backend (sodium OR openssl). mbstring/ctype/curl are optional (polyfilled/fallback).
		if ( ! extension_loaded( 'pdo_mysql' ) ) {
			wp_die( 'NivCreative Panel needs the PHP extension pdo_mysql. Enable it in hPanel → Advanced → PHP Configuration → PHP extensions.', 'NivCreative Panel', array( 'back_link' => true ) );
		}
		if ( ! extension_loaded( 'sodium' ) && ! extension_loaded( 'openssl' ) ) {
			wp_die( 'NivCreative Panel needs the PHP extension openssl (or sodium).', 'NivCreative Panel', array( 'back_link' => true ) );
		}
		self::ensure_storage();
		if ( ! get_option( 'nivp_app_key' ) ) {
			add_option( 'nivp_app_key', base64_encode( random_bytes( 32 ) ), '', false );
		}
		try {
			self::boot();
			\Nivc\Controllers\Web\InstallController::runSchema( \Nivc\Core\Db::connect() );
		} catch ( \Throwable $e ) {
			wp_die( 'NivCreative Panel could not create its tables: ' . esc_html( $e->getMessage() ), 'NivCreative Panel', array( 'back_link' => true ) );
		}
		update_option( 'nivp_ready', '1', false );
		update_option( 'nivp_version', NIVP_VERSION, false );
		self::schedule();
		set_transient( 'nivp_activated', 1, 60 );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'nivp_maintenance' );
	}

	private static function ensure_storage() {
		$dir = self::storage_dir();
		foreach ( array( '', '/sessions', '/logs', '/cache' ) as $sub ) {
			wp_mkdir_p( $dir . $sub );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "# Private data\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" );
		}
	}

	/** Applies schema + SQL migrations after a plugin update (admin side only, runs once per version). */
	public static function maybe_upgrade() {
		if ( get_option( 'nivp_version' ) === NIVP_VERSION || '1' !== (string) get_option( 'nivp_ready' ) ) {
			return;
		}
		try {
			self::ensure_storage();
			self::boot();
			\Nivc\Controllers\Web\InstallController::runSchema( \Nivc\Core\Db::connect() );
			$applied = json_decode( (string) \Nivc\Services\Settings::get( 'migrations_applied', '[]' ), true ) ?: array();
			$files   = glob( NIVP_DIR . 'app/database/migrations/*.sql' ) ?: array();
			sort( $files );
			foreach ( $files as $f ) {
				if ( in_array( basename( $f ), $applied, true ) ) {
					continue;
				}
				foreach ( array_filter( array_map( 'trim', preg_split( '/;\s*\n/', (string) file_get_contents( $f ) ) ?: array() ) ) as $stmt ) {
					if ( 0 === strpos( ltrim( $stmt ), '--' ) && false === strpos( $stmt, "\n" ) ) {
						continue; // comment-only chunk
					}
					try {
						\Nivc\Core\Db::exec( $stmt );
					} catch ( \PDOException $e ) {
						if ( 1060 !== (int) ( $e->errorInfo[1] ?? 0 ) ) { // 1060 = duplicate column: schema.sql already added it
							throw $e;
						}
					}
				}
				$applied[] = basename( $f );
				\Nivc\Services\Settings::set( 'migrations_applied', wp_json_encode( $applied ) );
			}
			update_option( 'nivp_version', NIVP_VERSION, false );
		} catch ( \Throwable $e ) {
			error_log( 'NivCreative Panel upgrade failed: ' . $e->getMessage() );
		}
	}

	public static function schedules( $s ) {
		$s['nivp_ten_minutes'] = array( 'interval' => 600, 'display' => 'Every 10 minutes' );
		return $s;
	}

	private static function schedule() {
		if ( ! wp_next_scheduled( 'nivp_maintenance' ) ) {
			wp_schedule_event( time() + 120, 'nivp_ten_minutes', 'nivp_maintenance' );
		}
	}

	public static function run_maintenance() {
		try {
			self::boot();
			\Nivc\Core\I18n::setLocale( 'he' );
			\Nivc\Services\MaintenanceService::run();
		} catch ( \Throwable $e ) {
			error_log( 'NivCreative Panel maintenance failed: ' . $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------ admin page */

	public static function menu() {
		add_menu_page( 'NivCreative Panel', 'NivCreative Panel', 'manage_options', 'nivcreative-panel', array( __CLASS__, 'page' ), 'dashicons-chart-area', 58 );
	}

	private static function status() {
		$s = array(
			'PHP 8.1+'                   => PHP_VERSION_ID >= 80100,
			'PHP extensions: pdo_mysql + (openssl or sodium)' => extension_loaded( 'pdo_mysql' ) && ( extension_loaded( 'openssl' ) || extension_loaded( 'sodium' ) ),
			'Panel activated'            => '1' === (string) get_option( 'nivp_ready' ),
			'Private storage writable'   => is_writable( self::storage_dir() ),
			'Panel administrator exists' => false,
		);
		try {
			self::boot();
			$s['Panel administrator exists'] = (bool) \Nivc\Core\Db::val( "SELECT id FROM users WHERE role = 'admin' LIMIT 1" );
		} catch ( \Throwable $e ) {
			$s['Database tables'] = false;
		}
		return $s;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$url   = home_url( '/' . NIVP_PATH . '/' );
		$msg   = isset( $_GET['nivp_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['nivp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$err   = isset( $_GET['nivp_err'] ) ? sanitize_text_field( wp_unslash( $_GET['nivp_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$st    = self::status();
		$user  = wp_get_current_user();
		echo '<div class="wrap"><h1>NivCreative Panel</h1>';
		if ( $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
		if ( $err ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
		}
		echo '<p>The panel runs at <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><strong>' . esc_html( $url ) . '</strong></a> with its own login (separate from WordPress).</p>';
		echo '<p><a class="button button-primary button-hero" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Open the panel</a></p>';

		echo '<h2>Status</h2><table class="widefat striped" style="max-width:560px"><tbody>';
		foreach ( $st as $label => $ok ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . ( $ok ? '<span style="color:#00a32a">✔</span>' : '<span style="color:#d63638">✖</span>' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>Panel administrator</h2><p>Create the administrator account for the panel (or reset the password of an existing one). This account is independent from your WordPress user.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:560px">';
		wp_nonce_field( 'nivp_save_admin' );
		echo '<input type="hidden" name="action" value="nivp_save_admin"><table class="form-table"><tbody>';
		echo '<tr><th><label for="nivp_name">Name</label></th><td><input class="regular-text" id="nivp_name" name="name" value="' . esc_attr( $user->display_name ) . '" required></td></tr>';
		echo '<tr><th><label for="nivp_email">E-mail (login)</label></th><td><input class="regular-text" type="email" id="nivp_email" name="email" value="' . esc_attr( $user->user_email ) . '" required></td></tr>';
		echo '<tr><th><label for="nivp_pw">Password</label></th><td><input class="regular-text" type="password" id="nivp_pw" name="password" autocomplete="new-password" required><p class="description">At least 8 characters, with a letter and a number.</p></td></tr>';
		echo '</tbody></table>';
		submit_button( 'Save administrator' );
		echo '</form>';

		echo '<h2>Connector plugin</h2><p>Install this plugin on every client website that should send leads and page views to the panel (Plugins → Add New → Upload).</p>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nivp_download_connector' ), 'nivp_download_connector' ) ) . '">Download NivCreative Connector (.zip)</a></p>';
		echo '<hr><p class="description">Data is stored in tables prefixed <code>nivp_</code> in this site\'s database and is kept when the plugin is deactivated or deleted. Sessions and logs: <code>wp-content/nivcreative-panel-data</code>.</p></div>';
	}

	public static function save_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'nivp_save_admin' );
		$back = admin_url( 'admin.php?page=nivcreative-panel' );
		$name = sanitize_text_field( wp_unslash( isset( $_POST['name'] ) ? $_POST['name'] : '' ) );
		$mail = strtolower( sanitize_email( wp_unslash( isset( $_POST['email'] ) ? $_POST['email'] : '' ) ) );
		$pw   = (string) wp_unslash( isset( $_POST['password'] ) ? $_POST['password'] : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		try {
			self::boot();
			if ( '' === $name || ! is_email( $mail ) ) {
				throw new \RuntimeException( 'Enter a name and a valid e-mail.' );
			}
			if ( ! \Nivc\Core\Auth::validPasswordRule( $pw ) ) {
				throw new \RuntimeException( 'Password must be at least 8 characters with a letter and a number.' );
			}
			$now = \Nivc\Core\NowTime::mysql();
			$row = \Nivc\Core\Db::one( 'SELECT id, role FROM users WHERE email = ?', array( $mail ) );
			if ( $row && 'admin' !== $row['role'] ) {
				throw new \RuntimeException( 'That e-mail belongs to a client account. Use a different e-mail.' );
			}
			$hash = \Nivc\Core\Auth::hashPassword( $pw );
			if ( $row ) {
				\Nivc\Core\Db::update( 'users', array( 'password_hash' => $hash, 'name' => $name, 'status' => 'active', 'updated_at' => $now ), array( 'id' => $row['id'] ) );
				\Nivc\Core\Db::exec( 'DELETE FROM remember_tokens WHERE user_id = ?', array( $row['id'] ) );
				$msg = 'Administrator updated.';
			} else {
				\Nivc\Core\Db::insert( 'users', array( 'client_id' => null, 'role' => 'admin', 'email' => $mail, 'password_hash' => $hash, 'name' => $name, 'locale' => 'he', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now ) );
				$msg = 'Administrator created. You can now sign in to the panel.';
			}
			wp_safe_redirect( add_query_arg( 'nivp_msg', rawurlencode( $msg ), $back ) );
		} catch ( \Throwable $e ) {
			wp_safe_redirect( add_query_arg( 'nivp_err', rawurlencode( $e->getMessage() ), $back ) );
		}
		exit;
	}

	public static function download_connector() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'nivp_download_connector' );
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( 'The PHP ZipArchive extension is not available. Copy the folder wp-content/plugins/nivcreative-panel/connector/nivcreative-connector to the client site instead.' );
		}
		$src = NIVP_DIR . 'connector/nivcreative-connector';
		$tmp = wp_tempnam( 'nivc-connector' );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::OVERWRITE );
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( $f->isFile() ) {
				$zip->addFile( $f->getPathname(), 'nivcreative-connector/' . ltrim( substr( $f->getPathname(), strlen( $src ) ), '/\\' ) );
			}
		}
		$zip->close();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="nivcreative-connector.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		unlink( $tmp );
		exit;
	}

	public static function activation_notice() {
		if ( get_transient( 'nivp_activated' ) && current_user_can( 'manage_options' ) ) {
			delete_transient( 'nivp_activated' );
			echo '<div class="notice notice-success is-dismissible"><p><strong>NivCreative Panel is active.</strong> Next: <a href="' . esc_url( admin_url( 'admin.php?page=nivcreative-panel' ) ) . '">create the panel administrator</a>, then open <a href="' . esc_url( home_url( '/' . NIVP_PATH . '/' ) ) . '">' . esc_html( home_url( '/' . NIVP_PATH . '/' ) ) . '</a>.</p></div>';
		}
	}
}

register_activation_hook( __FILE__, array( 'NivP', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NivP', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'NivP', 'maybe_serve' ), 0 );
add_filter( 'cron_schedules', array( 'NivP', 'schedules' ) );
add_action( 'nivp_maintenance', array( 'NivP', 'run_maintenance' ) );
add_action( 'init', static function () {
	if ( '1' === (string) get_option( 'nivp_ready' ) && ! wp_next_scheduled( 'nivp_maintenance' ) ) {
		wp_schedule_event( time() + 120, 'nivp_ten_minutes', 'nivp_maintenance' );
	}
} );
if ( is_admin() ) {
	add_action( 'admin_menu', array( 'NivP', 'menu' ) );
	add_action( 'admin_init', array( 'NivP', 'maybe_upgrade' ) );
	add_action( 'admin_notices', array( 'NivP', 'activation_notice' ) );
	add_action( 'admin_post_nivp_save_admin', array( 'NivP', 'save_admin' ) );
	add_action( 'admin_post_nivp_download_connector', array( 'NivP', 'download_connector' ) );
}
