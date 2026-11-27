<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tap\IdentityAccess\Domain\Exception\AuthorizationError;
use Tap\Shared\Infrastructure\Http\RequestContext;

/**
 * Middleware `account.ready`: bloquea el uso del sistema mientras el usuario
 * tenga pendiente cambiar su contraseña (primer ingreso o temporal) o, si la
 * política lo exige (TWO_FACTOR_REQUIRED=true), activar el 2FA.
 * Las rutas de /auth (me, password, 2fa, logout) quedan fuera para que pueda
 * resolverlo.
 */
final class EnsureAccountIsReady
{
    public function __construct(private readonly bool $twoFactorRequired)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = RequestContext::user($request);

        if ($user->mustChangePassword) {
            throw AuthorizationError::passwordChangeRequired();
        }

        if ($this->twoFactorRequired && ! $user->twoFactorEnabled) {
            throw AuthorizationError::twoFactorRequired();
        }

        return $next($request);
    }
}
