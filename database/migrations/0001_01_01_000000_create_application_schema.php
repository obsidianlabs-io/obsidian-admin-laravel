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

        Schema::create('cache', static function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create('cache_locks', static function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });

        Schema::create('jobs', static function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', static function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('personal_access_tokens', static function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('tenants', static function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('status', 1)->default('1');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at'], 'tenants_status_deleted_at_index');
        });

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

        Schema::create('permissions', static function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 100);
            $table->string('group', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 1)->default('1');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['group', 'status'], 'permissions_group_status_index');
            $table->index(['deleted_at'], 'permissions_deleted_at_index');
        });

        Schema::create('role_permission', static function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['role_id', 'permission_id']);
        });

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
                'ALTER TABLE roles ADD CONSTRAINT roles_tenant_scope_consistency_check '
                .'CHECK ((tenant_id IS NULL AND tenant_scope_id = 0) OR (tenant_id IS NOT NULL AND tenant_scope_id = tenant_id))'
            );

            DB::statement(
                'ALTER TABLE users ADD CONSTRAINT users_tenant_scope_consistency_check '
                .'CHECK ((tenant_id IS NULL AND tenant_scope_id = 0) OR (tenant_id IS NOT NULL AND tenant_scope_id = tenant_id))'
            );
        }

        Schema::create('audit_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('action', 120);
            $table->string('auditable_type', 120);
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 128)->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at'], 'audit_logs_action_created_at_index');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->index(['tenant_id', 'created_at'], 'audit_logs_tenant_created_at_index');
            $table->index(['request_id'], 'audit_logs_request_id_index');
        });

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

        Schema::create('languages', static function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('status', 1)->default('1');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort']);
        });

        Schema::create('language_translations', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
            $table->string('translation_key', 191);
            $table->text('translation_value');
            $table->text('description')->nullable();
            $table->string('status', 1)->default('1');
            $table->timestamps();

            $table->unique(['language_id', 'translation_key']);
            $table->index(['language_id', 'status']);
            $table->index('translation_key');
        });

        Schema::create('user_preferences', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('locale', 10)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('theme_schema', 30)->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();

            $table->index('locale');
            $table->index('timezone');
        });

        Schema::create('seed_versions', static function (Blueprint $table): void {
            $table->id();
            $table->string('module', 100);
            $table->unsignedInteger('version');
            $table->string('checksum', 64)->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->unique(['module', 'version']);
            $table->index('module');
        });

        Schema::create('theme_profiles', static function (Blueprint $table): void {
            $table->id();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('scope_key', 80)->unique();
            $table->string('name', 120)->nullable();
            $table->string('status', 1)->default('1');
            $table->json('config')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
            $table->index(['scope_type', 'status']);

            $table->foreign('scope_id')->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('idempotency_keys', static function (Blueprint $table): void {
            $table->id();
            $table->string('actor_key', 160);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 10);
            $table->string('route_path', 255);
            $table->string('idempotency_key', 128);
            $table->char('request_hash', 64);
            $table->string('status', 16)->default('processing');
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['actor_key', 'method', 'route_path', 'idempotency_key'],
                'idempotency_keys_actor_method_route_key_unique'
            );
            $table->index(['expires_at'], 'idempotency_keys_expires_at_index');
            $table->index(['user_id', 'created_at'], 'idempotency_keys_user_created_at_index');
        });

        Schema::create('features', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('scope');
            $table->text('value');
            $table->timestamps();

            $table->unique(['name', 'scope']);
        });

        if ($this->shouldCreatePulseTables()) {
            Schema::create('pulse_values', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('timestamp');
                $table->string('type');
                $table->mediumText('key');
                $this->addPulseKeyHashColumn($table);
                $table->mediumText('value');

                $table->index('timestamp');
                $table->index('type');
                $table->unique(['type', 'key_hash']);
            });

            Schema::create('pulse_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('timestamp');
                $table->string('type');
                $table->mediumText('key');
                $this->addPulseKeyHashColumn($table);
                $table->bigInteger('value')->nullable();

                $table->index('timestamp');
                $table->index('type');
                $table->index('key_hash');
                $table->index(['timestamp', 'type', 'key_hash', 'value']);
            });

            Schema::create('pulse_aggregates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('bucket');
                $table->unsignedMediumInteger('period');
                $table->string('type');
                $table->mediumText('key');
                $this->addPulseKeyHashColumn($table);
                $table->string('aggregate');
                $table->decimal('value', 20, 2);
                $table->unsignedInteger('count')->nullable();

                $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']);
                $table->index(['period', 'bucket']);
                $table->index('type');
                $table->index(['period', 'type', 'aggregate', 'bucket']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulse_aggregates');
        Schema::dropIfExists('pulse_entries');
        Schema::dropIfExists('pulse_values');
        Schema::dropIfExists('features');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('theme_profiles');
        Schema::dropIfExists('seed_versions');
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('language_translations');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('audit_policy_revisions');
        Schema::dropIfExists('audit_policies');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('tenants');
    }

    private function shouldCreatePulseTables(): bool
    {
        if (! (bool) config('pulse.enabled', true)) {
            return false;
        }

        return in_array($this->pulseDriver(), ['mariadb', 'mysql', 'pgsql', 'sqlite'], true);
    }

    private function pulseDriver(): string
    {
        $connection = config('pulse.storage.database.connection');

        return DB::connection($connection)->getDriverName();
    }

    private function addPulseKeyHashColumn(Blueprint $table): void
    {
        match ($this->pulseDriver()) {
            'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
            'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
            'sqlite' => $table->string('key_hash'),
            default => throw new \RuntimeException('Pulse key hash column is not supported for current database driver.'),
        };
    }

    private function supportsTenantScopeCheckConstraints(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql', 'sqlsrv'], true);
    }
};
