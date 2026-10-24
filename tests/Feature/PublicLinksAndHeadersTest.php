<?php

namespace Tests\Feature;

use App\Models\FamilyEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class PublicLinksAndHeadersTest extends TestCase
{
    use CreatesFamily, RefreshDatabase;

    public function test_public_links_show_only_intended_event_fields(): void
    {
        $family = $this->familyFor();
        $token = $family->generatePublicToken();
        $dashboardToken = $family->generateDashboardPublicToken();
        FamilyEvent::factory()->create(['family_id' => $family->id, 'title' => 'Visible event', 'starts_at' => now()->addHour(), 'notes' => 'private-note-marker', 'description' => 'private-description-marker']);
        FamilyEvent::factory()->create(['family_id' => $family->id, 'title' => 'parents-secret-marker', 'visibility' => 'parents_only', 'starts_at' => now()]);
        FamilyEvent::factory()->create(['title' => 'foreign-family-marker', 'starts_at' => now()]);
        foreach (["/public/family/$token", "/public/dashboard/$dashboardToken"] as $url) {
            $this->get($url)->assertOk()->assertSee('Visible event')->assertDontSee('parents-secret-marker')->assertDontSee('private-note-marker')->assertDontSee('private-description-marker')->assertDontSee('foreign-family-marker')
                ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }

    public function test_rotated_and_disabled_links_stop_working(): void
    {
        $family = $this->familyFor();
        $old = $family->generatePublicToken();
        $this->actingAs($family->owner)->post("/families/$family->id/public-link/regenerate", ['current_password' => 'password'])->assertRedirect();
        $this->get("/public/family/$old")->assertNotFound();
        $new = $family->fresh()->public_token;
        $this->get("/public/family/$new")->assertOk();
        $this->post("/families/$family->id/public-link/disable")->assertRedirect();
        $this->get("/public/family/$new")->assertNotFound();
    }

    public function test_tokens_are_not_exposed_when_serializing_family(): void
    {
        $family = $this->familyFor();
        $token = $family->generatePublicToken();
        $this->assertStringNotContainsString($token, $family->toJson());
    }

    public function test_security_headers_apply_to_login_and_errors(): void
    {
        foreach (['/login', '/missing-page'] as $url) {
            $response = $this->get($url)->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
            $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $response->headers->get('Content-Security-Policy'));
        }
    }

    public function test_https_and_hsts_are_enforced_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://family.example.org']);
        $this->get('http://family.example.org/login')->assertStatus(301)->assertRedirect('https://family.example.org/login');
        $this->get('https://family.example.org/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_untrusted_host_is_rejected(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://family.example.org']);
        $this->get('https://attacker.example.org/login')->assertStatus(400);
    }

    public function test_pages_use_built_assets_and_keep_core_views_working(): void
    {
        $family = $this->familyFor();
        FamilyEvent::factory()->create(['family_id' => $family->id]);
        $this->actingAs($family->owner);
        foreach (['/dashboard', '/calendar', '/print', '/families', "/families/$family->id", "/families/$family->id/public-link", "/families/$family->id/audit", "/families/$family->id/children", "/families/$family->id/document-imports/create"] as $url) {
            $this->get($url)->assertOk()->assertSee('/build/assets/', false)->assertDontSee('cdn.tailwindcss.com')->assertDontSee('onclick=', false);
        }
    }

    public function test_session_defaults_are_encrypted_and_use_json(): void
    {
        $this->assertTrue(config('session.encrypt'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('json', config('session.serialization'));
        $this->assertSame('lax', config('session.same_site'));
    }
}
