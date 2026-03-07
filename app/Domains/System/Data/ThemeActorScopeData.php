<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class ThemeActorScopeData
{
    public function __construct(
        public string $scopeType,
        public ?int $scopeId,
        public string $scopeName,
    ) {}
}
