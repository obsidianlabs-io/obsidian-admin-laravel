<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Services\CrudSchemaService;
use App\Support\ApiResultCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('tenant.context')]
#[Middleware('api.auth')]
final class CrudSchemaController extends ApiController
{
    public function __construct(private readonly CrudSchemaService $crudSchemaService) {}

    public function show(Request $request, string $resource): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }
        $user = $authResult->user();
        if (! $user instanceof User) {
            return $this->error(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }

        $schema = $this->crudSchemaService->find($resource);
        if ($schema === null) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Schema not found');
        }

        $permissionCode = $this->crudSchemaService->requiredPermissionCode($resource);
        if ($permissionCode !== null && ! $user->hasPermission($permissionCode)) {
            return $this->error(ApiResultCode::FORBIDDEN, 'Forbidden');
        }

        return $this->success($schema->toArray());
    }
}
