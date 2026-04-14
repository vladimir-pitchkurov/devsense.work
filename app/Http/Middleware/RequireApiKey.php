<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class RequireApiKey
{
    /**
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $requiredScope = ''): mixed
    {
        $raw = $this->extractKey($request);
        if ($raw === null) {
            return response()->json(['error' => ['code' => 'unauthorized', 'message' => 'Missing API key']], 401);
        }

        $hash = hash('sha256', $raw);
        $apiKey = ApiKey::query()
            ->where('key_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if (! $apiKey) {
            return response()->json(['error' => ['code' => 'unauthorized', 'message' => 'Invalid API key']], 401);
        }

        $scopes = is_array($apiKey->scopes) ? $apiKey->scopes : [];
        if ($requiredScope !== '' && ! in_array($requiredScope, $scopes, true)) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => 'Insufficient scope']], 403);
        }

        // Optional per-key rate limiting override.
        $perMinute = is_int($apiKey->rate_limit_per_minute) ? $apiKey->rate_limit_per_minute : null;
        if ($perMinute !== null) {
            $limiterKey = 'content-api-key:'.$apiKey->id;
            if (RateLimiter::tooManyAttempts($limiterKey, $perMinute)) {
                $retryAfter = RateLimiter::availableIn($limiterKey);
                return response()
                    ->json(['error' => ['code' => 'too_many_requests', 'message' => 'Rate limit exceeded']], 429)
                    ->header('Retry-After', (string) $retryAfter);
            }
            RateLimiter::hit($limiterKey, 60);
        }

        $request->attributes->set('api_key_id', $apiKey->id);
        $request->attributes->set('api_key_scopes', $scopes);

        // Best-effort usage tracking without per-request DB writes.
        $touchKey = 'content_api_key_touch_v1_'.$apiKey->id;
        if (Cache::add($touchKey, '1', now()->addMinutes(5))) {
            $apiKey->forceFill([
                'last_used_at' => Carbon::now(),
                'last_ip' => $request->ip(),
            ])->save();
        }

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));
            if ($token !== '') {
                return $token;
            }
        }

        $x = $request->header('X-DevSense-Api-Key');
        if (is_string($x) && trim($x) !== '') {
            return trim($x);
        }

        return null;
    }
}

