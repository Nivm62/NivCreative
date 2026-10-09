<?php
/**
 * Plugin Name:       NivCreative Connector
 * Description:       Sends Elementor form submissions and landing-page views from this WordPress site to the central NivCreative panel. Supports several clients on one site (one route per landing page).
 * Version:           1.4.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            NivCreative
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'NIVC_CONN_VERSION', '1.4.0' );

/**
 * Forwards leads (server-to-server, Bearer token) and loads the cookie-less view tracker.
 *
 * ROUTES: each route maps a landing-page path (e.g. /client-c, or /promo/* for a whole folder) to ONE panel website
 * (site key + token). A form submitted on /client-c is sent with that route's credentials, so the lead lands only in that
 * client's panel account; pages without a route send nothing. The secret token never reaches the browser.
 */
final class NivCreative_Connector {

	const OPT   = 'nivc_conn_settings';
	const QUEUE = 'nivc_conn_queue';

	/** @var array|null route chosen for the current front-end request (for the tracker tag) */
	private static $current = null;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'update_option_' . self::OPT, array( __CLASS__, 'after_save' ), 10, 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracker' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'tracker_tag' ), 10, 3 );
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'on_elementor_record' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'capture_atomic_post' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'capture_script' ), 99 );
		add_action( 'wp_ajax_nopriv_nivc_capture', array( __CLASS__, 'ajax_capture' ) );
		add_action( 'wp_ajax_nivc_capture', array( __CLASS__, 'ajax_capture' ) );
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

	/* ------------------------------------------------------------ settings */

	public static function settings() {
		$s = wp_parse_args( get_option( self::OPT, array() ), array(
			'panel_url' => '', 'routes' => array(), 'track_views' => 1, 'send_forms' => 1, 'capture_forms' => 1,
			'tracker_url' => '', 'track_endpoint' => '', // discovered from the panel via /api/v1/ping
			'site_key' => '', 'token' => '',              // legacy single-site config = default route for pages without a specific route
		) );
		if ( ! is_array( $s['routes'] ) ) {
			$s['routes'] = array();
		}
		return $s;
	}

	/** Normalizes a URL path for comparison: lower-case, no query, no trailing slash ("/" stays "/"). */
	public static function norm_path( $path ) {
		$p = rawurldecode( (string) wp_parse_url( (string) $path, PHP_URL_PATH ) );
		$p = '/' . trim( $p, '/' );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $p, 'UTF-8' ) : strtolower( $p );
	}

	/** @return array|null route {path, site_key, token} for a page path; exact match first, then "folder/*" prefixes, then the legacy default. */
	public static function route_for( $path ) {
		$s = self::settings();
		$p = self::norm_path( $path );
		$best = null;
		foreach ( $s['routes'] as $r ) {
			if ( empty( $r['path'] ) || empty( $r['site_key'] ) || empty( $r['token'] ) ) {
				continue;
			}
			$rp = (string) $r['path'];
			if ( '*' === substr( $rp, -1 ) ) {
				$prefix = rtrim( self::norm_path( substr( $rp, 0, -1 ) ), '/' );
				if ( $p === $prefix || 0 === strpos( $p, $prefix . '/' ) ) {
					if ( null === $best || strlen( $prefix ) > $best[0] ) {
						$best = array( strlen( $prefix ), $r );
					}
				}
			} elseif ( self::norm_path( $rp ) === $p ) {
				return $r; // exact route always wins
			}
		}
		if ( $best ) {
			return $best[1];
		}
		if ( $s['site_key'] && $s['token'] ) {
			return array( 'path' => '*', 'site_key' => $s['site_key'], 'token' => $s['token'] );
		}
		return null;
	}

	private static function configured() {
		$s = self::settings();
		if ( '' === $s['panel_url'] ) {
			return false;
		}
		if ( $s['site_key'] && $s['token'] ) {
			return true;
		}
		foreach ( $s['routes'] as $r ) {
			if ( ! empty( $r['path'] ) && ! empty( $r['site_key'] ) && ! empty( $r['token'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function menu() {
		add_options_page( 'NivCreative', 'NivCreative', 'manage_options', 'nivcreative-connector', array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting( 'nivc_conn', self::OPT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$old = self::settings();
		$known = array(); // site_key => saved token (a blank token field keeps the saved one)
		foreach ( $old['routes'] as $r ) {
			if ( ! empty( $r['site_key'] ) ) {
				$known[ $r['site_key'] ] = (string) $r['token'];
			}
		}
		$routes = array();
		foreach ( (array) ( $in['routes'] ?? array() ) as $r ) {
			$path = trim( (string) ( $r['path'] ?? '' ) );
			$key  = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $r['site_key'] ?? '' ) );
			$tok  = preg_replace( '/[^A-Za-z0-9_\-]/', '', trim( (string) ( $r['token'] ?? '' ) ) );
			if ( '' === $path || '' === $key ) {
				continue; // empty row
			}
			if ( '*' !== $path ) {
				$path = '/' . ltrim( (string) wp_parse_url( $path, PHP_URL_PATH ) ?: $path, '/' ); // accept a pasted full URL
			}
			if ( '' === $tok && isset( $known[ $key ] ) ) {
				$tok = $known[ $key ];
			}
			if ( '' !== $tok ) {
				$routes[] = array( 'path' => $path, 'site_key' => $key, 'token' => $tok );
			}
		}
		$legacyTok = trim( (string) ( $in['token'] ?? '' ) );
		return array(
			'tracker_url' => $old['tracker_url'], 'track_endpoint' => $old['track_endpoint'],
			'panel_url'   => esc_url_raw( rtrim( trim( (string) ( $in['panel_url'] ?? '' ) ), '/' ), array( 'https', 'http' ) ),
			'routes'      => $routes,
			'site_key'    => preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $in['site_key'] ?? '' ) ),
			'token'       => '' === $legacyTok ? $old['token'] : preg_replace( '/[^A-Za-z0-9_\-]/', '', $legacyTok ),
			'track_views' => empty( $in['track_views'] ) ? 0 : 1,
			'send_forms'  => empty( $in['send_forms'] ) ? 0 : 1,
			'capture_forms' => empty( $in['capture_forms'] ) ? 0 : 1,
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = self::settings();
		$o   = esc_attr( self::OPT );
		$msg = '';
		if ( isset( $_POST['nivc_test'] ) && check_admin_referer( 'nivc_test' ) ) {
			$msg = '<div class="notice notice-info"><p><strong>Connection test</strong></p><ul style="list-style:disc;margin-left:20px">';
			$any = false;
			foreach ( self::all_routes() as $r ) {
				$any = true;
				$res = self::ping_route( $r );
				$msg .= '<li><code>' . esc_html( $r['path'] ) . '</code> → ' . ( is_wp_error( $res ) ? '<span style="color:#d63638">✖ ' . esc_html( $res->get_error_message() ) . '</span>' : '<span style="color:#00a32a">✔ ' . esc_html( (string) ( $res['site'] ?? 'connected' ) ) . '</span>' ) . '</li>';
			}
			$msg .= $any ? '' : '<li>Add a route first.</li>';
			$msg .= '</ul></div>';
		}
		echo '<div class="wrap"><h1>NivCreative Connector</h1>' . $msg . '<form method="post" action="options.php">'; // phpcs:ignore WordPress.Security.EscapeOutput
		settings_fields( 'nivc_conn' );
		echo '<table class="form-table"><tr><th>Panel URL</th><td><input class="regular-text" type="url" name="' . $o . '[panel_url]" value="' . esc_attr( $s['panel_url'] ) . '" placeholder="https://nivcreative.com/app"></td></tr>';
		echo '<tr><th>Options</th><td><label><input type="checkbox" name="' . $o . '[send_forms]" value="1" ' . checked( 1, $s['send_forms'], false ) . '> Send Elementor form submissions</label><br><label><input type="checkbox" name="' . $o . '[capture_forms]" value="1" ' . checked( 1, $s['capture_forms'], false ) . '> Capture any form on routed pages (works with Elementor 4 atomic forms, mobile + desktop)</label><br><label><input type="checkbox" name="' . $o . '[track_views]" value="1" ' . checked( 1, $s['track_views'], false ) . '> Track landing-page views</label></td></tr></table>';

		echo '<h2>Routes — one landing page per client</h2><p class="description" style="max-width:760px">Each row connects <strong>one landing page of this site</strong> to <strong>one client website</strong> in the panel (site key + token from <em>Websites → Keys &amp; installation</em>). '
			. 'Forms submitted on that page, and views of it, go only to that client. Use <code>/folder/*</code> to cover a folder. Pages without a matching row send nothing. Leave the token blank to keep the saved one.</p>';
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Page path</th><th>Site key</th><th>API token</th></tr></thead><tbody>';
		$rows = array_values( $s['routes'] );
		for ( $i = 0; $i < count( $rows ) + 3; $i++ ) {
			$r = $rows[ $i ] ?? array( 'path' => '', 'site_key' => '', 'token' => '' );
			echo '<tr><td><input class="regular-text" name="' . $o . '[routes][' . (int) $i . '][path]" value="' . esc_attr( $r['path'] ) . '" placeholder="/client-landing"></td>';
			echo '<td><input class="regular-text" name="' . $o . '[routes][' . (int) $i . '][site_key]" value="' . esc_attr( $r['site_key'] ) . '" placeholder="ws_xxxxxxxxxxxxxxxxxxxxx"></td>';
			echo '<td><input class="regular-text" type="password" autocomplete="new-password" name="' . $o . '[routes][' . (int) $i . '][token]" value="" placeholder="' . ( $r['token'] ? '•••••••• (saved)' : 'nvc_…' ) . '"></td></tr>';
		}
		echo '</tbody></table>';

		echo '<details style="margin-top:18px;max-width:760px"><summary><strong>Default (single-site) credentials</strong> — only if this whole site belongs to ONE client</summary><table class="form-table">';
		echo '<tr><th>Site key</th><td><input class="regular-text" name="' . $o . '[site_key]" value="' . esc_attr( $s['site_key'] ) . '"></td></tr>';
		echo '<tr><th>API token</th><td><input class="regular-text" type="password" autocomplete="new-password" name="' . $o . '[token]" value="" placeholder="' . ( $s['token'] ? '•••••••• (saved)' : 'nvc_…' ) . '"></td></tr></table></details>';
		submit_button();
		echo '</form><form method="post">';
		wp_nonce_field( 'nivc_test' );
		echo '<p><button class="button" name="nivc_test" value="1">Test all routes</button></p></form></div>';
	}

	/** @return array[] every configured route (including the legacy default) */
	private static function all_routes() {
		$s   = self::settings();
		$out = array();
		foreach ( $s['routes'] as $r ) {
			if ( ! empty( $r['path'] ) && ! empty( $r['site_key'] ) && ! empty( $r['token'] ) ) {
				$out[] = $r;
			}
		}
		if ( $s['site_key'] && $s['token'] ) {
			$out[] = array( 'path' => '* (default)', 'site_key' => $s['site_key'], 'token' => $s['token'] );
		}
		return $out;
	}

	/* ------------------------------------------------------------- tracker */

	public static function enqueue_tracker() {
		$s = self::settings();
		if ( ! self::configured() || ! $s['track_views'] || is_admin() || current_user_can( 'manage_options' ) ) {
			return;
		}
		$path  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security
		$route = self::route_for( $path );
		if ( ! $route ) {
			return; // this page belongs to nobody: no tracker
		}
		self::$current = $route;
		$src = $s['tracker_url'] ? $s['tracker_url'] : $s['panel_url'] . '/assets/js/tracker.js';
		wp_enqueue_script( 'nivc-tracker', $src, array(), NIVC_CONN_VERSION, array( 'in_footer' => true, 'strategy' => 'async' ) );
	}

	public static function tracker_tag( $tag, $handle, $src ) {
		if ( 'nivc-tracker' !== $handle || ! self::$current ) {
			return $tag;
		}
		$s  = self::settings();
		$ep = $s['track_endpoint'] ? ' data-endpoint="' . esc_url( $s['track_endpoint'] ) . '"' : '';
		return '<script async src="' . esc_url( $src ) . '" data-site="' . esc_attr( self::$current['site_key'] ) . '"' . $ep . '></script>' . "\n";
	}

	/* ------------------------------------------------- browser form capture */

	/** Tiny script on routed pages: reports a submitted form (any plugin) to this site's own admin-ajax; the token stays on the server. */
	public static function capture_script() {
		$s = self::settings();
		if ( is_admin() || ! self::configured() || ! $s['capture_forms'] ) {
			return;
		}
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security
		if ( ! self::route_for( $path ) ) {
			return;
		}
		$url = esc_url_raw( admin_url( 'admin-ajax.php' ) );
		?>
<script>(function(){var U=<?php echo wp_json_encode( $url ); ?>;
document.addEventListener('submit',function(e){try{var f=e.target;if(!f||f.tagName!=='FORM'||f.closest('#wpadminbar'))return;
var d={},n=0;f.querySelectorAll('input,textarea,select').forEach(function(i){var t=(i.type||'').toLowerCase();
if(!i.name&&!i.id||/^(password|hidden|file|submit|button|checkbox|radio)$/.test(t)&&!i.checked)return;
if(/^(password|file)$/.test(t)||/pass|card|cvv|nonce|token/i.test(i.name||''))return;
var k=i.name||i.id,v=(i.value||'').trim();if(v&&n<30){d[k]=v;n++;}});
if(!Object.keys(d).length)return;
var b=new URLSearchParams();b.set('action','nivc_capture');b.set('page',location.pathname);b.set('form',f.getAttribute('data-form-name')||f.getAttribute('aria-label')||f.id||'');b.set('device',matchMedia('(max-width:767px)').matches?'mobile':'desktop');b.set('fields',JSON.stringify(d));
fetch(U,{method:'POST',body:b,keepalive:true,credentials:'same-origin'});}catch(_){}},true);})();</script>
		<?php
	}

	/**
	 * Server-side capture of Elementor 4 "atomic" forms: they POST to admin-ajax (action elementor_pro_atomic_forms_send_form)
	 * with form_fields[n][name|type|value] and the page URL in `referrer`. Works even when browser scripts are delayed/blocked.
	 */
	public static function capture_atomic_post() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! wp_doing_ajax() || empty( $_POST['action'] ) || 'elementor_pro_atomic_forms_send_form' !== $_POST['action'] || empty( $_POST['form_fields'] ) || ! is_array( $_POST['form_fields'] ) ) {
			return;
		}
		if ( ! self::configured() || ! self::settings()['capture_forms'] ) {
			return;
		}
		$referrer = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '';
		if ( '' === $referrer || ! self::route_for( $referrer ) || self::flooded( 8 ) ) {
			return;
		}
		$fields = array();
		foreach ( wp_unslash( $_POST['form_fields'] ) as $f ) {
			if ( ! is_array( $f ) || ! isset( $f['value'] ) || ! is_scalar( $f['value'] ) ) {
				continue;
			}
			$key = sanitize_text_field( (string) ( $f['name'] ?? $f['id'] ?? '' ) );
			if ( '' !== $key ) {
				$fields[ $key ] = array( 'type' => sanitize_text_field( (string) ( $f['type'] ?? '' ) ), 'value' => sanitize_textarea_field( (string) $f['value'] ) );
			}
		}
		if ( self::honeypot_hit( $fields ) ) {
			return;
		}
		$lead = self::map_fields( $fields );
		if ( '' === $lead['phone'] && '' === $lead['email'] ) {
			return;
		}
		$meta = array( 'landing_url' => $referrer, 'form_name' => isset( $_POST['form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['form_name'] ) ) : '' );
		// Forward only submissions that Elementor itself accepted (valid nonce + validation): decided from its JSON answer.
		ob_start( static function ( $buffer ) use ( $lead, $meta ) {
			$j = json_decode( (string) $buffer, true );
			if ( ! is_array( $j ) || false !== ( $j['success'] ?? true ) ) {
				try {
					self::send_lead( $lead, $meta );
				} catch ( \Throwable $e ) { // never break the visitor's response
					unset( $e );
				}
			}
			return $buffer;
		} );
		// phpcs:enable
	}

	/** Fields a human never sees: a hidden "hp / honeypot / website / url / fax" input that bots fill in. */
	private static function honeypot_hit( array $fields ) {
		foreach ( $fields as $k => $f ) {
			if ( preg_match( '/^(hp|honeypot|nv-hp|website|url|fax)$/i', (string) $k ) && '' !== trim( (string) ( $f['value'] ?? '' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** True when this IP already sent $max forms in the last 10 minutes (counts the current one). */
	private static function flooded( $max ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) $_SERVER['REMOTE_ADDR'] ) : '0'; // phpcs:ignore WordPress.Security
		$rk = 'nivc_r_' . md5( $ip );
		$n  = (int) get_transient( $rk );
		set_transient( $rk, $n + 1, 10 * MINUTE_IN_SECONDS );
		return $n >= $max;
	}

	public static function ajax_capture() {
		$s = self::settings();
		if ( ! self::configured() || ! $s['capture_forms'] ) {
			wp_send_json( array( 'ok' => false ), 200 );
		}
		if ( self::flooded( 8 ) ) {
			wp_send_json( array( 'ok' => false ), 429 );
		}
		$page = isset( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$raw  = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : ''; // phpcs:ignore WordPress.Security
		$f    = strlen( $raw ) < 8000 ? json_decode( $raw, true ) : null;
		if ( ! is_array( $f ) || '' === $page || ! self::route_for( $page ) ) {
			wp_send_json( array( 'ok' => false ), 200 );
		}
		$fields = array();
		foreach ( $f as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$fields[ sanitize_text_field( (string) $k ) ] = array( 'type' => '', 'value' => sanitize_textarea_field( (string) $v ) );
			}
		}
		if ( self::honeypot_hit( $fields ) ) {
			wp_send_json( array( 'ok' => false ), 200 );
		}
		$lead = self::map_fields( $fields );
		if ( '' === $lead['phone'] && '' === $lead['email'] ) {
			wp_send_json( array( 'ok' => false ), 200 ); // not a contact form (search box, newsletter-less filters...)
		}
		$dev = ( isset( $_POST['device'] ) && 'mobile' === $_POST['device'] ) ? 'mobile' : 'desktop'; // phpcs:ignore WordPress.Security.NonceVerification
		$_SERVER['NIVC_DEVICE'] = $dev;
		self::send_lead( $lead, array(
			'landing_url' => home_url( $page ),
			'form_name'   => isset( $_POST['form'] ) ? sanitize_text_field( wp_unslash( $_POST['form'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		) );
		wp_send_json( array( 'ok' => true ) );
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
	 * Public helper: send a lead to the panel from any form plugin. The route is chosen from `landing_url`:
	 *   do_action( 'nivcreative_send_lead', array( 'name' => ..., 'phone' => ..., 'email' => ..., 'message' => ... ), array( 'landing_url' => ... ) );
	 * A lead whose page matches no route is NOT sent (it must never reach another client).
	 */
	public static function send_lead( array $lead, array $meta = array() ) {
		$landing = (string) ( $meta['landing_url'] ?? '' );
		$route   = self::route_for( $landing );
		if ( ! $route ) {
			do_action( 'nivcreative_lead_unrouted', $lead, $landing );
			return new WP_Error( 'nivc_no_route', 'No panel route matches this page.' );
		}
		$dk = 'nivc_d_' . md5( strtolower( ( $lead['phone'] ?? '' ) . '|' . ( $lead['email'] ?? '' ) . '|' . ( $lead['name'] ?? '' ) ) . '|' . $route['site_key'] );
		if ( get_transient( $dk ) ) {
			return array( 'duplicate' => true ); // the same submission arrived through both capture paths
		}
		set_transient( $dk, 1, 120 );
		$attr = array();
		if ( ! empty( $_COOKIE['nc_attr'] ) ) {
			$d = json_decode( wp_unslash( $_COOKIE['nc_attr'] ), true ); // phpcs:ignore WordPress.Security
			$attr = is_array( $d ) ? $d : array();
		}
		$payload = array_merge( $lead, array(
			'external_id' => wp_generate_uuid4(),
			'landing_url' => $landing,
			'form_name'   => (string) ( $meta['form_name'] ?? '' ),
		) );
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer' ) as $k ) {
			if ( ! empty( $attr[ $k ] ) ) {
				$payload[ $k ] = sanitize_text_field( (string) $attr[ $k ] );
			}
		}
		$payload['device'] = ! empty( $_SERVER['NIVC_DEVICE'] ) ? $_SERVER['NIVC_DEVICE'] : ( wp_is_mobile() ? 'mobile' : 'desktop' );
		$res = self::request( $route, 'POST', '/api/v1/leads', $payload );
		if ( is_wp_error( $res ) && 'nivc_retry' === $res->get_error_code() ) {
			self::enqueue( $payload, $route['site_key'] ); // never lose a lead: retry from cron
		}
		return $res;
	}

	/* -------------------------------------------------------------- http */

	private static function request( $route, $method, $path, $body = null ) {
		$s = self::settings();
		$args = array(
			'method'  => $method, 'timeout' => 6, 'redirection' => 0,
			'headers' => array( 'X-Nivc-Site' => $route['site_key'], 'Authorization' => 'Bearer ' . $route['token'], 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$r = wp_remote_request( $s['panel_url'] . $path, $args );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'nivc_retry', $r->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$json = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( $code >= 200 && $code < 300 ) {
			return is_array( $json ) ? $json : array();
		}
		// 4xx (bad data / bad credentials / page not registered) won't succeed on retry; 5xx/429 will.
		return new WP_Error( ( $code >= 500 || 429 === $code ) ? 'nivc_retry' : 'nivc_rejected', 'Panel responded ' . $code . ( isset( $json['error']['message'] ) ? ': ' . $json['error']['message'] : '' ) );
	}

	/** Heartbeat + discovery of the tracker URL for one route. */
	public static function ping_route( $route ) {
		$s = self::settings();
		if ( '' === $s['panel_url'] ) {
			return new WP_Error( 'nivc_cfg', 'Fill in the panel URL first.' );
		}
		$r = self::request( $route, 'GET', '/api/v1/ping?connector=' . rawurlencode( NIVC_CONN_VERSION ) . '&wp=' . rawurlencode( get_bloginfo( 'version' ) ) );
		if ( ! is_wp_error( $r ) && ! empty( $r['tracker_url'] ) ) {
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

	/** Pings the first configured route (kept for backwards compatibility). */
	public static function ping() {
		$routes = self::all_routes();
		return $routes ? self::ping_route( $routes[0] ) : new WP_Error( 'nivc_cfg', 'Add a route first.' );
	}

	private static $saving = false;

	public static function after_save() {
		if ( ! self::$saving ) {
			self::heartbeat(); // refresh discovered URLs + show "Connected" in the panel right away
		}
	}

	public static function heartbeat() {
		foreach ( self::all_routes() as $r ) {
			self::ping_route( $r );
		}
	}

	private static function enqueue( array $payload, $site_key ) {
		$q = get_option( self::QUEUE, array() );
		$q = is_array( $q ) ? $q : array();
		if ( count( $q ) < 200 ) {
			$q[] = array( 'p' => $payload, 't' => time(), 'k' => $site_key );
			update_option( self::QUEUE, $q, false );
		}
	}

	public static function flush_queue() {
		$q = get_option( self::QUEUE, array() );
		if ( ! is_array( $q ) || ! $q || ! self::configured() ) {
			return;
		}
		$by_key = array();
		foreach ( self::all_routes() as $r ) {
			$by_key[ $r['site_key'] ] = $r;
		}
		$left = array();
		foreach ( $q as $item ) {
			if ( time() - (int) $item['t'] > 7 * DAY_IN_SECONDS ) {
				continue;
			}
			$route = $by_key[ $item['k'] ?? '' ] ?? null;
			if ( ! $route ) {
				continue; // route was removed: drop (never send with another client's credentials)
			}
			$r = self::request( $route, 'POST', '/api/v1/leads', $item['p'] ); // same external_id => idempotent
			if ( is_wp_error( $r ) && 'nivc_retry' === $r->get_error_code() ) {
				$left[] = $item;
			}
		}
		update_option( self::QUEUE, $left, false );
	}
}

add_action( 'nivcreative_send_lead', array( 'NivCreative_Connector', 'send_lead' ), 10, 2 );
NivCreative_Connector::init();
