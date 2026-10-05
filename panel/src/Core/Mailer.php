<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Plain-text mail. Uses wp_mail() when running inside WordPress (SMTP plugins, better deliverability), otherwise mail(). */
final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $from = (string) Config::get('mail_from', 'no-reply@localhost');
        if (function_exists('wp_mail')) {
            return (bool) wp_mail($to, $subject, $body, ['From: NivCreative <' . $from . '>', 'Content-Type: text/plain; charset=UTF-8']);
        }
        $headers = 'From: NivCreative <' . $from . ">\r\nContent-Type: text/plain; charset=UTF-8";
        return (bool) @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }
}
