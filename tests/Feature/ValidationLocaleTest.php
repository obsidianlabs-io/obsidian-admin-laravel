<?php

declare(strict_types=1);

namespace Tests\Feature;

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

        $response->assertOk()
            ->assertJsonPath('code', '1001');

        $this->assertStringContainsString('密码', (string) $response->json('msg'));
    }

    public function test_validation_error_prefers_x_locale_header_when_present(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'userName' => 'DemoUser',
        ], [
            'Accept-Language' => 'zh-CN',
            'X-Locale' => 'en-US',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1001');

        $this->assertStringContainsString('password', strtolower((string) $response->json('msg')));
    }
}
