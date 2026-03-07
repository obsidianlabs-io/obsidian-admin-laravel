<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Results;

final readonly class UpdateOwnProfileResult
{
    private function __construct(
        private bool $ok,
        private UserProfileSnapshot $oldProfile,
        private UserProfileSnapshot $newProfile,
    ) {}

    public static function success(UserProfileSnapshot $oldProfile, UserProfileSnapshot $newProfile): self
    {
        return new self(
            ok: true,
            oldProfile: $oldProfile,
            newProfile: $newProfile,
        );
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }

    public function oldProfile(): UserProfileSnapshot
    {
        return $this->oldProfile;
    }

    public function newProfile(): UserProfileSnapshot
    {
        return $this->newProfile;
    }

    public function timezone(): string
    {
        return $this->newProfile->timezone;
    }
}
