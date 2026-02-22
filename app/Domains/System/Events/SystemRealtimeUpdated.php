<?php

declare(strict_types=1);

namespace App\Domains\System\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SystemRealtimeUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $topic,
        private readonly string $action,
        private readonly array $context = [],
        private readonly ?int $actorUserId = null,
        private readonly ?int $tenantId = null
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('system.updates')];
    }

    public function broadcastAs(): string
    {
        return 'system.realtime.updated';
    }

    /**
     * @return array{
     *   topic: string,
     *   action: string,
     *   context: array<string, mixed>,
     *   actorUserId: int|null,
     *   tenantId: int|null,
     *   sentAt: string
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'topic' => $this->topic,
            'action' => $this->action,
            'context' => $this->context,
            'actorUserId' => $this->actorUserId,
            'tenantId' => $this->tenantId,
            'sentAt' => now()->toIso8601String(),
        ];
    }
}
