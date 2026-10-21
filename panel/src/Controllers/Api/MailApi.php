<?php
declare(strict_types=1);

namespace Nivc\Controllers\Api;

use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Services\Mail\MailService;

/** The administrator's own mailbox (IMAP/SMTP). Every call is scoped to the signed-in admin's single mailbox. */
final class MailApi extends Base
{
    public static function account(Request $r, array $p): Response
    {
        $a = self::admin();
        return self::ok(['account' => MailService::present(MailService::row($a)), 'presets' => MailService::PRESETS]);
    }

    public static function saveAccount(Request $r, array $p): Response
    {
        $a = self::admin();
        return self::ok(['account' => MailService::save($a, $r->body()), 'message' => t('mail.saved')]);
    }

    public static function removeAccount(Request $r, array $p): Response
    {
        $a = self::admin();
        MailService::remove($a);
        return self::ok(['message' => t('mail.disconnected')]);
    }

    public static function test(Request $r, array $p): Response
    {
        $a = self::admin();
        return self::ok(['result' => MailService::test($a)]);
    }

    public static function folders(Request $r, array $p): Response
    {
        return self::ok(['items' => MailService::folders(self::admin())]);
    }

    public static function messages(Request $r, array $p): Response
    {
        return self::ok(MailService::messages(self::admin(), $r->query));
    }

    public static function message(Request $r, array $p): Response
    {
        $a = self::admin();
        return self::ok(MailService::message($a, (string) ($r->query['folder'] ?? 'INBOX'), (int) ($r->query['uid'] ?? 0)));
    }

    public static function attachment(Request $r, array $p): Response
    {
        $a = self::admin();
        $f = MailService::attachment($a, (string) ($r->query['folder'] ?? 'INBOX'), (int) ($r->query['uid'] ?? 0), (string) ($r->query['part'] ?? ''));
        $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $f['name']) ?: 'attachment';
        // Never rendered by the browser: forced download, no sniffing.
        return new Response(200, $f['data'], [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private',
            'Content-Disposition' => "attachment; filename=\"" . addcslashes($name, '"\\') . "\"; filename*=UTF-8''" . rawurlencode($name),
        ]);
    }

    public static function flag(Request $r, array $p): Response
    {
        $a = self::admin();
        $b = $r->body();
        MailService::setSeen($a, (string) ($b['folder'] ?? 'INBOX'), array_map('intval', (array) ($b['uids'] ?? [])), !empty($b['seen']));
        return self::ok();
    }

    public static function delete(Request $r, array $p): Response
    {
        $a = self::admin();
        $b = $r->body();
        MailService::delete($a, (string) ($b['folder'] ?? 'INBOX'), (int) ($b['uid'] ?? 0));
        return self::ok(['message' => t('mail.deleted')]);
    }

    public static function send(Request $r, array $p): Response
    {
        $a = self::admin();
        return self::ok(['result' => MailService::send($a, $r->body()), 'message' => t('mail.sent')]);
    }
}
