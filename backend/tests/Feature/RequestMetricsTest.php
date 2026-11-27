<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * Métricas de rendimiento de la API (middleware RecordRequestMetrics) y la regla
 * "consultas acotadas": ninguna pantalla hace más de performance.max_queries
 * consultas y el número de consultas de un listado NO crece con las filas (N+1).
 */
final class RequestMetricsTest extends ApiTestCase
{
    public function test_cada_respuesta_trae_server_timing_con_tiempo_y_consultas(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');

        $header = (string) $this->withToken($token)->getJson('/api/v1/products')->assertOk()->headers->get('Server-Timing');

        self::assertMatchesRegularExpression('/^app;dur=\d+\.\d;desc="Laravel", db;dur=\d+\.\d;desc="MongoDB: \d+ consulta\(s\)"$/', $header);
        self::assertGreaterThan(0, $this->queriesIn($header));
    }

    public function test_registra_las_peticiones_lentas(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');
        config(['performance.slow_request_ms' => 0]); // cualquier petición cuenta como lenta
        Log::spy();

        $this->withToken($token)->getJson('/api/v1/products')->assertOk();

        Log::shouldHaveReceived('warning')->with('Petición lenta', Mockery::on(
            static fn (array $context): bool => $context['route'] === 'products.index' && $context['status'] === 200 && isset($context['duration_ms'], $context['db_ms'], $context['queries']),
        ));
    }

    public function test_ninguna_pantalla_hace_n_mas_1_ni_rebasa_el_presupuesto_de_consultas(): void
    {
        $this->admin();
        foreach (range(1, 12) as $i) {
            $this->makeUser("persona{$i}@tap.test", [['products', ['view']]]);
        }
        $token = $this->tokenFor('admin@tap.test');
        foreach (range(1, 25) as $i) {
            $this->withToken($token)->postJson('/api/v1/products', ['name' => "Producto {$i}", 'brand' => 'Marca', 'price' => $i])->assertCreated();
        }
        $productId = (string) $this->withToken($token)->getJson('/api/v1/products')->json('data.0.id');
        $userId = (string) $this->withToken($token)->getJson('/api/v1/users')->json('data.0.id');
        $profileId = (string) $this->withToken($token)->getJson('/api/v1/profiles')->json('data.0.id');

        $budget = (int) config('performance.max_queries');
        $over = [];
        $queries = function (string $url) use ($token, $budget, &$over): int {
            // Sin caché de consultas para medir el peor caso (lectura directa de MongoDB)
            $header = (string) $this->withToken($token)->withHeader('Cache-Control', 'no-cache')->getJson($url)->assertOk()->headers->get('Server-Timing');
            $count = $this->queriesIn($header);
            if ($count > $budget) {
                $over[] = "{$url}: {$count} consultas (presupuesto {$budget})";
            }

            return $count;
        };

        // N+1: pedir 5 o 100 filas debe costar las MISMAS consultas
        foreach (['products', 'users', 'profiles', 'audit-logs'] as $resource) {
            self::assertSame(
                $queries("/api/v1/{$resource}?per_page=5&search=a"),
                $queries("/api/v1/{$resource}?per_page=100&search=a"),
                "El listado de {$resource} hace más consultas cuando hay más filas (N+1)",
            );
        }

        foreach (['/api/v1/dashboard', '/api/v1/auth/me', "/api/v1/products/{$productId}", "/api/v1/users/{$userId}", "/api/v1/profiles/{$profileId}", '/api/v1/profiles/options', '/api/v1/sections', '/api/v1/audit-logs/catalog'] as $url) {
            $queries($url);
        }

        self::assertSame([], $over, implode("\n", $over));
    }

    private function queriesIn(string $serverTiming): int
    {
        return preg_match('/MongoDB: (\d+) consulta/', $serverTiming, $m) === 1 ? (int) $m[1] : -1;
    }
}
