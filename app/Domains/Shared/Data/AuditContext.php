<?php

declare(strict_types=1);

namespace App\Domains\Shared\Data;

use App\Domains\Access\Models\User;

final readonly class AuditContext
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function __construct(
        public User $actor,
        public array $oldValues = [],
        public array $newValues = [],
        public ?string $overrideAction = null,
        public ?int $tenantId = null,
    ) {}
}
