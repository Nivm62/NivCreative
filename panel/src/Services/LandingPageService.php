<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Actor;
use Nivc\Core\Db;
use Nivc\Core\HttpException;
use Nivc\Core\NowTime;
use Nivc\Core\Validator;

final class LandingPageService
{
    public static function create(int $clientId, int $websiteId, string $name, string $url): int
    {
        $now = NowTime::mysql();
        return Db::insert('landing_pages', [
            'client_id' => $clientId, 'website_id' => $websiteId, 'name' => $name, 'url' => $url,
            'path_key' => Domain::pathKey($url), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public static function findOwned(Actor $a, int $id): array
    {
        $r = Db::one('SELECT * FROM landing_pages WHERE id = ?', [$id]);
        if (!$r) {
            throw HttpException::notFound(t('error.not_found'));
        }
        $a->assertOwns((int) $r['client_id']);
        return $r;
    }

    /** Lists landing pages with stats; clients only ever see their own. */
    public static function list(Actor $a, array $q): array
    {
        $where = ['c.deleted_at IS NULL'];
        $p = [];
        $cid = $a->scopeClientId($q['client_id'] ?? null);
        if ($cid !== null) {
            $where[] = 'lp.client_id = ?';
            $p[] = $cid;
        }
        if (!empty($q['website_id']) && ctype_digit((string) $q['website_id'])) {
            $where[] = 'lp.website_id = ?';
            $p[] = (int) $q['website_id'];
        }
        if (in_array($q['status'] ?? '', ['active', 'paused', 'archived'], true)) {
            $where[] = 'lp.status = ?';
            $p[] = $q['status'];
        }
        $s = trim((string) ($q['search'] ?? ''));
        if ($s !== '') {
            $like = '%' . addcslashes($s, '%_\\') . '%';
            $where[] = '(lp.name LIKE ? OR lp.url LIKE ?)';
            array_push($p, $like, $like);
        }
        $pg = Paginator::params($q);
        $from = 'FROM landing_pages lp JOIN clients c ON c.id = lp.client_id JOIN websites w ON w.id = lp.website_id WHERE ' . implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) ' . $from, $p);
        $sortMap = ['name' => 'lp.name', 'views' => 'views', 'leads' => 'leads', 'created' => 'lp.created_at', 'conversion' => 'conversion'];
        $sort = $sortMap[$q['sort'] ?? ''] ?? 'lp.created_at';
        $dir  = (($q['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
        $rows = Db::all(
            "SELECT lp.*, c.business_name AS client_name, w.name AS website_name, w.domain AS website_domain,
                (SELECT COALESCE(SUM(pv.views),0) FROM page_views pv WHERE pv.landing_page_id = lp.id) AS views,
                (SELECT COUNT(*) FROM leads l WHERE l.landing_page_id = lp.id) AS leads,
                (SELECT COUNT(*) FROM leads l WHERE l.landing_page_id = lp.id) * 100 / NULLIF((SELECT COALESCE(SUM(pv.views),0) FROM page_views pv WHERE pv.landing_page_id = lp.id), 0) AS conversion
             {$from} ORDER BY {$sort} {$dir}, lp.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}",
            $p
        );
        $items = array_map(static fn(array $r) => [
            'id' => (int) $r['id'], 'client_id' => (int) $r['client_id'], 'client_name' => $r['client_name'], 'website_id' => (int) $r['website_id'],
            'website_name' => $r['website_name'], 'website_domain' => $r['website_domain'], 'name' => $r['name'], 'url' => $r['url'],
            'views' => (int) $r['views'], 'leads' => (int) $r['leads'], 'conversion' => $r['conversion'] === null ? null : round((float) $r['conversion'], 1),
            'status' => $r['status'], 'created_at' => $r['created_at'],
        ], $rows);
        return ['items' => $items, 'meta' => Paginator::meta($total, $pg['page'], $pg['per'])];
    }

    public static function save(?int $id, array $in): array
    {
        $v = new Validator($in);
        $name = $v->str('name', true, 190);
        $url  = $v->url('url', true);
        $wid  = $v->int('website_id', 1, null, true);
        $status = $v->enum('status', ['active', 'paused', 'archived'], 'active');
        $v->check();
        $site = Db::one('SELECT id, client_id, domain FROM websites WHERE id = ?', [$wid]);
        if (!$site) {
            throw HttpException::invalid(['website_id' => t('validation.invalid')]);
        }
        $data = ['client_id' => $site['client_id'], 'website_id' => $site['id'], 'name' => $name, 'url' => $url, 'path_key' => Domain::pathKey($url), 'status' => $status, 'updated_at' => NowTime::mysql()];
        if ($id === null) {
            $data['created_at'] = NowTime::mysql();
            return ['id' => Db::insert('landing_pages', $data)];
        }
        if (!Db::val('SELECT id FROM landing_pages WHERE id = ?', [$id])) {
            throw HttpException::notFound(t('error.not_found'));
        }
        Db::update('landing_pages', $data, ['id' => $id]);
        return ['id' => $id];
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'paused', 'archived'], true)) {
            throw HttpException::invalid(['status' => t('validation.invalid')]);
        }
        Db::update('landing_pages', ['status' => $status, 'updated_at' => NowTime::mysql()], ['id' => $id]);
    }

    public static function duplicate(int $id): int
    {
        $r = Db::one('SELECT * FROM landing_pages WHERE id = ?', [$id]);
        if (!$r) {
            throw HttpException::notFound(t('error.not_found'));
        }
        $now = NowTime::mysql();
        return Db::insert('landing_pages', [
            'client_id' => $r['client_id'], 'website_id' => $r['website_id'], 'name' => mb_substr($r['name'], 0, 170) . ' (' . t('common.copy_word') . ')',
            'url' => $r['url'], 'path_key' => $r['path_key'], 'status' => 'paused', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public static function delete(int $id): void
    {
        Db::exec('DELETE FROM landing_pages WHERE id = ?', [$id]);
    }
}
