<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ApiContractSnapshotCommandTest extends TestCase
{
    public function test_api_contract_snapshot_is_up_to_date(): void
    {
        $this->artisan('api:contract-snapshot')
            ->assertExitCode(0);
    }
}
