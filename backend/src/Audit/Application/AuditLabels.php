<?php

declare(strict_types=1);

namespace Tap\Audit\Application;

/** Etiquetas en español para eventos y entidades de la bitácora. */
final class AuditLabels
{
    public const EVENTS = [
        'created' => 'Alta',
        'updated' => 'Edición',
        'deleted' => 'Eliminación',
        'login' => 'Inicio de sesión',
        'logout' => 'Cierre de sesión',
        'login_failed' => 'Intento fallido',
        'account_locked' => 'Cuenta bloqueada',
        'password_reset_requested' => 'Recuperación de contraseña',
        'password_reset_unknown_email' => 'Recuperación (correo inexistente)',
        'temporary_password_used' => 'Uso de contraseña temporal',
        'password_changed' => 'Cambio de contraseña',
        'password_change_failed' => 'Cambio de contraseña fallido',
        'two_factor_enabled' => '2FA activado',
        'two_factor_disabled' => '2FA desactivado',
        'recovery_code_used' => 'Código de respaldo usado',
        'recovery_codes_regenerated' => 'Códigos de respaldo regenerados',
        'session_reuse_detected' => 'Reúso de sesión detectado',
        'mailbox_access' => 'Acceso a bandeja de correos',
        'mailbox_access_failed' => 'Acceso fallido a bandeja de correos',
    ];

    public const ENTITIES = [
        'product' => 'Producto',
        'user' => 'Usuario',
        'profile' => 'Perfil',
        'auth' => 'Sesión',
    ];

    public static function event(string $event): string
    {
        return self::EVENTS[$event] ?? $event;
    }

    public static function entity(string $entity): string
    {
        return self::ENTITIES[$entity] ?? $entity;
    }
}
