<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_read_social_links_and_admin_can_update_them(): void
    {
        $this->getJson('/api/settings/social-links')
            ->assertOk()
            ->assertJsonPath('data', []);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        $this->putJson('/api/admin/settings/social-links', [
            'facebook' => 'https://facebook.com/soar',
            'instagram' => 'https://instagram.com/soar',
            'youtube' => null,
        ])->assertOk()
            ->assertJsonPath('data.facebook', 'https://facebook.com/soar');

        $this->getJson('/api/settings/social-links')
            ->assertOk()
            ->assertJsonPath('data.instagram', 'https://instagram.com/soar')
            ->assertJsonPath('data.youtube', null);

        $this->assertSame(
            'https://facebook.com/soar',
            json_decode(Setting::get('social_links'), true)['facebook']
        );
    }

    public function test_social_links_require_http_or_https_urls(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        $this->putJson('/api/admin/settings/social-links', [
            'facebook' => 'javascript:alert(1)',
        ])->assertUnprocessable()->assertJsonValidationErrors('facebook');
    }
}