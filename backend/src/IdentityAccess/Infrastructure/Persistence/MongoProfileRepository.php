<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use Tap\IdentityAccess\Domain\Profile\Profile;
use Tap\IdentityAccess\Domain\Profile\ProfileRepository;
use Tap\IdentityAccess\Domain\Profile\SectionAccess;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;
use Tap\Shared\Domain\ValueObject\EntityCode;
use Tap\Shared\Infrastructure\Persistence\Mongo\Mongo;

/**
 * Adaptador MongoDB de perfiles. Las secciones van EMBEBIDAS (arreglo de
 * subdocumentos {section, actions}) porque pertenecen al perfil y siempre se
 * leen con él: una sola lectura, sin joins.
 */
final class MongoProfileRepository implements ProfileRepository
{
    // "name" se ordena por name_key (minúsculas, índice único): orden alfabético sin importar mayúsculas
    private const SORTABLE = ['code' => 'code', 'name' => 'name_key', 'created_at' => 'created_at'];

    private Collection $collection;

    public function __construct(Connection $connection)
    {
        $this->collection = $connection->getCollection('profiles');
    }

    public function nextId(): string
    {
        return (string) new ObjectId();
    }

    public function find(string $id): ?Profile
    {
        $doc = $this->collection->findOne(['_id' => Mongo::objectId($id, 'Perfil')], ['typeMap' => self::typeMap()]);

        return is_array($doc) ? $this->hydrate($doc) : null;
    }

    public function findMany(array $ids): array
    {
        $objectIds = Mongo::objectIds($ids);
        if ($objectIds === []) {
            return [];
        }

        $items = [];
        foreach ($this->collection->find(['_id' => ['$in' => $objectIds]], ['sort' => ['name_key' => 1], 'typeMap' => self::typeMap()]) as $doc) {
            $items[] = $this->hydrate($doc);
        }

        return $items;
    }

    public function save(Profile $profile): void
    {
        $this->collection->replaceOne(['_id' => new ObjectId($profile->id())], [
            'code' => $profile->code()->value(),
            'name' => $profile->name(),
            // Llave normalizada para unicidad sin importar mayúsculas (índice único)
            'name_key' => mb_strtolower($profile->name()),
            'description' => $profile->description(),
            'sections' => array_map(static fn (SectionAccess $a): array => $a->toArray(), $profile->sections()),
            'search_tokens' => Mongo::searchTokens($profile->code()->value(), $profile->name()),
            'created_at' => Mongo::date($profile->createdAt()),
            'updated_at' => Mongo::date($profile->updatedAt()),
        ], ['upsert' => true]);
    }

    public function delete(Profile $profile): void
    {
        $this->collection->deleteOne(['_id' => new ObjectId($profile->id())]);
    }

    public function existsByName(string $name, ?string $exceptId = null): bool
    {
        $filter = ['name_key' => mb_strtolower(trim($name))];
        if ($exceptId !== null) {
            $filter['_id'] = ['$ne' => Mongo::objectId($exceptId, 'Perfil')];
        }

        return $this->collection->countDocuments($filter, ['limit' => 1]) > 0;
    }

    public function paginate(ListQuery $query): Page
    {
        $filter = Mongo::searchFilter($query->search);
        $window = Mongo::window($query);

        $items = [];
        foreach ($this->collection->find($filter, [
            'sort' => Mongo::sort($query, self::SORTABLE, unique: ['code', 'name_key']),
            'skip' => $window['skip'],
            'limit' => $window['limit'],
            'projection' => ['search_tokens' => 0, 'name_key' => 0],
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
            'sort' => Mongo::sort($query, self::SORTABLE, unique: ['code', 'name_key']),
            'projection' => ['search_tokens' => 0, 'name_key' => 0],
            'typeMap' => self::typeMap(),
        ]) as $doc) {
            yield $this->hydrate($doc);
        }
    }

    public function all(): array
    {
        $items = [];
        foreach ($this->collection->find([], ['sort' => ['name_key' => 1], 'typeMap' => self::typeMap()]) as $doc) {
            $items[] = $this->hydrate($doc);
        }

        return $items;
    }

    public function count(): int
    {
        return $this->collection->estimatedDocumentCount();
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function hydrate(array $doc): Profile
    {
        $sections = [];
        foreach ((array) ($doc['sections'] ?? []) as $section) {
            if (is_array($section)) {
                $sections[] = SectionAccess::from((string) $section['section'], array_values(array_map('strval', (array) $section['actions'])));
            }
        }

        $createdAt = Mongo::toDate($doc['created_at']) ?? new DateTimeImmutable();

        return Profile::restore(
            (string) $doc['_id'],
            EntityCode::fromString((string) $doc['code']),
            (string) $doc['name'],
            isset($doc['description']) ? (string) $doc['description'] : null,
            $sections,
            $createdAt,
            Mongo::toDate($doc['updated_at'] ?? null) ?? $createdAt,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function typeMap(): array
    {
        return ['root' => 'array', 'document' => 'array', 'array' => 'array'];
    }
}
