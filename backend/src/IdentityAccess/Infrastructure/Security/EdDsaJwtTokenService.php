<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Security;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Tap\IdentityAccess\Application\Port\IssuedToken;
use Tap\IdentityAccess\Application\Port\TokenClaims;
use Tap\IdentityAccess\Application\Port\TokenService;
use Tap\IdentityAccess\Domain\Exception\AuthenticationError;
use Tap\IdentityAccess\Domain\User\User;
use Tap\Shared\Application\Port\Clock;
use Throwable;

/**
 * JWT firmados con EdDSA (Ed25519) usando firebase/php-jwt.
 *
 * Endurecimiento:
 *  - El algoritmo se FIJA del lado servidor (new Key(..., 'EdDSA')): un token
 *    con "alg":"none" o "HS256" (ataque de confusión de algoritmo) se rechaza.
 *  - Se validan iss, aud, exp, nbf, iat y el tipo (`typ`): un token MFA no
 *    sirve como token de acceso y viceversa.
 *  - `jti` único por token → permite revocarlo (logout) antes de que expire.
 *  - `tv` (token version) → revocación masiva por usuario.
 *  - El payload NO lleva datos sensibles (ni correo, ni permisos): un JWT es
 *    firmado, no cifrado; cualquiera puede leer su contenido en base64.
 */
final class EdDsaJwtTokenService implements TokenService
{
    private const ALGORITHM = 'EdDSA';

    public function __construct(
        private readonly JwtKeyStore $keys,
        private readonly Clock $clock,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $accessTtl,
        private readonly int $mfaTtl,
        private readonly int $leeway,
    ) {
    }

    public function issue(User $user, string $type): IssuedToken
    {
        $now = $this->clock->now()->getTimestamp();
        $ttl = $type === self::TYPE_MFA ? $this->mfaTtl : $this->accessTtl;
        $jti = (string) Str::uuid();

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => $user->id(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => $jti,
            'typ' => $type,
            'tv' => $user->tokenVersion(),
        ];

        $token = JWT::encode($payload, $this->keys->privateKey(), self::ALGORITHM, $this->keys->keyId());

        return new IssuedToken($token, $jti, $now + $ttl);
    }

    public function decode(string $token, string $expectedType): TokenClaims
    {
        if ($token === '' || strlen($token) > 4096) {
            throw AuthenticationError::tokenInvalid();
        }

        JWT::$leeway = $this->leeway;
        JWT::$timestamp = $this->clock->now()->getTimestamp();

        try {
            $claims = JWT::decode($token, new Key($this->keys->publicKey(), self::ALGORITHM));
        } catch (ExpiredException) {
            throw AuthenticationError::tokenExpired();
        } catch (Throwable) {
            throw AuthenticationError::tokenInvalid();
        } finally {
            JWT::$timestamp = null;
        }

        $valid = ($claims->iss ?? null) === $this->issuer
            && ($claims->aud ?? null) === $this->audience
            && ($claims->typ ?? null) === $expectedType
            && is_string($claims->sub ?? null)
            && is_string($claims->jti ?? null)
            && is_int($claims->tv ?? null)
            && is_int($claims->exp ?? null);

        if (! $valid) {
            throw AuthenticationError::tokenInvalid();
        }

        return new TokenClaims($claims->sub, $claims->jti, $claims->typ, $claims->tv, $claims->exp);
    }
}
