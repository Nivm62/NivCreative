<?php
defined( 'ABSPATH' ) || exit;

/**
 * Private REST API. Every route checks: logged in -> manage_options -> (writes) custom nonce.
 * The core REST cookie check additionally requires a valid X-WP-Nonce (wp_rest) or the user is treated as logged out.
 */
final class NIVC_REST {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'no_cache' ), 10, 3 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'block_page_cache' ), 1, 3 );
	}

	/** Tell page caches (LiteSpeed, WP Rocket, ...) before the handler runs: never cache private API responses. */
	public static function block_page_cache( $result, $server, $request ) {
		$route = $request->get_route();
		if ( 0 === strpos( $route, '/' . NIVC_REST_NS . '/' ) && '/' . NIVC_REST_NS . '/track' !== $route ) {
			foreach ( array( 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB' ) as $c ) {
				if ( ! defined( $c ) ) {
					define( $c, true );
				}
			}
			if ( ! headers_sent() ) {
				header( 'X-LiteSpeed-Cache-Control: no-cache, no-store' );
			}
			do_action( 'litespeed_control_set_nocache', 'nivcreative private api' );
		}
		return $result;
	}

	public static function routes(): void {
		$read  = array( __CLASS__, 'can_read' );
		$write = array( __CLASS__, 'can_write' );
		$id    = array( 'id' => array( 'validate_callback' => static function ( $v ) { return is_numeric( $v ) && (int) $v > 0; } ) );

		register_rest_route( NIVC_REST_NS, '/clients', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'list_clients' ), 'permission_callback' => $read ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create_client' ), 'permission_callback' => $write ),
		) );
		register_rest_route( NIVC_REST_NS, '/clients/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_client' ), 'permission_callback' => $read, 'args' => $id ),
			array( 'methods' => 'POST,PUT,PATCH', 'callback' => array( __CLASS__, 'update_client' ), 'permission_callback' => $write, 'args' => $id ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_client' ), 'permission_callback' => $write, 'args' => $id ),
		) );
		register_rest_route( NIVC_REST_NS, '/clients/(?P<id>\d+)/renew', array(
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'renew_client' ), 'permission_callback' => $write, 'args' => $id ),
		) );
		register_rest_route( NIVC_REST_NS, '/clients/(?P<id>\d+)/payments', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'client_payments' ), 'permission_callback' => $read, 'args' => $id ),
		) );
		register_rest_route( NIVC_REST_NS, '/pages', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'pages' ), 'permission_callback' => $read ),
		) );
		register_rest_route( NIVC_REST_NS, '/analytics', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'analytics_status' ), 'permission_callback' => $read ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'analytics_save' ), 'permission_callback' => $write ),
		) );
	}

	/* ------------------------------------------------------------ permissions */

	private static function authorize( bool $write, ?WP_REST_Request $request = null ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'nivc_unauthenticated', 'יש להתחבר לוורדפרס כדי להמשיך.', array( 'status' => 401 ) );
		}
		if ( ! current_user_can( NIVC_CAP ) ) {
			return new WP_Error( 'nivc_forbidden', 'אין לך הרשאה לבצע פעולה זו.', array( 'status' => 403 ) );
		}
		if ( $write ) {
			$nonce = $request ? $request->get_header( 'X-NIVC-Nonce' ) : '';
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'nivc_write' ) ) {
				return new WP_Error( 'nivc_bad_nonce', 'פג תוקף ההפעלה. רעננו את הדף ונסו שוב.', array( 'status' => 403 ) );
			}
		}
		return true;
	}

	public static function can_read() {
		return self::authorize( false );
	}

	public static function can_write( WP_REST_Request $request ) {
		return self::authorize( true, $request );
	}

	/** Private responses must never be cached by browsers, proxies or CDNs. */
	public static function no_cache( $result, $server, $request ) {
		$route = $request->get_route();
		if ( 0 === strpos( $route, '/' . NIVC_REST_NS . '/' ) && '/' . NIVC_REST_NS . '/track' !== $route && $result instanceof WP_HTTP_Response ) {
			$result->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
			$result->header( 'Pragma', 'no-cache' );
			$result->header( 'X-Robots-Tag', 'noindex' );
			$result->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		}
		return $result;
	}

	/* --------------------------------------------------------------- handlers */

	public static function list_clients( WP_REST_Request $r ) {
		$data             = NIVC_Repository::query( $r->get_params() );
		$data['analytics'] = NIVC_Tracker::status();
		$data['today']     = NIVC_Helpers::today();
		return rest_ensure_response( $data );
	}

	public static function get_client( WP_REST_Request $r ) {
		$c = NIVC_Repository::get( (int) $r['id'] );
		return $c ? rest_ensure_response( $c ) : self::not_found();
	}

	public static function create_client( WP_REST_Request $r ) {
		$d = NIVC_Repository::validate_client( (array) $r->get_json_params() );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$id = NIVC_Repository::create( $d );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return new WP_REST_Response( array( 'message' => 'הלקוח נוסף בהצלחה.', 'client' => NIVC_Repository::get( $id ) ), 201 );
	}

	public static function update_client( WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! NIVC_Repository::get( $id ) ) {
			return self::not_found();
		}
		$d = NIVC_Repository::validate_client( (array) $r->get_json_params() );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$ok = NIVC_Repository::update( $id, $d );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return rest_ensure_response( array( 'message' => 'הלקוח עודכן בהצלחה.', 'client' => NIVC_Repository::get( $id ) ) );
	}

	public static function delete_client( WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! NIVC_Repository::get( $id ) ) {
			return self::not_found();
		}
		NIVC_Repository::delete( $id );
		return rest_ensure_response( array( 'message' => 'רשומת הלקוח נמחקה מהדשבורד. דף הנחיתה עצמו לא נמחק.' ) );
	}

	public static function renew_client( WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! NIVC_Repository::get( $id ) ) {
			return self::not_found();
		}
		$res = NIVC_Repository::renew( $id, (array) $r->get_json_params() );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( array( 'message' => 'השירות חודש לשנה נוספת.', 'client' => NIVC_Repository::get( $id ) ) );
	}

	public static function client_payments( WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! NIVC_Repository::get( $id ) ) {
			return self::not_found();
		}
		return rest_ensure_response( array( 'items' => NIVC_Repository::payments( $id ) ) );
	}

	public static function pages() {
		return rest_ensure_response( array( 'items' => NIVC_Repository::pages() ) );
	}

	public static function analytics_status() {
		return rest_ensure_response( NIVC_Tracker::status() );
	}

	public static function analytics_save( WP_REST_Request $r ) {
		$p = (array) $r->get_json_params();
		NIVC_Settings::set_tracking( ! empty( $p['enabled'] ) );
		NIVC_Tracker::flush_cache();
		return rest_ensure_response( array_merge( NIVC_Tracker::status(), array( 'message' => ! empty( $p['enabled'] ) ? 'המעקב הופעל.' : 'המעקב כובה.' ) ) );
	}

	private static function not_found() {
		return new WP_Error( 'nivc_not_found', 'הלקוח לא נמצא.', array( 'status' => 404 ) );
	}
}
