<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Mail;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailables\Address;
use Tap\IdentityAccess\Application\Port\CredentialsNotifier;
use Tap\IdentityAccess\Domain\User\User;

/** Adaptador de correo: encola el Mailable (lo envía el contenedor `worker`). */
final class MailCredentialsNotifier implements CredentialsNotifier
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly string $loginUrl,
        private readonly string $timezone,
    ) {
    }

    public function sendWelcome(User $user, string $plainPassword): void
    {
        $this->mailer->to(new Address($user->email()->value(), $user->name()))->queue(new CredentialsMail(
            'welcome',
            $user->name(),
            $user->email()->value(),
            $plainPassword,
            null,
            $this->loginUrl,
        ));
    }

    public function sendTemporaryPassword(User $user, string $plainPassword, DateTimeImmutable $expiresAt): void
    {
        $this->mailer->to(new Address($user->email()->value(), $user->name()))->queue(new CredentialsMail(
            'reset',
            $user->name(),
            $user->email()->value(),
            $plainPassword,
            $expiresAt->setTimezone(new DateTimeZone($this->timezone))->format('d/m/Y H:i'),
            $this->loginUrl,
        ));
    }
}
