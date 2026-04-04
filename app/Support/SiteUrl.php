<?php

namespace App\Support;

/**
 * Absolute URLs from `config('app.url')` so links match the public origin, not the incoming request host.
 */
final class SiteUrl
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').route($name, $parameters, false);
    }
}
