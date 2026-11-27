<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

/** TOTP compatible con Google Authenticator, Microsoft Authenticator, Authy, 1Password... */
interface TotpProvider
{
    public function generateSecret(): string;

    /**
     * @return int|null El intervalo (timestep) aceptado, o null si el código no es válido
     *                  o ya se usó (solo acepta códigos POSTERIORES a $lastUsedStep).
     */
    public function verify(string $secret, string $code, ?int $lastUsedStep): ?int;

    public function provisioningUri(string $accountName, string $secret): string;

    public function qrCodeSvg(string $provisioningUri): string;
}
