<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\FamilyEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class FamilyAuthorizationTest extends TestCase
{
    use CreatesFamily, RefreshDatabase;

    public function test_guest_cannot_access_private_routes(): void
    {
        $family = $this->familyFor();
        foreach (['/dashboard', '/calendar', '/print', '/families/'.$family->id, '/families/'.$family->id.'/document-imports'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_another_family_cannot_read_or_change_records(): void
    {
        $own = $this->familyFor();
        $other = $this->familyFor();
        $event = FamilyEvent::factory()->create(['family_id' => $other->id]);
        $child = Child::factory()->create(['family_id' => $other->id]);
        $document = $this->documentFor($other);
        $suggestion = $this->suggestionFor($document);
        $this->actingAs($own->owner);
        foreach (["/families/$other->id", "/families/$other->id/events/$event->id", "/families/$other->id/children/$child->id/edit", "/document-imports/$document->id/review", "/families/$other->id/audit"] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->put("/families/$other->id/events/$event->id", $this->eventData())->assertForbidden();
        $this->post("/imported-event-suggestions/$suggestion->id/accept")->assertForbidden();
        $this->delete("/families/$other->id/children/$child->id")->assertForbidden();
        $this->assertModelExists($child);
    }

    public function test_nested_binding_rejects_a_record_under_the_wrong_family(): void
    {
        $own = $this->familyFor();
        $event = FamilyEvent::factory()->create();
        $this->actingAs($own->owner)->get("/families/$own->id/events/$event->id")->assertNotFound();
    }

    public function test_inactive_members_cannot_access_family(): void
    {
        $family = $this->familyFor();
        $family->users()->updateExistingPivot($family->owner_user_id, ['status' => 'invited']);
        $this->actingAs($family->owner)->get("/families/$family->id")->assertForbidden();
        $this->post("/families/$family->id/public-link/enable", ['current_password' => 'password'])->assertForbidden();
    }

    public function test_event_owner_must_belong_to_same_family(): void
    {
        $family = $this->familyFor();
        $foreignChild = Child::factory()->create();
        $this->actingAs($family->owner)->post("/families/$family->id/events", $this->eventData(['owner_type' => 'child', 'owner_id' => $foreignChild->id]))->assertSessionHasErrors('owner_id');
        $this->assertDatabaseCount('family_events', 0);
    }

    public function test_foreign_member_colors_cannot_be_changed_and_malformed_input_is_rejected(): void
    {
        $family = $this->familyFor();
        $other = $this->familyFor();
        $this->actingAs($family->owner)->post("/families/$family->id/member-colors", ['parent_colors' => [$other->owner_user_id => 'blue']])->assertSessionHasErrors('parent_colors');
        $this->post("/families/$family->id/member-colors", ['parent_colors' => 'broken'])->assertSessionHasErrors('parent_colors');
    }

    public function test_member_can_edit_events_but_only_owner_manages_access(): void
    {
        $family = $this->familyFor();
        $parent = User::factory()->create();
        $family->users()->attach($parent, ['role' => 'parent', 'status' => 'active']);
        $this->actingAs($parent)->post("/families/$family->id/events", $this->eventData())->assertRedirect();
        $this->get("/families/$family->id/public-link")->assertForbidden();
        $this->post("/families/$family->id/parents/invite", ['email' => 'new@example.org', 'current_password' => 'password'])->assertForbidden();
        $this->get("/families/$family->id/audit")->assertForbidden();
        $this->delete("/families/$family->id", ['current_password' => 'password'])->assertForbidden();
    }

    public function test_family_deletion_and_access_changes_require_current_password(): void
    {
        $family = $this->familyFor();
        $this->actingAs($family->owner)->delete("/families/$family->id")->assertSessionHasErrors('current_password');
        $this->post("/families/$family->id/public-link/enable")->assertSessionHasErrors('current_password');
        $this->assertModelExists($family);
        $this->assertFalse($family->fresh()->public_token_enabled);
    }

    public function test_owner_cannot_be_downgraded_by_inviting_themselves(): void
    {
        $family = $this->familyFor();
        $this->actingAs($family->owner)->post("/families/$family->id/parents/invite", ['email' => $family->owner->email, 'current_password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('family_users', ['user_id' => $family->owner_user_id, 'role' => 'owner']);
    }

    public function test_mass_assignment_does_not_change_family_or_source(): void
    {
        $family = $this->familyFor();
        $other = $this->familyFor();
        $this->actingAs($family->owner)->post("/families/$family->id/events", $this->eventData(['family_id' => $other->id, 'source' => 'import', 'document_import_id' => 123]))->assertRedirect();
        $this->assertDatabaseHas('family_events', ['family_id' => $family->id, 'source' => 'manual', 'document_import_id' => null]);
    }

    public function test_stored_html_is_escaped_in_private_views(): void
    {
        $family = $this->familyFor();
        $event = FamilyEvent::factory()->create(['family_id' => $family->id, 'title' => '<script>alert(1)</script>']);
        $this->actingAs($family->owner)->get("/families/$family->id/events/$event->id")->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_invalid_filter_input_returns_validation_error(): void
    {
        $family = $this->familyFor();
        $this->actingAs($family->owner)->get("/families/$family->id/events?from=not-a-date")->assertSessionHasErrors('from');
    }
}
