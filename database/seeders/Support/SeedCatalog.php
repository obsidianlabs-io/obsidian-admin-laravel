<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

final class SeedCatalog
{
    public const DEFAULT_LOCALE = 'en-US';

    public const DEFAULT_TIMEZONE = 'Asia/Kuala_Lumpur';

    public static function defaultLocale(): string
    {
        $configured = trim((string) config('i18n.default_locale', self::DEFAULT_LOCALE));

        return $configured !== '' ? $configured : self::DEFAULT_LOCALE;
    }

    /**
     * @return list<array{code: string, name: string, status: string}>
     */
    public static function tenants(): array
    {
        return [
            ['code' => 'TENANT_MAIN', 'name' => 'Main Tenant', 'status' => '1'],
            ['code' => 'TENANT_BRANCH', 'name' => 'Branch Tenant', 'status' => '1'],
        ];
    }

    /**
     * @return list<array{
     *   tenantCode: string,
     *   code: string,
     *   name: string,
     *   status: string,
     *   sort: int
     * }>
     */
    public static function organizations(): array
    {
        return [
            ['tenantCode' => 'TENANT_MAIN', 'code' => 'ORG_MAIN_HQ', 'name' => 'Main HQ', 'status' => '1', 'sort' => 10],
            ['tenantCode' => 'TENANT_MAIN', 'code' => 'ORG_MAIN_RETAIL', 'name' => 'Main Retail', 'status' => '1', 'sort' => 20],
            ['tenantCode' => 'TENANT_BRANCH', 'code' => 'ORG_BRANCH_HQ', 'name' => 'Branch HQ', 'status' => '1', 'sort' => 10],
            ['tenantCode' => 'TENANT_BRANCH', 'code' => 'ORG_BRANCH_RETAIL', 'name' => 'Branch Retail', 'status' => '1', 'sort' => 20],
        ];
    }

    /**
     * @return list<array{
     *   tenantCode: string,
     *   organizationCode: string,
     *   code: string,
     *   name: string,
     *   status: string,
     *   sort: int
     * }>
     */
    public static function teams(): array
    {
        return [
            [
                'tenantCode' => 'TENANT_MAIN',
                'organizationCode' => 'ORG_MAIN_HQ',
                'code' => 'TEAM_MAIN_PLATFORM',
                'name' => 'Main Platform Team',
                'status' => '1',
                'sort' => 10,
            ],
            [
                'tenantCode' => 'TENANT_MAIN',
                'organizationCode' => 'ORG_MAIN_RETAIL',
                'code' => 'TEAM_MAIN_RETAIL_SALES',
                'name' => 'Main Retail Sales Team',
                'status' => '1',
                'sort' => 10,
            ],
            [
                'tenantCode' => 'TENANT_BRANCH',
                'organizationCode' => 'ORG_BRANCH_HQ',
                'code' => 'TEAM_BRANCH_PLATFORM',
                'name' => 'Branch Platform Team',
                'status' => '1',
                'sort' => 10,
            ],
            [
                'tenantCode' => 'TENANT_BRANCH',
                'organizationCode' => 'ORG_BRANCH_RETAIL',
                'code' => 'TEAM_BRANCH_RETAIL_SALES',
                'name' => 'Branch Retail Sales Team',
                'status' => '1',
                'sort' => 10,
            ],
        ];
    }

    /**
     * @return list<array{code: string, name: string, group: string, status: string}>
     */
    public static function permissions(): array
    {
        return [
            ['code' => 'user.view', 'name' => 'View Users', 'group' => 'user', 'status' => '1'],
            ['code' => 'user.manage', 'name' => 'Manage Users', 'group' => 'user', 'status' => '1'],
            ['code' => 'organization.view', 'name' => 'View Organizations', 'group' => 'organization', 'status' => '1'],
            ['code' => 'organization.manage', 'name' => 'Manage Organizations', 'group' => 'organization', 'status' => '1'],
            ['code' => 'team.view', 'name' => 'View Teams', 'group' => 'team', 'status' => '1'],
            ['code' => 'team.manage', 'name' => 'Manage Teams', 'group' => 'team', 'status' => '1'],
            ['code' => 'role.view', 'name' => 'View Roles', 'group' => 'role', 'status' => '1'],
            ['code' => 'role.manage', 'name' => 'Manage Roles', 'group' => 'role', 'status' => '1'],
            ['code' => 'audit.view', 'name' => 'View Audit Logs', 'group' => 'audit', 'status' => '1'],
            ['code' => 'audit.policy.view', 'name' => 'View Audit Policies', 'group' => 'audit', 'status' => '1'],
            ['code' => 'audit.policy.manage', 'name' => 'Manage Audit Policies', 'group' => 'audit', 'status' => '1'],
            ['code' => 'permission.view', 'name' => 'View Permissions', 'group' => 'permission', 'status' => '1'],
            ['code' => 'permission.manage', 'name' => 'Manage Permissions', 'group' => 'permission', 'status' => '1'],
            ['code' => 'tenant.view', 'name' => 'View Tenants', 'group' => 'tenant', 'status' => '1'],
            ['code' => 'tenant.manage', 'name' => 'Manage Tenants', 'group' => 'tenant', 'status' => '1'],
            ['code' => 'theme.view', 'name' => 'View Theme Config', 'group' => 'theme', 'status' => '1'],
            ['code' => 'theme.manage', 'name' => 'Manage Theme Config', 'group' => 'theme', 'status' => '1'],
            ['code' => 'language.view', 'name' => 'View Languages', 'group' => 'language', 'status' => '1'],
            ['code' => 'language.manage', 'name' => 'Manage Languages', 'group' => 'language', 'status' => '1'],
            ['code' => 'system.manage', 'name' => 'Manage System Settings', 'group' => 'system', 'status' => '1'],
        ];
    }

