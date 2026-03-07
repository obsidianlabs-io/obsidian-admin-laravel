<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Results;

final readonly class ResolvedUserProfile
{
    public function __construct(
        public string $userId,
        public string $userName,
        public string $locale,
        public string $preferredLocale,
        public string $timezone,
        public ?string $themeSchema,
        public string $email,
        public string $roleCode,
        public string $roleName,
        public string $tenantId,
        public string $tenantName,
        public bool $twoFactorEnabled,
        public string $status,
        public string $version,
        public string $createTime,
        public string $updateTime,
    ) {}

    /**
     * @return array{
     *   userId: string,
     *   userName: string,
     *   locale: string,
     *   preferredLocale: string,
     *   timezone: string,
     *   themeSchema: string|null,
     *   email: string,
     *   roleCode: string,
     *   roleName: string,
     *   tenantId: string,
     *   tenantName: string,
     *   twoFactorEnabled: bool,
     *   status: string,
     *   version: string,
     *   createTime: string,
     *   updateTime: string
     * }
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'userName' => $this->userName,
            'locale' => $this->locale,
            'preferredLocale' => $this->preferredLocale,
            'timezone' => $this->timezone,
            'themeSchema' => $this->themeSchema,
            'email' => $this->email,
            'roleCode' => $this->roleCode,
            'roleName' => $this->roleName,
            'tenantId' => $this->tenantId,
            'tenantName' => $this->tenantName,
            'twoFactorEnabled' => $this->twoFactorEnabled,
            'status' => $this->status,
            'version' => $this->version,
            'createTime' => $this->createTime,
            'updateTime' => $this->updateTime,
        ];
    }
}
