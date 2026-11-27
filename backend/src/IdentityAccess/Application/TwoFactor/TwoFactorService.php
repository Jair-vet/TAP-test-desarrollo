<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\TwoFactor;

use Tap\IdentityAccess\Application\Auth\SessionService;
use Tap\IdentityAccess\Application\Auth\SessionTokens;
use Tap\IdentityAccess\Application\Authorization\AuthUserProvider;
use Tap\IdentityAccess\Application\Port\PasswordGenerator;
use Tap\IdentityAccess\Application\Port\PasswordHasher;
use Tap\IdentityAccess\Application\Port\SecretCipher;
use Tap\IdentityAccess\Application\Port\TotpProvider;
use Tap\IdentityAccess\Application\SecurityPolicy;
use Tap\IdentityAccess\Domain\Exception\AuthenticationError;
use Tap\IdentityAccess\Domain\Exception\IdentityConflict;
use Tap\IdentityAccess\Domain\Session\RefreshTokenRepository;
use Tap\IdentityAccess\Domain\User\User;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\Shared\Application\Actor;
use Tap\Shared\Application\Port\AuditTrail;
use Tap\Shared\Application\Port\Clock;
use Tap\Shared\Domain\Exception\NotFound;

/**
 * Autenticación en dos pasos (TOTP, RFC 6238).
 *
 *  setup()   → genera semilla (se guarda cifrada como "pendiente") + QR
 *  confirm() → el usuario captura un código de su app; si es válido se
 *              activa y se entregan 8 códigos de respaldo (se muestran UNA vez)
 *  disable() → requiere contraseña + código vigente
 */
final class TwoFactorService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly TotpProvider $totp,
        private readonly SecretCipher $cipher,
        private readonly PasswordHasher $hasher,
        private readonly PasswordGenerator $generator,
        private readonly SessionService $sessions,
        private readonly AuthUserProvider $authUsers,
        private readonly Clock $clock,
        private readonly AuditTrail $audit,
        private readonly SecurityPolicy $policy,
    ) {
    }

    /**
     * @return array{enabled: bool, recovery_codes_remaining: int, enabled_at: ?string, required: bool}
     */
    public function status(string $userId): array
    {
        $user = $this->user($userId);

        return [
            'enabled' => $user->twoFactor()->enabled,
            'recovery_codes_remaining' => count($user->twoFactor()->recoveryCodeHashes),
            'enabled_at' => $user->twoFactor()->enabledAt?->format(DATE_ATOM),
            'required' => $this->policy->twoFactorRequired,
        ];
    }

    public function setup(string $userId): TwoFactorSetup
    {
        $user = $this->user($userId);
        if ($user->twoFactor()->enabled) {
            throw IdentityConflict::twoFactorAlreadyEnabled();
        }

        $secret = $this->totp->generateSecret();
        $user->startTwoFactorSetup($this->cipher->encrypt($secret));
        $this->users->save($user);

        $uri = $this->totp->provisioningUri($user->email()->value(), $secret);

        return new TwoFactorSetup($secret, $uri, $this->totp->qrCodeSvg($uri));
    }

    /**
     * @return array{recovery_codes: list<string>, session: SessionTokens}
     */
    public function confirm(string $userId, string $code, Actor $actor): array
    {
        $user = $this->user($userId);
        if ($user->twoFactor()->enabled) {
            throw IdentityConflict::twoFactorAlreadyEnabled();
        }
        if ($user->twoFactor()->pendingSecret === null) {
            throw IdentityConflict::twoFactorSetupNotStarted();
        }

        $step = $this->totp->verify($this->cipher->decrypt($user->twoFactor()->pendingSecret), $code, null);
        if ($step === null) {
            throw AuthenticationError::invalidTwoFactorCode();
        }

        $codes = $this->generator->recoveryCodes($this->policy->recoveryCodesCount);
        $user->enableTwoFactor(array_map(SessionService::hashRecoveryCode(...), $codes), $step, $this->clock->now());
        $this->users->save($user);

        $this->audit->record('two_factor_enabled', 'user', $user->id(), $user->code()->value(), $actor);

        return ['recovery_codes' => $codes, 'session' => $this->rotateSessions($user, $actor)];
    }

    public function disable(string $userId, string $password, string $code, Actor $actor): SessionTokens
    {
        $user = $this->user($userId);
        if (! $user->twoFactor()->enabled) {
            throw IdentityConflict::twoFactorNotEnabled();
        }

        $this->assertPasswordAndCode($user, $password, $code);

        $user->disableTwoFactor($this->clock->now());
        $this->users->save($user);
        $this->audit->record('two_factor_disabled', 'user', $user->id(), $user->code()->value(), $actor);

        return $this->rotateSessions($user, $actor);
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(string $userId, string $password, Actor $actor): array
    {
        $user = $this->user($userId);
        if (! $user->twoFactor()->enabled) {
            throw IdentityConflict::twoFactorNotEnabled();
        }
        if (! $this->hasher->verify($password, $user->passwordHash())) {
            throw AuthenticationError::invalidPassword();
        }

        $codes = $this->generator->recoveryCodes($this->policy->recoveryCodesCount);
        $user->replaceRecoveryCodes(array_map(SessionService::hashRecoveryCode(...), $codes));
        $this->users->save($user);
        $this->audit->record('recovery_codes_regenerated', 'user', $user->id(), $user->code()->value(), $actor);

        return $codes;
    }

    private function assertPasswordAndCode(User $user, string $password, string $code): void
    {
        if (! $this->hasher->verify($password, $user->passwordHash())) {
            throw AuthenticationError::invalidPassword();
        }

        $step = $this->totp->verify($this->cipher->decrypt((string) $user->twoFactor()->secret), $code, $user->twoFactor()->lastUsedStep);
        if ($step === null) {
            throw AuthenticationError::invalidTwoFactorCode();
        }
        $user->markTotpStepUsed($step);
    }

    /** Cambio sensible: se cierran las demás sesiones y se emite una nueva. */
    private function rotateSessions(User $user, Actor $actor): SessionTokens
    {
        $this->refreshTokens->revokeAllForUser($user->id(), $this->clock->now());
        $this->authUsers->invalidate();

        return $this->sessions->startSession($user, $actor);
    }

    private function user(string $userId): User
    {
        return $this->users->find($userId) ?? throw NotFound::entity('Usuario', $userId);
    }
}
