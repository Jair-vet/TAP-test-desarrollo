<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Security;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;
use Tap\IdentityAccess\Application\Port\TotpProvider;
use Throwable;

/**
 * TOTP con pragmarx/google2fa + QR en SVG con bacon/bacon-qr-code
 * (el QR se genera en el servidor: la semilla no pasa por servicios externos).
 */
final class Google2faTotpProvider implements TotpProvider
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly string $issuer,
        private readonly int $window,
    ) {
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32); // 160 bits
    }

    public function verify(string $secret, string $code, ?int $lastUsedStep): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        try {
            // Con un "timestamp anterior" (aunque sea 0) google2fa devuelve el
            // intervalo exacto que coincidió y solo acepta intervalos POSTERIORES.
            $result = $this->google2fa->verifyKeyNewer($secret, $code, $lastUsedStep ?? 0, $this->window);
        } catch (Throwable) {
            return null;
        }

        return is_int($result) ? $result : null;
    }

    public function provisioningUri(string $accountName, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($this->issuer, $accountName, $secret);
    }

    public function qrCodeSvg(string $provisioningUri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd()));

        return $writer->writeString($provisioningUri);
    }
}
