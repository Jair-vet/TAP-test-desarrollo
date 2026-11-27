<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\User;

use DateTimeImmutable;
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Domain\ValueObject\Email;
use Tap\Shared\Domain\ValueObject\EntityCode;

/**
 * Agregado Usuario.
 *
 * Además de los datos del examen (nombre, foto, correo único, teléfono,
 * perfiles) encapsula el estado de seguridad: hash de contraseña,
 * contraseña temporal, bloqueo por intentos fallidos, 2FA y `tokenVersion`
 * (subirlo invalida al instante todos los JWT emitidos antes).
 */
final class User
{
    public const CODE_PREFIX = 'USR';

    /**
     * @param list<string> $profileIds
     */
    private function __construct(
        private readonly string $id,
        private readonly EntityCode $code,
        private string $name,
        private Email $email,
        private ?PhoneNumber $phone,
        private ?string $photoPath,
        private array $profileIds,
        private string $passwordHash,
        private bool $mustChangePassword,
        private ?TemporaryPassword $temporaryPassword,
        private int $tokenVersion,
        private int $failedLoginAttempts,
        private ?DateTimeImmutable $lockedUntil,
        private TwoFactorSettings $twoFactor,
        private ?DateTimeImmutable $lastLoginAt,
        private ?DateTimeImmutable $passwordChangedAt,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Alta: la contraseña inicial es generada por el sistema y enviada por
     * correo, por eso el usuario debe cambiarla en su primer ingreso.
     *
     * @param list<string> $profileIds
     */
    public static function register(
        string $id,
        EntityCode $code,
        string $name,
        Email $email,
        ?PhoneNumber $phone,
        string $photoPath,
        array $profileIds,
        string $passwordHash,
        DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            $code,
            self::cleanName($name),
            $email,
            $phone,
            $photoPath,
            array_values(array_unique($profileIds)),
            $passwordHash,
            true,
            null,
            1,
            0,
            null,
            TwoFactorSettings::disabled(),
            null,
            $now,
            $now,
            $now,
        );
    }

