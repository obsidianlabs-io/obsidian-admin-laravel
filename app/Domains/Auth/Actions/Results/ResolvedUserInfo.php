<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Results;

use App\Domains\Auth\Services\Results\ResolvedMenuItem;
use App\Domains\Auth\Services\Results\ResolvedUserNavigation;
use App\Domains\Shared\Auth\TenantOptionData;

final readonly class ResolvedUserInfo
{
    /**
     * @param  list<string>  $buttons
     * @param  list<TenantOptionData>  $tenants
     * @param  array<string, mixed>  $themeConfig
     */
    public function __construct(
        public ResolvedUserProfile $profile,
        public array $themeConfig,
        public int $themeProfileVersion,
        public ResolvedUserRoles $roles,
        public array $buttons,
        public string $currentTenantId,
        public string $currentTenantName,
        public array $tenants,
        public ResolvedUserNavigation $navigation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $routeRules = [];
        foreach ($this->navigation->routeRules as $key => $rule) {
            $routeRules[$key] = $rule->toArray();
        }

        return array_merge($this->profile->toArray(), [
            'themeConfig' => $this->themeConfig,
            'themeProfileVersion' => $this->themeProfileVersion,
            'roles' => $this->roles->toArray(),
            'buttons' => $this->buttons,
            'currentTenantId' => $this->currentTenantId,
            'currentTenantName' => $this->currentTenantName,
            'tenants' => array_map(
                static fn (TenantOptionData $tenant): array => $tenant->toArray(),
                $this->tenants,
            ),
            'menuScope' => $this->navigation->menuScope,
            'menus' => array_map(
                static fn (ResolvedMenuItem $item): array => $item->toArray(),
                $this->navigation->menus,
            ),
            'routeRules' => $routeRules,
        ]);
    }
}
