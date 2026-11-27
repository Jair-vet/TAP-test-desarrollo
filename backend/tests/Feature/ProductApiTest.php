<?php

declare(strict_types=1);

namespace Tests\Feature;

final class ProductApiTest extends ApiTestCase
{
    public function test_crud_completo_con_codigo_automatico_y_bitacora(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');

        $created = $this->withToken($token)->postJson('/api/v1/products', ['name' => 'Casco', 'brand' => '3M', 'price' => 120.5])
            ->assertCreated()
            ->assertJsonPath('data.code', 'PRD-000001')
            ->assertJsonPath('data.price', 120.5);
        $id = (string) $created->json('data.id');

        $this->withToken($token)->putJson("/api/v1/products/{$id}", ['name' => 'Casco E', 'brand' => '3M', 'price' => '150'])
            ->assertOk()->assertJsonPath('data.name', 'Casco E');

        $this->withToken($token)->getJson('/api/v1/products?search=casco')
            ->assertOk()->assertJsonPath('meta.total', 1);

        $audit = $this->withToken($token)->getJson('/api/v1/audit-logs?entity_type=product&event=updated')->assertOk();
        $detail = $this->withToken($token)->getJson('/api/v1/audit-logs/' . $audit->json('data.0.id'))->assertOk();
        self::assertSame('Casco', $detail->json('data.before.name'));
        self::assertSame('Casco E', $detail->json('data.after.name'));

        $this->withToken($token)->deleteJson("/api/v1/products/{$id}")->assertNoContent();
        $this->withToken($token)->getJson("/api/v1/products/{$id}")->assertNotFound();
    }

    public function test_validaciones_del_examen(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');

        $this->withToken($token)->postJson('/api/v1/products', [])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'brand', 'price']);

        $this->withToken($token)->postJson('/api/v1/products', ['name' => 'X1', 'brand' => 'Y', 'price' => 1000])
            ->assertStatus(422)->assertJsonValidationErrors(['price']);

        // Defensa en profundidad: sin etiquetas HTML ni saltos de línea en textos libres
        $this->withToken($token)->postJson('/api/v1/products', ['name' => '<script>alert(1)</script>', 'brand' => "3M\r\nX", 'price' => 10])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'brand']);
    }

    public function test_permisos_por_perfil(): void
    {
        $this->makeUser('consulta@tap.test', [['products', ['view']]]);
        $token = $this->tokenFor('consulta@tap.test');

        $this->withToken($token)->getJson('/api/v1/products')->assertOk();
        $this->withToken($token)->postJson('/api/v1/products', ['name' => 'X1', 'brand' => 'Y', 'price' => 1])
            ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/products/export?format=pdf')->assertForbidden();
    }

    public function test_exporta_pdf_y_excel(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');
        $this->withToken($token)->postJson('/api/v1/products', ['name' => 'Casco', 'brand' => '3M', 'price' => 10])->assertCreated();

        $pdf = $this->withToken($token)->get('/api/v1/products/export?format=pdf')->assertOk();
        self::assertStringStartsWith('application/pdf', (string) $pdf->headers->get('Content-Type'));

        $xlsx = $this->withToken($token)->get('/api/v1/products/export?format=xlsx')->assertOk();
        self::assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('Content-Type'));
    }

    public function test_orden_fuera_de_lista_blanca_y_busqueda_con_regex(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');

        $this->withToken($token)->getJson('/api/v1/products?sort=password')->assertStatus(422);
        $this->withToken($token)->getJson('/api/v1/products?search=' . urlencode('.*(a+)+$'))->assertOk()->assertJsonPath('meta.total', 0);
    }
}
