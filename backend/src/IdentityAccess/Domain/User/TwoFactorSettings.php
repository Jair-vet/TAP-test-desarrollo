<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\User;

use DateTimeImmutable;

/**
 * Estado del segundo factor (TOTP, RFC 6238) de un usuario. Inmutable.
 *
 *  - secret / pendingSecret se guardan CIFRADOS (AES-256 con APP_KEY).
 *  - recoveryCodeHashes: SHA-256 de los códigos de respaldo (uso único).
 *  - lastUsedStep: último intervalo de 30 s aceptado → un código interceptado
 *    no se puede reutilizar (protección contra replay).
 */
final class TwoFactorSettings
{
    /**
     * @param list<string> $recoveryCodeHashes
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly ?string $secret = null,
        public readonly ?string $pendingSecret = null,
        public readonly array $recoveryCodeHashes = [],
        public readonly ?int $lastUsedStep = null,
        public readonly ?DateTimeImmutable $enabledAt = null,
    ) {
    }

    public static function disabled(): self
    {
        return new self();
    }

    public function withPendingSecret(string $encryptedSecret): self
    {
        return new self($this->enabled, $this->secret, $encryptedSecret, $this->recoveryCodeHashes, $this->lastUsedStep, $this->enabledAt);
    }

    /**
     * @param list<string> $recoveryCodeHashes
     */
    public function confirm(array $recoveryCodeHashes, int $step, DateTimeImmutable $now): self
    {
        return new self(true, $this->pendingSecret, null, $recoveryCodeHashes, $step, $now);
    }

    public function withUsedStep(int $step): self
    {
        return new self($this->enabled, $this->secret, $this->pendingSecret, $this->recoveryCodeHashes, $step, $this->enabledAt);
    }

    /**
     * @param list<string> $recoveryCodeHashes
     */
    public function withRecoveryCodes(array $recoveryCodeHashes): self
    {
        return new self($this->enabled, $this->secret, $this->pendingSecret, $recoveryCodeHashes, $this->lastUsedStep, $this->enabledAt);
    }

    public function withoutRecoveryCode(string $hash): self
    {
        $remaining = array_values(array_filter($this->recoveryCodeHashes, static fn (string $h): bool => ! hash_equals($h, $hash)));

        return $this->withRecoveryCodes($remaining);
    }

    public function hasRecoveryCode(string $hash): bool
    {
        foreach ($this->recoveryCodeHashes as $stored) {
            if (hash_equals($stored, $hash)) {
                return true;
            }
        }

        return false;
    }
}
