<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use MongoDB\Database;

/**
 * Regla de escalabilidad: ninguna consulta que hace la API puede recorrer una
 * colección completa (COLLSCAN) ni ordenar los listados en memoria.
 *
 * Con 40 productos cualquier consulta es rápida; con 4 millones, un COLLSCAN o un
 * SORT en memoria (límite de 100 MB en MongoDB) tumba la pantalla. Esta prueba
 * enciende el profiler de MongoDB, recorre todas las pantallas y filtros de la
 * API y revisa el plan que eligió MongoDB para cada consulta real:
 *   - COLLSCAN en cualquier consulta → falla (falta un índice).
 *   - Listado sin filtros con SORT en memoria → falla (el índice no cubre el orden).
 */
final class QueryPlanTest extends ApiTestCase
{
    /** Colecciones de negocio: todas sus consultas deben usar un índice. */
    private const COLLECTIONS = ['products', 'users', 'profiles', 'audit_logs', 'refresh_tokens'];

    public function test_ninguna_consulta_de_la_api_recorre_la_coleccion_ni_ordena_en_memoria(): void
    {
        $this->admin();
        $login = $this->login('admin@tap.test')->assertOk();
        $h = ['Authorization' => 'Bearer ' . $login->json('data.access_token')];

        // Datos para que cada pantalla tenga algo que listar
        $productId = (string) $this->postJson('/api/v1/products', ['name' => 'Casco clase E', 'brand' => '3M', 'price' => 289.9], $h)->assertCreated()->json('data.id');
        $this->putJson("/api/v1/products/{$productId}", ['name' => 'Casco clase E', 'brand' => '3M', 'price' => 299.5], $h)->assertOk();
        $profileId = (string) $this->postJson('/api/v1/profiles', ['name' => 'Auditor', 'sections' => [['section' => 'audit', 'actions' => ['view']]]], $h)->assertCreated()->json('data.id');
        $userId = (string) $this->getJson('/api/v1/users', $h)->assertOk()->json('data.0.id');

        $db = $this->database();
        $this->startProfiler($db);

        // ---- Sesión: login (correo) y refresh (hash del token)
        $this->login('admin@tap.test')->assertOk();
        $this->withCredentials()->withUnencryptedCookie('tap_rt', (string) $login->getCookie('tap_rt', false)?->getValue())
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/api/v1/auth/refresh')->assertOk();

        // ---- Listados: cada columna ordenable en ambos sentidos, búsqueda y filtros
        $lists = [
            'products' => ['code', 'name', 'brand', 'price', 'created_at'],
            'users' => ['code', 'email', 'name', 'created_at'],
            'profiles' => ['code', 'name', 'created_at'],
            'audit-logs' => ['created_at', 'event', 'entity_type'],
        ];
        foreach ($lists as $resource => $columns) {
            foreach ($columns as $column) {
                foreach (['asc', 'desc'] as $direction) {
                    $this->getJson("/api/v1/{$resource}?sort={$column}&direction={$direction}&page=2&per_page=5", $h)->assertOk();
                }
            }
            $this->getJson("/api/v1/{$resource}?search=cas", $h)->assertOk();
        }
        $this->getJson('/api/v1/audit-logs?entity_type=product', $h)->assertOk();
        $this->getJson('/api/v1/audit-logs?event=updated', $h)->assertOk();
        $this->getJson("/api/v1/audit-logs?entity_type=product&entity_id={$productId}", $h)->assertOk();

        // ---- Detalles, tablero, selects y reglas de negocio que consultan
        $this->getJson("/api/v1/products/{$productId}", $h)->assertOk();
        $this->getJson("/api/v1/users/{$userId}", $h)->assertOk();
        $this->getJson("/api/v1/profiles/{$profileId}", $h)->assertOk();
        $this->getJson('/api/v1/profiles/options', $h)->assertOk();
        $this->getJson('/api/v1/dashboard', $h)->assertOk();
        $auditId = (string) $this->getJson('/api/v1/audit-logs', $h)->json('data.0.id');
        $this->getJson("/api/v1/audit-logs/{$auditId}", $h)->assertOk();
        $this->deleteJson("/api/v1/profiles/{$profileId}", [], $h)->assertNoContent(); // revisa si está asignado

        $problems = $this->stopProfilerAndCollectProblems($db);

        self::assertSame([], $problems, "Consultas que no escalan (agrega o ajusta un índice en la migración):\n" . implode("\n", $problems));
    }

    private function database(): Database
    {
        /** @var \MongoDB\Laravel\Connection $mongo */
        $mongo = DB::connection('mongodb');

        return $mongo->getDatabase();
    }

    private function startProfiler(Database $db): void
    {
        $db->command(['profile' => 0]);
        $db->dropCollection('system.profile');
        $db->command(['profile' => 2]); // registra TODAS las operaciones
    }

    /**
     * @return list<string>
     */
    private function stopProfilerAndCollectProblems(Database $db): array
    {
        $db->command(['profile' => 0]);
        $namespaces = array_map(static fn (string $c): string => $db->getDatabaseName() . '.' . $c, self::COLLECTIONS);

        $problems = [];
        foreach ($db->selectCollection('system.profile')->find(['ns' => ['$in' => $namespaces]], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]) as $op) {
            $plan = (string) ($op['planSummary'] ?? '');
            $command = (array) ($op['command'] ?? []);
            $unfilteredList = isset($command['find']) && ($command['filter'] ?? []) === [] && isset($command['sort']);

            if (str_starts_with($plan, 'COLLSCAN') || ($unfilteredList && ($op['hasSortStage'] ?? false) === true)) {
                $problems[] = sprintf('- %s · %s · filtro=%s orden=%s', $op['ns'], $plan, json_encode($command['filter'] ?? $command['pipeline'] ?? $command['query'] ?? []), json_encode($command['sort'] ?? null));
            }
        }

        return $problems;
    }
}
