<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Resources;

use DateTimeInterface;
use Tap\IdentityAccess\Application\User\UserDetail;
use Tap\IdentityAccess\Domain\User\User;
use Tap\IdentityAccess\Infrastructure\Storage\MediaUrlSigner;

/** Forma pública (JSON) de un usuario. Nunca expone hashes ni secretos. */
final class UserPresenter
{
    public function __construct(private readonly MediaUrlSigner $signer)
    {
    }

    /**
     * Fila del listado: Código, Usuario, Nombre, Fecha de creación.
     *
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        return [
            'id' => $user->id(),
            'code' => $user->code()->value(),
            'email' => $user->email()->value(),
            'name' => $user->name(),
            'photo_url' => $this->photoUrl($user),
            'created_at' => $user->createdAt()->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Detalle: Usuario, Nombre, Teléfono, Foto y Perfiles relacionados.
     *
     * @return array<string, mixed>
     */
    public function detail(UserDetail $detail): array
    {
        $user = $detail->user;

        return $this->summary($user) + [
            'phone' => $user->phone()?->value(),
            'profiles' => array_map(static fn ($p): array => [
                'id' => $p->id(),
                'code' => $p->code()->value(),
                'name' => $p->name(),
            ], $detail->profiles),
            'two_factor_enabled' => $user->twoFactor()->enabled,
            'must_change_password' => $user->mustChangePassword(),
            'last_login_at' => $user->lastLoginAt()?->format(DateTimeInterface::ATOM),
            'updated_at' => $user->updatedAt()->format(DateTimeInterface::ATOM),
        ];
    }

    private function photoUrl(User $user): ?string
    {
        return $user->photoPath() === null ? null : $this->signer->userPhotoUrl($user->id(), $user->photoPath());
    }
}
