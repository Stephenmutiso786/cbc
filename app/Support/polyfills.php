<?php

if (! function_exists('mb_split')) {
    /**
     * Laravel 12 calls mb_split() from Str::studly(), but some PHP builds
     * do not ship ext-mbstring and Symfony's polyfill does not define it.
     */
    function mb_split(string $pattern, string $string, int $limit = -1): array|false
    {
        return preg_split("/{$pattern}/u", $string, $limit);
    }
}
