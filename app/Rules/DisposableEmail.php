<?php

namespace App\Rules;

use App\Services\EmailIntegrityService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DisposableEmail implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Fails when the email address belongs to a known disposable / temporary
     * mail provider.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var EmailIntegrityService $service */
        $service = app(EmailIntegrityService::class);

        if ($service->isDisposable((string) $value)) {
            $fail(__('validation.disposable_email'));
        }
    }
}
