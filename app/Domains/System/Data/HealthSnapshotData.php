<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class HealthSnapshotData
{
    /**
     * @param  list<HealthCheckData>  $checks
     */
    public function __construct(
        public string $status,
        public array $checks,
        public HealthContextData $context,
    ) {}

    public function isReady(): bool
    {
        return $this->status !== 'fail';
    }

    /**
     * @return list<array{name: string, status: string, detail: string}>
     */
    public function checksToArray(): array
    {
        return array_map(
            static fn (HealthCheckData $check): array => $check->toArray(),
            $this->checks
        );
    }

    /**
     * @return array{
     *   status: string,
     *   checks: list<array{name: string, status: string, detail: string}>,
     *   context: array{
     *     environment: string,
     *     timezone: string,
     *     database: string,
     *     cache_store: string,
     *     queue_connection: string,
     *     log_channel: string
     *   }
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'checks' => $this->checksToArray(),
            'context' => $this->context->toArray(),
        ];
    }
}
