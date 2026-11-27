<?php

declare(strict_types=1);

namespace Tap\Catalog\Application;

/** DTO de entrada para alta/edición (ya validado por el adaptador HTTP). */
final class ProductData
{
    public function __construct(
        public readonly string $name,
        public readonly string $brand,
        public readonly string $price,
    ) {
    }
}
