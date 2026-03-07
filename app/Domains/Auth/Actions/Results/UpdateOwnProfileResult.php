<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Results;

final readonly class UpdateOwnProfileResult
{
    /**
     * @param  array{userName: string, email: string, timezone: string, themeSchema: string|null}  $oldValues
     * @param  array{userName: string, email: string, timezone: string, themeSchema: string|null}  $newValues
     */
    private function __construct(
        private bool $ok,
        private array $oldValues,
        private array $newValues
    ) {}

    /**
     * @param  array{userName: string, email: string, timezone: string, themeSchema: string|null}  $oldValues
     * @param  array{userName: string, email: string, timezone: string, themeSchema: string|null}  $newValues
     */
    public static function success(array $oldValues, array $newValues): self
    {
        return new self(
            ok: true,
            oldValues: $oldValues,
            newValues: $newValues
        );
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }

    /**
     * @return array{userName: string, email: string, timezone: string, themeSchema: string|null}
     */
    public function oldValues(): array
    {
        return $this->oldValues;
    }

    /**
     * @return array{userName: string, email: string, timezone: string, themeSchema: string|null}
     */
    public function newValues(): array
    {
        return $this->newValues;
    }

    public function timezone(): string
    {
        return $this->newValues['timezone'];
    }
}
