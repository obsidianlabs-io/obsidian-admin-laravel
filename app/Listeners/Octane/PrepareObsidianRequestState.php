<?php

declare(strict_types=1);

namespace App\Listeners\Octane;

use App\Support\ApiDateTime;
use App\Support\AppLocale;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Log;

class PrepareObsidianRequestState
{
    public function handle(object $event): void
    {
        ApiDateTime::flushState();
        RequestContext::flush();
        Log::withoutContext();
        app()->setLocale(AppLocale::defaultFrameworkLocale());
    }
}
