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
        // audit_logs: user_id is a foreign key, and future "filter by user" queries
        // would benefit from a composite index with created_at for time-range scans.
        if (! Schema::hasIndex('audit_logs', 'audit_logs_user_id_created_at_index')) {
            Schema::table('audit_logs', static function (Blueprint $table): void {
                $table->index(['user_id', 'created_at'], 'audit_logs_user_id_created_at_index');
            });
        }

        // api_access_logs: user_id is a foreign key; index supports per-user log
        // queries and time-range filtered lookups.
        if (! Schema::hasIndex('api_access_logs', 'api_access_logs_user_id_created_at_index')) {
            Schema::table('api_access_logs', static function (Blueprint $table): void {
                $table->index(['user_id', 'created_at'], 'api_access_logs_user_id_created_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('audit_logs', 'audit_logs_user_id_created_at_index')) {
            Schema::table('audit_logs', static function (Blueprint $table): void {
                $table->dropIndex('audit_logs_user_id_created_at_index');
            });
        }

        if (Schema::hasIndex('api_access_logs', 'api_access_logs_user_id_created_at_index')) {
            Schema::table('api_access_logs', static function (Blueprint $table): void {
                $table->dropIndex('api_access_logs_user_id_created_at_index');
            });
        }
    }
};
