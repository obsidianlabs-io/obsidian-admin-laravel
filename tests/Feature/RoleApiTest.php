<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Role;
use App\Support\ApiResultCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_roles(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/role/list?current=1&size=10', [
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

    public function test_super_admin_can_get_all_roles(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/role/all', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value);

        $records = $response->json('data.records');
        $this->assertIsArray($records);
        $this->assertNotEmpty($records);
    }

    public function test_super_admin_can_get_assignable_permissions(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/role/assignable-permissions', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value);

        $records = $response->json('data.records');
        $this->assertIsArray($records);
        $this->assertNotEmpty($records);
    }

    public function test_super_admin_can_create_role(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->postJson('/api/role', [
            'roleCode' => 'R_TEST_CRUD',
            'roleName' => 'Test CRUD Role',
            'description' => 'Role for CRUD testing',
            'level' => 50,
            'status' => '1',
            'permissionCodes' => [],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
            ->assertJsonPath('msg', 'Role created');

        $this->assertDatabaseHas('roles', [
            'code' => 'R_TEST_CRUD',
            'name' => 'Test CRUD Role',
        ]);
    }

    public function test_super_admin_can_update_role(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        // Create a role first
        $createResponse = $this->postJson('/api/role', [
            'roleCode' => 'R_UPDATE_TEST',
            'roleName' => 'Before Update',
            'description' => 'Before',
            'level' => 50,
            'status' => '1',
            'permissionCodes' => [],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $roleId = $createResponse->json('data.id');
        $updatedAt = $createResponse->json('data.updatedAt');

        $response = $this->putJson("/api/role/{$roleId}", [
            'roleCode' => 'R_UPDATE_TEST',
            'roleName' => 'After Update',
            'description' => 'After',
            'level' => 50,
            'status' => '1',
            'permissionCodes' => [],
            'updatedAt' => $updatedAt,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
            ->assertJsonPath('msg', 'Role updated');

        $this->assertDatabaseHas('roles', [
            'id' => $roleId,
            'name' => 'After Update',
        ]);
    }

    public function test_super_admin_can_deactivate_role(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        // Create a role first
        $createResponse = $this->postJson('/api/role', [
            'roleCode' => 'R_DELETE_TEST',
            'roleName' => 'Delete Target',
            'description' => 'To be deactivated',
            'level' => 50,
            'status' => '1',
            'permissionCodes' => [],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $roleId = $createResponse->json('data.id');

        $response = $this->deleteJson("/api/role/{$roleId}", [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value);

        $this->assertDatabaseHas('roles', [
            'id' => $roleId,
            'status' => '2',
        ]);
    }

    public function test_super_admin_can_sync_role_permissions(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        // Create a role first
        $createResponse = $this->postJson('/api/role', [
            'roleCode' => 'R_SYNC_TEST',
            'roleName' => 'Sync Test Role',
            'description' => 'For permission sync test',
            'level' => 50,
            'status' => '1',
            'permissionCodes' => [],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $roleId = $createResponse->json('data.id');
        $updatedAt = $createResponse->json('data.updatedAt');

        // Sync some permissions
        $response = $this->putJson("/api/role/{$roleId}/permissions", [
            'permissionCodes' => ['role.view', 'user.view'],
            'updatedAt' => $updatedAt,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
            ->assertJsonPath('msg', 'Role permissions updated');
    }

    public function test_cannot_create_role_with_reserved_code(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->postJson('/api/role', [
            'roleCode' => 'R_SUPER',
            'roleName' => 'Duplicate Super',
            'description' => '',
            'level' => 100,
            'status' => '1',
            'permissionCodes' => [],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $this->assertContains($response->status(), [403, 400, 422]);
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
