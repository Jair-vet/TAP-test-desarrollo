<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\TestCase;
use Tap\Catalog\Application\ProductData;
use Tap\Catalog\Application\ProductService;
use Tap\Shared\Application\Actor;
use Tap\Shared\Application\Export\ExportFormat;
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Domain\Exception\NotFound;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tests\Support\FakeExporter;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryProductRepository;
use Tests\Support\InMemorySequence;
use Tests\Support\PassThroughCache;
use Tests\Support\SpyAuditTrail;

/**
 * Caso de uso de productos probado 100% en memoria (sin BD): esto es lo que
 * habilita la arquitectura hexagonal.
 */
final class ProductServiceTest extends TestCase
{
    private InMemoryProductRepository $repo;
    private SpyAuditTrail $audit;
    private PassThroughCache $cache;
    private FakeExporter $exporter;
    private ProductService $service;
    private Actor $actor;

    protected function setUp(): void
    {
        $this->repo = new InMemoryProductRepository();
        $this->audit = new SpyAuditTrail();
        $this->cache = new PassThroughCache();
        $this->exporter = new FakeExporter();
        $this->service = new ProductService($this->repo, new InMemorySequence(), new FixedClock(), $this->cache, $this->audit, $this->exporter);
        $this->actor = new Actor('u1', 'USR-000001', 'Tester', 'test@tap.test');
    }

    public function test_alta_genera_codigo_consecutivo_fecha_y_bitacora(): void
    {
        $first = $this->service->create(new ProductData('Casco', '3M', '120'), $this->actor);
        $second = $this->service->create(new ProductData('Guantes', 'Ansell', '45.5'), $this->actor);

        self::assertSame('PRD-000001', $first->code()->value());
        self::assertSame('PRD-000002', $second->code()->value());
        self::assertSame('2026-11-25 18:00', $first->createdAt()->format('Y-m-d H:i'));
        self::assertSame('created', $this->audit->records[0]['event']);
        self::assertNull($this->audit->records[0]['before']);
        self::assertSame('Casco', $this->audit->records[0]['after']['name']);
        self::assertContains('products', $this->cache->invalidated);
    }

    public function test_edicion_guarda_informacion_anterior_y_actual(): void
    {
        $product = $this->service->create(new ProductData('Casco', '3M', '120'), $this->actor);

        $this->service->update($product->id(), new ProductData('Casco clase E', '3M', '150'), $this->actor);

        $record = $this->audit->records[1];
        self::assertSame('updated', $record['event']);
        self::assertSame('120.00', $record['before']['price']);
        self::assertSame('150.00', $record['after']['price']);
    }

    public function test_eliminacion_conserva_snapshot_en_bitacora(): void
    {
        $product = $this->service->create(new ProductData('Casco', '3M', '120'), $this->actor);

        $this->service->delete($product->id(), $this->actor);

        self::assertSame(0, $this->repo->count());
        self::assertSame('Casco', $this->audit->records[1]['before']['name']);
        self::assertNull($this->audit->records[1]['after']);
    }

    public function test_no_crea_producto_invalido(): void
    {
        $this->expectException(InvalidValue::class);
        $this->service->create(new ProductData('  ', '3M', '10'), $this->actor);
    }

    public function test_producto_inexistente(): void
    {
        $this->expectException(NotFound::class);
        $this->service->get('ffffffffffffffffffffffff');
    }

    public function test_exportacion_respeta_la_busqueda(): void
    {
        $this->service->create(new ProductData('Casco', '3M', '120'), $this->actor);
        $this->service->create(new ProductData('Guantes', 'Ansell', '45'), $this->actor);

        $file = $this->service->export(new ListQuery(search: 'cas'), ExportFormat::Excel);

        self::assertSame('productos.xlsx', $file->filename);
        self::assertCount(1, $this->exporter->rows);
        self::assertSame('PRD-000001', $this->exporter->rows[0]['code']);
    }
}
