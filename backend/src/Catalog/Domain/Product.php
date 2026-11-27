<?php

declare(strict_types=1);

namespace Tap\Catalog\Domain;

use DateTimeImmutable;
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Domain\ValueObject\EntityCode;

/**
 * Agregado Producto. Protege sus invariantes: nombre y marca obligatorios,
 * precio válido, código inmutable y fecha de creación automática.
 */
final class Product
{
    public const CODE_PREFIX = 'PRD';

    private function __construct(
        private readonly string $id,
        private readonly EntityCode $code,
        private string $name,
        private string $brand,
        private Price $price,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(string $id, EntityCode $code, string $name, string $brand, Price $price, DateTimeImmutable $now): self
    {
        return new self($id, $code, self::cleanName($name), self::cleanBrand($brand), $price, $now, $now);
    }

    /** Reconstruye desde persistencia (no vuelve a validar datos ya guardados). */
    public static function restore(string $id, EntityCode $code, string $name, string $brand, Price $price, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): self
    {
        return new self($id, $code, $name, $brand, $price, $createdAt, $updatedAt);
    }

    public function update(string $name, string $brand, Price $price, DateTimeImmutable $now): void
    {
        $this->name = self::cleanName($name);
        $this->brand = self::cleanBrand($brand);
        $this->price = $price;
        $this->updatedAt = $now;
    }

    /**
     * Foto del estado para la bitácora (antes/después).
     *
     * @return array<string, scalar>
     */
    public function snapshot(): array
    {
        return [
            'code' => $this->code->value(),
            'name' => $this->name,
            'brand' => $this->brand,
            'price' => $this->price->amount(),
        ];
    }

    public function id(): string
    {
        return $this->id;
    }

    public function code(): EntityCode
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function brand(): string
    {
        return $this->brand;
    }

    public function price(): Price
    {
        return $this->price;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) > 120) {
            throw InvalidValue::for('name', 'El nombre es obligatorio (máximo 120 caracteres).');
        }

        return $name;
    }

    private static function cleanBrand(string $brand): string
    {
        $brand = trim(preg_replace('/\s+/u', ' ', $brand) ?? '');
        if ($brand === '' || mb_strlen($brand) > 80) {
            throw InvalidValue::for('brand', 'La marca es obligatoria (máximo 80 caracteres).');
        }

        return $brand;
    }
}
