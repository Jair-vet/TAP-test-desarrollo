<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MongoDB\Laravel\Connection;
use Symfony\Component\HttpFoundation\Response;
use Tap\Shared\Infrastructure\Observability\MongoCommandMetrics;

/**
 * Métricas de rendimiento de cada petición a la API.
 *
 *  1. Cabecera estándar `Server-Timing` (se ve en DevTools → Network → Timing):
 *       Server-Timing: app;dur=18.4;desc="Laravel", db;dur=3.1;desc="MongoDB: 2 consulta(s)"
 *  2. Log de advertencia "Petición lenta" si tarda más de performance.slow_request_ms.
 *  3. Log de advertencia "posible N+1" si hace más de performance.max_queries consultas.
 *
 * Umbrales en config/performance.php (variables PERF_* del .env). La regla de
 * consultas acotadas se verifica en tests/Feature/RequestMetricsTest.php.
 */
final class RecordRequestMetrics
{
    public function __construct(
        private readonly MongoCommandMetrics $metrics,
        private readonly Connection $mongo,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // addSubscriber no duplica si ya está registrado en este cliente
        $this->mongo->getClient()?->addSubscriber($this->metrics);
        $this->metrics->reset();
        $start = hrtime(true);

        /** @var Response $response */
        $response = $next($request);

        $appMs = (hrtime(true) - $start) / 1e6;
        $dbMs = $this->metrics->milliseconds();
        $queries = $this->metrics->count();

        if ((bool) config('performance.server_timing', true)) {
            $response->headers->set('Server-Timing', sprintf(
                'app;dur=%.1f;desc="Laravel", db;dur=%.1f;desc="MongoDB: %d consulta(s)"',
                $appMs,
                $dbMs,
                $queries,
            ));
        }

        $slow = $appMs >= (int) config('performance.slow_request_ms', 500);
        $chatty = $queries > (int) config('performance.max_queries', 15);
        if ($slow || $chatty) {
            Log::warning($slow ? 'Petición lenta' : 'Demasiadas consultas en una petición (posible N+1)', [
                'method' => $request->method(),
                'route' => $request->route()?->getName() ?? $request->path(),
                'status' => $response->getStatusCode(),
                'duration_ms' => round($appMs, 1),
                'db_ms' => round($dbMs, 1),
                'queries' => $queries,
            ]);
        }

        return $response;
    }
}
