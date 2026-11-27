<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tap\IdentityAccess\Application\Port\PhotoStorage;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\IdentityAccess\Infrastructure\Storage\MediaUrlSigner;
use Throwable;

/**
 * GET /media/users/{id}/photo?f=&exp=&sig=
 * Sirve la foto solo si la firma HMAC es válida y no expiró.
 */
final class MediaController
{
    public function __construct(
        private readonly MediaUrlSigner $signer,
        private readonly UserRepository $users,
        private readonly PhotoStorage $photos,
    ) {
    }

    public function userPhoto(Request $request, string $id): BinaryFileResponse
    {
        $file = (string) $request->query('f', '');
        $exp = (int) $request->query('exp', '0');
        $sig = (string) $request->query('sig', '');

        if (preg_match('/^[a-f0-9]{32}$/', $file) !== 1 || ! $this->signer->verify($id, $file, $exp, $sig)) {
            throw new NotFoundHttpException();
        }

        try {
            $user = $this->users->find($id);
        } catch (Throwable) {
            $user = null;
        }

        $path = $user?->photoPath();
        if ($path === null || basename($path, '.webp') !== $file || ($absolute = $this->photos->absolutePath($path)) === null) {
            throw new NotFoundHttpException();
        }

        // El nombre del archivo cambia con cada foto nueva → se puede cachear agresivo
        return (new BinaryFileResponse($absolute, 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=3600, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]))->setAutoEtag();
    }
}
