<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\User;

use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;
use Tap\Shared\Domain\ValueObject\Email;

/**
 * Puerto de persistencia de usuarios (implementado por MongoUserRepository).
 * El correo es único: lo garantiza un índice único en MongoDB, además de existsByEmail().
 */
interface UserRepository
{
    public function nextId(): string;

    public function find(string $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function existsByEmail(Email $email, ?string $exceptId = null): bool;

    public function save(User $user): void;

    public function delete(User $user): void;

    /** @return Page<User> */
    public function paginate(ListQuery $query): Page;

    /** @return iterable<User> */
    public function cursor(ListQuery $query): iterable;

    public function countWithProfile(string $profileId): int;

    public function count(): int;
}
