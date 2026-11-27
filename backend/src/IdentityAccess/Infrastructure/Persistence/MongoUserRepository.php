<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use Tap\IdentityAccess\Domain\User\PhoneNumber;
use Tap\IdentityAccess\Domain\User\TemporaryPassword;
use Tap\IdentityAccess\Domain\User\TwoFactorSettings;
use Tap\IdentityAccess\Domain\User\User;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;
use Tap\Shared\Domain\ValueObject\Email;
use Tap\Shared\Domain\ValueObject\EntityCode;
use Tap\Shared\Infrastructure\Persistence\Mongo\Mongo;

/**
 * Adaptador MongoDB de usuarios.
 *
 * Modelado NoSQL:
 *  - `profile_ids`: arreglo de REFERENCIAS (ObjectId) a perfiles, porque un
 *    perfil se comparte entre muchos usuarios (índice multikey).
 *  - `security`: subdocumento EMBEBIDO (contraseña, bloqueo, versión de token),
 *    porque siempre se lee junto con el usuario y no se comparte.
 *  - `two_factor`: subdocumento embebido con la semilla cifrada.
 *
 * El listado usa una PROYECCIÓN que excluye `security` y `two_factor`: los
 * hashes nunca salen de Mongo si no se necesitan.
 */
final class MongoUserRepository implements UserRepository
{
    private const SORTABLE = [
        'code' => 'code',
        'email' => 'email',
        'name' => 'name',
        'created_at' => 'created_at',
    ];

    private const LIST_PROJECTION = ['search' => 0, 'security' => 0, 'two_factor' => 0];

    private Collection $collection;

    public function __construct(Connection $connection)
    {
        $this->collection = $connection->getCollection('users');
    }

    public function nextId(): string
    {
        return (string) new ObjectId();
    }

    public function find(string $id): ?User
    {
        $doc = $this->collection->findOne(['_id' => Mongo::objectId($id, 'Usuario')], ['typeMap' => self::typeMap()]);

        return is_array($doc) ? $this->hydrate($doc) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $doc = $this->collection->findOne(['email' => $email->value()], ['typeMap' => self::typeMap()]);

        return is_array($doc) ? $this->hydrate($doc) : null;
    }

    public function existsByEmail(Email $email, ?string $exceptId = null): bool
    {
        $filter = ['email' => $email->value()];
        if ($exceptId !== null) {
            $filter['_id'] = ['$ne' => Mongo::objectId($exceptId, 'Usuario')];
        }

        return $this->collection->countDocuments($filter, ['limit' => 1]) > 0;
    }

    public function save(User $user): void
    {
        $tf = $user->twoFactor();
        $temp = $user->temporaryPassword();

        $this->collection->replaceOne(['_id' => new ObjectId($user->id())], [
            'code' => $user->code()->value(),
            'name' => $user->name(),
            'email' => $user->email()->value(),
            'phone' => $user->phone()?->value(),
            'photo_path' => $user->photoPath(),
            'profile_ids' => Mongo::objectIds($user->profileIds()),
            'search' => Mongo::searchable($user->code()->value(), $user->name(), $user->email()->value()),
            'security' => [
                'password_hash' => $user->passwordHash(),
                'must_change_password' => $user->mustChangePassword(),
                'password_changed_at' => self::date($user->passwordChangedAt()),
                'temporary_password' => $temp === null ? null : [
                    'hash' => $temp->hash,
                    'expires_at' => Mongo::date($temp->expiresAt),
                ],
                'token_version' => $user->tokenVersion(),
                'failed_login_attempts' => $user->failedLoginAttempts(),
                'locked_until' => self::date($user->lockedUntil()),
                'last_login_at' => self::date($user->lastLoginAt()),
            ],
            'two_factor' => [
                'enabled' => $tf->enabled,
                'secret' => $tf->secret,
                'pending_secret' => $tf->pendingSecret,
                'recovery_codes' => $tf->recoveryCodeHashes,
                'last_used_step' => $tf->lastUsedStep,
                'enabled_at' => self::date($tf->enabledAt),
            ],
            'created_at' => Mongo::date($user->createdAt()),
            'updated_at' => Mongo::date($user->updatedAt()),
        ], ['upsert' => true]);
    }

