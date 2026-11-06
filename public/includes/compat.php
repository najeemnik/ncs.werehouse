<?php
/**
 * PHP compatibility shim (PHP 7.4 – 8.x).
 *
 * Free shared hosts sometimes run PHP 7.4. The application only uses a few
 * PHP 8 string helpers, so defining them here keeps the site working instead
 * of dying with "Call to undefined function" (HTTP 500).
 */

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        $length = strlen($needle);
        return $length <= strlen($haystack) && substr($haystack, -$length) === $needle;
    }
}

if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        $expected = 0;
        foreach ($array as $key => $value) {
            if ($key !== $expected++) return false;
        }
        return true;
    }
}

if (!function_exists('preg_last_error_msg')) {
    function preg_last_error_msg(): string
    {
        switch (preg_last_error()) {
            case PREG_NO_ERROR: return 'No error';
            case PREG_INTERNAL_ERROR: return 'Internal error';
            case PREG_BACKTRACK_LIMIT_ERROR: return 'Backtrack limit exhausted';
            case PREG_RECURSION_LIMIT_ERROR: return 'Recursion limit exhausted';
            case PREG_BAD_UTF8_ERROR: return 'Malformed UTF-8 characters';
            case PREG_BAD_UTF8_OFFSET_ERROR: return 'Malformed UTF-8 offset';
            default: return 'Unknown error';
        }
    }
}
