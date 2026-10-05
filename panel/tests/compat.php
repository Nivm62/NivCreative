<?php
declare(strict_types=1);

// Run WITHOUT mbstring/ctype/curl:   php -n tests/compat.php
// Verifies the polyfills and the OpenSSL encryption fallback (used on hosts without the sodium extension).
define('NIVC_PANEL_VERSION', 'test');
define('NIVC_NO_SODIUM', true);
require dirname(__DIR__) . '/src/autoload.php';
use Nivc\Core\{Config, Crypto};

$pass = 0; $fail = 0;
function eq(mixed $a, mixed $b, string $l): void { global $pass, $fail; if ($a === $b) { $pass++; } else { $fail++; echo "FAIL $l: " . json_encode($a, JSON_UNESCAPED_UNICODE) . ' != ' . json_encode($b, JSON_UNESCAPED_UNICODE) . "\n"; } }

eq(mb_strlen('שלום עולם'), 9, 'mb_strlen hebrew'); eq(mb_strlen('abc'), 3, 'mb_strlen ascii');
eq(mb_substr('שלום עולם', 0, 4), 'שלום', 'mb_substr'); eq(mb_substr('שלום עולם', 5), 'עולם', 'mb_substr tail'); eq(mb_substr('abcdef', 2, 2), 'cd', 'mb_substr ascii');
eq(mb_strtolower('ABC שלום'), 'abc שלום', 'mb_strtolower'); eq(mb_strtoupper('abc'), 'ABC', 'mb_strtoupper');
eq(ctype_digit('12345'), true, 'ctype_digit yes'); eq(ctype_digit('12a'), false, 'ctype_digit no'); eq(ctype_digit(''), false, 'ctype_digit empty'); eq(ctype_digit(12), false, 'ctype_digit int');

Config::set(['app_key' => base64_encode(random_bytes(32))]);
$blob = Crypto::encrypt('abcd efgh ijkl mnop');
eq(str_starts_with($blob, 'o1:'), true, 'openssl blob prefix'); eq(str_contains($blob, 'abcd'), false, 'ciphertext hides plaintext');
eq(Crypto::decrypt($blob), 'abcd efgh ijkl mnop', 'openssl roundtrip');
$tampered = substr($blob, 0, -6) . 'AAAAAA';
eq(Crypto::decrypt($tampered), null, 'tampered blob rejected');
Config::set(['app_key' => base64_encode(random_bytes(32))]);
eq(Crypto::decrypt($blob), null, 'wrong key rejected');
echo 'compat: ' . (function_exists('mb_strlen') ? 'ok' : 'missing') . "\n";
echo "PASS=$pass FAIL=$fail\n";
exit($fail ? 1 : 0);
