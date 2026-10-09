<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Actor;
use Nivc\Core\Auth;
use Nivc\Core\Db;
use Nivc\Core\HttpException;
use Nivc\Core\NowTime;
use Nivc\Core\Validator;

/** Administrator-only management of every panel login (admins and client users). */
final class UserService
{
    private static function present(array $r): array
    {
        return [
            'id' => (int) $r['id'], 'name' => $r['name'], 'email' => $r['email'], 'role' => $r['role'], 'status' => $r['status'],
            'client_id' => $r['client_id'] === null ? null : (int) $r['client_id'], 'client_name' => $r['business_name'] ?? null,
            'locale' => $r['locale'], 'last_login_at' => $r['last_login_at'], 'created_at' => $r['created_at'],
        ];
    }

    public static function list(array $q): array
    {
        $w = ['1=1'];
        $p = [];
        $s = trim((string) ($q['search'] ?? ''));
        if ($s !== '') {
            $like = '%' . addcslashes($s, '%_\\') . '%';
            $w[] = '(u.name LIKE ? OR u.email LIKE ? OR c.business_name LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        if (in_array($q['role'] ?? '', ['admin', 'client'], true)) {
            $w[] = 'u.role = ?';
            $p[] = $q['role'];
        }
        if (in_array($q['status'] ?? '', ['active', 'disabled'], true)) {
            $w[] = 'u.status = ?';
            $p[] = $q['status'];
        }
        $pg = Paginator::params($q);
        $from = 'FROM users u LEFT JOIN clients c ON c.id = u.client_id WHERE ' . implode(' AND ', $w);
        $total = (int) Db::val('SELECT COUNT(*) ' . $from, $p);
        $rows = Db::all('SELECT u.*, c.business_name ' . $from . ' ORDER BY u.role ASC, u.created_at DESC, u.id DESC LIMIT ' . $pg['per'] . ' OFFSET ' . $pg['offset'], $p);
        return ['items' => array_map([self::class, 'present'], $rows), 'meta' => Paginator::meta($total, $pg['page'], $pg['per'])];
    }

    private static function find(int $id): array
    {
        $r = Db::one('SELECT u.*, c.business_name FROM users u LEFT JOIN clients c ON c.id = u.client_id WHERE u.id = ?', [$id]);
        if (!$r) {
            throw HttpException::notFound(t('error.not_found'));
        }
        return $r;
    }

    private static function activeAdmins(?int $except = null): int
    {
        return (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?", [$except ?? 0]);
    }

    /** @return array validated fields */
    private static function validate(array $in, ?array $existing): array
    {
        $v = new Validator($in);
        $d = [];
        $d['name']   = $v->str('name', true, 190);
        $d['email']  = $v->email('email');
        $d['role']   = $v->enum('role', ['admin', 'client'], $existing['role'] ?? 'admin');
        $d['status'] = $v->enum('status', ['active', 'disabled'], $existing['status'] ?? 'active');
        $d['locale'] = in_array($in['locale'] ?? '', ['he', 'en'], true) ? $in['locale'] : ($existing['locale'] ?? 'he');
        $cid = $in['client_id'] ?? null;
        $d['client_id'] = null;
        if ($d['role'] === 'client') {
            if ($cid === null || $cid === '' || !ctype_digit((string) $cid) || !Db::val('SELECT id FROM clients WHERE id = ? AND deleted_at IS NULL', [(int) $cid])) {
                $v->fail('client_id', t('validation.required'));
            } else {
                $d['client_id'] = (int) $cid;
            }
        }
        $pw = (string) ($in['password'] ?? '');
        if ($existing === null || $pw !== '') {
            if (!Auth::validPasswordRule($pw)) {
                $v->fail('password', t('validation.password_rule'));
            }
        }
        $d['password'] = $pw;
        if ($d['email'] !== '' && !isset($v->errors()['email']) && Db::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$d['email'], $existing['id'] ?? 0])) {
            $v->fail('email', t('validation.email_taken'));
        }
        $v->check();
        return $d;
    }

    public static function create(array $in): array
    {
        $d = self::validate($in, null);
        $now = NowTime::mysql();
        $id = Db::insert('users', [
            'client_id' => $d['client_id'], 'role' => $d['role'], 'email' => $d['email'], 'password_hash' => Auth::hashPassword($d['password']),
            'name' => $d['name'], 'locale' => $d['locale'], 'status' => $d['status'], 'created_at' => $now, 'updated_at' => $now,
        ]);
        return self::present(self::find($id));
    }

    public static function update(Actor $a, int $id, array $in): array
    {
        $u = self::find($id);
        $d = self::validate($in, $u);
        $demotes = $u['role'] === 'admin' && ($d['role'] !== 'admin' || $d['status'] !== 'active');
        if ($demotes) {
            if ($id === $a->userId) {
                throw HttpException::invalid(['role' => t('users.err_self')], t('users.err_self'));
            }
            if (self::activeAdmins($id) < 1) {
                throw HttpException::invalid(['role' => t('users.err_last_admin')], t('users.err_last_admin'));
            }
        }
        if ($id === $a->userId && $d['status'] !== 'active') {
            throw HttpException::invalid(['status' => t('users.err_self')], t('users.err_self'));
        }
        $row = ['name' => $d['name'], 'email' => $d['email'], 'role' => $d['role'], 'client_id' => $d['client_id'], 'status' => $d['status'], 'locale' => $d['locale'], 'updated_at' => NowTime::mysql()];
        if ($d['password'] !== '') {
            $row['password_hash'] = Auth::hashPassword($d['password']);
        }
        Db::update('users', $row, ['id' => $id]);
        if ($d['password'] !== '' || $d['status'] !== 'active') {
            Db::exec('DELETE FROM remember_tokens WHERE user_id = ?', [$id]); // sign the user out of remembered devices
        }
        return self::present(self::find($id));
    }

    public static function delete(Actor $a, int $id): void
    {
        $u = self::find($id);
        if ($id === $a->userId) {
            throw HttpException::invalid([], t('users.err_self'));
        }
        if ($u['role'] === 'admin' && $u['status'] === 'active' && self::activeAdmins($id) < 1) {
            throw HttpException::invalid([], t('users.err_last_admin'));
        }
        Db::exec('DELETE FROM users WHERE id = ?', [$id]);
    }
}
