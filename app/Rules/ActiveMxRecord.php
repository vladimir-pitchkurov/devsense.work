<?php

namespace App\Rules;

use App\Services\EmailIntegrityService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActiveMxRecord implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Fails when the email domain has no active MX records, meaning it cannot
     * receive emails at all.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var EmailIntegrityService $service */
        $service = app(EmailIntegrityService::class);

        if (!$service->hasMxRecord((string) $value)) {
            $fail(__('validation.no_mx_record'));
        }
    }
}
