<?php

declare(strict_types=1);

namespace App\Domains\Access\Models;

use App\Domains\Tenant\Models\Tenant;
use App\Policies\RolePolicy;
use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(RolePolicy::class)]
class Role extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'level',
        'tenant_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
            'level' => 'integer',
            'tenant_id' => 'integer',
            'tenant_scope_id' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    #[Boot]
    protected static function syncTenantScopeId(): void
    {
        static::saving(function (Role $role): void {
            $role->setAttribute('tenant_scope_id', $role->tenant_id !== null ? (int) $role->tenant_id : 0);
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', '1');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inTenantScope(Builder $query, ?int $tenantId): void
    {
        if ($tenantId === null) {
            $query->whereNull('tenant_id');

            return;
        }

        $query->where('tenant_id', $tenantId);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upToLevel(Builder $query, int $level): void
    {
        $query->where('level', '<=', $level);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function belowLevel(Builder $query, int $level): void
    {
        $query->where('level', '<', $level);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->withTimestamps();
    }
}
