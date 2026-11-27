<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tap\IdentityAccess\Domain\Exception\AuthorizationError;
use Tap\Shared\Infrastructure\Http\RequestContext;

/**
 * Middleware `permission:{sección},{acción}`.
 * Ej: ->middleware('permission:products,delete')
 *
 * Es la regla "los usuarios solo podrán ingresar a las secciones asignadas
 * en sus perfiles" aplicada en el SERVIDOR (ocultar botones en Angular es
 * solo comodidad; la seguridad real está aquí).
 */
final class RequirePermission
{
    public function handle(Request $request, Closure $next, string $section, string $action = 'view'): Response
    {
        if (! RequestContext::user($request)->can($section, $action)) {
            throw AuthorizationError::forbidden($section, $action);
        }

        return $next($request);
    }
}
