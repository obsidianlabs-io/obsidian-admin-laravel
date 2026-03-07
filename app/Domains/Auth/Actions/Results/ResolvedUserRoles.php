<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Results;

final readonly class ResolvedUserRoles
{
    /**
     * @param  list<string>  $codes
     */
    public function __construct(
        private array $codes,
    ) {}

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->codes;
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return $this->codes;
    }
}
