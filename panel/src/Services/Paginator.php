<?php
declare(strict_types=1);

namespace Nivc\Services;

final class Paginator
{
    /** @return array{page:int,per:int,offset:int} */
    public static function params(array $q, int $defaultPer = 25): array
    {
        $per  = (int) ($q['per_page'] ?? $defaultPer);
        $per  = in_array($per, [5, 6, 10, 25, 50, 100], true) ? $per : $defaultPer;
        $page = max(1, (int) ($q['page'] ?? 1));
        return ['page' => $page, 'per' => $per, 'offset' => ($page - 1) * $per];
    }

    public static function meta(int $total, int $page, int $per): array
    {
        return ['total' => $total, 'page' => $page, 'per_page' => $per, 'pages' => max(1, (int) ceil($total / $per))];
    }
}
