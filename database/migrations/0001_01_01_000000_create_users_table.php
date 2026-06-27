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
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status', 1)->default('1');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('tenant_scope_id')->default(0);
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'role_id', 'status'], 'users_tenant_role_status_index');
            $table->index(['tenant_id', 'deleted_at'], 'users_tenant_deleted_at_index');
            $table->index(['role_id', 'tenant_scope_id'], 'users_role_tenant_scope_index');
        });

        Schema::create('password_reset_tokens', static function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
