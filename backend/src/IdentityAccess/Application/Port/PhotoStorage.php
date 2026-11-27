<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

/**
 * Almacenamiento de fotos de perfil. Hoy: disco local re-codificando a WebP.
 * Mañana: S3/Azure Blob implementando esta misma interfaz.
 */
interface PhotoStorage
{
    /** Procesa y guarda la imagen; devuelve la ruta relativa. */
    public function store(string $sourcePath): string;

    public function delete(string $path): void;

    public function absolutePath(string $path): ?string;
}
