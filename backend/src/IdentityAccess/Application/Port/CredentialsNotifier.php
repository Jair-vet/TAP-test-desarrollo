<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

use DateTimeImmutable;
use Tap\IdentityAccess\Domain\User\User;

/**
 * "Enviar credenciales al correo registrado". El adaptador actual encola un
 * Mailable (Laravel Queue + Redis) y en local se ve en Mailpit.
 */
interface CredentialsNotifier
{
    public function sendWelcome(User $user, string $plainPassword): void;

    public function sendTemporaryPassword(User $user, string $plainPassword, DateTimeImmutable $expiresAt): void;
}
