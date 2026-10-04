<?php
// Run: php tests/test-helpers.php  (pure-logic tests, no WordPress needed)
define( 'ABSPATH', __DIR__ );
function wp_timezone() { return new DateTimeZone( 'Asia/Jerusalem' ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
require __DIR__ . '/../includes/class-nivc-helpers.php';

$fail = 0;
function eq( $a, $b, $label ) { global $fail; if ( $a !== $b ) { $fail++; echo "FAIL $label: got " . json_encode( $a ) . ' expected ' . json_encode( $b ) . "\n"; } else { echo "ok   $label\n"; } }

foreach ( array(
	'050-1234567' => '972501234567', '0501234567' => '972501234567', '+972 50 123 4567' => '972501234567',
	'00972501234567' => '972501234567', '+972-050-1234567' => '972501234567', '972501234567' => '972501234567',
	'03-1234567' => '97231234567', '072-3456789' => '972723456789', '052 123-4567' => '972521234567',
) as $in => $out ) { $n = NIVC_Helpers::normalize_phone( $in ); eq( $n ? $n['intl'] : null, $out, "phone $in" ); }
foreach ( array( '', 'abc', '12345', '050123', '0601234567', '+1 415 555 2671', '05012345678' ) as $bad ) eq( NIVC_Helpers::normalize_phone( $bad ), null, "bad phone '$bad'" );
eq( NIVC_Helpers::normalize_phone( '0501234567' )['display'], '050-1234567', 'display mobile' );
eq( NIVC_Helpers::normalize_phone( '031234567' )['display'], '03-1234567', 'display landline' );
eq( NIVC_Helpers::whatsapp_url( '972501234567' ), 'https://wa.me/972501234567', 'wa url has no text param' );

eq( NIVC_Helpers::add_years( '2025-03-15' ), '2026-03-15', 'add year' );
eq( NIVC_Helpers::add_years( '2024-02-29' ), '2025-02-28', 'leap day -> 28 Feb' );
eq( NIVC_Helpers::add_years( '2025-02-28', -1 ), '2024-02-28', 'minus year' );
eq( NIVC_Helpers::days_between( '2026-10-04', '2026-10-04' ), 0, 'days 0' );
eq( NIVC_Helpers::days_between( '2026-03-28', '2026-03-30' ), 2, 'days across DST' );
eq( NIVC_Helpers::days_between( '2026-10-25', '2026-10-24' ), -1, 'days negative' );

$t = '2026-10-04';
eq( NIVC_Helpers::service_state( '2026-11-04', $t )['status'], 'active', '31 days = active' );
eq( NIVC_Helpers::service_state( '2026-11-03', $t )['status'], 'soon', '30 days = soon' );
eq( NIVC_Helpers::service_state( '2026-10-04', $t )['status'], 'soon', 'expires today = soon' );
eq( NIVC_Helpers::service_state( '2026-10-03', $t ), array( 'status' => 'expired', 'days_left' => -1, 'overdue_days' => 1 ), 'yesterday = expired 1 day' );
eq( NIVC_Helpers::progress_percent( '2027-10-04', $t ), 0, 'progress start' );
eq( NIVC_Helpers::progress_percent( '2026-10-04', $t ), 100, 'progress end' );
eq( NIVC_Helpers::progress_percent( '2026-04-04', $t ), 100, 'progress overdue clamps' );

eq( NIVC_Helpers::path_key_from_url( 'https://www.nivcreative.com/Client-A/?x=1', 'https://nivcreative.com' ), '/client-a', 'path key' );
eq( NIVC_Helpers::path_key_from_url( 'https://nivcreative.com', 'https://nivcreative.com' ), '/', 'home path' );
eq( NIVC_Helpers::path_key_from_url( 'https://other.com/x', 'https://nivcreative.com' ), '', 'external -> empty' );
eq( NIVC_Helpers::normalize_path( '/%D7%A9%D7%9C%D7%95%D7%9D/' ), '/שלום', 'hebrew slug' );
eq( NIVC_Helpers::is_valid_date( '2026-02-30' ), false, 'invalid date' );
eq( NIVC_Helpers::is_valid_date( '2026-02-28' ), true, 'valid date' );
exit( $fail ? 1 : 0 );
