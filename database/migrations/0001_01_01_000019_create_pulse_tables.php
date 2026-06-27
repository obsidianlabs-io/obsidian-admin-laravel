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
        if (! $this->shouldCreatePulseTables()) {
            return;
        }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->shouldCreatePulseTables()) {
            return;
        }

        Schema::dropIfExists('pulse_aggregates');
        Schema::dropIfExists('pulse_entries');
        Schema::dropIfExists('pulse_values');
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
            default => throw new RuntimeException('Pulse key hash column is not supported for current database driver.'),
        };
    }
};