    /**
     * @return list<array{code: string, name: string, group: string, status: string}>
     */
    public static function permissionExtensions(): array
    {
        return [
            ['code' => 'system.feature_flags.view', 'name' => 'View Feature Flags', 'group' => 'system', 'status' => '1'],
            ['code' => 'system.feature_flags.manage', 'name' => 'Manage Feature Flags', 'group' => 'system', 'status' => '1'],
            ['code' => 'system.crud_schema.view', 'name' => 'View CRUD Schemas', 'group' => 'system', 'status' => '1'],
        ];
    }

    /**
     * @return list<array{
     *   code: string,
     *   name: string,
     *   description: string,
     *   status: string,
     *   level: int,
     *   tenantCode: string|null,
     *   permissionSet: 'super'|'admin'|'user'
     * }>
     */
    public static function roles(): array
    {
        return [
            [
                'code' => 'R_SUPER',
                'name' => 'Super Admin',
                'description' => 'Super administrator role',
                'status' => '1',
                'level' => 999,
                'tenantCode' => null,
                'permissionSet' => 'super',
            ],
            [
                'code' => 'R_ADMIN',
                'name' => 'Admin',
                'description' => 'Main tenant administrator role',
                'status' => '1',
                'level' => 500,
                'tenantCode' => 'TENANT_MAIN',
                'permissionSet' => 'admin',
            ],
            [
                'code' => 'R_ADMIN',
                'name' => 'Admin',
                'description' => 'Branch tenant administrator role',
                'status' => '1',
                'level' => 500,
                'tenantCode' => 'TENANT_BRANCH',
                'permissionSet' => 'admin',
            ],
            [
                'code' => 'R_USER',
                'name' => 'User',
                'description' => 'Main tenant standard user role',
                'status' => '1',
                'level' => 100,
                'tenantCode' => 'TENANT_MAIN',
                'permissionSet' => 'user',
            ],
            [
                'code' => 'R_USER',
                'name' => 'User',
                'description' => 'Branch tenant standard user role',
                'status' => '1',
                'level' => 100,
                'tenantCode' => 'TENANT_BRANCH',
                'permissionSet' => 'user',
            ],
        ];
    }

    /**
     * @return list<array{
     *   name: string,
     *   email: string,
     *   password: string,
     *   status: string,
     *   roleCode: string,
     *   tenantCode: string|null,
     *   organizationCode: string|null,
     *   teamCode: string|null
     * }>
     */
    public static function users(): array
    {
        return [
            [
                'name' => 'Super',
                'email' => 'super@obsidian.local',
                'password' => '123456',
                'status' => '1',
                'roleCode' => 'R_SUPER',
                'tenantCode' => null,
                'organizationCode' => null,
                'teamCode' => null,
            ],
            [
                'name' => 'Admin',
                'email' => 'admin@obsidian.local',
                'password' => '123456',
                'status' => '1',
                'roleCode' => 'R_ADMIN',
                'tenantCode' => 'TENANT_MAIN',
                'organizationCode' => 'ORG_MAIN_HQ',
                'teamCode' => 'TEAM_MAIN_PLATFORM',
            ],
            [
                'name' => 'AdminBranch',
                'email' => 'admin.branch@obsidian.local',
                'password' => '123456',
                'status' => '1',
                'roleCode' => 'R_ADMIN',
                'tenantCode' => 'TENANT_BRANCH',
                'organizationCode' => 'ORG_BRANCH_HQ',
                'teamCode' => 'TEAM_BRANCH_PLATFORM',
            ],
            [
                'name' => 'User',
                'email' => 'user@obsidian.local',
                'password' => '123456',
                'status' => '1',
                'roleCode' => 'R_USER',
                'tenantCode' => 'TENANT_MAIN',
                'organizationCode' => 'ORG_MAIN_RETAIL',
                'teamCode' => 'TEAM_MAIN_RETAIL_SALES',
            ],
            [
                'name' => 'UserBranch',
                'email' => 'user.branch@obsidian.local',
                'password' => '123456',
                'status' => '1',
                'roleCode' => 'R_USER',
                'tenantCode' => 'TENANT_BRANCH',
                'organizationCode' => 'ORG_BRANCH_RETAIL',
                'teamCode' => 'TEAM_BRANCH_RETAIL_SALES',
            ],
        ];
    }

    /**
     * @return array{
     *   scope_type: string,
     *   scope_id: int|null,
     *   name: string,
     *   status: string,
     *   config: array{themeColor: string, themeRadius: int},
     *   version: int
     * }
     */
    public static function projectThemeProfile(): array
    {
        return [
            'scope_type' => 'platform',
            'scope_id' => null,
            'name' => 'Project Default',
            'status' => '1',
            'config' => [
                'darkSider' => true,
                'tabVisible' => false,
                'themeConfigVisible' => false,
                'themeSchemaVisible' => false,
                'globalSearchVisible' => false,
                'tabFullscreenVisible' => false,
                'headerFullscreenVisible' => false,
                'footerVisible' => false,
            ],
            'version' => 1,
        ];
    }

    /**
     * @return list<array{
     *   action: string,
     *   enabled: bool,
     *   retention_days: int
     * }>
     */
    public static function auditPolicies(): array
    {
        return [
            [
                'action' => 'user.locale.update',
                'enabled' => false,
                'retention_days' => 30,
            ],
        ];
    }
}
