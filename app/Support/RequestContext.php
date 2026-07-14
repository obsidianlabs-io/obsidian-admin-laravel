<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Context;

final class RequestContext
{
    public const KEY_REQUEST_ID = 'request_id';

    public const KEY_TRACE_ID = 'trace_id';

    public const KEY_TRACE_PARENT = 'traceparent';

    public const KEY_SPAN_ID = 'span_id';

    public const KEY_PARENT_SPAN_ID = 'parent_span_id';

    public const KEY_USER_ID = 'user_id';

    public const KEY_TENANT_ID = 'tenant_id';

    public const KEY_ROLE_SCOPE_TENANT_ID = 'role_scope_tenant_id';

    public const KEY_IS_SUPER_ADMIN = 'is_super_admin';

    public const KEY_IP_ADDRESS = 'ip_address';

    public const KEY_USER_AGENT = 'user_agent';

    /**
     * @param  array<string, mixed>  $items
     */
    public static function add(array $items): void
    {
        foreach ($items as $key => $value) {
            if (trim($key) === '') {
                continue;
            }

            if ($value === null || (is_string($value) && trim($value) === '')) {
                Context::forget($key);

                continue;
            }

            Context::add($key, $value);
        }
    }

    public static function requestId(): string
    {
        $value = Context::get(self::KEY_REQUEST_ID);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function traceId(): string
    {
        $value = Context::get(self::KEY_TRACE_ID);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function tenantId(): ?int
    {
        $value = Context::get(self::KEY_TENANT_ID);

        return is_numeric($value) ? (int) $value : null;
    }

    public static function userId(): ?int
    {
        $value = Context::get(self::KEY_USER_ID);

        return is_numeric($value) ? (int) $value : null;
    }

    public static function ipAddress(): ?string
    {
        $value = Context::get(self::KEY_IP_ADDRESS);

        return is_scalar($value) ? trim((string) $value) : null;
    }

    public static function userAgent(): ?string
    {
        $value = Context::get(self::KEY_USER_AGENT);

        return is_scalar($value) ? trim((string) $value) : null;
    }

    public static function flush(): void
    {
        Context::flush();
    }
}
