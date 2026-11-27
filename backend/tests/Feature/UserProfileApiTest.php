<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tap\IdentityAccess\Infrastructure\Mail\CredentialsMail;

final class UserProfileApiTest extends ApiTestCase
{
    public function test_alta_de_usuario_con_foto_perfiles_y_envio_de_credenciales(): void
    {
        Mail::fake();
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');

        $profile = $this->withToken($token)->postJson('/api/v1/profiles', [
            'name' => 'Almacén',
            'sections' => [['section' => 'products', 'actions' => ['create', 'update']]],
        ])->assertCreated()->assertJsonPath('data.sections.0.actions', ['view', 'create', 'update']);

        $user = $this->withToken($token)->post('/api/v1/users', [
            'name' => 'Laura Pérez',
            'email' => 'laura@tap.test',
            'phone' => '+52 314 123 4567',
            'photo' => UploadedFile::fake()->image('foto.jpg', 300, 300),
            'profile_ids' => [$profile->json('data.id')],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.code', 'USR-000002')
            ->assertJsonPath('data.phone', '+523141234567')
            ->assertJsonPath('data.profiles.0.name', 'Almacén');

        self::assertStringStartsWith('/api/v1/media/users/', (string) $user->json('data.photo_url'));
        Mail::assertQueued(CredentialsMail::class, static fn (CredentialsMail $m): bool => $m->kind === 'welcome');

        // El perfil en uso no se puede eliminar
        $this->withToken($token)->deleteJson('/api/v1/profiles/' . $profile->json('data.id'))
            ->assertStatus(409)->assertJsonPath('code', 'PROFILE_IN_USE');
    }

    public function test_reglas_de_usuario(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');

        $this->withToken($token)->post('/api/v1/users', [
            'name' => 'Otro',
            'email' => 'ADMIN@tap.test',
            'photo' => UploadedFile::fake()->image('foto.png', 100, 100),
        ], ['Accept' => 'application/json'])->assertStatus(409)->assertJsonPath('code', 'EMAIL_TAKEN');

        $this->withToken($token)->post('/api/v1/users', [
            'name' => 'Otro',
            'email' => 'otro@tap.test',
            'phone' => '3141234567',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['phone', 'photo']);
    }

    public function test_perfil_con_varias_secciones_que_comparten_acciones(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');

        // Regresión: 'view' en dos secciones no debe marcarse como duplicado
        $this->withToken($token)->postJson('/api/v1/profiles', [
            'name' => 'Auditor externo',
            'sections' => [
                ['section' => 'products', 'actions' => ['view', 'export']],
                ['section' => 'audit', 'actions' => ['view']],
            ],
        ])->assertCreated()->assertJsonCount(2, 'data.sections');
    }

    public function test_validaciones_de_texto_en_usuarios_y_perfiles(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');

        $this->withToken($token)->post('/api/v1/users', [
            'name' => 'Juan123 <b>',
            'email' => 'juan@tap',
            'photo' => UploadedFile::fake()->image('foto.png', 100, 100),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['name', 'email']);

        $this->withToken($token)->postJson('/api/v1/profiles', [
            'name' => 'Perfil <script>',
            'description' => '<img src=x onerror=alert(1)>',
            'sections' => [['section' => 'products', 'actions' => ['view']]],
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'description']);
    }
}