    /**
     * @param list<string> $profileIds
     */
    public static function restore(
        string $id,
        EntityCode $code,
        string $name,
        Email $email,
        ?PhoneNumber $phone,
        ?string $photoPath,
        array $profileIds,
        string $passwordHash,
        bool $mustChangePassword,
        ?TemporaryPassword $temporaryPassword,
        int $tokenVersion,
        int $failedLoginAttempts,
        ?DateTimeImmutable $lockedUntil,
        TwoFactorSettings $twoFactor,
        ?DateTimeImmutable $lastLoginAt,
        ?DateTimeImmutable $passwordChangedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $code,
            $name,
            $email,
            $phone,
            $photoPath,
            $profileIds,
            $passwordHash,
            $mustChangePassword,
            $temporaryPassword,
            $tokenVersion,
            $failedLoginAttempts,
            $lockedUntil,
            $twoFactor,
            $lastLoginAt,
            $passwordChangedAt,
            $createdAt,
            $updatedAt,
        );
    }

    // ---------------------------------------------------------------- datos

    /**
     * @param list<string> $profileIds
     */
    public function updateDetails(string $name, Email $email, ?PhoneNumber $phone, array $profileIds, DateTimeImmutable $now): void
    {
        $profilesChanged = $this->profileIds !== array_values(array_unique($profileIds));

        $this->name = self::cleanName($name);
        $this->email = $email;
        $this->phone = $phone;
        $this->profileIds = array_values(array_unique($profileIds));
        $this->updatedAt = $now;

        if ($profilesChanged) {
            // Cambiaron sus permisos: los tokens viejos ya no deben valer
            $this->tokenVersion++;
        }
    }

    public function replacePhoto(string $photoPath, DateTimeImmutable $now): void
    {
        $this->photoPath = $photoPath;
        $this->updatedAt = $now;
    }

    // ---------------------------------------------------------- contraseña

    public function changePassword(string $newHash, DateTimeImmutable $now): void
    {
        $this->passwordHash = $newHash;
        $this->mustChangePassword = false;
        $this->temporaryPassword = null;
        $this->passwordChangedAt = $now;
        $this->updatedAt = $now;
        $this->tokenVersion++; // cierra todas las sesiones abiertas
    }

    public function rehashPassword(string $newHash): void
    {
        $this->passwordHash = $newHash;
    }

    public function issueTemporaryPassword(string $hash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->temporaryPassword = new TemporaryPassword($hash, $expiresAt);
        $this->updatedAt = $now;
    }

    /**
     * Se entró con la contraseña temporal: pasa a ser la contraseña vigente
     * y se obliga a cambiarla.
     */
    public function promoteTemporaryPassword(DateTimeImmutable $now): void
    {
        if ($this->temporaryPassword === null) {
            return;
        }

        $this->passwordHash = $this->temporaryPassword->hash;
        $this->temporaryPassword = null;
        $this->mustChangePassword = true;
        $this->updatedAt = $now;
    }

    // ------------------------------------------------------ intentos/bloqueo

    public function isLocked(DateTimeImmutable $now): bool
    {
        return $this->lockedUntil !== null && $now < $this->lockedUntil;
    }

    public function registerFailedLogin(DateTimeImmutable $now, int $maxAttempts, int $lockMinutes): void
    {
        $this->failedLoginAttempts++;

        if ($this->failedLoginAttempts >= $maxAttempts) {
            $this->lockedUntil = $now->modify("+{$lockMinutes} minutes");
            $this->failedLoginAttempts = 0;
        }
    }

    public function registerSuccessfulLogin(DateTimeImmutable $now): void
    {
        $this->failedLoginAttempts = 0;
        $this->lockedUntil = null;
        $this->lastLoginAt = $now;
    }

    public function revokeAllTokens(): void
    {
        $this->tokenVersion++;
    }

    // ------------------------------------------------------------------ 2FA

    public function startTwoFactorSetup(string $encryptedSecret): void
    {
        $this->twoFactor = $this->twoFactor->withPendingSecret($encryptedSecret);
    }

    /**
     * @param list<string> $recoveryCodeHashes
     */
    public function enableTwoFactor(array $recoveryCodeHashes, int $step, DateTimeImmutable $now): void
    {
        $this->twoFactor = $this->twoFactor->confirm($recoveryCodeHashes, $step, $now);
        $this->updatedAt = $now;
        $this->tokenVersion++;
    }

    public function disableTwoFactor(DateTimeImmutable $now): void
    {
        $this->twoFactor = TwoFactorSettings::disabled();
        $this->updatedAt = $now;
        $this->tokenVersion++;
    }

    public function markTotpStepUsed(int $step): void
    {
        $this->twoFactor = $this->twoFactor->withUsedStep($step);
    }

    public function consumeRecoveryCode(string $hash): void
    {
        $this->twoFactor = $this->twoFactor->withoutRecoveryCode($hash);
    }

    /**
     * @param list<string> $hashes
     */
    public function replaceRecoveryCodes(array $hashes): void
    {
        $this->twoFactor = $this->twoFactor->withRecoveryCodes($hashes);
    }

    // ------------------------------------------------------------- bitácora

    /**
     * Snapshot para la bitácora. NUNCA incluye hashes, secretos 2FA ni la
     * contraseña temporal.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'code' => $this->code->value(),
            'name' => $this->name,
            'email' => $this->email->value(),
            'phone' => $this->phone?->value(),
            'photo' => $this->photoPath === null ? null : basename($this->photoPath),
            'profile_ids' => $this->profileIds,
            'two_factor_enabled' => $this->twoFactor->enabled,
        ];
    }

    // -------------------------------------------------------------- getters

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

    public function email(): Email
    {
        return $this->email;
    }

    public function phone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function photoPath(): ?string
    {
        return $this->photoPath;
    }

    /** @return list<string> */
    public function profileIds(): array
    {
        return $this->profileIds;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function temporaryPassword(): ?TemporaryPassword
    {
        return $this->temporaryPassword;
    }

    public function tokenVersion(): int
    {
        return $this->tokenVersion;
    }

    public function failedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function lockedUntil(): ?DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    public function twoFactor(): TwoFactorSettings
    {
        return $this->twoFactor;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function passwordChangedAt(): ?DateTimeImmutable
    {
        return $this->passwordChangedAt;
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
}
