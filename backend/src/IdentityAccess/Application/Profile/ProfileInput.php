<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Profile;

/** DTO de alta/edición de perfil. */
final class ProfileInput
{
    /**
     * @param list<array{section: string, actions: list<string>}> $sections
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $sections,
    ) {
    }
}