    public function delete(User $user): void
    {
        $this->collection->deleteOne(['_id' => new ObjectId($user->id())]);
    }

    public function paginate(ListQuery $query): Page
    {
        $filter = Mongo::searchFilter($query->search);
        $window = Mongo::window($query);

        $items = [];
        foreach ($this->collection->find($filter, [
            'sort' => Mongo::sort($query, self::SORTABLE),
            'skip' => $window['skip'],
            'limit' => $window['limit'],
            'projection' => self::LIST_PROJECTION,
            'typeMap' => self::typeMap(),
        ]) as $doc) {
            $items[] = $this->hydrate($doc);
        }

        $total = $filter === [] ? $this->collection->estimatedDocumentCount() : $this->collection->countDocuments($filter);

        return new Page($items, $total, $query->page, $query->perPage);
    }

    public function cursor(ListQuery $query): iterable
    {
        foreach ($this->collection->find(Mongo::searchFilter($query->search), [
            'sort' => Mongo::sort($query, self::SORTABLE),
            'projection' => self::LIST_PROJECTION,
            'typeMap' => self::typeMap(),
            'batchSize' => 500,
        ]) as $doc) {
            yield $this->hydrate($doc);
        }
    }

    public function countWithProfile(string $profileId): int
    {
        return $this->collection->countDocuments(['profile_ids' => Mongo::objectId($profileId, 'Perfil')]);
    }

    public function count(): int
    {
        return $this->collection->estimatedDocumentCount();
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function hydrate(array $doc): User
    {
        /** @var array<string, mixed> $sec */
        $sec = $doc['security'] ?? [];
        /** @var array<string, mixed> $tf */
        $tf = $doc['two_factor'] ?? [];
        /** @var array<string, mixed>|null $temp */
        $temp = $sec['temporary_password'] ?? null;

        $createdAt = Mongo::toDate($doc['created_at']) ?? new DateTimeImmutable();

        return User::restore(
            id: (string) $doc['_id'],
            code: EntityCode::fromString((string) $doc['code']),
            name: (string) $doc['name'],
            email: Email::from((string) $doc['email']),
            phone: isset($doc['phone']) && is_string($doc['phone']) ? PhoneNumber::from($doc['phone']) : null,
            photoPath: isset($doc['photo_path']) && is_string($doc['photo_path']) ? $doc['photo_path'] : null,
            profileIds: array_values(array_map('strval', (array) ($doc['profile_ids'] ?? []))),
            passwordHash: (string) ($sec['password_hash'] ?? ''),
            mustChangePassword: (bool) ($sec['must_change_password'] ?? false),
            temporaryPassword: is_array($temp) && ($expires = Mongo::toDate($temp['expires_at'] ?? null)) !== null
                ? new TemporaryPassword((string) $temp['hash'], $expires)
                : null,
            tokenVersion: (int) ($sec['token_version'] ?? 1),
            failedLoginAttempts: (int) ($sec['failed_login_attempts'] ?? 0),
            lockedUntil: Mongo::toDate($sec['locked_until'] ?? null),
            twoFactor: new TwoFactorSettings(
                enabled: (bool) ($tf['enabled'] ?? false),
                secret: isset($tf['secret']) ? (string) $tf['secret'] : null,
                pendingSecret: isset($tf['pending_secret']) ? (string) $tf['pending_secret'] : null,
                recoveryCodeHashes: array_values(array_map('strval', (array) ($tf['recovery_codes'] ?? []))),
                lastUsedStep: isset($tf['last_used_step']) ? (int) $tf['last_used_step'] : null,
                enabledAt: Mongo::toDate($tf['enabled_at'] ?? null),
            ),
            lastLoginAt: Mongo::toDate($sec['last_login_at'] ?? null),
            passwordChangedAt: Mongo::toDate($sec['password_changed_at'] ?? null),
            createdAt: $createdAt,
            updatedAt: Mongo::toDate($doc['updated_at'] ?? null) ?? $createdAt,
        );
    }

    private static function date(?DateTimeImmutable $date): ?\MongoDB\BSON\UTCDateTime
    {
        return $date === null ? null : Mongo::date($date);
    }

    /**
     * @return array<string, string>
     */
    private static function typeMap(): array
    {
        return ['root' => 'array', 'document' => 'array', 'array' => 'array'];
    }
}
