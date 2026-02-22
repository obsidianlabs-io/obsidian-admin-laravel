<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LanguageSeedRunner::class,
            TenantSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            ThemeProfileSeeder::class,
            AuditPolicySeeder::class,
            UserSeeder::class,
        ]);
    }
}
