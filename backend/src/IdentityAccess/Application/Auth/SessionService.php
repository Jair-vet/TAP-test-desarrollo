<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Auth;

use Tap\IdentityAccess\Application\Authorization\AuthUserProvider;
use Tap\IdentityAccess\Application\Port\PasswordHasher;
use Tap\IdentityAccess\Application\Port\SecretCipher;
use Tap\IdentityAccess\Application\Port\TokenRevocationList;
use Tap\IdentityAccess\Application\Port\TokenService;
use Tap\IdentityAccess\Application\Port\TotpProvider;
use Tap\IdentityAccess\Application\SecurityPolicy;
use Tap\IdentityAccess\Domain\Exception\AuthenticationError;
use Tap\IdentityAccess\Domain\Session\RefreshToken;
use Tap\IdentityAccess\Domain\Session\RefreshTokenRepository;
use Tap\IdentityAccess\Domain\User\User;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\Shared\Application\Actor;
use Tap\Shared\Application\Port\AuditTrail;
use Tap\Shared\Application\Port\Clock;
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Domain\Exception\NotFound;
use Tap\Shared\Domain\ValueObject\Email;

/**
 * Casos de uso de sesión: login, segundo factor, renovación y cierre.
 *
 * Flujo completo:
 *   1) login(correo, contraseña)
 *        ├─ sin 2FA → sesión (JWT access 10 min + refresh 7 días en cookie HttpOnly)
 *        └─ con 2FA → mfa_token (5 min)
 *   2) completeTwoFactor(mfa_token, código TOTP | código de respaldo) → sesión
 *   3) refresh(cookie) → rota el refresh token y emite un JWT nuevo
 *   4) logout → revoca el JWT (jti en Redis) y la familia del refresh token
 */
final class SessionService
{
    private static ?string $dummyHash = null;

    public function __construct(
        private readonly UserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly PasswordHasher $hasher,
        private readonly TokenService $tokens,
        private readonly TokenRevocationList $revocations,
        private readonly TotpProvider $totp,
        private readonly SecretCipher $cipher,
        private readonly AuthUserProvider $authUsers,
        private readonly Clock $clock,
        private readonly AuditTrail $audit,
        private readonly SecurityPolicy $policy,
    ) {
    }

    public function login(string $email, string $password, Actor $actor): LoginResult
    {
        $now = $this->clock->now();
        $user = $this->findUserByEmail($email);

        if ($user === null) {
            // Mismo costo de CPU que un usuario real: evita enumerar correos por tiempo de respuesta
            $this->hasher->verify($password, $this->dummyHash());
            $this->audit->record('login_failed', 'auth', null, null, $actor, null, null, ['email' => mb_substr($email, 0, 150), 'reason' => 'unknown_user']);

            throw AuthenticationError::invalidCredentials();
        }

        $this->assertNotLocked($user);

        $validPassword = $this->hasher->verify($password, $user->passwordHash());
        $validTemporary = ! $validPassword
            && $user->temporaryPassword() !== null
            && ! $user->temporaryPassword()->isExpired($now)
            && $this->hasher->verify($password, $user->temporaryPassword()->hash);

        if (! $validPassword && ! $validTemporary) {
            $this->failLogin($user, $actor, 'bad_password');
        }

        if ($validTemporary) {
            $user->promoteTemporaryPassword($now);
            $this->audit->record('temporary_password_used', 'user', $user->id(), $user->code()->value(), $actor);
        } elseif ($this->hasher->needsRehash($user->passwordHash())) {
            $user->rehashPassword($this->hasher->hash($password));
        }

        $this->users->save($user);

        if ($user->twoFactor()->enabled) {
            return LoginResult::twoFactorRequired($this->tokens->issue($user, TokenService::TYPE_MFA));
        }

        return LoginResult::authenticated($this->startSession($user, $actor));
    }

    public function completeTwoFactor(string $mfaToken, ?string $code, ?string $recoveryCode, Actor $actor): SessionTokens
    {
        $claims = $this->tokens->decode($mfaToken, TokenService::TYPE_MFA);

        if ($this->revocations->isRevoked($claims->tokenId)) {
            throw AuthenticationError::tokenRevoked();
        }

        $user = $this->users->find($claims->subject);
        if ($user === null || $user->tokenVersion() !== $claims->tokenVersion || ! $user->twoFactor()->enabled) {
            throw AuthenticationError::tokenInvalid();
        }

        $this->assertNotLocked($user);

        if ($recoveryCode !== null && $recoveryCode !== '') {
            $hash = self::hashRecoveryCode($recoveryCode);
            if (! $user->twoFactor()->hasRecoveryCode($hash)) {
                $this->failLogin($user, $actor, 'bad_recovery_code', AuthenticationError::invalidTwoFactorCode());
            }
            $user->consumeRecoveryCode($hash);
            $this->audit->record('recovery_code_used', 'user', $user->id(), $user->code()->value(), $actor, null, null, [
                'remaining' => count($user->twoFactor()->recoveryCodeHashes),
            ]);
        } else {
            $secret = $this->cipher->decrypt((string) $user->twoFactor()->secret);
            $step = $this->totp->verify($secret, (string) $code, $user->twoFactor()->lastUsedStep);
            if ($step === null) {
                $this->failLogin($user, $actor, 'bad_totp', AuthenticationError::invalidTwoFactorCode());
            }
            $user->markTotpStepUsed($step);
        }

        // El token MFA es de un solo uso
        $this->revocations->revoke($claims->tokenId, $claims->expiresAt);

        return $this->startSession($user, $actor);
    }

