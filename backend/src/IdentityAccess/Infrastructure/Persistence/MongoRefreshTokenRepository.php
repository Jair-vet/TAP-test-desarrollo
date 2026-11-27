<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use Tap\IdentityAccess\Domain\Session\RefreshToken;
use Tap\IdentityAccess\Domain\Session\RefreshTokenRepository;
use Tap\Shared\Infrastructure\Persistence\Mongo\Mongo;

/**
 * Refresh tokens en MongoDB.
 * Índice TTL sobre `expires_at`: Mongo borra solo los vencidos (limpieza
 * automática sin cron). Índice único sobre `token_hash`.
 */
final class MongoRefreshTokenRepository implements RefreshTokenRepository
{
    private Collection $collection;

    public function __construct(Connection $connection)
    {
        $this->collection = $connection->getCollection('refresh_tokens');
    }

    public function nextId(): string
    {
        return (string) new ObjectId();
    }

    public function save(RefreshToken $token): void
    {
        $this->collection->replaceOne(['_id' => new ObjectId($token->id)], [
            'user_id' => new ObjectId($token->userId),
            'family_id' => $token->familyId,
            'token_hash' => $token->tokenHash,
            'expires_at' => Mongo::date($token->expiresAt),
            'created_at' => Mongo::date($token->createdAt),
            'revoked_at' => $token->revokedAt() === null ? null : Mongo::date($token->revokedAt()),
            'replaced_by' => $token->replacedBy(),
            'ip' => $token->ip,
            'user_agent' => $token->userAgent,
        ], ['upsert' => true]);
    }

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        $doc = $this->collection->findOne(['token_hash' => $tokenHash], ['typeMap' => ['root' => 'array', 'document' => 'array']]);
        if (! is_array($doc)) {
            return null;
        }

        return new RefreshToken(
            id: (string) $doc['_id'],
            userId: (string) $doc['user_id'],
            familyId: (string) $doc['family_id'],
            tokenHash: (string) $doc['token_hash'],
            expiresAt: Mongo::toDate($doc['expires_at']) ?? new DateTimeImmutable('@0'),
            createdAt: Mongo::toDate($doc['created_at']) ?? new DateTimeImmutable('@0'),
            ip: isset($doc['ip']) ? (string) $doc['ip'] : null,
            userAgent: isset($doc['user_agent']) ? (string) $doc['user_agent'] : null,
            revokedAt: Mongo::toDate($doc['revoked_at'] ?? null),
            replacedBy: isset($doc['replaced_by']) ? (string) $doc['replaced_by'] : null,
        );
    }

    public function revokeFamily(string $familyId, DateTimeImmutable $now): void
    {
        $this->collection->updateMany(
            ['family_id' => $familyId, 'revoked_at' => null],
            ['$set' => ['revoked_at' => Mongo::date($now)]],
        );
    }

    public function revokeAllForUser(string $userId, DateTimeImmutable $now, ?string $exceptFamilyId = null): void
    {
        $filter = ['user_id' => new ObjectId($userId), 'revoked_at' => null];
        if ($exceptFamilyId !== null) {
            $filter['family_id'] = ['$ne' => $exceptFamilyId];
        }

        $this->collection->updateMany($filter, ['$set' => ['revoked_at' => Mongo::date($now)]]);
    }
}
