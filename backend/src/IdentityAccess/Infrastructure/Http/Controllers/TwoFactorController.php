<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tap\IdentityAccess\Application\TwoFactor\TwoFactorService;
use Tap\IdentityAccess\Infrastructure\Http\Requests\ConfirmTwoFactorRequest;
use Tap\IdentityAccess\Infrastructure\Http\Requests\DisableTwoFactorRequest;
use Tap\IdentityAccess\Infrastructure\Http\Requests\PasswordConfirmationRequest;
use Tap\IdentityAccess\Infrastructure\Http\Resources\SessionResponder;
use Tap\Shared\Infrastructure\Http\RequestContext;
use Tap\Shared\Infrastructure\Http\Responses\ApiResponse;

/** Gestión del 2FA del usuario autenticado (prefijo /api/v1/auth/two-factor). */
final class TwoFactorController
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly SessionResponder $responder,
    ) {
    }

    /** GET /auth/two-factor */
    public function status(Request $request): JsonResponse
    {
        return ApiResponse::data($this->twoFactor->status(RequestContext::user($request)->id));
    }

    /** POST /auth/two-factor/setup → QR (SVG) + clave manual */
    public function setup(Request $request): JsonResponse
    {
        $setup = $this->twoFactor->setup(RequestContext::user($request)->id);

        return ApiResponse::data([
            'secret' => $setup->secret,
            'otpauth_url' => $setup->provisioningUri,
            'qr_svg' => $setup->qrSvg,
        ])->header('Cache-Control', 'no-store');
    }

    /** POST /auth/two-factor/confirm → activa y devuelve códigos de respaldo + sesión nueva */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $result = $this->twoFactor->confirm(
            RequestContext::user($request)->id,
            (string) $request->validated('code'),
            RequestContext::actor($request),
        );

        return $this->responder->respond($request, $result['session'], [
            'recovery_codes' => $result['recovery_codes'],
            'message' => 'Verificación en dos pasos activada. Guarda tus códigos de respaldo.',
        ]);
    }

    /** DELETE /auth/two-factor (contraseña + código) */
    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        $session = $this->twoFactor->disable(
            RequestContext::user($request)->id,
            (string) $request->validated('password'),
            (string) $request->validated('code'),
            RequestContext::actor($request),
        );

        return $this->responder->respond($request, $session, ['message' => 'Verificación en dos pasos desactivada.']);
    }

    /** POST /auth/two-factor/recovery-codes */
    public function regenerateRecoveryCodes(PasswordConfirmationRequest $request): JsonResponse
    {
        $codes = $this->twoFactor->regenerateRecoveryCodes(
            RequestContext::user($request)->id,
            (string) $request->validated('password'),
            RequestContext::actor($request),
        );

        return ApiResponse::data(['recovery_codes' => $codes])->header('Cache-Control', 'no-store');
    }
}
