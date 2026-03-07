<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class HealthCheckData
{
    public function __construct(
        public string $name,
        public string $status,
        public string $detail,
    ) {}

    public function isFail(): bool
    {
        return $this->status === 'fail';
    }

    public function isWarn(): bool
    {
        return $this->status === 'warn';
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'detail' => $this->detail,
        ];
    }
}
