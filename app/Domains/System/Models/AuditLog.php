<?php

declare(strict_types=1);

namespace App\Domains\System\Models;

use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Tenant;
use App\Policies\AuditLogPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(AuditLogPolicy::class)]
class AuditLog extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    public const LOG_TYPE_LOGIN = 'login';

    public const LOG_TYPE_API = 'api';

    public const LOG_TYPE_OPERATION = 'operation';

    public const LOG_TYPE_DATA = 'data';

    public const LOG_TYPE_PERMISSION = 'permission';

    /**
     * @var list<string>
     */
    protected $fillable = [
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tenant_id' => 'integer',
            'log_type' => 'string',
            'auditable_id' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
            'request_id' => 'string',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withLogType(Builder $query, string $logType): void
    {
        $query->where('log_type', $logType);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matchingRequestId(Builder $query, string $requestId): void
    {
        $query->where('request_id', 'like', '%'.$requestId.'%');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forAction(Builder $query, string $action): void
    {
        $query->where('action', $action);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function beforeTimestamp(Builder $query, CarbonInterface $cutoff): void
    {
        $query->where('created_at', '<', $cutoff);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function fromTimestamp(Builder $query, CarbonInterface $from): void
    {
        $query->where('created_at', '>=', $from);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function untilTimestamp(Builder $query, CarbonInterface $to): void
    {
        $query->where('created_at', '<=', $to);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return list<string>
     */
    public static function logTypes(): array
    {
        return [
            self::LOG_TYPE_LOGIN,
            self::LOG_TYPE_API,
            self::LOG_TYPE_OPERATION,
            self::LOG_TYPE_DATA,
            self::LOG_TYPE_PERMISSION,
        ];
    }
}
