<?php

declare(strict_types=1);

namespace App\Domains\Access\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domains\Tenant\Models\Organization;
use App\Domains\Tenant\Models\Team;
use App\Domains\Tenant\Models\Tenant;
use App\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Cached permission codes for current model instance.
     *
     * @var list<string>|null
     */
    private ?array $resolvedPermissionCodes = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'role_id',
        'tenant_id',
        'organization_id',
        'team_id',
        'tenant_scope_id',
        'two_factor_enabled',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'string',
            'tenant_id' => 'integer',
            'organization_id' => 'integer',
            'team_id' => 'integer',
            'tenant_scope_id' => 'integer',
            'two_factor_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    #[Boot]
    protected static function syncTenantScopeId(): void
    {
        static::saving(function (User $user): void {
            $user->setAttribute('tenant_scope_id', $user->tenant_id !== null ? (int) $user->tenant_id : 0);
        });
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasOne<UserPreference, $this>
     */
    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * @return list<string>
     */
    public function permissionCodes(): array
    {
        if ($this->resolvedPermissionCodes !== null) {
            return $this->resolvedPermissionCodes;
        }

        if (! $this->relationLoaded('role')) {
            $this->load('role.permissions');
        } elseif ($this->role && ! $this->role->relationLoaded('permissions')) {
            $this->role->load('permissions');
        }

        if (! $this->role || $this->role->status !== '1') {
            $this->resolvedPermissionCodes = [];

            return $this->resolvedPermissionCodes;
        }

        $codes = $this->role->permissions
            ->where('status', '1')
            ->pluck('code')
            ->all();

        $this->resolvedPermissionCodes = array_values(
            array_filter(
                array_map(static fn (mixed $code): string => (string) $code, $codes),
                static fn (string $code): bool => $code !== ''
            )
        );

        return $this->resolvedPermissionCodes;
    }

    public function hasPermission(string $permissionCode): bool
    {
        return in_array($permissionCode, $this->permissionCodes(), true);
    }
}
