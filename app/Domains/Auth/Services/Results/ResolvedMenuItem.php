<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\Results;

final readonly class ResolvedMenuItem
{
    /**
     * @param  list<ResolvedMenuItem>  $children
     */
    public function __construct(
        public string $key,
        public string $routeKey,
        public string $routePath,
        public string $label,
        public ?string $i18nKey,
        public ?string $icon,
        public int $order,
        public string $scope,
        public ?string $featureFlag,
        public array $children,
    ) {}

    /**
     * @return array{
     *   key: string,
     *   routeKey: string,
     *   routePath: string,
     *   label: string,
     *   i18nKey: string|null,
     *   icon: string|null,
     *   order: int,
     *   scope: string,
     *   featureFlag: string|null,
     *   children: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'routeKey' => $this->routeKey,
            'routePath' => $this->routePath,
            'label' => $this->label,
            'i18nKey' => $this->i18nKey,
            'icon' => $this->icon,
            'order' => $this->order,
            'scope' => $this->scope,
            'featureFlag' => $this->featureFlag,
            'children' => array_map(
                static fn (ResolvedMenuItem $child): array => $child->toArray(),
                $this->children,
            ),
        ];
    }
}
