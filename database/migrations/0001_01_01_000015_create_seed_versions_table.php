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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seed_versions');
    }
};
