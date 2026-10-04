<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pure helpers: dates (site timezone), Israeli phone numbers, URL paths.
 * Dates are stored as DATE (Y-m-d) and compared as calendar days, so DST never skews the countdown.
 */
final class NIVC_Helpers {

	const SOON_DAYS = 30;

	/** Today's date (Y-m-d) in the WordPress site timezone. */
	public static function today(): string {
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
	}

	public static function now_mysql(): string {
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d H:i:s' );
	}

	public static function is_valid_date( $value ): bool {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) && (int) $m[1] >= 2000 && (int) $m[1] <= 2100;
	}

	/** Adds whole calendar years; 29 Feb falls back to 28 Feb in non-leap years. */
	public static function add_years( string $date, int $years = 1 ): string {
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $date ) );
		$y += $years;
		$last = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $y ) );
		return sprintf( '%04d-%02d-%02d', $y, $m, min( $d, $last ) );
	}

	/** Whole calendar days from $from to $to (negative when $to is earlier). */
	public static function days_between( string $from, string $to ): int {
		return (int) round( ( strtotime( $to . ' 00:00:00 UTC' ) - strtotime( $from . ' 00:00:00 UTC' ) ) / 86400 );
	}

	/**
	 * Status from days left. The expiry date itself is the last active day (0 days left = "soon").
	 *
	 * @return array{status:string,days_left:int,overdue_days:int}
	 */
	public static function service_state( string $expires_on, string $today ): array {
		$left = self::days_between( $today, $expires_on );
		if ( $left < 0 ) {
			$status = 'expired';
		} elseif ( $left <= self::SOON_DAYS ) {
			$status = 'soon';
		} else {
			$status = 'active';
		}
		return array(
			'status'       => $status,
			'days_left'    => $left,
			'overdue_days' => $left < 0 ? -$left : 0,
		);
	}

	/** Share (0-100) of the one-year service period that has elapsed. */
	public static function progress_percent( string $expires_on, string $today ): int {
		$start = self::add_years( $expires_on, -1 );
		$total = self::days_between( $start, $expires_on );
		if ( $total <= 0 ) {
			return 100;
		}
		$done = self::days_between( $start, $today );
		return (int) max( 0, min( 100, round( $done / $total * 100 ) ) );
	}

	/**
	 * Normalizes an Israeli phone number.
	 *
	 * @return array{display:string,intl:string}|null intl = digits only with country code (WhatsApp format).
	 */
	public static function normalize_phone( string $raw ): ?array {
		$digits = preg_replace( '/\D+/', '', $raw );
		if ( '' === $digits ) {
			return null;
		}
		if ( 0 === strpos( $digits, '00972' ) ) {
			$national = substr( $digits, 5 );
		} elseif ( 0 === strpos( $digits, '972' ) ) {
			$national = substr( $digits, 3 );
		} elseif ( 0 === strpos( $digits, '0' ) ) {
			$national = substr( $digits, 1 );
		} else {
			$national = $digits;
		}
		// A "0" typed after the country code, e.g. +972-050-1234567.
		if ( 0 === strpos( $national, '0' ) && $national !== $digits ) {
			$national = substr( $national, 1 );
		}
		// Mobile 5X + 7 digits, VoIP 7X + 7 digits, landline [23489] + 7 digits.
		if ( ! preg_match( '/^(5\d{8}|7\d{8}|[23489]\d{7})$/', $national ) ) {
			return null;
		}
		$local = '0' . $national;
		$cut   = strlen( $local ) === 10 ? 3 : 2;
		return array(
			'display' => substr( $local, 0, $cut ) . '-' . substr( $local, $cut ),
			'intl'    => '972' . $national,
		);
	}

	/** wa.me opens the chat only; no text parameter, so nothing is sent automatically. */
	public static function whatsapp_url( string $intl ): string {
		return '' === $intl ? '' : 'https://wa.me/' . $intl;
	}

	/** Canonical comparison key for a URL path (decoded, lowercase, no trailing slash). */
	public static function normalize_path( string $path ): string {
		$path = rawurldecode( (string) $path );
		$path = '/' . trim( $path, '/' );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $path, 'UTF-8' ) : strtolower( $path );
	}

	/** Returns the path key when $url belongs to this site, otherwise ''. */
	public static function path_key_from_url( string $url, string $home_url ): string {
		$u = wp_parse_url( $url );
		$h = wp_parse_url( $home_url );
		if ( empty( $u['host'] ) || empty( $h['host'] ) ) {
			return '';
		}
		$strip = static function ( $host ) {
			return preg_replace( '/^www\./', '', strtolower( $host ) );
		};
		if ( $strip( $u['host'] ) !== $strip( $h['host'] ) ) {
			return '';
		}
		return self::normalize_path( isset( $u['path'] ) ? $u['path'] : '/' );
	}
}
