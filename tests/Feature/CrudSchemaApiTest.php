<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudSchemaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_schema_is_returned_for_authorized_user(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);
        $token = (string) $loginResponse->json('data.token');

        $response = $this->getJson('/api/system/ui/crud-schema/user', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.resource', 'user')
            ->assertJsonPath('data.permission', 'user.view')
            ->assertJsonPath('data.columns.0.key', 'index');
    }

    public function test_schema_endpoint_requires_authentication(): void
    {
        $this->seed();

        $response = $this->getJson('/api/system/ui/crud-schema/user');

        $response->assertOk()
            ->assertJsonPath('code', '8888')
            ->assertJsonPath('msg', 'Unauthorized');
    }
}
