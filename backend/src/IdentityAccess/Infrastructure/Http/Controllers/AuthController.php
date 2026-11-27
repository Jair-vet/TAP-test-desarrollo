<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Controllers;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tap\IdentityAccess\Application\Auth\PasswordService;
use Tap\IdentityAccess\Application\Auth\SessionService;
use Tap\IdentityAccess\Application\Port\TokenService;
use Tap\IdentityAccess\Application\User\UserService;
use Tap\IdentityAccess\Domain\Exception\AuthenticationError;
use Tap\IdentityAccess\Infrastructure\Http\RefreshCookie;
use Tap\IdentityAccess\Infrastructure\Http\Requests\ChangePasswordRequest;
use Tap\IdentityAccess\Infrastructure\Http\Requests\ForgotPasswordRequest;
use Tap\IdentityAccess\Infrastructure\Http\Requests\LoginRequest;
use Tap\IdentityAccess\Infrastructure\Http\Requests\TwoFactorChallengeRequest;
use Tap\IdentityAccess\Infrastructure\Http\Resources\SessionResponder;
use Tap\IdentityAccess\Infrastructure\Http\Resources\UserPresenter;
use Tap\Shared\Application\Port\Clock;
use Tap\Shared\Infrastructure\Http\ApiExceptionRenderer;
use Tap\Shared\Infrastructure\Http\RequestContext;
use Tap\Shared\Infrastructure\Http\Responses\ApiResponse;
use Throwable;

/**
 * Endpoints de autenticación (prefijo /api/v1/auth).
 */
final class AuthController
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly PasswordService $passwords,
        private readonly UserService $users,
        private readonly TokenService $tokens,
        private readonly SessionResponder $responder,
        private readonly UserPresenter $presenter,
        private readonly RefreshCookie $cookie,
        private readonly Clock $clock,
    ) {
    }

    /** POST /auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->sessions->login(
            (string) $request->validated('email'),
            (string) $request->validated('password'),
            RequestContext::actor($request),
        );

        if ($result->requiresTwoFactor() && $result->mfaToken !== null) {
            return new JsonResponse(['data' => [
                'status' => 'two_factor_required',
                'mfa_token' => $result->mfaToken->token,
                'expires_in' => $result->mfaToken->expiresIn($this->clock->now()->getTimestamp()),
            ]]);
        }

        /** @var \Tap\IdentityAccess\Application\Auth\SessionTokens $session */
        $session = $result->session;

        return $this->responder->respond($request, $session);
    }

    /** POST /auth/two-factor/challenge */
    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $session = $this->sessions->completeTwoFactor(
            (string) $request->validated('mfa_token'),
            $request->validated('code'),
            $request->validated('recovery_code'),
            RequestContext::actor($request),
        );

        return $this->responder->respond($request, $session);
    }

    /**
     * POST /auth/refresh — usa la cookie HttpOnly. Exige el header
     * X-Requested-With (un formulario de otro sitio no puede ponerlo sin
     * pasar por CORS) como defensa CSRF adicional a SameSite=Strict.
     */
    public function refresh(Request $request): JsonResponse
    {
        $plain = (string) $request->cookies->get($this->cookie->name(), '');

        if ($plain === '' || $request->headers->get('X-Requested-With') !== 'XMLHttpRequest') {
            throw AuthenticationError::invalidRefreshToken();
        }

        try {
            $session = $this->sessions->refresh($plain, RequestContext::actor($request));
        } catch (AuthenticationError $e) {
            // Token inválido/reusado: además de 401 se borra la cookie
            $response = (new ApiExceptionRenderer())($e, $request);

            return ($response ?? new JsonResponse(null, 401))->withCookie($this->cookie->forget($request));
        }

        return $this->responder->respond($request, $session);
    }

    /** POST /auth/logout — funciona aunque el access token ya haya expirado. */
    public function logout(Request $request): Response
    {
        $jti = null;
        $exp = null;
        $userId = null;
        $bearer = $request->bearerToken();

        if ($bearer !== null && $bearer !== '') {
            try {
                $claims = $this->tokens->decode($bearer, TokenService::TYPE_ACCESS);
                $jti = $claims->tokenId;
                $exp = $claims->expiresAt;
                $userId = $claims->subject;
            } catch (Throwable) {
                // token expirado o inválido: no hay nada que revocar
            }
        }

        $this->sessions->logout($jti, $exp, (string) $request->cookies->get($this->cookie->name(), ''), RequestContext::actor($request), $userId);

        return response()->noContent()->withCookie($this->cookie->forget($request));
    }

    /** POST /auth/forgot-password */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $expiresAt = $this->passwords->requestReset((string) $request->validated('email'), RequestContext::actor($request));

        return new JsonResponse([
            'message' => 'Enviamos una contraseña temporal a tu correo registrado.',
            'data' => ['expires_at' => $expiresAt->format(DateTimeInterface::ATOM)],
        ]);
    }

    /** GET /auth/me */
    public function me(Request $request): JsonResponse
    {
        $auth = RequestContext::user($request);
        $detail = $this->users->detail($auth->id);

        return ApiResponse::data($this->presenter->detail($detail) + ['permissions' => $auth->permissions]);
    }

    /** PUT /auth/password — cambia contraseña y rota la sesión. */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $session = $this->passwords->change(
            RequestContext::user($request)->id,
            (string) $request->validated('current_password'),
            (string) $request->validated('password'),
            RequestContext::actor($request),
        );

        return $this->responder->respond($request, $session, ['message' => 'Contraseña actualizada. Se cerraron tus demás sesiones.']);
    }
}
