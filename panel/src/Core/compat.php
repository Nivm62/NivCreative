<?php
declare(strict_types=1);

// Polyfills for OPTIONAL PHP extensions that some shared hosts leave disabled (mbstring, ctype).
// They only define a function when the real one is missing, so servers with the extensions are unaffected.

if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null)
    {
        $n = @preg_match_all('/./su', (string) $string);
        return $n === false ? strlen((string) $string) : $n;
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null)
    {
        $string = (string) $string;
        if (!preg_match_all('/./su', $string, $m)) {
            return $length === null ? substr($string, $start) : substr($string, $start, $length);
        }
        return implode('', $length === null ? array_slice($m[0], $start) : array_slice($m[0], $start, $length));
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null)
    {
        return strtolower((string) $string); // ASCII only; Hebrew has no letter case
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = null)
    {
        return strtoupper((string) $string);
    }
}
if (!function_exists('ctype_digit')) {
    function ctype_digit($text)
    {
        return is_string($text) && $text !== '' && preg_match('/^[0-9]+$/', $text) === 1;
    }
}
