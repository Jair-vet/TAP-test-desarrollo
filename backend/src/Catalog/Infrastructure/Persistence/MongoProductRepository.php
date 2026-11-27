<?php

declare(strict_types=1);

namespace Tap\Catalog\Infrastructure\Persistence;

use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use Tap\Catalog\Domain\Price;
use Tap\Catalog\Domain\Product;
use Tap\Catalog\Domain\ProductRepository;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;
use Tap\Shared\Domain\ValueObject\EntityCode;
use Tap\Shared\Infrastructure\Persistence\Mongo\Mongo;

/**
 * Adaptador MongoDB del repositorio de productos.
 *
 * Documento:
 * {
 *   _id: ObjectId, code: "PRD-000001", name, brand,
 *   price: Decimal128("125.50"),          // decimal exacto, no float
 *   search: "prd-000001 casco 3m",        // texto normalizado para búsqueda
 *   created_at: ISODate, updated_at: ISODate
 * }
 * Índices (ver migración): code único, created_at, name, price, search.
 */
final class MongoProductRepository implements ProductRepository
{
    private const SORTABLE = [
        'code' => 'code',
        'name' => 'name',
        'brand' => 'brand',
        'price' => 'price',
        'created_at' => 'created_at',
    ];

    private Collection $collection;

    public function __construct(Connection $connection)
    {
        $this->collection = $connection->getCollection('products');
    }

    public function nextId(): string
    {
        return (string) new ObjectId();
    }

    public function find(string $id): ?Product
    {
        $doc = $this->collection->findOne(['_id' => Mongo::objectId($id, 'Producto')], ['typeMap' => $this->typeMap()]);

        return is_array($doc) ? $this->hydrate($doc) : null;
    }

    public function save(Product $product): void
    {
        $this->collection->replaceOne(
            ['_id' => new ObjectId($product->id())],
            [
                'code' => $product->code()->value(),
                'name' => $product->name(),
                'brand' => $product->brand(),
                'price' => new Decimal128($product->price()->amount()),
                // Motor de búsqueda: prefijos de código, nombre y marca (índice multikey)
                'search_tokens' => Mongo::searchTokens($product->code()->value(), $product->name(), $product->brand()),
                'created_at' => Mongo::date($product->createdAt()),
                'updated_at' => Mongo::date($product->updatedAt()),
            ],
            ['upsert' => true],
        );
    }

    public function delete(Product $product): void
    {
        $this->collection->deleteOne(['_id' => new ObjectId($product->id())]);
    }

    public function paginate(ListQuery $query): Page
    {
        $filter = Mongo::searchFilter($query->search);
        $window = Mongo::window($query);

        $docs = $this->collection->find($filter, [
            'sort' => Mongo::sort($query, self::SORTABLE, unique: ['code']),
            'skip' => $window['skip'],
            'limit' => $window['limit'],
            // Proyección: solo lo que se usa (menos datos desde Mongo)
            'projection' => ['search_tokens' => 0],
            'typeMap' => $this->typeMap(),
        ]);

        $items = [];
        foreach ($docs as $doc) {
            $items[] = $this->hydrate($doc);
        }

        $total = $filter === [] ? $this->collection->estimatedDocumentCount() : $this->collection->countDocuments($filter);

        return new Page($items, $total, $query->page, $query->perPage);
    }

    public function cursor(ListQuery $query): iterable
    {
        $docs = $this->collection->find(Mongo::searchFilter($query->search), [
            'sort' => Mongo::sort($query, self::SORTABLE, unique: ['code']),
            'projection' => ['search_tokens' => 0],
            'typeMap' => $this->typeMap(),
            'batchSize' => 500,
        ]);

        foreach ($docs as $doc) {
            yield $this->hydrate($doc);
        }
    }

    public function count(): int
    {
        return $this->collection->estimatedDocumentCount();
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function hydrate(array $doc): Product
    {
        return Product::restore(
            (string) $doc['_id'],
            EntityCode::fromString((string) $doc['code']),
            (string) $doc['name'],
            (string) $doc['brand'],
            Price::from((string) $doc['price']),
            Mongo::toDate($doc['created_at']) ?? new \DateTimeImmutable(),
            Mongo::toDate($doc['updated_at'] ?? null) ?? Mongo::toDate($doc['created_at']) ?? new \DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function typeMap(): array
    {
        return ['root' => 'array', 'document' => 'array', 'array' => 'array'];
    }
}
