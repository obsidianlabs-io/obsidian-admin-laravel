<?php

declare(strict_types=1);

namespace App\Listeners\Octane;

use App\Domains\System\Services\FeatureFlagService;
use Laravel\Octane\Events\RequestTerminated;

class FlushFeatureFlagOverrideCache
{
    public function __construct(private readonly FeatureFlagService $featureFlagService) {}

    public function handle(RequestTerminated $event): void
    {
        $this->featureFlagService->clearOverrideCache();
    }
}
