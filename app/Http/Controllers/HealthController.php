<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Data\HealthResult;
use App\Domain\Operations\Services\HealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public endpoint /health (progettazione-tecnica §5.2.7; D-179; PR-09) for the external uptime
 * service and for the deploy: 200 {"status":"ok"} or 503 {"status":"fail"}, never the details, which
 * stay on the server ("php artisan health:check" and the log).
 *
 * Outside the "web" group: no session, no cookie, no CSRF (bootstrap/app.php).
 */
class HealthController extends Controller
{
    /** A failing state is written to the log at most once every 10 minutes (the monitor calls every minute). */
    private const LOG_EVERY_SECONDS = 600;

    public function __invoke(HealthCheck $health): JsonResponse
    {
        $results = $health->run();
        $healthy = HealthCheck::healthy($results);

        if (! $healthy) {
            self::logFailure($results);
        }

        return response()
            ->json(['status' => $healthy ? 'ok' : 'fail'], $healthy ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @param  list<HealthResult>  $results
     */
    private static function logFailure(array $results): void
    {
        $failed = [];
        foreach ($results as $result) {
            if (! $result->ok) {
                $failed[$result->check] = $result->detail;
            }
        }

        try {
            $first = Cache::add('operations:health-logged', true, self::LOG_EVERY_SECONDS);
        } catch (Throwable) {
            // The cache is on Redis: when Redis is the failing check, write anyway.
            $first = true;
        }

        if ($first) {
            Log::warning('Health check failed', ['checks' => $failed]);
        }
    }
}
