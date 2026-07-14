<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Permission;
use App\Support\ApiResultCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_permissions(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/permission/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => ['records', 'current', 'size', 'total'],
            ]);

        $this->assertNotEmpty($response->json('data.records'));
    }

    public function test_super_admin_can_get_all_permissions(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/permission/all', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value);

        $records = $response->json('data.records');
        $this->assertIsArray($records);
        $this->assertNotEmpty($records);
    }

    public function test_super_admin_can_create_permission(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->postJson('/api/permission', [
            'permissionCode' => 'test.custom.view',
            'permissionName' => 'Test Custom View',
            'group' => 'Test',
            'description' => 'Test permission for CRUD test',
            'status' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
            ->assertJsonPath('msg', 'Permission created');

        $this->assertDatabaseHas('permissions', [
            'code' => 'test.custom.view',
            'name' => 'Test Custom View',
        ]);
    }

    public function test_super_admin_can_update_permission(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        // Create a permission first
        $createResponse = $this->postJson('/api/permission', [
            'permissionCode' => 'test.update.target',
            'permissionName' => 'Before Update',
            'group' => 'Test',
            'description' => 'Before',
            'status' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $permissionId = $createResponse->json('data.id');

        $response = $this->putJson("/api/permission/{$permissionId}", [
            'permissionCode' => 'test.update.target',
            'permissionName' => 'After Update',
            'group' => 'Test',
            'description' => 'After',
            'status' => '1',
            'updatedAt' => $createResponse->json('data.updatedAt'),
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
            ->assertJsonPath('msg', 'Permission updated');

        $this->assertDatabaseHas('permissions', [
            'id' => $permissionId,
            'name' => 'After Update',
        ]);
    }

    public function test_super_admin_can_deactivate_permission(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        // Create a permission first
        $createResponse = $this->postJson('/api/permission', [
            'permissionCode' => 'test.delete.target',
            'permissionName' => 'Delete Target',
            'group' => 'Test',
            'description' => 'To be deactivated',
            'status' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $permissionId = $createResponse->json('data.id');

        $response = $this->deleteJson("/api/permission/{$permissionId}", [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value);

        $this->assertDatabaseHas('permissions', [
            'id' => $permissionId,
            'status' => '2',
        ]);
    }

    public function test_regular_admin_without_tenant_cannot_access_permissions(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Admin');

        $response = $this->getJson('/api/permission/list', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $this->assertContains($response->status(), [403, 200]);
        if ($response->status() === 200) {
            // Admin with tenant context might get "switch to no tenant" error
            $this->assertNotSame(ApiResultCode::SUCCESS->value, $response->json('code'));
        }
    }

    public function test_cannot_create_duplicate_permission_code(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        // Try to create with an existing code
        $existingCode = Permission::query()->value('code');

        $response = $this->postJson('/api/permission', [
            'permissionCode' => $existingCode,
            'permissionName' => 'Duplicate',
            'group' => 'Test',
            'description' => '',
            'status' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $this->assertContains($response->status(), [422, 400, 409]);
    }

    private function loginAndGetToken(string $userName): string
    {
        $response = $this->postJson('/api/auth/login', [
            'userName' => $userName,
            'password' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value);

        return (string) $response->json('data.token');
    }
}
