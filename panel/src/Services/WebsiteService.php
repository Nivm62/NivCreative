<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Actor;
use Nivc\Core\Crypto;
use Nivc\Core\Db;
use Nivc\Core\HttpException;
use Nivc\Core\NowTime;
use Nivc\Core\Validator;

final class WebsiteService
{
    /** @return array{site_key:string,token:string,token_hash:string,token_prefix:string} */
    private static function newCredentials(): array
    {
        $token = 'nvc_' . Crypto::randomToken(32);
        return [
            'site_key' => 'ws_' . substr(bin2hex(random_bytes(11)), 0, 21),
            'token' => $token, 'token_hash' => Crypto::hashToken($token), 'token_prefix' => substr($token, 0, 8),
        ];
    }

    /** Creates a website. The plain token is returned ONCE and never stored. */
    public static function create(int $clientId, string $name, string $url, bool $strict = false): array
    {
        $c  = self::newCredentials();
        $now = NowTime::mysql();
        $id = Db::insert('websites', [
            'client_id' => $clientId, 'name' => $name, 'domain' => Domain::host($url), 'url' => $url,
            'site_key' => $c['site_key'], 'token_hash' => $c['token_hash'], 'token_prefix' => $c['token_prefix'],
            'connection_status' => 'disconnected', 'status' => 'active', 'strict_pages' => $strict ? 1 : 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return ['id' => $id, 'site_key' => $c['site_key'], 'token' => $c['token']];
    }

    public static function rotateToken(Actor $a, int $id): array
    {
        $w = self::findOwned($a, $id);
        $c = self::newCredentials();
        Db::update('websites', ['token_hash' => $c['token_hash'], 'token_prefix' => $c['token_prefix'], 'updated_at' => NowTime::mysql()], ['id' => $w['id']]);
        return ['site_key' => $w['site_key'], 'token' => $c['token']];
    }

    public static function findOwned(Actor $a, int $id): array
    {
        $w = Db::one('SELECT * FROM websites WHERE id = ?', [$id]);
        if (!$w) {
            throw HttpException::notFound(t('error.not_found'));
        }
        $a->assertOwns((int) $w['client_id']);
        return $w;
    }

    /** Shapes a row for the API: NEVER includes token hash or encrypted credentials. */
    public static function present(array $w): array
    {
        return [
            'id' => (int) $w['id'], 'client_id' => (int) $w['client_id'], 'client_name' => $w['client_name'] ?? null,
            'name' => $w['name'], 'domain' => $w['domain'], 'url' => $w['url'], 'site_key' => $w['site_key'],
            'token_prefix' => $w['token_prefix'], 'has_wp_credentials' => !empty($w['wp_api_secret_enc']), 'wp_api_user' => $w['wp_api_user'],
            'connection_status' => $w['connection_status'], 'connection_message' => $w['connection_message'],
            'last_seen_at' => $w['last_seen_at'], 'connector_version' => $w['connector_version'], 'wp_version' => $w['wp_version'],
            'status' => $w['status'], 'strict_pages' => (bool) ($w['strict_pages'] ?? 0), 'created_at' => $w['created_at'],
            'pages_count' => (int) ($w['pages_count'] ?? 0), 'leads_count' => (int) ($w['leads_count'] ?? 0), 'views' => (int) ($w['views'] ?? 0),
        ];
    }

    public static function list(Actor $a, array $q): array
    {
        $where = ['1=1'];
        $p = [];
        $cid = $a->scopeClientId($q['client_id'] ?? null);
        if ($cid !== null) {
            $where[] = 'w.client_id = ?';
            $p[] = $cid;
        }
        $s = trim((string) ($q['search'] ?? ''));
        if ($s !== '') {
            $like = '%' . addcslashes($s, '%_\\') . '%';
            $where[] = '(w.name LIKE ? OR w.domain LIKE ?)';
            array_push($p, $like, $like);
        }
        if (in_array($q['connection'] ?? '', ['connected', 'disconnected', 'error', 'auth_required'], true)) {
            $where[] = 'w.connection_status = ?';
            $p[] = $q['connection'];
        }
        $rows = Db::all(
            'SELECT w.*, c.business_name AS client_name,
                (SELECT COUNT(*) FROM landing_pages lp WHERE lp.website_id = w.id) AS pages_count,
                (SELECT COUNT(*) FROM leads l WHERE l.website_id = w.id) AS leads_count,
                (SELECT COALESCE(SUM(pv.views),0) FROM page_views pv WHERE pv.website_id = w.id) AS views
             FROM websites w JOIN clients c ON c.id = w.client_id AND c.deleted_at IS NULL
             WHERE ' . implode(' AND ', $where) . ' ORDER BY w.created_at DESC LIMIT 500',
            $p
        );
        return array_map([self::class, 'present'], $rows);
    }

    public static function save(Actor $a, ?int $id, array $in): array
    {
        $v = new Validator($in);
        $name = $v->str('name', true, 190);
        $url  = $v->url('url', true);
        $cid  = $v->int('client_id', 1, null, true);
        $wpUser = $v->str('wp_api_user', false, 190);
        $wpSecret = (string) ($in['wp_api_secret'] ?? '');
        $strict = !empty($in['strict_pages']) && $in['strict_pages'] !== '0' && $in['strict_pages'] !== 'false';
        $v->check();
        if (!Db::val('SELECT id FROM clients WHERE id = ? AND deleted_at IS NULL', [$cid])) {
            throw HttpException::invalid(['client_id' => t('validation.invalid')]);
        }
        $data = ['name' => $name, 'url' => $url, 'domain' => Domain::host($url), 'wp_api_user' => $wpUser !== '' ? $wpUser : null, 'strict_pages' => $strict ? 1 : 0, 'updated_at' => NowTime::mysql()];
        if ($wpSecret !== '') {
            $data['wp_api_secret_enc'] = Crypto::encrypt($wpSecret);
        }
        if ($id === null) {
            $res = self::create($cid, $name, $url, $strict);
            Db::update('websites', array_diff_key($data, ['updated_at' => 1]), ['id' => $res['id']]);
            return ['id' => $res['id'], 'site_key' => $res['site_key'], 'token' => $res['token']];
        }
        $w = Db::one('SELECT id FROM websites WHERE id = ?', [$id]);
        if (!$w) {
            throw HttpException::notFound(t('error.not_found'));
        }
        $data['client_id'] = $cid;
        Db::update('websites', $data, ['id' => $id]);
        return ['id' => $id];
    }

    public static function delete(int $id): void
    {
        Db::exec('DELETE FROM websites WHERE id = ?', [$id]);
    }

    /** Tests reachability of the external WordPress REST API (and optional credentials). */
    public static function testConnection(Actor $a, int $id): array
    {
        $w = self::findOwned($a, $id);
        $base = rtrim($w['url'], '/');
        $status = 'error';
        $msg = '';
        $wpVersion = $w['wp_version'];
        $res = UrlGuard::get($base . '/wp-json/');
        if ($res['error'] === 'blocked') {
            $msg = 'blocked_url';
        } elseif ($res['status'] === 0) {
            $msg = 'unreachable';
        } elseif ($res['status'] >= 200 && $res['status'] < 300) {
            $j = json_decode($res['body'], true);
            if (is_array($j) && isset($j['namespaces'])) {
                $status = 'connected';
                $msg = in_array('nivcreative/v1', (array) $j['namespaces'], true) ? 'connector_found' : 'wordpress_ok';
                // Optional authenticated check using stored credentials.
                if (!empty($w['wp_api_secret_enc']) && !empty($w['wp_api_user'])) {
                    $secret = Crypto::decrypt($w['wp_api_secret_enc']);
                    $auth = UrlGuard::get($base . '/wp-json/wp/v2/users/me', ['Authorization: Basic ' . base64_encode($w['wp_api_user'] . ':' . $secret)]);
                    if (in_array($auth['status'], [401, 403], true)) {
                        $status = 'auth_required';
                        $msg = 'auth_failed';
                    }
                }
            } else {
                $msg = 'not_wordpress';
            }
        } elseif (in_array($res['status'], [401, 403], true)) {
            $status = 'auth_required';
            $msg = 'auth_required';
        } else {
            $msg = 'http_' . $res['status'];
        }
        Db::update('websites', ['connection_status' => $status, 'connection_message' => $msg, 'last_seen_at' => $status === 'connected' ? NowTime::mysql() : $w['last_seen_at'], 'wp_version' => $wpVersion, 'updated_at' => NowTime::mysql()], ['id' => $id]);
        return ['connection_status' => $status, 'connection_message' => $msg];
    }
}
