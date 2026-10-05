<?php
declare(strict_types=1);

namespace Nivc\Controllers\Api;

use Nivc\Core\Auth;
use Nivc\Core\Db;
use Nivc\Core\HttpException;
use Nivc\Core\I18n;
use Nivc\Core\NowTime;
use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Core\Validator;
use Nivc\Services\AnalyticsService;
use Nivc\Services\BillingService;
use Nivc\Services\ClientService;
use Nivc\Services\DashboardService;
use Nivc\Services\Domain;
use Nivc\Services\MaintenanceService;
use Nivc\Services\NotificationService;
use Nivc\Services\Settings;

/** Dashboard, analytics, notifications, billing, account and settings endpoints. */
final class MiscApi extends Base
{
    public static function adminDashboard(Request $r, array $p): Response
    {
        $a = self::admin();
        MaintenanceService::runThrottled();
        return self::ok(DashboardService::admin($a, $r->query));
    }

    public static function clientDashboard(Request $r, array $p): Response
    {
        $a = self::actor();
        MaintenanceService::runThrottled();
        $d = DashboardService::client($a, $r->query);
        if ($d === []) {
            throw HttpException::notFound(t('error.not_found'));
        }
        return self::ok($d);
    }

    public static function analytics(Request $r, array $p): Response
    {
        return self::ok(AnalyticsService::overview(self::actor(), $r->query));
    }

    /** Dropdown data for filters. Clients only receive their own websites/pages/campaigns. */
    public static function filters(Request $r, array $p): Response
    {
        $a = self::actor();
        $cid = $a->scopeClientId($r->query['client_id'] ?? null);
        $w = $cid !== null ? 'AND client_id = ' . (int) $cid : '';
        $out = [
            'websites' => Db::all("SELECT id, name, client_id FROM websites WHERE 1=1 {$w} ORDER BY name LIMIT 500"),
            'landing_pages' => Db::all("SELECT id, name, website_id, client_id FROM landing_pages WHERE 1=1 {$w} ORDER BY name LIMIT 1000"),
            'campaigns' => array_column(Db::all("SELECT DISTINCT utm_campaign FROM leads WHERE utm_campaign <> '' {$w} ORDER BY utm_campaign LIMIT 200"), 'utm_campaign'),
        ];
        if ($a->isAdmin()) {
            $out['clients'] = ClientService::options();
        }
        return self::ok($out);
    }

    public static function notifications(Request $r, array $p): Response
    {
        $a = self::actor();
        return self::ok(['items' => NotificationService::list($a, 50), 'unread' => NotificationService::unreadCount($a)]);
    }

    public static function notificationsRead(Request $r, array $p): Response
    {
        $a = self::actor();
        $ids = $r->input('ids');
        NotificationService::markRead($a, is_array($ids) ? $ids : null);
        return self::ok(['unread' => NotificationService::unreadCount($a)]);
    }

    public static function billing(Request $r, array $p): Response
    {
        self::admin();
        return self::ok(BillingService::overview($r->query));
    }

    public static function billingUpdate(Request $r, array $p): Response
    {
        self::admin();
        BillingService::updateSubscription(self::id($p), $r->body());
        return self::ok(['message' => t('billing.updated')]);
    }

    /* ---------------------------------------------------------- account */

    public static function account(Request $r, array $p): Response
    {
        $a = self::actor();
        $out = ['user' => ['name' => $a->name, 'email' => $a->email, 'locale' => $a->locale, 'role' => $a->role]];
        if ($a->clientId !== null) {
            $c = Db::one('SELECT public_id, business_name, contact_name, email, phone, website_url, plan FROM clients WHERE id = ?', [$a->clientId]);
            $out['client'] = $c;
            $out['subscription'] = BillingService::current($a->clientId);
        }
        return self::ok($out);
    }

    public static function accountUpdate(Request $r, array $p): Response
    {
        $a = self::actor();
        $v = new Validator($r->body());
        $name = $v->str('name', true, 190);
        $email = $v->email('email');
        $newPw = (string) $r->input('new_password', '');
        $cur = (string) $r->input('current_password', '');
        if ($email !== '' && Db::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $a->userId])) {
            $v->fail('email', t('validation.email_taken'));
        }
        $row = Db::one('SELECT password_hash FROM users WHERE id = ?', [$a->userId]);
        if ($newPw !== '' || $email !== $a->email) {
            if (!password_verify($cur, (string) $row['password_hash'])) {
                $v->fail('current_password', t('account.wrong_password'));
            }
        }
        if ($newPw !== '' && !Auth::validPasswordRule($newPw)) {
            $v->fail('new_password', t('validation.password_rule'));
        }
        $v->check();
        $d = ['name' => $name, 'email' => $email, 'updated_at' => NowTime::mysql()];
        if ($newPw !== '') {
            $d['password_hash'] = Auth::hashPassword($newPw);
        }
        Db::update('users', $d, ['id' => $a->userId]);
        if ($a->clientId !== null) {
            Db::update('clients', ['contact_name' => $name, 'email' => $email], ['id' => $a->clientId]);
        }
        Auth::reset();
        return self::ok(['message' => t('account.saved')]);
    }

    public static function locale(Request $r, array $p): Response
    {
        $l = (string) $r->input('locale', '');
        if (!in_array($l, I18n::LOCALES, true)) {
            throw HttpException::invalid(['locale' => t('validation.invalid')]);
        }
        $a = Auth::actor();
        if ($a) {
            Db::update('users', ['locale' => $l], ['id' => $a->userId]);
        }
        Auth::setCookie('nivc_lang', $l, time() + 365 * 86400);
        return self::ok(['locale' => $l]);
    }

    /* --------------------------------------------------------- settings */

    public static function settings(Request $r, array $p): Response
    {
        self::admin();
        $s = Settings::all();
        return self::ok(['settings' => [
            'company_name' => $s['company_name'] ?? 'NivCreative',
            'support_email' => $s['support_email'] ?? \Nivc\Core\Config::get('support_email'),
            'support_phone' => $s['support_phone'] ?? '',
            'support_whatsapp' => $s['support_whatsapp'] ?? '',
        ], 'system' => [
            'api_leads' => \Nivc\Core\Urls::api($r, 'leads'), 'api_track' => \Nivc\Core\Urls::api($r, 'track'),
            'api_ping' => \Nivc\Core\Urls::api($r, 'ping'), 'tracker' => \Nivc\Core\Urls::assets($r) . '/js/tracker.js',
            'timezone' => \Nivc\Core\Config::get('timezone'), 'version' => NIVC_PANEL_VERSION,
            'last_maintenance' => (int) ($s['maint_last'] ?? 0) ?: null,
        ]]);
    }

    public static function settingsUpdate(Request $r, array $p): Response
    {
        self::admin();
        $v = new Validator($r->body());
        $company = $v->str('company_name', true, 80);
        $email = $v->email('support_email', false);
        $phone = $v->str('support_phone', false, 40);
        $wa = $v->str('support_whatsapp', false, 40);
        $v->check();
        Settings::set('company_name', $company);
        Settings::set('support_email', $email);
        Settings::set('support_phone', $phone);
        Settings::set('support_whatsapp', $wa);
        return self::ok(['message' => t('settings.saved')]);
    }
}
