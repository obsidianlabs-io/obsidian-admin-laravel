<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ApiResultCode;
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
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
            ->assertJsonPath('data.resource', 'user')
            ->assertJsonPath('data.permission', 'user.view')
            ->assertJsonPath('data.columns.0.key', 'index');
    }

    public function test_schema_endpoint_requires_authentication(): void
    {
        $this->seed();

        $response = $this->getJson('/api/system/ui/crud-schema/user');

        $response->assertUnauthorized()
            ->assertJsonPath('code', ApiResultCode::UNAUTHORIZED->value)
            ->assertJsonPath('msg', 'Unauthorized');
    }
}
