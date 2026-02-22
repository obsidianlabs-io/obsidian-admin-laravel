<?php

declare(strict_types=1);

namespace App\Domains\System\Models;

use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeProfile extends Model
{
    use HasFactory;

    public const SCOPE_PLATFORM = 'platform';

    public const SCOPE_TENANT = 'tenant';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scope_type',
        'scope_id',
        'scope_key',
        'name',
        'status',
        'config',
        'version',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => 'string',
            'scope_id' => 'integer',
            'scope_key' => 'string',
            'name' => 'string',
            'status' => 'string',
            'config' => 'array',
            'version' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'scope_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function scopeKey(string $scopeType, ?int $scopeId): string
    {
        $normalizedScopeId = $scopeId ?? 0;

        return sprintf('%s:%d', $scopeType, $normalizedScopeId);
    }
}
