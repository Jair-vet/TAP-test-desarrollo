<?php

declare(strict_types=1);

namespace Tap\Catalog\Application;

use Tap\Catalog\Domain\Price;
use Tap\Catalog\Domain\Product;
use Tap\Catalog\Domain\ProductRepository;
use Tap\Shared\Application\Actor;
use Tap\Shared\Application\Export\ExportDocument;
use Tap\Shared\Application\Export\ExportedFile;
use Tap\Shared\Application\Export\ExportFormat;
use Tap\Shared\Application\Port\AuditTrail;
use Tap\Shared\Application\Port\Clock;
use Tap\Shared\Application\Port\QueryCache;
use Tap\Shared\Application\Port\SequenceGenerator;
use Tap\Shared\Application\Port\TabularExporter;
use Tap\Shared\Domain\Exception\NotFound;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;
use Tap\Shared\Domain\ValueObject\EntityCode;

/**
 * Casos de uso de productos. Orquesta: dominio + persistencia + caché +
 * bitácora. No sabe nada de HTTP ni de MongoDB.
 */
final class ProductService
{
    public const CACHE_NAMESPACE = 'products';
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly SequenceGenerator $sequences,
        private readonly Clock $clock,
        private readonly QueryCache $cache,
        private readonly AuditTrail $audit,
        private readonly TabularExporter $exporter,
    ) {
    }

    public function create(ProductData $data, Actor $actor): Product
    {
        $product = Product::create(
            $this->products->nextId(),
            EntityCode::generate(Product::CODE_PREFIX, $this->sequences->next('products')),
            $data->name,
            $data->brand,
            Price::from($data->price),
            $this->clock->now(),
        );

        $this->products->save($product);
        $this->afterWrite();
        $this->audit->record('created', 'product', $product->id(), $product->code()->value(), $actor, null, $product->snapshot());

        return $product;
    }

    public function update(string $id, ProductData $data, Actor $actor): Product
    {
        $product = $this->get($id);
        $before = $product->snapshot();

        $product->update($data->name, $data->brand, Price::from($data->price), $this->clock->now());

        $this->products->save($product);
        $this->afterWrite();
        $this->audit->record('updated', 'product', $product->id(), $product->code()->value(), $actor, $before, $product->snapshot());

        return $product;
    }

    public function delete(string $id, Actor $actor): void
    {
        $product = $this->get($id);

        $this->products->delete($product);
        $this->afterWrite();
        // La bitácora conserva el snapshot completo: el registro se puede reconstruir
        $this->audit->record('deleted', 'product', $product->id(), $product->code()->value(), $actor, $product->snapshot(), null);
    }

    public function get(string $id): Product
    {
        return $this->products->find($id) ?? throw NotFound::entity('Producto', $id);
    }

    /** @return Page<Product> */
    public function list(ListQuery $query): Page
    {
        return $this->cache->remember(self::CACHE_NAMESPACE, $query->fingerprint(), self::CACHE_TTL, fn () => $this->products->paginate($query));
    }

    public function export(ListQuery $query, ExportFormat $format): ExportedFile
    {
        $rows = (function () use ($query): iterable {
            foreach ($this->products->cursor($query->withoutPagination()) as $product) {
                yield [
                    'code' => $product->code()->value(),
                    'name' => $product->name(),
                    'brand' => $product->brand(),
                    'price' => '$' . $product->price()->amount(),
                    'created_at' => $product->createdAt(),
                ];
            }
        })();

        return $this->exporter->export(new ExportDocument(
            title: 'Listado de productos',
            filenameBase: 'productos',
            columns: [
                'code' => 'Código',
                'name' => 'Nombre',
                'brand' => 'Marca',
                'price' => 'Precio',
                'created_at' => 'Fecha de creación',
            ],
            rows: $rows,
            subtitle: $query->search !== null ? "Filtro: \"{$query->search}\"" : null,
        ), $format);
    }

    private function afterWrite(): void
    {
        $this->cache->invalidate(self::CACHE_NAMESPACE, 'dashboard');
    }
}
