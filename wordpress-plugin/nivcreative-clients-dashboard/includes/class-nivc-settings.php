<?php
defined( 'ABSPATH' ) || exit;

/** Plugin settings (analytics tracking switch). Stored in one non-autoloaded option. */
final class NIVC_Settings {

	const OPTION = 'nivc_settings';

	public static function get(): array {
		$s = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'tracking_enabled' => false,
				'tracking_since'   => '', // site-timezone datetime when tracking was first enabled
			)
		);
	}

	public static function tracking_enabled(): bool {
		return (bool) self::get()['tracking_enabled'];
	}

	public static function set_tracking( bool $enabled ): array {
		$s                     = self::get();
		$s['tracking_enabled'] = $enabled;
		if ( $enabled && '' === $s['tracking_since'] ) {
			$s['tracking_since'] = NIVC_Helpers::now_mysql();
		}
		update_option( self::OPTION, $s, false );
		return $s;
	}
}
