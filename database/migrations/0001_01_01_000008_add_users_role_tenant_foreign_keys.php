<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->foreign(['role_id', 'tenant_scope_id'], 'users_role_tenant_scope_foreign')
                ->references(['id', 'tenant_scope_id'])
                ->on('roles')
                ->restrictOnDelete();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();
        });

        if ($this->supportsTenantScopeCheckConstraints()) {
            DB::statement(
                'ALTER TABLE users ADD CONSTRAINT users_tenant_scope_consistency_check '
                .'CHECK ((tenant_id IS NULL AND tenant_scope_id = 0) OR (tenant_id IS NOT NULL AND tenant_scope_id = tenant_id))'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->supportsTenantScopeCheckConstraints()) {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_tenant_scope_consistency_check');
        }

        Schema::table('users', static function (Blueprint $table): void {
            $table->dropForeign('users_role_tenant_scope_foreign');
            $table->dropForeign('users_tenant_id_foreign');
        });
    }

    private function supportsTenantScopeCheckConstraints(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql', 'sqlsrv'], true);
    }
};
