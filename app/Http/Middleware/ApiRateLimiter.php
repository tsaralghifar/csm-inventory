<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiRateLimiter — middleware rate limiting berbasis user & endpoint.
 *
 * Cara daftar di Kernel.php (middlewareAliases):
 *   'api.limit' => \App\Http\Middleware\ApiRateLimiter::class,
 *
 * Cara pemakaian di routes/api.php:
 *   Route::middleware(['auth:sanctum', 'api.limit:60,1'])->group(...);
 *   // artinya 60 request per 1 menit
 *
 * Atau gunakan preset yang sudah ada:
 *   'api.limit:strict'   → 30 req/menit  (untuk endpoint sensitif)
 *   'api.limit:standard' → 120 req/menit (default)
 *   'api.limit:relaxed'  → 300 req/menit (untuk polling / realtime)
 */
class ApiRateLimiter
{
    // Preset yang bisa dipakai langsung di route
    private const PRESETS = [
        'strict'   => [30,  1],
        'standard' => [120, 1],
        'relaxed'  => [300, 1],
    ];

    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next, string $limit = 'standard', int $decayMinutes = 1): Response
    {
        [$maxAttempts, $decay] = $this->resolveLimit($limit, $decayMinutes);

        $key = $this->resolveKey($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildTooManyResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decay * 60);

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $key, $maxAttempts);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function resolveLimit(string $limit, int $decayMinutes): array
    {
        if (isset(self::PRESETS[$limit])) {
            return self::PRESETS[$limit];
        }

        // Format numerik: "60,1"
        if (is_numeric($limit)) {
            return [(int) $limit, $decayMinutes];
        }

        return [120, 1]; // fallback
    }

    private function resolveKey(Request $request): string
    {
        $userId = Auth::id() ?? $request->ip();
        $route  = $request->route()?->getName() ?? $request->path();

        return "api_rate:{$userId}:{$route}";
    }

    private function buildTooManyResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'success' => false,
            'message' => 'Terlalu banyak permintaan. Silakan coba lagi dalam beberapa saat.',
            'retry_after_seconds' => $retryAfter,
        ], Response::HTTP_TOO_MANY_REQUESTS)
            ->header('Retry-After', $retryAfter)
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', 0);
    }

    private function addRateLimitHeaders(Response $response, string $key, int $maxAttempts): Response
    {
        $remaining = max(0, $maxAttempts - $this->limiter->attempts($key));

        $response->headers->set('X-RateLimit-Limit',     (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }
}
