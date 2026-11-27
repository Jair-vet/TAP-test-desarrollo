<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Http;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tap\IdentityAccess\Domain\Exception\AuthenticationError;
use Tap\IdentityAccess\Domain\Exception\AuthorizationError;
use Tap\Shared\Domain\Exception\Conflict;
use Tap\Shared\Domain\Exception\DomainError;
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Domain\Exception\NotFound;
use Throwable;

/**
 * Traduce cualquier excepción a un JSON uniforme:
 *
 *   { "message": "...", "code": "SNAKE_CASE", "errors": {...}?, "request_id": "..." }
 *
 * Nunca expone trazas ni mensajes internos en producción.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        $requestId = (string) $request->attributes->get('request_id', '');

        return match (true) {
            $e instanceof ValidationException => $this->json(422, 'Los datos enviados no son válidos.', 'VALIDATION_ERROR', $requestId, ['errors' => $e->errors()]),
            $e instanceof InvalidValue => $this->json(422, $e->getMessage(), 'VALIDATION_ERROR', $requestId, ['errors' => [$e->field() => [$e->getMessage()]]]),
            $e instanceof AuthenticationError => $this->json(401, $e->getMessage(), $e->errorCode(), $requestId, $e->context()),
            $e instanceof AuthorizationError => $this->json(403, $e->getMessage(), $e->errorCode(), $requestId, $e->context()),
            $e instanceof NotFound => $this->json(404, $e->getMessage(), $e->errorCode(), $requestId),
            $e instanceof Conflict => $this->json(409, $e->getMessage(), $e->errorCode(), $requestId, $e->context()),
            $e instanceof DomainError => $this->json(422, $e->getMessage(), $e->errorCode(), $requestId, $e->context()),
            $e instanceof ThrottleRequestsException => $this->json(429, 'Demasiadas solicitudes. Intenta más tarde.', 'TOO_MANY_REQUESTS', $requestId, [], $e->getHeaders()),
            $e instanceof HttpExceptionInterface => $this->json(
                $e->getStatusCode(),
                $e->getStatusCode() === 404 ? 'Recurso no encontrado.' : ($e->getMessage() ?: 'Error en la solicitud.'),
                $e->getStatusCode() === 404 ? 'NOT_FOUND' : 'HTTP_ERROR',
                $requestId,
                [],
                $e->getHeaders(),
            ),
            default => $this->json(500, 'Ocurrió un error interno. Ya quedó registrado.', 'INTERNAL_ERROR', $requestId),
        };
    }

    /**
     * @param array<string, mixed>  $extra
     * @param array<string, string> $headers
     */
    private function json(int $status, string $message, string $code, string $requestId, array $extra = [], array $headers = []): JsonResponse
    {
        $body = ['message' => $message, 'code' => $code] + $extra;

        if ($requestId !== '') {
            $body['request_id'] = $requestId;
        }

        return new JsonResponse($body, $status, $headers);
    }
}
