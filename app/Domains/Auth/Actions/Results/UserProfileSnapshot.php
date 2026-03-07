<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Results;

final readonly class UserProfileSnapshot
{
    public function __construct(
        public string $userName,
        public string $email,
        public string $timezone,
        public ?string $themeSchema,
    ) {}

    /**
     * @return array{
     *   userName: string,
     *   email: string,
     *   timezone: string,
     *   themeSchema: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'userName' => $this->userName,
            'email' => $this->email,
            'timezone' => $this->timezone,
            'themeSchema' => $this->themeSchema,
        ];
    }
}
