<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\Results;

final readonly class ResolvedUserNavigation
{
    /**
     * @param  list<ResolvedMenuItem>  $menus
     * @param  array<string, ResolvedRouteRule>  $routeRules
     */
    public function __construct(
        public string $menuScope,
        public array $menus,
        public array $routeRules,
    ) {}

    /**
     * @return array{
     *   menuScope: string,
     *   menus: list<array<string, mixed>>,
     *   routeRules: array<string, array{
     *     enabled: bool,
     *     permissions: list<string>,
     *     roles: list<string>,
     *     noTenantOnly: bool,
     *     tenantOnly: bool
     *   }>
     * }
     */
    public function toArray(): array
    {
        $routeRules = [];
        foreach ($this->routeRules as $key => $rule) {
            $routeRules[$key] = $rule->toArray();
        }

        return [
            'menuScope' => $this->menuScope,
            'menus' => array_map(
                static fn (ResolvedMenuItem $item): array => $item->toArray(),
                $this->menus,
            ),
            'routeRules' => $routeRules,
        ];
    }
}
