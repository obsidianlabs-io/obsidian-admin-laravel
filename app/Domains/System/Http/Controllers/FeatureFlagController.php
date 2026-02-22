<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Actions\FeatureFlag\ListFeatureFlagsAction;
use App\Domains\System\Actions\FeatureFlag\PurgeFeatureFlagAction;
use App\Domains\System\Actions\FeatureFlag\ToggleFeatureFlagAction;
use App\Domains\System\Events\SystemRealtimeUpdated;
use App\Http\Requests\Api\FeatureFlag\ListFeatureFlagsRequest;
use App\Http\Requests\Api\FeatureFlag\PurgeFeatureFlagRequest;
use App\Http\Requests\Api\FeatureFlag\ToggleFeatureFlagRequest;
use Illuminate\Http\JsonResponse;

class FeatureFlagController extends ApiController
{
    public function __construct(
        private readonly ListFeatureFlagsAction $listFeatureFlagsAction,
        private readonly ToggleFeatureFlagAction $toggleFeatureFlagAction,
        private readonly PurgeFeatureFlagAction $purgeFeatureFlagAction,
    ) {}

    /**
     * List all feature flag definitions with their current overrides, paginated.
     */
    public function index(ListFeatureFlagsRequest $request): JsonResponse
    {
        return $this->success(($this->listFeatureFlagsAction)($request->toDTO()));
    }

    /**
     * Toggle a feature flag's global override.
     */
    public function toggle(ToggleFeatureFlagRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        if (! ($this->toggleFeatureFlagAction)($dto)) {
            return $this->error('4004', 'Feature flag not found.', [], 404);
        }

        $authUser = $request->attributes->get('auth_user');
        $actorUserId = $authUser instanceof User ? (int) $authUser->id : null;

        event(new SystemRealtimeUpdated(
            topic: 'feature-flag',
            action: 'feature-flag.toggle',
            context: [
                'key' => $dto->key,
                'enabled' => $dto->enabled,
            ],
            actorUserId: $actorUserId,
        ));

        return $this->success([
            'key' => $dto->key,
            'global_override' => $dto->enabled,
        ], 'Feature flag override updated.');
    }

    /**
     * Purge all overrides for a feature flag (revert to config defaults).
     */
    public function purge(PurgeFeatureFlagRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        if (! ($this->purgeFeatureFlagAction)($dto)) {
            return $this->error('4004', 'Feature flag not found.', [], 404);
        }

        $authUser = $request->attributes->get('auth_user');
        $actorUserId = $authUser instanceof User ? (int) $authUser->id : null;

        event(new SystemRealtimeUpdated(
            topic: 'feature-flag',
            action: 'feature-flag.purge',
            context: [
                'key' => $dto->key,
            ],
            actorUserId: $actorUserId,
        ));

        return $this->success([
            'key' => $dto->key,
            'global_override' => null,
        ], 'Feature flag overrides purged.');
    }
}
