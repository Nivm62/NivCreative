<?php
declare(strict_types=1);

namespace Nivc\Controllers\Api;

use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Services\LeadService;

/** Lead management for both roles. Every call is tenant-scoped inside LeadService. */
final class LeadsApi extends Base
{
    public static function list(Request $r, array $p): Response
    {
        return self::ok(LeadService::list(self::actor(), $r->query));
    }

    public static function get(Request $r, array $p): Response
    {
        return self::ok(['lead' => LeadService::detail(self::actor(), self::id($p))]);
    }

    public static function update(Request $r, array $p): Response
    {
        return self::ok(['lead' => LeadService::update(self::actor(), self::id($p), $r->body()), 'message' => t('lead.updated')]);
    }

    public static function addNote(Request $r, array $p): Response
    {
        return self::ok(['lead' => LeadService::addNote(self::actor(), self::id($p), $r->body()), 'message' => t('lead.note_added')], 201);
    }

    public static function contact(Request $r, array $p): Response
    {
        LeadService::logContact(self::actor(), self::id($p), (string) $r->input('channel', ''));
        return self::ok();
    }

    public static function export(Request $r, array $p): Response
    {
        $a = self::actor();
        $csv = LeadService::exportCsv($a, $r->query);
        return new Response(200, $csv, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="leads-' . date('Y-m-d') . '.csv"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
