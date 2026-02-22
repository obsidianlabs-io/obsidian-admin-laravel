<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Services\CrudSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CrudSchemaController extends ApiController
{
    public function __construct(private readonly CrudSchemaService $crudSchemaService) {}

    public function show(Request $request, string $resource): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');
        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }
        $user = $authResult['user'] ?? null;
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        $schema = $this->crudSchemaService->find($resource);
        if ($schema === null) {
            return $this->error(self::PARAM_ERROR_CODE, 'Schema not found');
        }

        $permissionCode = $this->crudSchemaService->requiredPermissionCode($resource);
        if ($permissionCode !== null && ! $user->hasPermission($permissionCode)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        return $this->success($schema);
    }
}
