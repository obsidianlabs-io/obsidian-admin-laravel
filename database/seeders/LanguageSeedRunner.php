<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Support\VersionedSeeder;
use Illuminate\Support\Facades\DB;

class LanguageSeedRunner extends VersionedSeeder
{
    /**
     * @var array<int, list<array{code: string, name: string, status: string, is_default: bool, sort: int}>>
     */
    private const VERSIONED_LOCALES = [
        1 => [
            [
                'code' => 'en-US',
                'name' => 'English',
                'status' => '1',
                'is_default' => true,
                'sort' => 1,
            ],
            [
                'code' => 'zh-CN',
                'name' => '简体中文',
                'status' => '1',
                'is_default' => false,
                'sort' => 2,
            ],
        ],
    ];

    /**
     * @return list<string>
     */
    protected function requiredTables(): array
    {
        return array_merge(parent::requiredTables(), ['languages']);
    }

    protected function module(): string
    {
        return 'language.locales';
    }

    /**
     * @return array<int, list<array{code: string, name: string, status: string, is_default: bool, sort: int}>>
     */
    protected function versionedPayloads(): array
    {
        return self::VERSIONED_LOCALES;
    }

    protected function applyVersion(int $version, mixed $payload): void
    {
        unset($version);

        /** @var list<array{code: string, name: string, status: string, is_default: bool, sort: int}> $locales */
        $locales = $payload;
        $this->upsertLocales($locales);
    }

    /**
     * @param  list<array{code: string, name: string, status: string, is_default: bool, sort: int}>  $locales
     */
    private function upsertLocales(array $locales): void
    {
        $now = now();
        $rows = array_map(static fn (array $locale): array => [
            'code' => $locale['code'],
            'name' => $locale['name'],
            'status' => $locale['status'],
            'is_default' => $locale['is_default'],
            'sort' => $locale['sort'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $locales);

        DB::table('languages')->upsert(
            $rows,
            ['code'],
            ['name', 'status', 'is_default', 'sort', 'updated_at']
        );
    }
}
