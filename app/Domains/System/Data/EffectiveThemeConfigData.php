<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class EffectiveThemeConfigData
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public array $config,
        public int $profileVersion,
    ) {}
}
