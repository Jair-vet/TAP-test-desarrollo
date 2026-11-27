<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tap\IdentityAccess\Application\Auth\SessionTokens;
use Tap\IdentityAccess\Infrastructure\Http\RefreshCookie;
use Tap\Shared\Application\Port\Clock;

/**
 * Arma la respuesta de una sesión: el access token va en el BODY (Angular
 * lo guarda solo en memoria) y el refresh token en la cookie HttpOnly.
 */
final class SessionResponder
{
    public function __construct(
        private readonly RefreshCookie $cookie,
        private readonly UserPresenter $users,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function respond(Request $request, SessionTokens $session, array $extra = [], int $status = 200): JsonResponse
    {
        return (new JsonResponse(['data' => $this->payload($session) + $extra], $status))
            ->withCookie($this->cookie->make($request, $session->refreshToken, $session->refreshExpiresAt))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(SessionTokens $session): array
    {
        $user = $session->user;

        return [
            'status' => 'authenticated',
            'access_token' => $session->accessToken->token,
            'token_type' => 'Bearer',
            'expires_in' => $session->accessToken->expiresIn($this->clock->now()->getTimestamp()),
            'user' => $this->users->summary($user) + [
                'must_change_password' => $user->mustChangePassword(),
                'two_factor_enabled' => $user->twoFactor()->enabled,
                'permissions' => $session->permissions->toArray(),
            ],
        ];
    }
}
