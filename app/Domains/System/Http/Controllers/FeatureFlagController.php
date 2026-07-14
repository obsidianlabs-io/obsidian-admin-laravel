<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Actions\FeatureFlagAction;
use App\Domains\System\Data\FeatureFlagOverrideResponseData;
use App\Domains\System\Events\SystemRealtimeUpdated;
use App\Http\Requests\Api\FeatureFlag\ListFeatureFlagsRequest;
use App\Http\Requests\Api\FeatureFlag\PurgeFeatureFlagRequest;
use App\Http\Requests\Api\FeatureFlag\ToggleFeatureFlagRequest;
use App\Support\ApiResultCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

#[Middleware('tenant.context')]
#[Middleware('api.auth')]
class FeatureFlagController extends ApiController
{
    public function __construct(
        private readonly FeatureFlagAction $featureFlagAction,
    ) {}

    /**
     * List all feature flag definitions with their current overrides, paginated.
     */
    public function index(ListFeatureFlagsRequest $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (! $authUser instanceof User || ! $this->isSuperAdmin($authUser)) {
            return $this->error(ApiResultCode::FORBIDDEN, 'Forbidden');
        }

        $result = $this->featureFlagAction->list(
            $request->current(),
            $request->size(),
            $request->keyword(),
        );

        return $this->success($result->toArray());
    }

    /**
     * Toggle a feature flag's global override.
     */
    public function toggle(ToggleFeatureFlagRequest $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (! $authUser instanceof User || ! $this->isSuperAdmin($authUser)) {
            return $this->error(ApiResultCode::FORBIDDEN, 'Forbidden');
        }

        $key = $request->key();
        $enabled = $request->enabled();
        $actorUserId = (int) $authUser->id;

        $success = DB::transaction(fn (): bool => $this->featureFlagAction->toggle($key, $enabled));

        if (! $success) {
            return $this->error('4004', 'Feature flag not found.', [], 404);
        }

        DB::afterCommit(static function () use ($authUser, $key, $enabled, $actorUserId) {
            event(DomainAuditEvent::make(
                action: 'feature-flag.toggle',
                auditable: 'feature-flag',
                actor: $authUser,
                newValues: [
                    'key' => $key,
                    'enabled' => $enabled,
                ]
            ));

            event(new SystemRealtimeUpdated(
                topic: 'feature-flag',
                action: 'feature-flag.toggle',
                context: [
                    'key' => $key,
                    'enabled' => $enabled,
                ],
                actorUserId: $actorUserId,
            ));
        });

        return $this->success(
            FeatureFlagOverrideResponseData::forToggle($key, $enabled)->toArray(),
            'Feature flag override updated.'
        );
    }

    /**
     * Purge all overrides for a feature flag (revert to config defaults).
     */
    public function purge(PurgeFeatureFlagRequest $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (! $authUser instanceof User || ! $this->isSuperAdmin($authUser)) {
            return $this->error(ApiResultCode::FORBIDDEN, 'Forbidden');
        }

        $key = $request->key();
        $actorUserId = (int) $authUser->id;

        $success = DB::transaction(fn (): bool => $this->featureFlagAction->purge($key));

        if (! $success) {
            return $this->error('4004', 'Feature flag not found.', [], 404);
        }

        DB::afterCommit(static function () use ($authUser, $key, $actorUserId) {
            event(DomainAuditEvent::make(
                action: 'feature-flag.purge',
                auditable: 'feature-flag',
                actor: $authUser,
                newValues: [
                    'key' => $key,
                ]
            ));

            event(new SystemRealtimeUpdated(
                topic: 'feature-flag',
                action: 'feature-flag.purge',
                context: [
                    'key' => $key,
                ],
                actorUserId: $actorUserId,
            ));
        });

        return $this->success(
            FeatureFlagOverrideResponseData::forPurge($key)->toArray(),
            'Feature flag override purged.'
        );
    }
}
