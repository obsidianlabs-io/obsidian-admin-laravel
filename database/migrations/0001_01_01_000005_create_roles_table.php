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
        Schema::create('roles', static function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('status', 1)->default('1');
            $table->unsignedSmallInteger('level')->default(10);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('tenant_scope_id')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_scope_id', 'code'], 'roles_scope_code_unique');
            $table->unique(['id', 'tenant_scope_id'], 'roles_id_tenant_scope_unique');
            $table->index('code', 'roles_code_index');
            $table->index(['tenant_id', 'level', 'status'], 'roles_tenant_level_status_index');
            $table->index(['tenant_id', 'deleted_at'], 'roles_tenant_deleted_at_index');
        });

        if ($this->supportsTenantScopeCheckConstraints()) {
            DB::statement(
                'ALTER TABLE roles ADD CONSTRAINT roles_tenant_scope_consistency_check '
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
            DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_tenant_scope_consistency_check');
        }

        Schema::dropIfExists('roles');
    }

    private function supportsTenantScopeCheckConstraints(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql', 'sqlsrv'], true);
    }
};
