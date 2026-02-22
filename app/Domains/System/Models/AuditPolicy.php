<?php

declare(strict_types=1);

namespace App\Domains\System\Models;

use App\Domains\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditPolicy extends Model
{
    use HasFactory;

    public const PLATFORM_SCOPE_ID = 0;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_scope_id',
        'tenant_id',
        'action',
        'is_mandatory',
        'enabled',
        'sampling_rate',
        'retention_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_scope_id' => 'integer',
            'tenant_id' => 'integer',
            'is_mandatory' => 'boolean',
            'enabled' => 'boolean',
            'sampling_rate' => 'decimal:4',
            'retention_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AuditPolicy $policy): void {
            $policy->tenant_scope_id = $policy->tenant_id !== null ? (int) $policy->tenant_id : self::PLATFORM_SCOPE_ID;
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
