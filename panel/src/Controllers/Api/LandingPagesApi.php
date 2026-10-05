<?php
declare(strict_types=1);

namespace Nivc\Controllers\Api;

use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Services\LandingPageService;

final class LandingPagesApi extends Base
{
    /** Both roles; clients see only their own pages (scoped in the service). */
    public static function list(Request $r, array $p): Response
    {
        return self::ok(LandingPageService::list(self::actor(), $r->query));
    }

    public static function create(Request $r, array $p): Response
    {
        self::admin();
        return self::ok(['result' => LandingPageService::save(null, $r->body()), 'message' => t('page.created')], 201);
    }

    public static function update(Request $r, array $p): Response
    {
        self::admin();
        return self::ok(['result' => LandingPageService::save(self::id($p), $r->body()), 'message' => t('page.updated')]);
    }

    public static function setStatus(Request $r, array $p): Response
    {
        self::admin();
        LandingPageService::setStatus(self::id($p), (string) $r->input('status', ''));
        return self::ok(['message' => t('page.updated')]);
    }

    public static function duplicate(Request $r, array $p): Response
    {
        self::admin();
        return self::ok(['id' => LandingPageService::duplicate(self::id($p)), 'message' => t('page.duplicated')], 201);
    }

    public static function delete(Request $r, array $p): Response
    {
        self::admin();
        LandingPageService::delete(self::id($p));
        return self::ok(['message' => t('page.deleted')]);
    }
}
