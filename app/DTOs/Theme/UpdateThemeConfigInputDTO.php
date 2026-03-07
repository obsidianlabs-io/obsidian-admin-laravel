<?php

declare(strict_types=1);

namespace App\DTOs\Theme;

final readonly class UpdateThemeConfigInputDTO
{
    public function __construct(
        public ?string $themeScheme = null,
        public ?string $themeColor = null,
        public ?int $themeRadius = null,
        public ?int $headerHeight = null,
        public ?int $siderWidth = null,
        public ?int $siderCollapsedWidth = null,
        public ?string $layoutMode = null,
        public ?string $scrollMode = null,
        public ?bool $darkSider = null,
        public ?bool $themeSchemaVisible = null,
        public ?bool $headerFullscreenVisible = null,
        public ?bool $tabVisible = null,
        public ?bool $tabFullscreenVisible = null,
        public ?bool $breadcrumbVisible = null,
        public ?bool $footerVisible = null,
        public ?int $footerHeight = null,
        public ?bool $multilingualVisible = null,
        public ?bool $globalSearchVisible = null,
        public ?bool $themeConfigVisible = null,
        public ?bool $pageAnimate = null,
        public ?string $pageAnimateMode = null,
        public ?bool $fixedHeaderAndTab = null,
    ) {}

    public function hasChanges(): bool
    {
        return $this->toArray() !== [];
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        $payload = [];

        foreach (get_object_vars($this) as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
