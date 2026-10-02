<?php
// Lead form handler (PHP mail) for Hostinger. Change $TO to the inbox that should receive leads.
$TO   = 'CHANGE-ME@yourdomain.co.il'; // <- המייל שיקבל את הלידים
$FROM = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost'); // חייב להיות מייל מהדומיין שלך

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }
if (!empty($_POST['bot-field'])) { echo '{"ok":true}'; exit; } // honeypot

function clean($v) { return trim(strip_tags(str_replace(["\r", "\n"], ' ', (string)$v))); }
$name  = clean($_POST['name']  ?? '');
$phone = clean($_POST['phone'] ?? '');
$email = clean($_POST['email'] ?? '');

if (mb_strlen($name) < 2 || !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422); echo '{"ok":false}'; exit;
}

$subject = '=?UTF-8?B?' . base64_encode('הרשמה חדשה לסדנת עוצמה ואמונה - ' . $name) . '?=';
$body = "שם: $name\nטלפון: $phone\nמייל: $email\nIP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
$headers = "From: Power & Faith <$FROM>\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8\r\n";

$ok = mail($TO, $subject, $body, $headers);
http_response_code($ok ? 200 : 500);
echo $ok ? '{"ok":true}' : '{"ok":false}';
