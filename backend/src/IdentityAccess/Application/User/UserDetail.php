<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\User;

use Tap\IdentityAccess\Domain\Profile\Profile;
use Tap\IdentityAccess\Domain\User\User;

/** Read model del "Detalle de usuario": usuario + sus perfiles. */
final class UserDetail
{
    /**
     * @param list<Profile> $profiles
     */
    public function __construct(
        public readonly User $user,
        public readonly array $profiles,
    ) {
    }
}
