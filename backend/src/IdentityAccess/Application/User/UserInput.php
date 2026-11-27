<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\User;

/** DTO de alta/edición de usuario (ya validado por el FormRequest). */
final class UserInput
{
    /**
     * @param list<string> $profileIds
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly array $profileIds,
    ) {
    }
}
