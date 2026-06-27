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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_profiles');
    }
};
