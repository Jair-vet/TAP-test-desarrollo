<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cookie del refresh token:
 *  - HttpOnly: JavaScript (y un XSS) no puede leerla.
 *  - Secure: solo por HTTPS (el túnel de Cloudflare es HTTPS).
 *  - SameSite=Strict: no viaja en peticiones iniciadas desde otro sitio (CSRF).
 *  - Path=/api/v1/auth: solo se envía a los endpoints de sesión, no a toda la API.
 */
final class RefreshCookie
{
    public function __construct(
        private readonly string $name,
        private readonly string $path,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function make(Request $request, string $value, int $expiresAt): Cookie
    {
        return Cookie::create($this->name)
            ->withValue($value)
            ->withExpires($expiresAt)
            ->withPath($this->path)
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT)
            ->withRaw(true);
    }

    public function forget(Request $request): Cookie
    {
        return $this->make($request, '', 1);
    }
}
