<?php
defined( 'ABSPATH' ) || exit;

/** [nivcreative_clients_dashboard] – renders only for administrators; keeps the page out of caches. */
final class NIVC_Shortcode {

	private static $rendered = false;

	public static function init(): void {
		add_shortcode( NIVC_SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'guard_page' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		if ( wp_script_is( 'nivc-dashboard', 'registered' ) ) {
			return;
		}
		wp_register_style( 'nivc-fonts', 'https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		wp_register_style( 'nivc-dashboard', NIVC_URL . 'assets/css/dashboard.css', array( 'nivc-fonts' ), NIVC_VERSION );
		wp_register_script( 'nivc-dashboard', NIVC_URL . 'assets/js/dashboard.js', array(), NIVC_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/** Does this post contain the shortcode (classic content or Elementor data)? */
	private static function post_has_dashboard( $post ): bool {
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		if ( has_shortcode( (string) $post->post_content, NIVC_SHORTCODE ) ) {
			return true;
		}
		$el = get_post_meta( $post->ID, '_elementor_data', true );
		return is_string( $el ) && false !== strpos( $el, NIVC_SHORTCODE );
	}

	private static function no_cache(): void {
		foreach ( array( 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB', 'DONOTMINIFY', 'DONOTCDN' ) as $c ) {
			if ( ! defined( $c ) ) {
				define( $c, true );
			}
		}
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
		do_action( 'litespeed_control_set_nocache', 'nivcreative private dashboard' );
	}

	/** Runs before any output: redirects visitors to login, blocks non-admins, disables caching. */
	public static function guard_page(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! self::post_has_dashboard( $post ) ) {
			return;
		}
		self::no_cache();
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( get_permalink( $post ) ) );
			exit;
		}
		if ( ! current_user_can( NIVC_CAP ) ) {
			status_header( 403 );
		}
	}

	private static function logo_url(): string {
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $id ) {
			$id = (int) get_option( 'site_logo' );
		}
		$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		return $url ? $url : '';
	}

	public static function render( $atts = array() ): string {
		self::no_cache();

		if ( ! is_user_logged_in() ) {
			return '<div class="nivc-app nivc-denied" dir="rtl" lang="he"><p>לוח הניהול פרטי. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">התחברות</a></p></div>' . self::inline_denied_css();
		}
		if ( ! current_user_can( NIVC_CAP ) ) {
			return '<div class="nivc-app nivc-denied" dir="rtl" lang="he" role="alert"><h2>אין גישה</h2><p>אין לך הרשאה לצפות בלוח הניהול. הגישה מוגבלת למנהלי האתר בלבד.</p></div>' . self::inline_denied_css();
		}
		if ( self::$rendered ) {
			return '';
		}
		self::$rendered = true;

		// Block themes render content before wp_enqueue_scripts fires, so make sure the handles exist.
		self::register_assets();
		wp_enqueue_style( 'nivc-dashboard' );
		wp_enqueue_script( 'nivc-dashboard' );
		$config = array(
			'api'        => esc_url_raw( rest_url( NIVC_REST_NS . '/' ) ),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
			'writeNonce' => wp_create_nonce( 'nivc_write' ),
			'logo'       => esc_url_raw( self::logo_url() ),
			'siteName'   => 'NivCreative',
		);
		wp_add_inline_script( 'nivc-dashboard', 'window.NIVC_CONFIG=' . wp_json_encode( $config ) . ';', 'before' );

		return '<div class="nivc-app" id="nivc-root" dir="rtl" lang="he"><div class="nivc-boot" role="status">טוען את לוח הניהול…</div><noscript>נדרש JavaScript כדי להשתמש בלוח הניהול.</noscript></div>';
	}

	private static function inline_denied_css(): string {
		return '<style>.nivc-denied{direction:rtl;max-width:520px;margin:40px auto;padding:24px;border:1px solid #e5e7eb;border-radius:16px;font-family:Heebo,Arial,sans-serif;text-align:center}</style>';
	}
}
