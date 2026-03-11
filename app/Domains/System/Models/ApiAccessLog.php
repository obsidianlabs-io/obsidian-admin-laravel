<?php

declare(strict_types=1);

namespace App\Domains\System\Models;

use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiAccessLog extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'trace_id',
        'user_id',
        'tenant_id',
        'method',
        'path',
        'route_name',
        'status_code',
        'duration_ms',
        'request_size',
        'response_size',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tenant_id' => 'integer',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'request_size' => 'integer',
            'response_size' => 'integer',
            'request_id' => 'string',
            'trace_id' => 'string',
        ];
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
}
