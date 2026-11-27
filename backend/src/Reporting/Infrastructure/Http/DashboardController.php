<?php

declare(strict_types=1);

namespace Tap\Reporting\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tap\Audit\Infrastructure\Http\AuditPresenter;
use Tap\Reporting\Application\DashboardService;
use Tap\Shared\Infrastructure\Http\RequestContext;
use Tap\Shared\Infrastructure\Http\Responses\ApiResponse;

/**
 * GET /dashboard — contadores y actividad reciente de la pantalla Inicio.
 * Solo incluye las secciones que el usuario puede ver según sus perfiles.
 */
final class DashboardController
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    /** GET /dashboard */
    public function __invoke(Request $request): JsonResponse
    {
        $summary = $this->service->summary(RequestContext::user($request));

        return ApiResponse::data([
            'counts' => (object) $summary['counts'],
            'recent_activity' => array_map(AuditPresenter::summary(...), $summary['recent']),
        ]);
    }
}
