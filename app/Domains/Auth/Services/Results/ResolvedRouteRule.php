<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\Results;

final readonly class ResolvedRouteRule
{
    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     */
    public function __construct(
        public bool $enabled,
        public array $permissions,
        public array $roles,
        public bool $noTenantOnly,
        public bool $tenantOnly,
    ) {}

    /**
     * @return array{
     *   enabled: bool,
     *   permissions: list<string>,
     *   roles: list<string>,
     *   noTenantOnly: bool,
     *   tenantOnly: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'permissions' => $this->permissions,
            'roles' => $this->roles,
            'noTenantOnly' => $this->noTenantOnly,
            'tenantOnly' => $this->tenantOnly,
        ];
    }
}
