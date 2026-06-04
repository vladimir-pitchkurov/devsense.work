<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Events\ArticlePublished;
use App\Events\VacancyPublished;
use App\Listeners\PingIndexNowListener;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Registers application-level bindings and bootstraps framework hooks.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the service container.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services after all providers have been registered.
     */
    public function boot(): void
    {
        URL::defaults(['locale' => app()->getLocale()]);

        VerifyEmail::createUrlUsing(function ($notifiable) {
            return URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'locale' => app()->getLocale(),
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });

        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject(__('ui.auth.email_verification.subject'))
                ->greeting(__('ui.auth.email_verification.greeting'))
                ->line(__('ui.auth.email_verification.line_1'))
                ->action(__('ui.auth.email_verification.button'), $url)
                ->line(__('ui.auth.email_verification.line_2'));
        });

        ResetPassword::createUrlUsing(function ($notifiable, $token) {
            return route('password.reset', [
                'locale' => app()->getLocale(),
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        ResetPassword::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject(__('ui.auth.password_reset.subject'))
                ->greeting(__('ui.auth.password_reset.greeting'))
                ->line(__('ui.auth.password_reset.line_1'))
                ->action(__('ui.auth.password_reset.button'), $url)
                ->line(__('ui.auth.password_reset.line_2'));
        });


        Event::listen(ArticlePublished::class, PingIndexNowListener::class);
        Event::listen(VacancyPublished::class, PingIndexNowListener::class);

        Gate::define('access-admin', function ($user) {
            return true;
        });

        Gate::define('manage-tags', function ($user) {
            return $user->isAuthor();
        });

        Gate::define('manage-users', function ($user) {
            return $user->isAdmin();
        });

        $appUrl = config('app.url');
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        \Illuminate\Pagination\Paginator::defaultView('partials.pagination');

        RateLimiter::for('content-api', function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) $request->ip());
        });

        RateLimiter::for('content-api-search', function (Request $request): Limit {
            $apiKeyId = $request->attributes->get('api_key_id');
            $id = is_int($apiKeyId) ? 'key:'.$apiKeyId : (string) $request->ip();

            return Limit::perMinute(60)->by($id);
        });

        RateLimiter::for('content-api-content', function (Request $request): Limit {
            $apiKeyId = $request->attributes->get('api_key_id');
            $id = is_int($apiKeyId) ? 'key:'.$apiKeyId : (string) $request->ip();

            return Limit::perMinute(30)->by($id);
        });
    }
}
