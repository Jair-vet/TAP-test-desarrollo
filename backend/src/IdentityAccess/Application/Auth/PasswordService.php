<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Auth;

use DateTimeImmutable;
use Tap\IdentityAccess\Application\Authorization\AuthUserProvider;
use Tap\IdentityAccess\Application\Port\CredentialsNotifier;
use Tap\IdentityAccess\Application\Port\PasswordGenerator;
use Tap\IdentityAccess\Application\Port\PasswordHasher;
use Tap\IdentityAccess\Application\SecurityPolicy;
use Tap\IdentityAccess\Domain\Exception\AuthenticationError;
use Tap\IdentityAccess\Domain\Exception\IdentityConflict;
use Tap\IdentityAccess\Domain\Exception\UserNotRegistered;
use Tap\IdentityAccess\Domain\Session\RefreshTokenRepository;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\Shared\Application\Actor;
use Tap\Shared\Application\Port\AuditTrail;
use Tap\Shared\Application\Port\Clock;
use Tap\Shared\Domain\Exception\NotFound;
use Tap\Shared\Domain\ValueObject\Email;

/**
 * Recuperación y cambio de contraseña.
 *
 * Recuperación (requisito del examen):
 *   1. Validar que el usuario existe.
 *   2. Enviar credenciales al correo registrado.
 * Implementación segura: se genera una contraseña TEMPORAL aleatoria con
 * caducidad (60 min). La actual sigue funcionando hasta que se use la
 * temporal; al entrar con ella se obliga a definir una nueva.
 */
final class PasswordService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly PasswordHasher $hasher,
        private readonly PasswordGenerator $generator,
        private readonly CredentialsNotifier $notifier,
        private readonly SessionService $sessions,
        private readonly AuthUserProvider $authUsers,
        private readonly Clock $clock,
        private readonly AuditTrail $audit,
        private readonly SecurityPolicy $policy,
    ) {
    }

    public function requestReset(string $email, Actor $actor): DateTimeImmutable
    {
        $user = $this->users->findByEmail(Email::from($email));

        if ($user === null) {
            $this->audit->record('password_reset_unknown_email', 'auth', null, null, $actor, null, null, ['email' => mb_substr($email, 0, 150)]);

            throw UserNotRegistered::forEmail();
        }

        $now = $this->clock->now();
        $expiresAt = $now->modify("+{$this->policy->temporaryPasswordTtlMinutes} minutes");
        $temporary = $this->generator->generate();

        $user->issueTemporaryPassword($this->hasher->hash($temporary), $expiresAt, $now);
        $this->users->save($user);

        $this->notifier->sendTemporaryPassword($user, $temporary, $expiresAt);
        $this->audit->record('password_reset_requested', 'user', $user->id(), $user->code()->value(), $actor);

        return $expiresAt;
    }

    /**
     * Cambia la contraseña, cierra TODAS las sesiones y devuelve una sesión
     * nueva para el dispositivo actual.
     */
    public function change(string $userId, string $currentPassword, string $newPassword, Actor $actor): SessionTokens
    {
        $user = $this->users->find($userId) ?? throw NotFound::entity('Usuario', $userId);

        if (! $this->hasher->verify($currentPassword, $user->passwordHash())) {
            $this->audit->record('password_change_failed', 'user', $user->id(), $user->code()->value(), $actor);

            throw AuthenticationError::invalidPassword();
        }

        if ($this->hasher->verify($newPassword, $user->passwordHash())) {
            throw IdentityConflict::passwordReused();
        }

        $now = $this->clock->now();
        $user->changePassword($this->hasher->hash($newPassword), $now);
        $this->users->save($user);

        $this->refreshTokens->revokeAllForUser($user->id(), $now);
        $this->authUsers->invalidate();
        $this->audit->record('password_changed', 'user', $user->id(), $user->code()->value(), $actor);

        return $this->sessions->startSession($user, $actor);
    }
}
