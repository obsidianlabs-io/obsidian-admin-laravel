<?php

declare(strict_types=1);

namespace App\Domains\Shared\Services\Results;

use App\Domains\System\Models\IdempotencyKey;
use Illuminate\Http\JsonResponse;
use LogicException;

final readonly class IdempotencyBeginResult
{
    private function __construct(
        private bool $enabled,
        private ?string $error = null,
        private ?JsonResponse $replayResponse = null,
        private ?IdempotencyKey $record = null,
    ) {}

    public static function disabled(): self
    {
        return new self(enabled: false);
    }

    public static function error(string $error): self
    {
        return new self(enabled: true, error: $error);
    }

    public static function replay(JsonResponse $response): self
    {
        return new self(enabled: true, replayResponse: $response);
    }

    public static function acquired(IdempotencyKey $record): self
    {
        return new self(enabled: true, record: $record);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function errorMessage(): ?string
    {
        return $this->error;
    }

    public function hasReplayResponse(): bool
    {
        return $this->replayResponse instanceof JsonResponse;
    }

    public function requireReplayResponse(): JsonResponse
    {
        if (! $this->replayResponse instanceof JsonResponse) {
            throw new LogicException('Idempotency replay response is missing.');
        }

        return $this->replayResponse;
    }

    public function record(): ?IdempotencyKey
    {
        return $this->record;
    }
}
