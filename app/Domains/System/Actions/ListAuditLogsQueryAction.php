<?php

declare(strict_types=1);

namespace App\Domains\System\Actions;

use App\Domains\Shared\Support\TenantVisibility;
use App\Domains\System\Models\AuditLog;
use App\DTOs\Audit\ListAuditLogsInputDTO;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class ListAuditLogsQueryAction
{
    /**
     * @return Builder<AuditLog>
     */
    public function handle(
        ListAuditLogsInputDTO $input,
        ?int $tenantId,
        bool $isSuper,
        string $userTimezone,
    ): Builder {
        $query = AuditLog::query()
            ->with(['user:id,name', 'tenant:id,name'])
            ->select([
                'id',
                'user_id',
                'tenant_id',
                'action',
                'log_type',
                'auditable_type',
                'auditable_id',
                'old_values',
                'new_values',
                'ip_address',
                'user_agent',
                'request_id',
                'created_at',
            ]);

        TenantVisibility::applyScope($query, $tenantId, $isSuper);
        $this->applyFilters($query, $input, $userTimezone);

        return $query;
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    private function applyFilters(Builder $query, ListAuditLogsInputDTO $input, string $userTimezone): void
    {
        if ($input->keyword !== '') {
            $query->where(function (Builder $builder) use ($input): void {
                $builder->where('action', 'like', '%'.$input->keyword.'%')
                    ->orWhere('log_type', 'like', '%'.$input->keyword.'%')
                    ->orWhere('auditable_type', 'like', '%'.$input->keyword.'%')
                    ->orWhere('ip_address', 'like', '%'.$input->keyword.'%')
                    ->orWhere('request_id', 'like', '%'.$input->keyword.'%')
                    ->orWhereHas('user', static function (Builder $userQuery) use ($input): void {
                        $userQuery->where('name', 'like', '%'.$input->keyword.'%');
                    });
            });
        }

        if ($input->action !== '') {
            $query->where('action', 'like', '%'.$input->action.'%');
        }

        if ($input->logType !== '') {
            $query->where('log_type', $input->logType);
        }

        if ($input->userName !== '') {
            $query->whereHas('user', static function (Builder $userQuery) use ($input): void {
                $userQuery->where('name', 'like', '%'.$input->userName.'%');
            });
        }

        if ($input->requestId !== '') {
            $query->where('request_id', 'like', '%'.$input->requestId.'%');
        }

        if ($input->dateFrom === '' && $input->dateTo === '') {
            $defaultFrom = now($userTimezone)->subDays(7)->utc();
            $query->where('created_at', '>=', $defaultFrom);
        }

        if ($input->dateFrom !== '') {
            $from = Carbon::parse($input->dateFrom, $userTimezone)->utc();
            $query->where('created_at', '>=', $from);
        }

        if ($input->dateTo !== '') {
            $to = Carbon::parse($input->dateTo, $userTimezone)->utc();

            if ($input->dateFrom !== '' && $input->dateFrom === $input->dateTo) {
                $to = $to->copy()->addMinute()->subSecond();
            }

            $query->where('created_at', '<=', $to);
        }
    }
}
