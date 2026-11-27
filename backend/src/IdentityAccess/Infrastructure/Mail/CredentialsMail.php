<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo con credenciales (alta de usuario o recuperación).
 *
 * ShouldBeEncrypted: el job se guarda CIFRADO en la cola de Redis, así la
 * contraseña temporal no queda en texto plano mientras espera al worker.
 */
final class CredentialsMail extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $kind,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $plainPassword,
        public readonly ?string $expiresAt,
        public readonly string $loginUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->kind === 'welcome'
                ? 'Bienvenido(a): tus credenciales de acceso'
                : 'Recuperación de contraseña: contraseña temporal',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.credentials');
    }
}
