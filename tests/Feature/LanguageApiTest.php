<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\User;
use App\Domains\Access\Models\UserPreference;
use App\Domains\System\Models\Language;
use App\Domains\System\Models\LanguageTranslation;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_bootstrap_endpoint_returns_configured_default_locale(): void
    {
        $this->seed();

        config()->set('i18n.default_locale', 'zh-CN');

        $response = $this->getJson('/api/system/bootstrap');

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.defaultLocale', 'zh-CN');
    }

    public function test_public_messages_endpoint_returns_versioned_payload(): void
    {
        $this->seed();

        $zhLocale = Language::query()->where('code', 'zh-CN')->firstOrFail();
        LanguageTranslation::query()->create([
            'language_id' => $zhLocale->id,
            'translation_key' => 'route.language',
            'translation_value' => '语言管理',
            'description' => 'Route title',
            'status' => '1',
        ]);

        $response = $this->getJson('/api/language/messages?locale=zh-CN');

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.locale', 'zh-CN')
            ->assertJsonPath('data.notModified', false);

        $messages = $response->json('data.messages');
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('route.language', $messages);
        $this->assertSame('语言管理', $messages['route.language']);

        $version = (string) $response->json('data.version');
        $this->assertNotSame('', $version);

        $notModifiedResponse = $this->getJson('/api/language/messages?locale=zh-CN&version='.$version);

        $notModifiedResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.notModified', true)
            ->assertJsonCount(0, 'data.messages');
    }

    public function test_super_admin_can_crud_language_translations(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');

        $createResponse = $this->postJson('/api/language', [
            'locale' => 'en-US',
            'translationKey' => 'route.language',
            'translationValue' => 'Language Console',
            'description' => 'Route title',
            'status' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $translationId = (int) $createResponse->json('data.id');

        $listResponse = $this->getJson('/api/language/list?current=1&size=10&locale=en-US&keyword=route.language', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $listResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.records.0.translationKey', 'route.language')
            ->assertJsonPath('data.records.0.translationValue', 'Language Console');

        $updateResponse = $this->putJson('/api/language/'.$translationId, [
            'locale' => 'en-US',
            'translationKey' => 'route.language',
            'translationValue' => 'Language Center',
            'description' => 'Updated title',
            'status' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $this->assertDatabaseHas('language_translations', [
            'id' => $translationId,
            'translation_key' => 'route.language',
            'translation_value' => 'Language Center',
        ]);

        $deleteResponse = $this->deleteJson('/api/language/'.$translationId, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $deleteResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $this->assertDatabaseMissing('language_translations', [
            'id' => $translationId,
        ]);
    }

    public function test_admin_cannot_access_language_management_endpoints(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');

        $response = $this->getJson('/api/language/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_super_admin_with_selected_tenant_cannot_manage_languages(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');

        $response = $this->getJson('/api/language/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Switch to No Tenant to manage languages');
    }

    public function test_language_list_timestamps_follow_user_timezone(): void
    {
        $this->seed();

        $super = User::query()->where('name', 'Super')->firstOrFail();
        UserPreference::query()->updateOrCreate(
            ['user_id' => $super->id],
            ['timezone' => 'Asia/Kuala_Lumpur']
        );

        $en = Language::query()->where('code', 'en-US')->firstOrFail();
        $translation = LanguageTranslation::query()->create([
            'language_id' => $en->id,
            'translation_key' => 'timezone.test',
            'translation_value' => 'Timezone Test',
            'description' => 'Timezone test',
            'status' => '1',
        ]);

        $translation->forceFill([
            'created_at' => '2026-02-17 00:00:00',
            'updated_at' => '2026-02-17 00:00:00',
        ])->save();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');

        $response = $this->getJson('/api/language/list?current=1&size=10&keyword=timezone.test', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.records.0.updateTime', '2026-02-17 08:00:00')
            ->assertJsonPath('data.records.0.createTime', '2026-02-17 08:00:00');
    }
}
