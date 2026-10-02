<?php
// Lead form handler for Hostinger (PHP mail). Edit the two values below.
$TO   = 'hello@nivcreative.com';        // <- המייל שיקבל את הלידים
$FROM = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'nivcreative.com'); // חייב להיות מייל מהדומיין שלך

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }
if (!empty($_POST['bot-field'])) { echo '{"ok":true}'; exit; } // honeypot

function clean($v) { return trim(strip_tags(str_replace(["\r", "\n"], ' ', (string)$v))); }
$name  = clean($_POST['name']  ?? '');
$phone = clean($_POST['phone'] ?? '');
$goal  = clean($_POST['goal']  ?? '');

if ($name === '' || $phone === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
  http_response_code(422); echo '{"ok":false}'; exit;
}

$subject = '=?UTF-8?B?' . base64_encode('ליד חדש מהאתר - ' . $name) . '?=';
$body = "שם: $name\nטלפון: $phone\nתחום / מטרה: $goal\nIP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
$headers = "From: NivCreative <$FROM>\r\nContent-Type: text/plain; charset=UTF-8\r\n";

$ok = mail($TO, $subject, $body, $headers);
http_response_code($ok ? 200 : 500);
echo $ok ? '{"ok":true}' : '{"ok":false}';
