<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_policies', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_scope_id')->default(0);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('action', 120);
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('enabled')->default(true);
            $table->decimal('sampling_rate', 5, 4)->default(1.0000);
            $table->unsignedSmallInteger('retention_days')->nullable();
            $table->timestamps();

            $table->unique(['tenant_scope_id', 'action'], 'audit_policies_scope_action_unique');
            $table->index(['tenant_id', 'action'], 'audit_policies_tenant_action_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_policies');
    }
};
