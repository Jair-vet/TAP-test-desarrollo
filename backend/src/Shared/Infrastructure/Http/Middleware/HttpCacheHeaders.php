<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad + ETag para respuestas JSON de la API.
 *
 * ETag: si el listado no cambió, el navegador manda If-None-Match y
 * respondemos 304 sin cuerpo (menos datos por la red, tabla más fluida).
 * `private, no-cache` = el navegador puede guardar la respuesta, pero
 * siempre revalida, y ningún proxy/CDN la comparte entre usuarios.
 */
final class HttpCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            // Acentos legibles en Postman/Swagger ("sesión" en vez de "sesi\u00f3n")
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        if (! $response->headers->has('Cache-Control') || $response->headers->get('Cache-Control') === 'no-cache, private') {
            $response->headers->set('Cache-Control', 'private, no-cache');
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && str_contains($contentType, 'json')) {
            $etag = '"' . hash('xxh128', (string) $response->getContent()) . '"';
            $response->headers->set('ETag', $etag);
            $response->headers->set('Vary', 'Authorization');

            $ifNoneMatch = (string) $request->headers->get('If-None-Match', '');
            if ($ifNoneMatch !== '' && trim(explode(',', $ifNoneMatch)[0]) === $etag) {
                $response->setNotModified();
            }
        }

        return $response;
    }
}
