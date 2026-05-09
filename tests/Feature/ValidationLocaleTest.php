<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ApiResultCode;
use Tests\TestCase;

class ValidationLocaleTest extends TestCase
{
    public function test_validation_error_uses_chinese_when_accept_language_is_zh_cn(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'userName' => 'DemoUser',
        ], [
            'Accept-Language' => 'zh-CN',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', ApiResultCode::LOGIN_FAILED->value);

        $this->assertStringContainsString('密码', (string) $response->json('msg'));
        $this->assertStringContainsString('密码', (string) $response->json('data.errors.password.0'));
    }

    public function test_validation_error_prefers_x_locale_header_when_present(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'userName' => 'DemoUser',
        ], [
            'Accept-Language' => 'zh-CN',
            'X-Locale' => 'en-US',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', ApiResultCode::LOGIN_FAILED->value);

        $this->assertStringContainsString('password', strtolower((string) $response->json('msg')));
        $this->assertStringContainsString('password', strtolower((string) $response->json('data.errors.password.0')));
    }
}
