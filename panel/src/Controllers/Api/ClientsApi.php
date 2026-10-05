<?php
declare(strict_types=1);

namespace Nivc\Controllers\Api;

use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Services\BillingService;
use Nivc\Services\ClientService;

/** Client management — administrators only. */
final class ClientsApi extends Base
{
    public static function list(Request $r, array $p): Response
    {
        self::admin();
        return self::ok(ClientService::list($r->query));
    }

    public static function options(Request $r, array $p): Response
    {
        self::admin();
        return self::ok(['items' => ClientService::options()]);
    }

    public static function get(Request $r, array $p): Response
    {
        self::admin();
        return self::ok(['client' => ClientService::get(self::id($p))]);
    }

    public static function create(Request $r, array $p): Response
    {
        self::admin();
        $res = ClientService::create($r->body());
        return self::ok(['result' => $res, 'message' => t('client.created')], 201);
    }

    public static function update(Request $r, array $p): Response
    {
        self::admin();
        $id = self::id($p);
        ClientService::update($id, $r->body());
        return self::ok(['client' => ClientService::get($id), 'message' => t('client.updated')]);
    }

    public static function setStatus(Request $r, array $p): Response
    {
        self::admin();
        $id = self::id($p);
        ClientService::setStatus($id, (string) $r->input('status', ''));
        return self::ok(['client' => ClientService::get($id)]);
    }

    public static function extend(Request $r, array $p): Response
    {
        self::admin();
        $id = self::id($p);
        $res = BillingService::extend($id, $r->body());
        return self::ok(['result' => $res, 'client' => ClientService::get($id), 'message' => t('client.extended')]);
    }

    public static function delete(Request $r, array $p): Response
    {
        self::admin();
        ClientService::delete(self::id($p));
        return self::ok(['message' => t('client.deleted')]);
    }
}
