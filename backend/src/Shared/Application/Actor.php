<?php

declare(strict_types=1);

namespace Tap\Shared\Application;

/**
 * Quién ejecuta el caso de uso. Se pasa explícitamente a los servicios de
 * aplicación (nada de estado global), lo que permite auditar y probar fácil.
 */
final class Actor
{
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $code,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {
    }

    public static function system(): self
    {
        return new self(null, null, 'Sistema', null);
    }

    public static function anonymous(?string $ip, ?string $userAgent): self
    {
        return new self(null, null, 'Anónimo', null, $ip, $userAgent);
    }

    /**
     * @return array{id: ?string, code: ?string, name: string, email: ?string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'email' => $this->email];
    }
}
