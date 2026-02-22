<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleScopeUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_role_code_cannot_be_duplicated_when_tenant_is_null(): void
    {
        $this->seed();

        $this->expectException(QueryException::class);

        Role::query()->create([
            'code' => 'R_SUPER',
            'name' => 'Super Admin Duplicate',
            'description' => 'Duplicate global role',
            'status' => '1',
            'level' => 100,
            'tenant_id' => null,
        ]);
    }
}
