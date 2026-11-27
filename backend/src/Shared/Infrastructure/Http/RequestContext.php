<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Http;

use Illuminate\Http\Request;
use LogicException;
use Tap\Shared\Application\Actor;
use Tap\Shared\Application\AuthenticatedUser;

/** Puente entre el Request de Laravel y los objetos de la capa de aplicación. */
final class RequestContext
{
    public const AUTH_ATTRIBUTE = 'auth_user';

    public static function user(Request $request): AuthenticatedUser
    {
        $user = $request->attributes->get(self::AUTH_ATTRIBUTE);

        if (! $user instanceof AuthenticatedUser) {
            throw new LogicException('Ruta protegida sin middleware de autenticación.');
        }

        return $user;
    }

    public static function actor(Request $request): Actor
    {
        $user = $request->attributes->get(self::AUTH_ATTRIBUTE);
        $ip = $request->ip();
        $userAgent = mb_substr((string) $request->userAgent(), 0, 255);

        return $user instanceof AuthenticatedUser
            ? $user->toActor($ip, $userAgent)
            : Actor::anonymous($ip, $userAgent);
    }
}
