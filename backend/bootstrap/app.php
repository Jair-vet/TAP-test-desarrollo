<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Tap\IdentityAccess\Infrastructure\Http\Middleware\AuthenticateJwt;
use Tap\IdentityAccess\Infrastructure\Http\Middleware\EnsureAccountIsReady;
use Tap\IdentityAccess\Infrastructure\Http\Middleware\RequirePermission;
use Tap\Shared\Domain\Exception\DomainError;
use Tap\Shared\Infrastructure\Http\ApiExceptionRenderer;
use Tap\Shared\Infrastructure\Http\Middleware\AssignRequestId;
use Tap\Shared\Infrastructure\Http\Middleware\ForceJsonResponse;
use Tap\Shared\Infrastructure\Http\Middleware\HttpCacheHeaders;
use Tap\Shared\Infrastructure\Http\Middleware\RecordRequestMetrics;
use Tap\Shared\Infrastructure\Http\Middleware\RejectNoSqlOperators;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Detrás de Nginx (y del túnel de Cloudflare): confiar SOLO en la red interna de Docker
        $middleware->trustProxies(
            at: ['172.16.0.0/12', '10.0.0.0/8', '192.168.0.0/16', '127.0.0.1'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT,
        );

        // Orden de ejecución en /api: id → métricas → JSON → anti-inyección NoSQL → cabeceras/ETag
        $middleware->api(prepend: [
            AssignRequestId::class,
            RecordRequestMetrics::class,
            ForceJsonResponse::class,
            RejectNoSqlOperators::class,
            HttpCacheHeaders::class,
        ]);

        $middleware->alias([
            'auth.jwt' => AuthenticateJwt::class,
            'permission' => RequirePermission::class,
            'account.ready' => EnsureAccountIsReady::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Errores de negocio esperados: no ensucian el log de errores
        $exceptions->dontReport([DomainError::class]);
        $exceptions->render(new ApiExceptionRenderer());
    })->create();
