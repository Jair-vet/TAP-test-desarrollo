<?php

declare(strict_types=1);

namespace Tap\Audit\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tap\Audit\Application\AuditLabels;
use Tap\Audit\Application\AuditService;
use Tap\Shared\Infrastructure\Http\Responses\ApiResponse;

/** Bitácora (prefijo /api/v1/audit-logs). Solo lectura. */
final class AuditController
{
    public function __construct(private readonly AuditService $service)
    {
    }

    /** GET /audit-logs */
    public function index(AuditListRequest $request): JsonResponse
    {
        return ApiResponse::paginated($this->service->list($request->toListQuery()), AuditPresenter::summary(...));
    }

    /** GET /audit-logs/{id} — antes vs después */
    public function show(string $id): JsonResponse
    {
        return ApiResponse::data(AuditPresenter::detail($this->service->get($id)));
    }

    /** GET /audit-logs/catalog — eventos y entidades para los filtros */
    public function catalog(): JsonResponse
    {
        return ApiResponse::data([
            'events' => array_map(static fn ($k, $v): array => ['key' => $k, 'label' => $v], array_keys(AuditLabels::EVENTS), AuditLabels::EVENTS),
            'entities' => array_map(static fn ($k, $v): array => ['key' => $k, 'label' => $v], array_keys(AuditLabels::ENTITIES), AuditLabels::ENTITIES),
        ]);
    }

    /** GET /audit-logs/export?format=pdf|xlsx */
    public function export(AuditListRequest $request): BinaryFileResponse
    {
        return ApiResponse::file($this->service->export($request->toListQuery(), $request->exportFormat()));
    }
}
