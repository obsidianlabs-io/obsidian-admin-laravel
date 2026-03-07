<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class HealthContextData
{
    public function __construct(
        public string $environment,
        public string $timezone,
        public string $database,
        public string $cacheStore,
        public string $queueConnection,
        public string $logChannel,
    ) {}

    /**
     * @return array{
     *   environment: string,
     *   timezone: string,
     *   database: string,
     *   cache_store: string,
     *   queue_connection: string,
     *   log_channel: string
     * }
     */
    public function toArray(): array
    {
        return [
            'environment' => $this->environment,
            'timezone' => $this->timezone,
            'database' => $this->database,
            'cache_store' => $this->cacheStore,
            'queue_connection' => $this->queueConnection,
            'log_channel' => $this->logChannel,
        ];
    }
}
