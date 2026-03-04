<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Organization;
use App\Domains\Tenant\Models\Team;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTeamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_organization_and_team_in_tenant_scope(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');

        $createOrganizationResponse = $this->postJson('/api/organization', [
            'organizationCode' => 'ORG_MAIN_DEV',
            'organizationName' => 'Main Dev Org',
            'status' => '1',
            'sort' => 30,
            'description' => 'Main tenant development organization',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createOrganizationResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.organizationCode', 'ORG_MAIN_DEV');

        $organizationId = (int) $createOrganizationResponse->json('data.id');

        $listOrganizationResponse = $this->getJson('/api/organization/list?current=1&size=100&keyword=ORG_MAIN_DEV', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $listOrganizationResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $createTeamResponse = $this->postJson('/api/team', [
            'organizationId' => $organizationId,
            'teamCode' => 'TEAM_MAIN_DEV_CORE',
            'teamName' => 'Main Dev Core Team',
            'status' => '1',
            'sort' => 50,
            'description' => 'Core development team',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createTeamResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.teamCode', 'TEAM_MAIN_DEV_CORE');

        $teamId = (int) $createTeamResponse->json('data.id');

        $listTeamResponse = $this->getJson('/api/team/list?current=1&size=100&organizationId='.$organizationId, [
            'Authorization' => 'Bearer '.$token,
        ]);

        $listTeamResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $updateTeamResponse = $this->putJson('/api/team/'.$teamId, [
            'organizationId' => $organizationId,
            'teamCode' => 'TEAM_MAIN_DEV_CORE',
            'teamName' => 'Main Dev Core Team Updated',
            'status' => '2',
            'sort' => 60,
            'description' => 'Updated team',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateTeamResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.teamName', 'Main Dev Core Team Updated')
            ->assertJsonPath('data.status', '2');

        $deleteTeamResponse = $this->deleteJson('/api/team/'.$teamId, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $deleteTeamResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $deleteOrganizationResponse = $this->deleteJson('/api/organization/'.$organizationId, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $deleteOrganizationResponse->assertOk()
            ->assertJsonPath('code', '0000');
    }

    public function test_super_admin_without_selected_tenant_cannot_access_tenant_scoped_org_team_apis(): void
    {
        $this->seed();

        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');

        $organizationListWithoutTenant = $this->getJson('/api/organization/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $organizationListWithoutTenant->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Please select a tenant first');

        $teamListWithoutTenant = $this->getJson('/api/team/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $teamListWithoutTenant->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Please select a tenant first');

        $organizationListWithTenant = $this->getJson('/api/organization/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $branchTenant->id,
        ]);

        $organizationListWithTenant->assertOk()
            ->assertJsonPath('code', '0000');
    }

    public function test_user_create_with_team_auto_binds_organization(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');
        $team = Team::query()->where('code', 'TEAM_MAIN_PLATFORM')->firstOrFail();

        $createResponse = $this->postJson('/api/user', [
            'userName' => 'BoundByTeamUser',
            'email' => 'bound.by.team.user@obsidian.local',
            'roleCode' => 'R_USER',
            'status' => '1',
            'password' => 'BoundByTeam123',
            'teamId' => $team->id,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.teamId', (string) $team->id)
            ->assertJsonPath('data.organizationId', (string) $team->organization_id);

        $this->assertDatabaseHas('users', [
            'email' => 'bound.by.team.user@obsidian.local',
            'team_id' => $team->id,
            'organization_id' => $team->organization_id,
        ]);
    }

    public function test_user_create_rejects_team_and_organization_mismatch(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');
        $organization = Organization::query()->where('code', 'ORG_MAIN_HQ')->firstOrFail();
        $team = Team::query()->where('code', 'TEAM_MAIN_RETAIL_SALES')->firstOrFail();

        $createResponse = $this->postJson('/api/user', [
            'userName' => 'InvalidBindingUser',
            'email' => 'invalid.binding.user@obsidian.local',
            'roleCode' => 'R_USER',
            'status' => '1',
            'password' => 'InvalidBinding123',
            'organizationId' => $organization->id,
            'teamId' => $team->id,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Team does not belong to selected organization');
    }

    public function test_platform_user_create_rejects_organization_team_binding_without_tenant_scope(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');
        $organization = Organization::query()->where('code', 'ORG_MAIN_HQ')->firstOrFail();
        $team = Team::query()->where('code', 'TEAM_MAIN_PLATFORM')->firstOrFail();

        $createResponse = $this->postJson('/api/user', [
            'userName' => 'PlatformBoundUser',
            'email' => 'platform.bound.user@obsidian.local',
            'roleCode' => 'R_SUPER',
            'status' => '1',
            'password' => 'PlatformBound123',
            'organizationId' => $organization->id,
            'teamId' => $team->id,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Organization and team are not available for platform users');

        $this->assertDatabaseMissing('users', [
            'email' => 'platform.bound.user@obsidian.local',
        ]);
    }

    public function test_user_create_rejects_inactive_organization_binding(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');
        $organization = Organization::query()->where('code', 'ORG_MAIN_HQ')->firstOrFail();
        $organization->forceFill(['status' => '2'])->save();

        $createResponse = $this->postJson('/api/user', [
            'userName' => 'InactiveOrgBindingUser',
            'email' => 'inactive.org.binding@obsidian.local',
            'roleCode' => 'R_USER',
            'status' => '1',
            'password' => 'InactiveOrg123',
            'organizationId' => $organization->id,
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $organization->tenant_id,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Organization is inactive');
    }

    public function test_user_create_rejects_inactive_team_binding(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');
        $team = Team::query()->where('code', 'TEAM_MAIN_PLATFORM')->firstOrFail();
        $team->forceFill(['status' => '2'])->save();

        $createResponse = $this->postJson('/api/user', [
            'userName' => 'InactiveTeamBindingUser',
            'email' => 'inactive.team.binding@obsidian.local',
            'roleCode' => 'R_USER',
            'status' => '1',
            'password' => 'InactiveTeam123',
            'teamId' => $team->id,
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $team->tenant_id,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Team is inactive');
    }

    public function test_tenant_user_with_inactive_organization_is_blocked_from_business_api_without_logout(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Admin');

        $admin = User::query()->where('email', 'admin@obsidian.local')->firstOrFail();
        $organizationId = $admin->organization_id;
        $this->assertNotNull($organizationId);

        Organization::query()->whereKey($organizationId)->update(['status' => '2']);

        $response = $this->getJson('/api/user/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Organization is inactive');
    }

    private function loginAndGetToken(string $userName): string
    {
        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => $userName,
            'password' => '123456',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('code', '0000');

        return (string) $loginResponse->json('data.token');
    }
}
