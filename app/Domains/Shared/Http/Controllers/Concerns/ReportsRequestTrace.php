<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers\Concerns;

use App\Support\RequestTraceContext;

trait ReportsRequestTrace
{
    protected function requestId(): string
    {
        return RequestTraceContext::requestId();
    }

    protected function traceId(): string
    {
        return RequestTraceContext::traceId();
    }
}
