<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditPolicyHistoryRecordData
{
    /**
     * @param  list<string>  $changedActions
     */
    public function __construct(
        public string $id,
        public string $scope,
        public string $changedByUserId,
        public string $changedByUserName,
        public string $changeReason,
        public int $changedCount,
        public array $changedActions,
        public string $createdAt,
    ) {}

    /**
     * @return array{
     *   id: string,
     *   scope: string,
     *   changedByUserId: string,
     *   changedByUserName: string,
     *   changeReason: string,
     *   changedCount: int,
     *   changedActions: list<string>,
     *   createdAt: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'changedByUserId' => $this->changedByUserId,
            'changedByUserName' => $this->changedByUserName,
            'changeReason' => $this->changeReason,
            'changedCount' => $this->changedCount,
            'changedActions' => $this->changedActions,
            'createdAt' => $this->createdAt,
        ];
    }
}
