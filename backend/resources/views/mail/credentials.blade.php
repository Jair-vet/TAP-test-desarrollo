<x-mail::message>
# Hola, {{ $userName }}

@if ($kind === 'welcome')
Se creó tu cuenta en el **Sistema de Gestión TAP**. Estas son tus credenciales de acceso:
@else
Recibimos una solicitud para recuperar tu contraseña. Usa esta **contraseña temporal**:
@endif

<x-mail::panel>
**Usuario:** {{ $userEmail }}<br>
**Contraseña{{ $kind === 'welcome' ? '' : ' temporal' }}:** `{{ $plainPassword }}`
</x-mail::panel>

@if ($expiresAt)
La contraseña temporal vence el **{{ $expiresAt }}**. Tu contraseña anterior seguirá funcionando hasta que uses esta.
@endif

Por seguridad, el sistema te pedirá **definir una contraseña nueva** al ingresar.

<x-mail::button :url="$loginUrl" color="primary">
Iniciar sesión
</x-mail::button>

Si no solicitaste este correo, ignóralo y avisa al administrador.

Saludos,<br>
Sistema de Gestión TAP
</x-mail::message>
