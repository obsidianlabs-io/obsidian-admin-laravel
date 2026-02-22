<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

abstract class VersionedSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->hasRequiredTables()) {
            return;
        }

        $versions = $this->versionedPayloads();
        if ($versions === []) {
            return;
        }

        ksort($versions);
        $this->assertVersionSequence($versions);

        foreach ($versions as $version => $payload) {
            $checksum = $this->checksum($payload);
            $appliedVersion = DB::table('seed_versions')
                ->where('module', $this->module())
                ->where('version', $version)
                ->first();

            if ($appliedVersion) {
                if ((string) ($appliedVersion->checksum ?? '') !== $checksum) {
                    throw new RuntimeException(sprintf(
                        'Seed version checksum mismatch for module [%s] version [%s]. Create a new seed version instead of mutating applied data.',
                        $this->module(),
                        $version
                    ));
                }

                continue;
            }

            DB::transaction(function () use ($version, $checksum, $payload): void {
                $this->applyVersion($version, $payload);

                DB::table('seed_versions')->insert([
                    'module' => $this->module(),
                    'version' => $version,
                    'checksum' => $checksum,
                    'applied_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    /**
     * @return list<string>
     */
    protected function requiredTables(): array
    {
        return ['seed_versions'];
    }

    abstract protected function module(): string;

    /**
     * @return array<int, mixed>
     */
    abstract protected function versionedPayloads(): array;

    abstract protected function applyVersion(int $version, mixed $payload): void;

    private function hasRequiredTables(): bool
    {
        foreach ($this->requiredTables() as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $versions
     */
    private function assertVersionSequence(array $versions): void
    {
        $expectedVersion = 1;

        foreach (array_keys($versions) as $version) {
            if ($version !== $expectedVersion) {
                throw new RuntimeException(sprintf(
                    'Seed module [%s] has invalid version sequence. Expected version [%d], got [%d].',
                    $this->module(),
                    $expectedVersion,
                    $version
                ));
            }

            $expectedVersion++;
        }
    }

    private function checksum(mixed $payload): string
    {
        return hash('sha256', json_encode($this->normalize($payload)) ?: '');
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
