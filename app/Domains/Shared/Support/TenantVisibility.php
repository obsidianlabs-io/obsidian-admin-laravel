<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class TenantVisibility
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function applyScope(
        Builder $query,
        ?int $tenantId,
        bool $isSuper,
        string $tenantColumn = 'tenant_id'
    ): void {
        $qualifiedTenantColumn = self::qualifyColumn($query, $tenantColumn);

        if ($isSuper) {
            if ($tenantId === null) {
                $query->whereNull($qualifiedTenantColumn);

                return;
            }

            $query->where($qualifiedTenantColumn, $tenantId);

            return;
        }

        if ($tenantId === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($qualifiedTenantColumn, $tenantId);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function qualifyColumn(Builder $query, string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $query->getModel()->qualifyColumn($column);
    }
}
