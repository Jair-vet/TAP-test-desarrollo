<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Authorization;

use Tap\IdentityAccess\Application\Port\TokenClaims;
use Tap\IdentityAccess\Domain\Exception\AuthenticationError;
use Tap\IdentityAccess\Domain\Profile\PermissionSet;
use Tap\IdentityAccess\Domain\Profile\ProfileRepository;
use Tap\IdentityAccess\Domain\User\User;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\Shared\Application\AuthenticatedUser;
use Tap\Shared\Application\Port\QueryCache;
use Tap\Shared\Domain\Exception\NotFound;

/**
 * Resuelve el usuario y sus permisos efectivos a partir del JWT.
 *
 * Rendimiento: el resultado se cachea 5 min en Redis, así cada petición
 * autenticada NO consulta Mongo (usuario + perfiles). Cualquier cambio de
 * usuario o perfil invalida el namespace `auth` al instante.
 *
 * Seguridad: si el `tv` (token version) del JWT no coincide con el del
 * usuario (cambió contraseña, perfiles, 2FA, fue eliminado...), el token se
 * rechaza aunque su firma y expiración sean válidas.
 */
final class AuthUserProvider
{
    public const CACHE_NAMESPACE = 'auth';
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly UserRepository $users,
        private readonly ProfileRepository $profiles,
        private readonly QueryCache $cache,
    ) {
    }

    public function resolve(TokenClaims $claims): AuthenticatedUser
    {
        /** @var array{id: string, code: string, name: string, email: string, tv: int, must_change: bool, two_factor: bool, permissions: array<string, list<string>>}|null $snapshot */
        $snapshot = $this->cache->remember(self::CACHE_NAMESPACE, ['u' => $claims->subject], self::CACHE_TTL, function () use ($claims): ?array {
            try {
                $user = $this->users->find($claims->subject);
            } catch (NotFound) {
                return null;
            }

            return $user === null ? null : [
                'id' => $user->id(),
                'code' => $user->code()->value(),
                'name' => $user->name(),
                'email' => $user->email()->value(),
                'tv' => $user->tokenVersion(),
                'must_change' => $user->mustChangePassword(),
                'two_factor' => $user->twoFactor()->enabled,
                'permissions' => $this->permissionsFor($user)->toArray(),
            ];
        });

        if ($snapshot === null || $snapshot['tv'] !== $claims->tokenVersion) {
            throw AuthenticationError::tokenRevoked();
        }

        return new AuthenticatedUser(
            id: $snapshot['id'],
            code: $snapshot['code'],
            name: $snapshot['name'],
            email: $snapshot['email'],
            permissions: $snapshot['permissions'],
            mustChangePassword: $snapshot['must_change'],
            twoFactorEnabled: $snapshot['two_factor'],
            tokenId: $claims->tokenId,
            tokenExpiresAt: $claims->expiresAt,
        );
    }

    public function permissionsFor(User $user): PermissionSet
    {
        return PermissionSet::fromProfiles($this->profiles->findMany($user->profileIds()));
    }

    public function invalidate(): void
    {
        $this->cache->invalidate(self::CACHE_NAMESPACE);
    }
}
