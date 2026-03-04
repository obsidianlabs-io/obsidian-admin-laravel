<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

use App\Domains\Access\Models\User;
use ArrayAccess;
use Laravel\Sanctum\PersonalAccessToken;
use LogicException;

/**
 * @implements ArrayAccess<mixed, mixed>
 */
final readonly class ApiAuthResult implements ArrayAccess
{
    public function __construct(
        private bool $ok,
        private string $code,
        private string $message,
        private ?User $user = null,
        private ?PersonalAccessToken $token = null
    ) {}

    public static function success(
        User $user,
        ?PersonalAccessToken $token = null,
        string $code = '0000',
        string $message = 'ok'
    ): self {
        return new self(
            ok: true,
            code: $code,
            message: $message,
            user: $user,
            token: $token
        );
    }

    public static function failure(string $code, string $message): self
    {
        return new self(
            ok: false,
            code: $code,
            message: $message
        );
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function requireUser(): User
    {
        if (! $this->user instanceof User) {
            throw new LogicException('Authenticated user is missing.');
        }

        return $this->user;
    }

    public function token(): ?PersonalAccessToken
    {
        return $this->token;
    }

    public function requireToken(): PersonalAccessToken
    {
        if (! $this->token instanceof PersonalAccessToken) {
            throw new LogicException('Authenticated access token is missing.');
        }

        return $this->token;
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['ok', 'code', 'msg', 'user', 'token'], true);
    }

    public function offsetGet(mixed $offset): bool|string|User|PersonalAccessToken|null
    {
        if (! is_string($offset)) {
            return null;
        }

        switch ($offset) {
            case 'ok':
                return $this->ok;
            case 'code':
                return $this->code;
            case 'msg':
                return $this->message;
            case 'user':
                return $this->user;
            case 'token':
                return $this->token;
            default:
                return null;
        }
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('ApiAuthResult is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('ApiAuthResult is immutable.');
    }
}
