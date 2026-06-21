<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers;

use App\Domains\Shared\Http\Controllers\Concerns\AuthenticatesApiRequests;
use App\Domains\Shared\Http\Controllers\Concerns\BuildsDeletionResponses;
use App\Domains\Shared\Http\Controllers\Concerns\HandlesIdempotency;
use App\Domains\Shared\Http\Controllers\Concerns\HandlesOptimisticLocks;
use App\Domains\Shared\Http\Controllers\Concerns\PaginatesApiQueries;
use App\Domains\Shared\Http\Controllers\Concerns\ReportsRequestTrace;
use App\Domains\Shared\Http\Controllers\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;

abstract class ApiController extends Controller
{
    use AuthenticatesApiRequests;
    use BuildsDeletionResponses;
    use HandlesIdempotency;
    use HandlesOptimisticLocks;
    use PaginatesApiQueries;
    use ReportsRequestTrace;
    use RespondsWithJson;
}
