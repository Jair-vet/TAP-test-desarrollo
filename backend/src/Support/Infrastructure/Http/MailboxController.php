<?php

declare(strict_types=1);

namespace Tap\Support\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Tap\Shared\Application\Port\AuditTrail;
use Tap\Shared\Infrastructure\Http\RequestContext;

/**
 * Acceso a la bandeja de correos de prueba (Mailpit, servida por Nginx en /mail/).
 *
 * Herramienta de soporte para la demo: NO es parte del dominio. Reemplaza la
 * autenticación básica nativa del navegador por una pantalla propia (/correos):
 *
 *   POST   /mailbox/session      usuario + contraseña de la bandeja → cookie
 *   POST   /mailbox/session/me   sesión JWT con permiso users:create → cookie
 *   GET    /mailbox/verify       lo llama Nginx (auth_request) en cada petición a /mail/
 *   DELETE /mailbox/session      cierra el acceso
 *
 * Cookie `tap_mail`: HttpOnly, SameSite=Strict, Path=/mail/ (solo viaja a la
 * bandeja), Secure por HTTPS, expira en 2 h.
 */
final class MailboxController
{
    public function __construct(
        private readonly MailboxPass $pass,
        private readonly AuditTrail $audit,
    ) {
    }

    /** POST /mailbox/session */
    public function login(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'user' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $expectedUser = (string) config('security.mailbox.user');
        $expectedPassword = (string) config('security.mailbox.password');

        // Comparación en tiempo constante; si no hay contraseña configurada, nadie entra
        $valid = $expectedPassword !== ''
            && hash_equals($expectedUser, (string) $data['user'])
            && hash_equals($expectedPassword, (string) $data['password']);

        if (! $valid) {
            $this->audit->record('mailbox_access_failed', 'auth', null, null, RequestContext::actor($request));

            return new JsonResponse(['message' => 'Usuario o contraseña incorrectos.', 'code' => 'INVALID_CREDENTIALS'], 401);
        }

        $this->audit->record('mailbox_access', 'auth', null, null, RequestContext::actor($request), null, null, ['via' => 'credentials']);

        return $this->granted($request);
    }

    /** POST /mailbox/session/me — un administrador entra con su sesión actual */
    public function loginWithSession(Request $request): Response
    {
        $this->audit->record('mailbox_access', 'auth', null, null, RequestContext::actor($request), null, null, ['via' => 'session']);

        return $this->granted($request);
    }

    /** GET /mailbox/verify — 204 si la cookie es válida, 401 si no (Nginx redirige a /correos) */
    public function verify(Request $request): Response
    {
        $value = $request->cookies->get((string) config('security.mailbox.cookie'));

        return response()->noContent($this->pass->verify(is_string($value) ? $value : null) ? 204 : 401);
    }

    /** DELETE /mailbox/session */
    public function logout(Request $request): Response
    {
        return response()->noContent()->withCookie($this->cookie($request, '', 1));
    }

    private function granted(Request $request): Response
    {
        $pass = $this->pass->issue();

        return response()->noContent()->withCookie($this->cookie($request, $pass['value'], $pass['expires']));
    }

    private function cookie(Request $request, string $value, int $expires): Cookie
    {
        return Cookie::create((string) config('security.mailbox.cookie'))
            ->withValue($value)
            ->withExpires($expires)
            ->withPath((string) config('security.mailbox.path'))
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT)
            ->withRaw(true);
    }
}
