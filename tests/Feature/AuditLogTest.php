<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesFamily, RefreshDatabase;

    public function test_changes_record_actor_and_field_names_without_sensitive_contents(): void
    {
        $family = $this->familyFor();
        $this->actingAs($family->owner)->put("/families/$family->id", ['name' => 'Private family name', 'notes' => 'Medical private notes'])->assertRedirect();
        $entry = AuditLog::where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame($family->id, $entry->family_id);
        $this->assertSame($family->owner_user_id, $entry->user_id);
        $this->assertContains('notes', $entry->changed_fields);
        $this->assertStringNotContainsString('Medical private notes', $entry->toJson());
        app(AuditLogger::class)->record('security_test', $family, ['password', 'token', 'random secret', 'notes']);
        $this->assertSame(['notes'], AuditLog::latest('id')->first()->changed_fields);
    }

    public function test_audit_is_scoped_to_family_and_pruning_respects_retention(): void
    {
        $family = $this->familyFor();
        $other = $this->familyFor();
        app(AuditLogger::class)->record('own-action', $family);
        app(AuditLogger::class)->record('foreign-action-marker', $other);
        $this->actingAs($family->owner)->get("/families/$family->id/audit")->assertOk()->assertSee('own-action')->assertDontSee('foreign-action-marker');
        AuditLog::where('action', 'own-action')->update(['created_at' => now()->subDays(181)]);
        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'own-action']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'foreign-action-marker']);
    }

    public function test_demo_seeding_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        (new DatabaseSeeder)->run();
    }
}
