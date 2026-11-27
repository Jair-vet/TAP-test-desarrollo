<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Storage;

use RuntimeException;
use Tap\IdentityAccess\Application\Port\PhotoStorage;
use Tap\Shared\Domain\Exception\InvalidValue;

/**
 * Fotos de perfil en disco privado (storage/app/private/photos), fuera de
 * la carpeta pública: solo se sirven con URL firmada (MediaController).
 *
 * Seguridad de subida de archivos:
 *  - Se DECODIFICA y RE-CODIFICA la imagen con GD a WebP: elimina metadatos
 *    EXIF (GPS del celular), scripts incrustados y archivos "políglota"
 *    (un .jpg que también es .php/.html).
 *  - El nombre es aleatorio (128 bits): no se usa el nombre original.
 *  - Se recorta al centro y se reduce a 512x512: ahorra espacio y ancho de banda.
 */
final class LocalPhotoStorage implements PhotoStorage
{
    private const SIZE = 512;
    private const QUALITY = 82;

    public function __construct(private readonly string $root)
    {
    }

    public function store(string $sourcePath): string
    {
        $info = @getimagesize($sourcePath);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw InvalidValue::for('photo', 'La foto debe ser una imagen JPG, PNG o WebP válida.');
        }

        if ($info[0] * $info[1] > 40_000_000) {
            throw InvalidValue::for('photo', 'La imagen es demasiado grande.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($sourcePath));
        if ($source === false) {
            throw InvalidValue::for('photo', 'No se pudo procesar la imagen.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            (int) (($width - $side) / 2),
            (int) (($height - $side) / 2),
            self::SIZE,
            self::SIZE,
            $side,
            $side,
        );

        $name = bin2hex(random_bytes(16)) . '.webp';
        $relative = 'photos/' . substr($name, 0, 2) . '/' . $name;
        $absolute = $this->root . '/' . $relative;

        if (! is_dir(dirname($absolute)) && ! mkdir(dirname($absolute), 0750, true) && ! is_dir(dirname($absolute))) {
            throw new RuntimeException('No se pudo crear el directorio de fotos.');
        }

        imagewebp($target, $absolute, self::QUALITY);
        imagedestroy($source);
        imagedestroy($target);

        return $relative;
    }

    public function delete(string $path): void
    {
        $absolute = $this->absolutePath($path);
        if ($absolute !== null) {
            @unlink($absolute);
        }
    }

    /**
     * Resuelve la ruta evitando path traversal (../../etc/passwd).
     */
    public function absolutePath(string $path): ?string
    {
        if (preg_match('#^photos/[a-f0-9]{2}/[a-f0-9]{32}\.webp$#', $path) !== 1) {
            return null;
        }

        $absolute = $this->root . '/' . $path;

        return is_file($absolute) ? $absolute : null;
    }
}
