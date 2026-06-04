<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class EmailIntegrityService
{
    /**
     * The list of blocked disposable email domains loaded from config.
     */
    protected array $blocklist;

    public function __construct()
    {
        $this->blocklist = config('disposable_emails.domains', []);
    }

    /**
     * Check whether the given email address belongs to a disposable domain.
     */
    public function isDisposable(string $email): bool
    {
        $domain = $this->extractDomain($email);

        if ($domain === null) {
            return false;
        }

        return in_array(strtolower($domain), $this->blocklist, true);
    }

    /**
     * Check whether the domain has at least one valid MX record.
     * Results are cached per-domain to avoid hammering DNS for the same domain.
     */
    public function hasMxRecord(string $email): bool
    {
        $domain = $this->extractDomain($email);

        if ($domain === null) {
            return false;
        }

        $cacheKey = 'mx_check_' . md5(strtolower($domain));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($domain) {
            // getmxrr() returns true and populates $hosts when MX records exist.
            $hosts = [];
            return getmxrr($domain, $hosts) && count($hosts) > 0;
        });
    }

    /**
     * Returns true when the email passes both checks:
     *   1. Not a disposable address.
     *   2. Domain has at least one MX record.
     */
    public function validate(string $email): bool
    {
        return !$this->isDisposable($email) && $this->hasMxRecord($email);
    }

    /**
     * Extract the domain portion from an email address.
     */
    protected function extractDomain(string $email): ?string
    {
        $parts = explode('@', $email, 2);

        return isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;
    }
}
