<?php

declare(strict_types=1);

namespace Tap\Catalog\Infrastructure\Http;

use DateTimeInterface;
use Tap\Catalog\Domain\Product;

/**
 * Forma pública (JSON) de un producto. Las fechas salen en ISO-8601 UTC;
 * el frontend las muestra como DD/MM/YYYY HH:MM en la zona del usuario.
 */
final class ProductPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Product $product): array
    {
        return [
            'id' => $product->id(),
            'code' => $product->code()->value(),
            'name' => $product->name(),
            'brand' => $product->brand(),
            'price' => $product->price()->toFloat(),
            'created_at' => $product->createdAt()->format(DateTimeInterface::ATOM),
            'updated_at' => $product->updatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
