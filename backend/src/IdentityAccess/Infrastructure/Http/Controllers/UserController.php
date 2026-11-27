<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tap\IdentityAccess\Application\User\UserService;
use Tap\IdentityAccess\Infrastructure\Http\Requests\UserExportRequest;
use Tap\IdentityAccess\Infrastructure\Http\Requests\UserListRequest;
use Tap\IdentityAccess\Infrastructure\Http\Requests\UserRequest;
use Tap\IdentityAccess\Infrastructure\Http\Resources\UserPresenter;
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Infrastructure\Http\RequestContext;
use Tap\Shared\Infrastructure\Http\Responses\ApiResponse;

/** CRUD de usuarios (prefijo /api/v1/users). */
final class UserController
{
    public function __construct(
        private readonly UserService $service,
        private readonly UserPresenter $presenter,
    ) {
    }

    /** GET /users */
    public function index(UserListRequest $request): JsonResponse
    {
        return ApiResponse::paginated($this->service->list($request->toListQuery()), $this->presenter->summary(...));
    }

    /** POST /users (multipart/form-data) */
    public function store(UserRequest $request): JsonResponse
    {
        $photo = $request->photoPath() ?? throw InvalidValue::for('photo', 'La foto de perfil es obligatoria.');
        $user = $this->service->create($request->toInput(), $photo, RequestContext::actor($request));

        return ApiResponse::data($this->presenter->detail($this->service->detail($user->id())), 201);
    }

    /** GET /users/{id} — detalle (usuario, nombre, teléfono, foto, perfiles) */
    public function show(string $id): JsonResponse
    {
        return ApiResponse::data($this->presenter->detail($this->service->detail($id)));
    }

    /** PUT /users/{id} (JSON) o POST /users/{id} con _method=PUT (multipart con foto) */
    public function update(UserRequest $request, string $id): JsonResponse
    {
        $user = $this->service->update($id, $request->toInput(), $request->photoPath(), RequestContext::actor($request));

        return ApiResponse::data($this->presenter->detail($this->service->detail($user->id())));
    }

    /** DELETE /users/{id} */
    public function destroy(Request $request, string $id): Response
    {
        $this->service->delete($id, RequestContext::actor($request));

        return response()->noContent();
    }

    /** GET /users/export?format=pdf|xlsx */
    public function export(UserExportRequest $request): BinaryFileResponse
    {
        return ApiResponse::file($this->service->export($request->toListQuery(), $request->exportFormat()));
    }
}
