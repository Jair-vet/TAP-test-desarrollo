<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tap\IdentityAccess\Application\Profile\ProfileService;
use Tap\IdentityAccess\Infrastructure\Http\Requests\ProfileExportRequest;
use Tap\IdentityAccess\Infrastructure\Http\Requests\ProfileListRequest;
use Tap\IdentityAccess\Infrastructure\Http\Requests\ProfileRequest;
use Tap\IdentityAccess\Infrastructure\Http\Resources\ProfilePresenter;
use Tap\Shared\Infrastructure\Http\RequestContext;
use Tap\Shared\Infrastructure\Http\Responses\ApiResponse;

/** CRUD de perfiles (prefijo /api/v1/profiles) + catálogo de secciones. */
final class ProfileController
{
    public function __construct(private readonly ProfileService $service)
    {
    }

    /** GET /profiles */
    public function index(ProfileListRequest $request): JsonResponse
    {
        return ApiResponse::paginated($this->service->list($request->toListQuery()), static fn ($p): array => ProfilePresenter::toArray($p));
    }

    /** GET /profiles/options — {id, code, name} para selects */
    public function options(): JsonResponse
    {
        return ApiResponse::data($this->service->options());
    }

    /** GET /sections — secciones y acciones asignables */
    public function sections(): JsonResponse
    {
        return ApiResponse::data($this->service->sectionsCatalog());
    }

    /** POST /profiles */
    public function store(ProfileRequest $request): JsonResponse
    {
        $profile = $this->service->create($request->toInput(), RequestContext::actor($request));

        return ApiResponse::data(ProfilePresenter::toArray($profile, 0), 201);
    }

    /** GET /profiles/{id} — detalle (modal): código, nombre, fecha, secciones */
    public function show(string $id): JsonResponse
    {
        $detail = $this->service->detail($id);

        return ApiResponse::data(ProfilePresenter::toArray($detail['profile'], $detail['users_count']));
    }

    /** PUT /profiles/{id} */
    public function update(ProfileRequest $request, string $id): JsonResponse
    {
        $profile = $this->service->update($id, $request->toInput(), RequestContext::actor($request));

        return ApiResponse::data(ProfilePresenter::toArray($profile));
    }

    /** DELETE /profiles/{id} — 409 si está asignado a usuarios */
    public function destroy(Request $request, string $id): Response
    {
        $this->service->delete($id, RequestContext::actor($request));

        return response()->noContent();
    }

    /** GET /profiles/export?format=pdf|xlsx */
    public function export(ProfileExportRequest $request): BinaryFileResponse
    {
        return ApiResponse::file($this->service->export($request->toListQuery(), $request->exportFormat()));
    }
}
