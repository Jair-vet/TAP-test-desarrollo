<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defensa en profundidad contra inyección NoSQL (el equivalente de la
 * inyección SQL en MongoDB).
 *
 * El ataque clásico es mandar `{"email": {"$ne": null}, "password": {"$gt": ""}}`
 * o `?search[$regex]=.*` para que el valor llegue como OPERADOR a la consulta.
 * Este middleware rechaza con 400 cualquier llave que empiece con `$`, que
 * contenga `.` en posición de operador o bytes nulos, en query, body y JSON.
 *
 * Es la primera capa; la segunda es la validación estricta de tipos
 * (FormRequest con 'string') y la tercera el adaptador Mongo, que nunca pasa
 * input crudo a un filtro.
 */
final class RejectNoSqlOperators
{
    private const MAX_DEPTH = 6;

    public function handle(Request $request, Closure $next): Response
    {
        $payloads = [$request->query->all(), $request->request->all()];

        if ($request->isJson()) {
            $payloads[] = $request->json()->all();
        }

        foreach ($payloads as $payload) {
            if ($this->isMalicious($payload, 0)) {
                return new JsonResponse([
                    'message' => 'La solicitud contiene caracteres o estructuras no permitidas.',
                    'code' => 'MALFORMED_INPUT',
                ], 400);
            }
        }

        return $next($request);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function isMalicious(array $data, int $depth): bool
    {
        if ($depth > self::MAX_DEPTH) {
            return true;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && (str_starts_with($key, '$') || str_contains($key, "\0"))) {
                return true;
            }

            if (is_string($value) && str_contains($value, "\0")) {
                return true;
            }

            if (is_array($value) && $this->isMalicious($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }
}
