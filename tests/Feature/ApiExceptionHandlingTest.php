<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ApiResultCode;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiExceptionHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/testing/boom', static function (): void {
            throw new RuntimeException('boom');
        });
    }

    public function test_unknown_api_route_returns_api_error_wrapper(): void
    {
        $response = $this->getJson('/api/testing/route-does-not-exist');

        $response->assertNotFound()
            ->assertJsonPath('code', ApiResultCode::NOT_FOUND->value)
            ->assertJsonPath('msg', 'Not Found')
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
                'requestId',
                'traceId',
            ]);
    }

    public function test_unhandled_api_exception_returns_wrapped_server_error(): void
    {
        $response = $this->getJson('/api/testing/boom');
        $expectedMessage = (bool) config('app.debug', false) ? 'boom' : 'Server error';

        $response->assertStatus(500)
            ->assertJsonPath('code', ApiResultCode::SERVER_ERROR->value)
            ->assertJsonPath('msg', $expectedMessage)
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
                'requestId',
                'traceId',
            ]);

        $this->assertNotSame('', (string) $response->json('requestId'));
    }
}
