<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Resources;

use DateTimeInterface;
use Tap\IdentityAccess\Domain\Profile\Profile;
use Tap\IdentityAccess\Domain\Profile\SectionAccess;

/**
 * Convierte un perfil en JSON para la API (secciones con sus acciones y número de usuarios).
 */
final class ProfilePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Profile $profile, ?int $usersCount = null): array
    {
        $data = [
            'id' => $profile->id(),
            'code' => $profile->code()->value(),
            'name' => $profile->name(),
            'description' => $profile->description(),
            'sections' => array_map(static fn (SectionAccess $a): array => [
                'key' => $a->section->value,
                'label' => $a->section->label(),
                'actions' => $a->actionValues(),
            ], $profile->sections()),
            'created_at' => $profile->createdAt()->format(DateTimeInterface::ATOM),
            'updated_at' => $profile->updatedAt()->format(DateTimeInterface::ATOM),
        ];

        if ($usersCount !== null) {
            $data['users_count'] = $usersCount;
        }

        return $data;
    }
}