    public function refresh(string $plainRefreshToken, Actor $actor): SessionTokens
    {
        $now = $this->clock->now();
        $stored = $this->refreshTokens->findByHash(self::hashRefreshToken($plainRefreshToken));

        if ($stored === null || $stored->isExpired($now)) {
            throw AuthenticationError::invalidRefreshToken();
        }

        if ($stored->isRevoked()) {
            // Reúso de un token ya rotado: posible robo. Se mata toda la familia.
            $this->refreshTokens->revokeFamily($stored->familyId, $now);
            $this->audit->record('session_reuse_detected', 'auth', $stored->userId, null, $actor, null, null, ['family' => $stored->familyId]);

            throw AuthenticationError::refreshTokenReused();
        }

        $user = $this->users->find($stored->userId);
        if ($user === null) {
            throw AuthenticationError::invalidRefreshToken();
        }

        [$plain, $next] = $this->newRefreshToken($user, $stored->familyId, $actor);
        $stored->rotate($next->id, $now);
        $this->refreshTokens->save($stored);
        $this->refreshTokens->save($next);

        return new SessionTokens(
            $this->tokens->issue($user, TokenService::TYPE_ACCESS),
            $plain,
            $next->expiresAt->getTimestamp(),
            $user,
            $this->authUsers->permissionsFor($user),
        );
    }

    public function logout(?string $accessTokenId, ?int $accessExpiresAt, ?string $plainRefreshToken, Actor $actor, ?string $userId = null): void
    {
        if ($accessTokenId !== null && $accessExpiresAt !== null) {
            $this->revocations->revoke($accessTokenId, $accessExpiresAt);
        }

        if ($plainRefreshToken !== null && $plainRefreshToken !== '') {
            $stored = $this->refreshTokens->findByHash(self::hashRefreshToken($plainRefreshToken));
            if ($stored !== null) {
                $this->refreshTokens->revokeFamily($stored->familyId, $this->clock->now());
                $userId ??= $stored->userId;
            }
        }

        // El endpoint no exige token (debe funcionar aunque haya expirado), así que
        // identificamos al usuario por el JWT o por el refresh token para la bitácora.
        $user = $userId !== null ? $this->users->find($userId) : null;
        $who = $user === null ? $actor : new Actor(
            $user->id(),
            $user->code()->value(),
            $user->name(),
            $user->email()->value(),
            $actor->ip,
            $actor->userAgent,
        );

        $this->audit->record('logout', 'auth', $who->id, $who->code, $who);
    }

    /**
     * Emite una sesión nueva. También la usan cambio de contraseña y 2FA
     * para "rotar" la sesión tras un cambio sensible.
     */
    public function startSession(User $user, Actor $actor): SessionTokens
    {
        $now = $this->clock->now();
        $user->registerSuccessfulLogin($now);
        $this->users->save($user);

        [$plain, $refresh] = $this->newRefreshToken($user, bin2hex(random_bytes(16)), $actor);
        $this->refreshTokens->save($refresh);

        $this->audit->record('login', 'auth', $user->id(), $user->code()->value(), new Actor(
            $user->id(),
            $user->code()->value(),
            $user->name(),
            $user->email()->value(),
            $actor->ip,
            $actor->userAgent,
        ));

        return new SessionTokens(
            $this->tokens->issue($user, TokenService::TYPE_ACCESS),
            $plain,
            $refresh->expiresAt->getTimestamp(),
            $user,
            $this->authUsers->permissionsFor($user),
        );
    }

    public static function hashRecoveryCode(string $code): string
    {
        return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''));
    }

    private static function hashRefreshToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @return array{0: string, 1: RefreshToken}
     */
    private function newRefreshToken(User $user, string $familyId, Actor $actor): array
    {
        $now = $this->clock->now();
        $plain = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '='); // 384 bits de entropía

        return [$plain, new RefreshToken(
            id: $this->refreshTokens->nextId(),
            userId: $user->id(),
            familyId: $familyId,
            tokenHash: self::hashRefreshToken($plain),
            expiresAt: $now->modify("+{$this->policy->refreshTtlSeconds} seconds"),
            createdAt: $now,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        )];
    }

    private function findUserByEmail(string $email): ?User
    {
        try {
            return $this->users->findByEmail(Email::from($email));
        } catch (InvalidValue|NotFound) {
            return null;
        }
    }

    private function assertNotLocked(User $user): void
    {
        $lockedUntil = $user->lockedUntil();
        if ($lockedUntil !== null && $user->isLocked($this->clock->now())) {
            throw AuthenticationError::accountLocked($lockedUntil);
        }
    }

    /**
     * Registra el intento fallido (bloquea al llegar al máximo) y lanza el error.
     */
    private function failLogin(User $user, Actor $actor, string $reason, ?AuthenticationError $error = null): never
    {
        $now = $this->clock->now();
        $user->registerFailedLogin($now, $this->policy->maxLoginAttempts, $this->policy->lockoutMinutes);
        $this->users->save($user);

        $this->audit->record('login_failed', 'auth', $user->id(), $user->code()->value(), $actor, null, null, ['reason' => $reason]);

        if ($user->isLocked($now) && $user->lockedUntil() !== null) {
            $this->audit->record('account_locked', 'user', $user->id(), $user->code()->value(), $actor, null, null, [
                'until' => $user->lockedUntil()->format(DATE_ATOM),
            ]);

            throw AuthenticationError::accountLocked($user->lockedUntil());
        }

        throw $error ?? AuthenticationError::invalidCredentials();
    }

    private function dummyHash(): string
    {
        return self::$dummyHash ??= $this->hasher->hash(bin2hex(random_bytes(16)));
    }
}
