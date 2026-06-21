<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers\Concerns;

use App\Support\ApiResultCode;
use Illuminate\Http\JsonResponse;

trait BuildsDeletionResponses
{
    /**
     * @param  array<string, int>  $dependencies
     */
    protected function deleteConflict(
        string $resource,
        int $resourceId,
        array $dependencies,
        string $suggestedAction = 'resolve_dependencies_and_retry',
        string $message = 'Delete conflict'
    ): JsonResponse {
        $normalizedDependencies = [];
        foreach ($dependencies as $dependency => $count) {
            $resolvedCount = (int) $count;
            if ($resolvedCount > 0) {
                $normalizedDependencies[$dependency] = $resolvedCount;
            }
        }

        return $this->error(ApiResultCode::CONFLICT, $message, [
            'resource' => $resource,
            'resourceId' => (string) $resourceId,
            'reason' => 'dependency_exists',
            'dependencies' => $normalizedDependencies,
            'suggestedAction' => $suggestedAction,
        ]);
    }

    protected function deletionActionSuccess(
        string $resource,
        int $resourceId,
        string $action,
        string $message = 'ok'
    ): JsonResponse {
        $data = [
            'action' => $action,
            'resource' => $resource,
            'resourceId' => (string) $resourceId,
        ];

        if ($action === 'soft_deleted') {
            $retentionDays = max(1, (int) config('security.deletion.retention_days', 30));
            $data['recoverableUntil'] = now()
                ->addDays($retentionDays)
                ->setTimezone('UTC')
                ->toIso8601String();
        }

        return $this->success($data, $message);
    }
}
