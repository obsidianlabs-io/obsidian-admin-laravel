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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('language_translations');
    }
};
