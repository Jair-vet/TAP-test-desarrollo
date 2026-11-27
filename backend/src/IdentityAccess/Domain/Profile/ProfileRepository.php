<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Profile;

use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;

/**
 * Puerto de persistencia de perfiles (implementado por MongoProfileRepository).
 */
interface ProfileRepository
{
    public function nextId(): string;

    public function find(string $id): ?Profile;

    /**
     * @param list<string> $ids
     *
     * @return list<Profile>
     */
    public function findMany(array $ids): array;

    public function save(Profile $profile): void;

    public function delete(Profile $profile): void;

    public function existsByName(string $name, ?string $exceptId = null): bool;

    /** @return Page<Profile> */
    public function paginate(ListQuery $query): Page;

    /** @return iterable<Profile> */
    public function cursor(ListQuery $query): iterable;

    /** @return list<Profile> Todos, ordenados por nombre (para selects) */
    public function all(): array;

    public function count(): int;
}
