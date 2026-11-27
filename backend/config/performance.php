<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Métricas y presupuestos de rendimiento de la API
|--------------------------------------------------------------------------
| Los usa el middleware RecordRequestMetrics (cabecera Server-Timing y logs de
| advertencia). Los presupuestos de carga (k6) y de frontend (Lighthouse,
| budgets de Angular) están en docs/RENDIMIENTO.md.
*/

return [
    // Cabecera Server-Timing en cada respuesta (tiempo total, tiempo en MongoDB y número de consultas)
    'server_timing' => (bool) env('PERF_SERVER_TIMING', true),

    // Una petición que tarde más que esto se registra como "Petición lenta"
    'slow_request_ms' => (int) env('PERF_SLOW_REQUEST_MS', 500),

    // Más consultas que esto en UNA petición suele ser un N+1: se registra y la prueba RequestMetricsTest falla
    'max_queries' => (int) env('PERF_MAX_QUERIES', 15),
];
