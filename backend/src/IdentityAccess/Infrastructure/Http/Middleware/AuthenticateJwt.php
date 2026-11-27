<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tap\IdentityAccess\Application\Authorization\AuthUserProvider;
use Tap\IdentityAccess\Application\Port\TokenRevocationList;
use Tap\IdentityAccess\Application\Port\TokenService;
use Tap\IdentityAccess\Domain\Exception\AuthenticationError;
use Tap\Shared\Infrastructure\Http\RequestContext;

/**
 * Middleware `auth.jwt`: valida el Bearer token y adjunta el usuario.
 *
 *   1. Firma Ed25519 + iss/aud/exp/nbf/typ  (EdDsaJwtTokenService)
 *   2. jti no revocado (logout)              (Redis)
 *   3. token version vigente                 (AuthUserProvider, cacheado)
 *
 * Implementa AuthenticatesRequests (interfaz marcadora de Laravel) para que el
 * ordenamiento por prioridad de middleware lo ejecute ANTES de `throttle:api`.
 * Sin esto, el límite de 120 peticiones/min se calculaba por IP y no por usuario
 * (todos los usuarios de una misma red compartían el cupo). Ver RateLimitTest.
 */
final class AuthenticateJwt implements AuthenticatesRequests
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly TokenRevocationList $revocations,
        private readonly AuthUserProvider $users,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            throw AuthenticationError::unauthenticated();
        }

        $claims = $this->tokens->decode($token, TokenService::TYPE_ACCESS);

        if ($this->revocations->isRevoked($claims->tokenId)) {
            throw AuthenticationError::tokenRevoked();
        }

        $request->attributes->set(RequestContext::AUTH_ATTRIBUTE, $this->users->resolve($claims));

        return $next($request);
    }
}
