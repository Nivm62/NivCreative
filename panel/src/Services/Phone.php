<?php
declare(strict_types=1);

namespace Nivc\Services;

/** Israeli phone normalization + WhatsApp link (opening a chat never sends a message). */
final class Phone
{
    /** @return array{display:string,intl:string}|null */
    public static function normalize(string $raw): ?array
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00972')) {
            $n = substr($digits, 5);
        } elseif (str_starts_with($digits, '972')) {
            $n = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $n = substr($digits, 1);
        } else {
            $n = $digits;
        }
        if (str_starts_with($n, '0') && $n !== $digits) {
            $n = substr($n, 1); // +972-050-...
        }
        if (!preg_match('/^(5\d{8}|7\d{8}|[23489]\d{7})$/', $n)) {
            return null;
        }
        $local = '0' . $n;
        $cut   = strlen($local) === 10 ? 3 : 2;
        return ['display' => substr($local, 0, $cut) . '-' . substr($local, $cut), 'intl' => '972' . $n];
    }

    public static function whatsappUrl(string $intl): string
    {
        return $intl === '' ? '' : 'https://wa.me/' . $intl;
    }
}
