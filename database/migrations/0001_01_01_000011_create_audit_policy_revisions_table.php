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
        Schema::create('audit_policy_revisions', static function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 32)->default('global');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason', 500);
            $table->unsignedSmallInteger('changed_count')->default(0);
            $table->json('changed_actions')->nullable();
            $table->json('changes')->nullable();
            $table->json('policy_snapshot');
            $table->timestamps();

            $table->index(['scope', 'id'], 'audit_policy_revisions_scope_id_index');
            $table->index('changed_by_user_id', 'audit_policy_revisions_changed_by_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_policy_revisions');
    }
};
