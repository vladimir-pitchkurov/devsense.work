<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

/**
 * Base HTTP controller for the application.
 *
 * Concrete controllers extend this class to inherit standard controller behaviour.
 */
abstract class Controller
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function scalarMetaString(array $meta, string $key): ?string
    {
        if (! isset($meta[$key])) {
            return null;
        }

        $value = $meta[$key];

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function publishedCarbon(array $meta, Carbon $fallback): Carbon
    {
        $raw = $this->scalarMetaString($meta, 'published');
        if ($raw === null) {
            return $fallback;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
