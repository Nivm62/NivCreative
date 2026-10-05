<?php
/**
 * Plugin Name:       NivCreative Connector
 * Description:       Sends Elementor form submissions and landing-page views from this WordPress site to the central NivCreative panel.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            NivCreative
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'NIVC_CONN_VERSION', '1.0.0' );

/**
 * Forwards leads (server-to-server, Bearer token) and loads the cookie-less view tracker.
 * The secret token never reaches the browser; only the public site key is printed in the tracker tag.
 */
final class NivCreative_Connector {

	const OPT   = 'nivc_conn_settings';
	const QUEUE = 'nivc_conn_queue';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'update_option_' . self::OPT, array( __CLASS__, 'after_save' ), 10, 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracker' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'tracker_tag' ), 10, 3 );
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'on_elementor_record' ), 10, 2 );
		add_action( 'nivc_conn_heartbeat', array( __CLASS__, 'heartbeat' ) );
		add_action( 'nivc_conn_flush_queue', array( __CLASS__, 'flush_queue' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		if ( ! wp_next_scheduled( 'nivc_conn_heartbeat' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'nivc_conn_heartbeat' );
		}
		if ( ! wp_next_scheduled( 'nivc_conn_flush_queue' ) ) {
			wp_schedule_event( time() + 120, 'nivc_five_minutes', 'nivc_conn_flush_queue' );
		}
	}

	public static function schedules( $s ) {
		$s['nivc_five_minutes'] = array( 'interval' => 300, 'display' => 'Every 5 minutes' );
		return $s;
	}

	public static function settings() {
		return wp_parse_args( get_option( self::OPT, array() ), array(
			'panel_url' => '', 'site_key' => '', 'token' => '', 'track_views' => 1, 'send_forms' => 1,
			'tracker_url' => '', 'track_endpoint' => '', // discovered from the panel via /api/v1/ping
		) );
	}

	private static function configured() {
		$s = self::settings();
		return '' !== $s['panel_url'] && '' !== $s['site_key'] && '' !== $s['token'];
	}

	/* ------------------------------------------------------------ settings */

	public static function menu() {
		add_options_page( 'NivCreative', 'NivCreative', 'manage_options', 'nivcreative-connector', array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting( 'nivc_conn', self::OPT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$old = self::settings();
		$url = esc_url_raw( rtrim( trim( (string) ( $in['panel_url'] ?? '' ) ), '/' ), array( 'https', 'http' ) );
		$token = trim( (string) ( $in['token'] ?? '' ) );
		return array(
			'tracker_url' => $old['tracker_url'], 'track_endpoint' => $old['track_endpoint'],
			'panel_url'   => $url,
			'site_key'    => preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $in['site_key'] ?? '' ) ),
			'token'       => '' === $token ? $old['token'] : preg_replace( '/[^A-Za-z0-9_\-]/', '', $token ), // blank keeps the saved token
			'track_views' => empty( $in['track_views'] ) ? 0 : 1,
			'send_forms'  => empty( $in['send_forms'] ) ? 0 : 1,
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::settings();
		$msg = '';
		if ( isset( $_POST['nivc_test'] ) && check_admin_referer( 'nivc_test' ) ) {
			$r = self::ping();
			$msg = is_wp_error( $r ) ? '<div class="notice notice-error"><p>' . esc_html( $r->get_error_message() ) . '</p></div>'
				: '<div class="notice notice-success"><p>Connected to the NivCreative panel ✔ (site: ' . esc_html( (string) ( $r['site'] ?? '' ) ) . ')</p></div>';
		}
		echo '<div class="wrap"><h1>NivCreative Connector</h1>' . $msg . '<form method="post" action="options.php">'; // phpcs:ignore WordPress.Security.EscapeOutput
		settings_fields( 'nivc_conn' );
		echo '<table class="form-table">';
		echo '<tr><th>Panel URL</th><td><input class="regular-text" type="url" name="' . esc_attr( self::OPT ) . '[panel_url]" value="' . esc_attr( $s['panel_url'] ) . '" placeholder="https://nivcreative.com/panel"></td></tr>';
		echo '<tr><th>Site key</th><td><input class="regular-text" name="' . esc_attr( self::OPT ) . '[site_key]" value="' . esc_attr( $s['site_key'] ) . '" placeholder="ws_xxxxxxxxxxxxxxxxxxxxx"></td></tr>';
		echo '<tr><th>API token</th><td><input class="regular-text" type="password" autocomplete="new-password" name="' . esc_attr( self::OPT ) . '[token]" value="" placeholder="' . ( $s['token'] ? '•••••••• (saved)' : 'nvc_…' ) . '"><p class="description">Shown once in the panel when the website is created. Leave blank to keep the saved token.</p></td></tr>';
		echo '<tr><th>Options</th><td><label><input type="checkbox" name="' . esc_attr( self::OPT ) . '[send_forms]" value="1" ' . checked( 1, $s['send_forms'], false ) . '> Send Elementor form submissions</label><br><label><input type="checkbox" name="' . esc_attr( self::OPT ) . '[track_views]" value="1" ' . checked( 1, $s['track_views'], false ) . '> Track landing-page views</label></td></tr>';
		echo '</table>';
		submit_button();
		echo '</form><form method="post">';
		wp_nonce_field( 'nivc_test' );
		echo '<p><button class="button" name="nivc_test" value="1">Test connection</button></p></form></div>';
	}

	/* ------------------------------------------------------------- tracker */

	public static function enqueue_tracker() {
		$s = self::settings();
		if ( ! self::configured() || ! $s['track_views'] || is_admin() || current_user_can( 'manage_options' ) ) {
			return;
		}
		$src = $s['tracker_url'] ? $s['tracker_url'] : $s['panel_url'] . '/assets/js/tracker.js';
		wp_enqueue_script( 'nivc-tracker', $src, array(), NIVC_CONN_VERSION, array( 'in_footer' => true, 'strategy' => 'async' ) );
	}

	public static function tracker_tag( $tag, $handle, $src ) {
		if ( 'nivc-tracker' !== $handle ) {
			return $tag;
		}
		$s = self::settings();
		$ep = $s['track_endpoint'] ? ' data-endpoint="' . esc_url( $s['track_endpoint'] ) . '"' : '';
		return '<script async src="' . esc_url( $src ) . '" data-site="' . esc_attr( $s['site_key'] ) . '"' . $ep . '></script>' . "\n";
	}

	/* ------------------------------------------------------------ elementor */

	/** @param object $record Elementor Pro Form_Record */
	public static function on_elementor_record( $record, $handler = null ) {
		$s = self::settings();
		if ( ! self::configured() || ! $s['send_forms'] ) {
			return;
		}
		$fields = array();
		foreach ( (array) $record->get( 'fields' ) as $id => $f ) {
			$fields[ $id ] = array( 'type' => $f['type'] ?? '', 'value' => is_scalar( $f['value'] ?? '' ) ? (string) $f['value'] : '' );
		}
		$settings = (array) $record->get( 'form_settings' );
		$meta     = (array) $record->get( 'meta' );
		$page_url = '';
		if ( ! empty( $meta['page_url']['value'] ) ) {
			$page_url = (string) $meta['page_url']['value'];
		} elseif ( ! empty( $_POST['referrer'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$page_url = esc_url_raw( wp_unslash( $_POST['referrer'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		self::send_lead( self::map_fields( $fields ), array(
			'form_name' => (string) ( $settings['form_name'] ?? '' ), 'landing_url' => $page_url,
		) );
	}

	/** Maps arbitrary Elementor field ids to name / phone / email / message. */
	public static function map_fields( array $fields ) {
		$out = array( 'name' => '', 'phone' => '', 'email' => '', 'message' => '' );
		$extra = array();
		foreach ( $fields as $id => $f ) {
			$id = strtolower( (string) $id );
			$type = (string) ( $f['type'] ?? '' );
			$val = trim( (string) ( $f['value'] ?? '' ) );
			if ( '' === $val ) {
				continue;
			}
			if ( 'email' === $type || false !== strpos( $id, 'email' ) || false !== strpos( $id, 'mail' ) ) {
				$out['email'] = $out['email'] ?: $val;
			} elseif ( 'tel' === $type || preg_match( '/phone|tel|mobile|cell|נייד|טלפון/u', $id ) ) {
				$out['phone'] = $out['phone'] ?: $val;
			} elseif ( 'textarea' === $type || preg_match( '/message|comment|msg|note|הודעה/u', $id ) ) {
				$out['message'] = $out['message'] ?: $val;
			} elseif ( preg_match( '/name|שם/u', $id ) ) {
				$out['name'] = trim( $out['name'] . ' ' . $val );
			} else {
				$extra[] = $id . ': ' . $val;
			}
		}
		if ( $extra ) {
			$out['message'] = trim( $out['message'] . "\n" . implode( "\n", $extra ) );
		}
		return $out;
	}

	/**
	 * Public helper: send a lead to the panel from any form plugin.
	 *   do_action( 'nivcreative_send_lead', array( 'name' => ..., 'phone' => ..., 'email' => ..., 'message' => ... ), array( 'landing_url' => ... ) );
	 */
	public static function send_lead( array $lead, array $meta = array() ) {
		$attr = array();
		if ( ! empty( $_COOKIE['nc_attr'] ) ) {
			$d = json_decode( wp_unslash( $_COOKIE['nc_attr'] ), true ); // phpcs:ignore WordPress.Security
			$attr = is_array( $d ) ? $d : array();
		}
		$payload = array_merge( $lead, array(
			'external_id' => wp_generate_uuid4(),
			'landing_url' => (string) ( $meta['landing_url'] ?? '' ),
			'form_name'   => (string) ( $meta['form_name'] ?? '' ),
		) );
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer' ) as $k ) {
			if ( ! empty( $attr[ $k ] ) ) {
				$payload[ $k ] = sanitize_text_field( (string) $attr[ $k ] );
			}
		}
		$payload['device'] = wp_is_mobile() ? 'mobile' : 'desktop';
		$res = self::post( '/api/v1/leads', $payload );
		if ( is_wp_error( $res ) ) {
			self::enqueue( $payload ); // never lose a lead: retry from cron
		}
		return $res;
	}

	/* -------------------------------------------------------------- http */

	private static function request( $method, $path, $body = null ) {
		$s = self::settings();
		$args = array(
			'method'  => $method, 'timeout' => 6, 'redirection' => 0,
			'headers' => array( 'X-Nivc-Site' => $s['site_key'], 'Authorization' => 'Bearer ' . $s['token'], 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$r = wp_remote_request( $s['panel_url'] . $path, $args );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$json = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( $code >= 200 && $code < 300 ) {
			return is_array( $json ) ? $json : array();
		}
		// 4xx (bad data / bad credentials) won't succeed on retry; 5xx/429 will.
		return new WP_Error( ( $code >= 500 || 429 === $code ) ? 'nivc_retry' : 'nivc_rejected', 'Panel responded ' . $code . ( isset( $json['error']['message'] ) ? ': ' . $json['error']['message'] : '' ) );
	}

	private static function post( $path, $body ) {
		return self::request( 'POST', $path, $body );
	}

	public static function ping() {
		if ( ! self::configured() ) {
			return new WP_Error( 'nivc_cfg', 'Fill in the panel URL, site key and token first.' );
		}
		$r = self::request( 'GET', '/api/v1/ping?connector=' . rawurlencode( NIVC_CONN_VERSION ) . '&wp=' . rawurlencode( get_bloginfo( 'version' ) ) );
		if ( ! is_wp_error( $r ) && ! empty( $r['tracker_url'] ) ) {
			// The panel tells us where its tracker script and beacon endpoint live (differs between hosting layouts).
			$s = self::settings();
			if ( $s['tracker_url'] !== $r['tracker_url'] || $s['track_endpoint'] !== ( $r['track_endpoint'] ?? '' ) ) {
				$s['tracker_url']    = esc_url_raw( $r['tracker_url'] );
				$s['track_endpoint'] = esc_url_raw( (string) ( $r['track_endpoint'] ?? '' ) );
				self::$saving = true;
				update_option( self::OPT, $s );
				self::$saving = false;
			}
		}
		return $r;
	}

	private static $saving = false;

	public static function after_save() {
		if ( ! self::$saving ) {
			self::ping(); // refresh discovered URLs + show "Connected" in the panel right away
		}
	}

	public static function heartbeat() {
		if ( self::configured() ) {
			self::ping();
		}
	}

	private static function enqueue( array $payload ) {
		$q = get_option( self::QUEUE, array() );
		$q = is_array( $q ) ? $q : array();
		if ( count( $q ) < 200 ) {
			$q[] = array( 'p' => $payload, 't' => time() );
			update_option( self::QUEUE, $q, false );
		}
	}

	public static function flush_queue() {
		$q = get_option( self::QUEUE, array() );
		if ( ! is_array( $q ) || ! $q || ! self::configured() ) {
			return;
		}
		$left = array();
		foreach ( $q as $item ) {
			if ( time() - (int) $item['t'] > 7 * DAY_IN_SECONDS ) {
				continue;
			}
			$r = self::post( '/api/v1/leads', $item['p'] ); // same external_id => idempotent
			if ( is_wp_error( $r ) && 'nivc_retry' === $r->get_error_code() ) {
				$left[] = $item;
			}
		}
		update_option( self::QUEUE, $left, false );
	}
}

add_action( 'nivcreative_send_lead', array( 'NivCreative_Connector', 'send_lead' ), 10, 2 );
NivCreative_Connector::init();
