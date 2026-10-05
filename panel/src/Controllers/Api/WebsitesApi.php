<?php
declare(strict_types=1);

namespace Nivc\Controllers\Api;

use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Services\WebsiteService;

/** Connected websites — administrators only. Secrets are never returned except the one-time token on creation/rotation. */
final class WebsitesApi extends Base
{
    public static function list(Request $r, array $p): Response
    {
        return self::ok(['items' => WebsiteService::list(self::admin(), $r->query)]);
    }

    public static function create(Request $r, array $p): Response
    {
        $res = WebsiteService::save(self::admin(), null, $r->body());
        return self::ok(['result' => $res, 'message' => t('website.created')], 201);
    }

    public static function update(Request $r, array $p): Response
    {
        $res = WebsiteService::save(self::admin(), self::id($p), $r->body());
        return self::ok(['result' => $res, 'message' => t('website.updated')]);
    }

    public static function delete(Request $r, array $p): Response
    {
        self::admin();
        WebsiteService::delete(self::id($p));
        return self::ok(['message' => t('website.deleted')]);
    }

    public static function test(Request $r, array $p): Response
    {
        return self::ok(['result' => WebsiteService::testConnection(self::admin(), self::id($p))]);
    }

    public static function rotate(Request $r, array $p): Response
    {
        return self::ok(['result' => WebsiteService::rotateToken(self::admin(), self::id($p)), 'message' => t('website.token_rotated')]);
    }
}
