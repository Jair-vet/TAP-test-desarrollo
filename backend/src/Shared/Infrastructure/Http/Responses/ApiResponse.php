<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tap\Shared\Application\Export\ExportedFile;
use Tap\Shared\Domain\Pagination\Page;

/** Formas de respuesta homogéneas para todos los controladores. */
final class ApiResponse
{
    /**
     * @template T
     *
     * @param Page<T>                          $page
     * @param callable(T): array<string, mixed> $transform
     */
    public static function paginated(Page $page, callable $transform): JsonResponse
    {
        return new JsonResponse([
            'data' => array_map($transform, $page->items),
            'meta' => [
                'page' => $page->page,
                'per_page' => $page->perPage,
                'total' => $page->total,
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     */
    public static function data(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $data], $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }

    public static function file(ExportedFile $file): BinaryFileResponse
    {
        return (new BinaryFileResponse($file->path, 200, [
            'Content-Type' => $file->mimeType,
            'Cache-Control' => 'no-store',
        ]))
            ->setContentDisposition('attachment', $file->filename)
            ->deleteFileAfterSend(true);
    }
}
