<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Security;

use Tap\IdentityAccess\Application\Port\PasswordGenerator;

/**
 * Generador con random_int (CSPRNG del sistema). Garantiza al menos una
 * mayúscula, minúscula, dígito y símbolo; excluye caracteres ambiguos
 * (0/O, 1/l/I) para que se puedan teclear desde el correo.
 */
final class SecurePasswordGenerator implements PasswordGenerator
{
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const LOWER = 'abcdefghijkmnopqrstuvwxyz';
    private const DIGITS = '23456789';
    private const SYMBOLS = '!@#$%*-_+=?';
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(int $length = 16): string
    {
        $length = max(12, $length);
        $all = self::UPPER . self::LOWER . self::DIGITS . self::SYMBOLS;

        $chars = [
            self::pick(self::UPPER),
            self::pick(self::LOWER),
            self::pick(self::DIGITS),
            self::pick(self::SYMBOLS),
        ];

        while (count($chars) < $length) {
            $chars[] = self::pick($all);
        }

        // Fisher-Yates con CSPRNG (shuffle() no es criptográficamente seguro)
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    public function recoveryCodes(int $count): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = '';
            for ($c = 0; $c < 10; $c++) {
                $raw .= self::pick(self::RECOVERY_ALPHABET);
            }
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
        }

        return $codes;
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
