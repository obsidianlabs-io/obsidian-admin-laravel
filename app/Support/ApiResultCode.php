<?php

declare(strict_types=1);

namespace App\Support;

enum ApiResultCode: string
{
    case SUCCESS = '0000';
    case LOGIN_FAILED = '1001';
    case PARAM_ERROR = '1002';
    case FORBIDDEN = '1003';
    case CONFLICT = '1009';
    case TWO_FACTOR_REQUIRED = '4020';
    case NOT_FOUND = '4040';
    case METHOD_NOT_ALLOWED = '4050';
    case TOO_MANY_REQUESTS = '4290';
    case SERVER_ERROR = '5000';
    case UNAUTHORIZED = '8888';
    case TOKEN_EXPIRED = '9999';

    /**
     * Map business error codes to semantic HTTP status codes.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::UNAUTHORIZED, self::TOKEN_EXPIRED => 401,
            self::FORBIDDEN => 403,
            self::PARAM_ERROR, self::LOGIN_FAILED => 422,
            self::TWO_FACTOR_REQUIRED => 200,
            self::CONFLICT => 409,
            self::NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::TOO_MANY_REQUESTS => 429,
            self::SERVER_ERROR => 500,
            self::SUCCESS => 200,
        };
    }

    public static function resolveHttpStatus(string $code): int
    {
        return self::tryFrom($code)?->httpStatus() ?? 200;
    }
}
