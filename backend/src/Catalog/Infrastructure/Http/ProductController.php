<?php

declare(strict_types=1);

namespace Tap\Catalog\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tap\Catalog\Application\ProductService;
use Tap\Shared\Infrastructure\Http\RequestContext;
use Tap\Shared\Infrastructure\Http\Responses\ApiResponse;

/**
 * Adaptador primario (driving adapter) HTTP de productos.
 * Responsabilidad única: traducir HTTP <-> casos de uso. Sin lógica de negocio.
 *
 * Rutas en routes/api.php (prefijo /api/v1/products).
 */
final class ProductController
{
    public function __construct(private readonly ProductService $service)
    {
    }

    /** GET /products — listado paginado con búsqueda y orden. */
    public function index(ProductListRequest $request): JsonResponse
    {
        return ApiResponse::paginated($this->service->list($request->toListQuery()), ProductPresenter::toArray(...));
    }

    /** POST /products — alta (código y fecha se generan solos). */
    public function store(ProductRequest $request): JsonResponse
    {
        $product = $this->service->create($request->toData(), RequestContext::actor($request));

        return ApiResponse::data(ProductPresenter::toArray($product), 201);
    }

    /** GET /products/{id} — visualización. */
    public function show(string $id): JsonResponse
    {
        return ApiResponse::data(ProductPresenter::toArray($this->service->get($id)));
    }

    /** PUT /products/{id} — edición. */
    public function update(ProductRequest $request, string $id): JsonResponse
    {
        $product = $this->service->update($id, $request->toData(), RequestContext::actor($request));

        return ApiResponse::data(ProductPresenter::toArray($product));
    }

    /** DELETE /products/{id} — eliminación. */
    public function destroy(Request $request, string $id): Response
    {
        $this->service->delete($id, RequestContext::actor($request));

        return response()->noContent();
    }

    /** GET /products/export?format=pdf|xlsx — descarga respetando la búsqueda. */
    public function export(ProductExportRequest $request): BinaryFileResponse
    {
        return ApiResponse::file($this->service->export($request->toListQuery(), $request->exportFormat()));
    }
}
