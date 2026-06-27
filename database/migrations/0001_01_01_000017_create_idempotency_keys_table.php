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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
