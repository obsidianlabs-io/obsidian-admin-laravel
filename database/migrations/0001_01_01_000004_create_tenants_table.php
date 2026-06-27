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
        Schema::create('tenants', static function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('status', 1)->default('1');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at'], 'tenants_status_deleted_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
