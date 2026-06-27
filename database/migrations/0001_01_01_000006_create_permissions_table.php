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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
