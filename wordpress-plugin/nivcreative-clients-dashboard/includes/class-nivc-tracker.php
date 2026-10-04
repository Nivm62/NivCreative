<?php
defined( 'ABSPATH' ) || exit;

/**
 * Built-in, cookie-less page-view counter for client landing pages hosted on this site.
 *
 *  - Only counts from the moment tracking is enabled; nothing is back-filled.
 *  - The beacon is a separate request, so it works with full-page caching.
 *  - Only pages that belong to a client are measured; other paths are ignored.
 *  - Logged-in administrators are excluded server-side (auth cookie is checked directly, no nonce needed).
 *  - "Unique visitors" = distinct anonymous daily hashes (IP + user agent + date + site salt).
 */
final class NIVC_Tracker {

	const TRANSIENT = 'nivc_tracked_paths';

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_action( 'save_post_page', array( 'NIVC_Repository', 'sync_page' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	public static function register_route(): void {
		register_rest_route(
			NIVC_REST_NS,
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_beacon' ),
				// Intentionally public: it only increments a counter and never returns data.
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function flush_cache(): void {
		delete_transient( self::TRANSIENT );
	}

	/** @return string[] path keys of all clients that live on this site. */
	public static function tracked_paths(): array {
		$paths = get_transient( self::TRANSIENT );
		if ( ! is_array( $paths ) ) {
			global $wpdb;
			$col   = $wpdb->get_col( "SELECT DISTINCT path_key FROM " . NIVC_DB::table( 'clients' ) . " WHERE path_key <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL
			$paths = array_values( $col ? $col : array() );
			set_transient( self::TRANSIENT, $paths, DAY_IN_SECONDS );
		}
		return $paths;
	}

	private static function request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security
		$p   = wp_parse_url( $uri, PHP_URL_PATH );
		return NIVC_Helpers::normalize_path( is_string( $p ) ? $p : '/' );
	}

	/** Loads the beacon script only on pages that belong to a client. */
	public static function maybe_enqueue(): void {
		if ( ! NIVC_Settings::tracking_enabled() || is_admin() || is_preview() || ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) ) {
			return;
		}
		if ( current_user_can( NIVC_CAP ) ) {
			return;
		}
		if ( ! in_array( self::request_path(), self::tracked_paths(), true ) ) {
			return;
		}
		wp_enqueue_script( 'nivc-tracker', NIVC_URL . 'assets/js/tracker.js', array(), NIVC_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script( 'nivc-tracker', 'window.nivcTrack=' . wp_json_encode( array( 'url' => esc_url_raw( rest_url( NIVC_REST_NS . '/track' ) ) ) ) . ';', 'before' );
	}

	public static function handle_beacon( WP_REST_Request $request ) {
		$res = new WP_REST_Response( null, 204 );
		$res->header( 'Cache-Control', 'no-store' );

		if ( ! NIVC_Settings::tracking_enabled() ) {
			return $res;
		}
		$path = $request->get_param( 'p' );
		if ( ! is_string( $path ) || strlen( $path ) > 400 ) {
			return $res;
		}
		$key = NIVC_Helpers::normalize_path( $path );
		if ( ! in_array( $key, self::tracked_paths(), true ) ) {
			return $res;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security
		if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|headless|lighthouse|curl|wget|python|monitor|uptime/i', $ua ) ) {
			return $res;
		}
		// Exclude logged-in administrators even on cached pages.
		$uid = function_exists( 'wp_validate_auth_cookie' ) ? wp_validate_auth_cookie( '', 'logged_in' ) : 0;
		if ( $uid && user_can( $uid, NIVC_CAP ) ) {
			return $res;
		}

		global $wpdb;
		$day   = NIVC_Helpers::today();
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security
		$hash  = substr( hash_hmac( 'sha256', $ip . '|' . $ua . '|' . $day, wp_salt( 'auth' ) ), 0, 32 );
		$table = NIVC_DB::table( 'visits' );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (path_key, day, visitor_hash, views, last_seen) VALUES (%s, %s, %s, 1, %s)
				ON DUPLICATE KEY UPDATE views = views + 1, last_seen = VALUES(last_seen)",
				$key,
				$day,
				$hash,
				NIVC_Helpers::now_mysql()
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL
		return $res;
	}

	/** Measurement info for the dashboard. */
	public static function status(): array {
		global $wpdb;
		$s    = NIVC_Settings::get();
		$last = $s['tracking_enabled'] ? $wpdb->get_var( 'SELECT MAX(last_seen) FROM ' . NIVC_DB::table( 'visits' ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL
		$det  = array();
		if ( class_exists( '\Google\Site_Kit\Plugin' ) ) {
			$det[] = 'Site Kit';
		}
		if ( class_exists( 'WP_STATISTICS\Statistics' ) || defined( 'WP_STATISTICS_VERSION' ) ) {
			$det[] = 'WP Statistics';
		}
		if ( class_exists( 'Jetpack' ) ) {
			$det[] = 'Jetpack';
		}
		return array(
			'connected'    => (bool) $s['tracking_enabled'],
			'source'       => $s['tracking_enabled'] ? 'internal' : null,
			'since'        => $s['tracking_since'] ? $s['tracking_since'] : null,
			'last_update'  => $last ? $last : null,
			'detected'     => $det,
			'pages_tracked' => count( self::tracked_paths() ),
		);
	}
}
