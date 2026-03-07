<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\Results;

final readonly class UpdateSessionAliasResult
{
    public function __construct(
        private int $updatedTokenCount,
        private bool $updatedCurrentSession,
        private ?string $deviceAlias
    ) {}

    public function updatedTokenCount(): int
    {
        return $this->updatedTokenCount;
    }

    public function updatedCurrentSession(): bool
    {
        return $this->updatedCurrentSession;
    }

    public function deviceAlias(): ?string
    {
        return $this->deviceAlias;
    }

    /**
     * @return array{updatedTokenCount: int, updatedCurrentSession: bool, deviceAlias: ?string}
     */
    public function toArray(): array
    {
        return [
            'updatedTokenCount' => $this->updatedTokenCount,
            'updatedCurrentSession' => $this->updatedCurrentSession,
            'deviceAlias' => $this->deviceAlias,
        ];
    }
}
