<?php
declare(strict_types=1);

namespace Nivc\Controllers\Web;

use Nivc\Core\Auth;
use Nivc\Core\Config;
use Nivc\Core\Csrf;
use Nivc\Core\I18n;
use Nivc\Core\NowTime;
use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Core\View;
use Nivc\Services\Domain;
use Nivc\Services\NotificationService;
use Nivc\Services\Settings;

/** Renders the application shell. Page data is loaded through the authorised JSON API. */
final class PageController
{
    private const ADMIN_NAV = [
        ['dashboard', '/admin', 'home'], ['clients', '/admin/clients', 'users'], ['users', '/admin/users', 'key'], ['leads', '/admin/leads', 'target'],
        ['landing_pages', '/admin/landing-pages', 'file'], ['websites', '/admin/websites', 'globe'], ['analytics', '/admin/analytics', 'chart'],
        ['billing', '/admin/billing', 'card'], ['notifications', '/admin/notifications', 'bell'], ['settings', '/admin/settings', 'settings'],
    ];
    private const CLIENT_NAV = [
        ['dashboard', '/dashboard', 'home'], ['leads', '/leads', 'target'], ['analytics', '/analytics', 'chart'],
        ['landing_page', '/landing-page', 'file'], ['account', '/account', 'settings'], ['support', '/support', 'help'],
    ];

    public static function render(Request $r, string $page, string $area, array $extra = []): Response
    {
        $a = Auth::actor();
        $nav = $area === 'admin' ? self::ADMIN_NAV : self::CLIENT_NAV;
        $s = Settings::all();
        $dict = I18n::all();
        $boot = [
            'base' => Request::basePath(), 'origin' => \Nivc\Core\Urls::origin($r), 'assets' => \Nivc\Core\Urls::assets($r), 'csrf' => Csrf::token(), 'locale' => I18n::locale(), 'dir' => I18n::dir(),
            'page' => $page, 'area' => $area, 'today' => NowTime::today(), 'extra' => $extra,
            'user' => ['name' => $a->name, 'email' => $a->email, 'role' => $a->role],
            'i18n' => $dict, 'statuses' => Domain::LEAD_STATUSES, 'sources' => Domain::SOURCES, 'plans' => Domain::PLANS, 'payment' => Domain::PAYMENT,
            'support' => ['email' => $s['support_email'] ?? Config::get('support_email'), 'phone' => $s['support_phone'] ?? '', 'whatsapp' => $s['support_whatsapp'] ?? ''],
            'unread' => NotificationService::unreadCount($a),
        ];
        return Response::html(View::render('layouts/app', [
            'boot' => $boot, 'nav' => $nav, 'page' => $page, 'area' => $area, 'user' => $a, 'unread' => $boot['unread'],
            'company' => $s['company_name'] ?? 'NivCreative',
        ]));
    }
}
