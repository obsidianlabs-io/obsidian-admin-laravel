<?php

declare(strict_types=1);

namespace App\Domains\System\Contracts;

interface AsyncAuditEvent
{
    public function action(): string;

    public function tenantId(): ?int;

    /**
     * @return array{
     *   user_id: int|null,
     *   auditable_type: string,
     *   auditable_id: int|null,
     *   old_values: array<string, mixed>|null,
     *   new_values: array<string, mixed>|null,
     *   ip_address: string|null,
     *   user_agent: string|null,
     *   request_id: string|null
     * }
     */
    public function payload(): array;
}
