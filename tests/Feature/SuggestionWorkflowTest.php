<?php

namespace Tests\Feature;

use App\Models\Child;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class SuggestionWorkflowTest extends TestCase
{
    use CreatesFamily, RefreshDatabase;

    public function test_accepting_twice_creates_one_event_and_cannot_later_reject_it(): void
    {
        $family = $this->familyFor();
        $suggestion = $this->suggestionFor($this->documentFor($family));
        $this->actingAs($family->owner);
        $this->post("/imported-event-suggestions/$suggestion->id/accept")->assertRedirect();
        $this->post("/imported-event-suggestions/$suggestion->id/accept")->assertRedirect();
        $this->assertDatabaseCount('family_events', 1);
        $this->post("/imported-event-suggestions/$suggestion->id/reject")->assertStatus(409);
        $this->assertSame('imported', $suggestion->fresh()->status);
    }

    public function test_accept_all_imports_only_pending_items_once(): void
    {
        $family = $this->familyFor();
        $document = $this->documentFor($family);
        $first = $this->suggestionFor($document);
        $this->suggestionFor($document);
        $rejected = $this->suggestionFor($document);
        $rejected->update(['status' => 'rejected']);
        $this->actingAs($family->owner)->post("/imported-event-suggestions/$first->id/accept-all")->assertRedirect();
        $this->post("/imported-event-suggestions/$first->id/accept-all")->assertRedirect();
        $this->assertDatabaseCount('family_events', 2);
        $this->assertSame('rejected', $rejected->fresh()->status);
    }

    public function test_foreign_owner_cannot_be_imported_from_stale_or_untrusted_ai_data(): void
    {
        $family = $this->familyFor();
        $suggestion = $this->suggestionFor($this->documentFor($family));
        $suggestion->update(['suggested_owner_type' => 'child', 'suggested_owner_id' => Child::factory()->create()->id]);
        $this->actingAs($family->owner)->post("/imported-event-suggestions/$suggestion->id/accept")->assertStatus(422);
        $this->assertDatabaseCount('family_events', 0);
        $this->assertSame('pending', $suggestion->fresh()->status);
    }

    public function test_accept_all_rolls_back_when_any_assignment_is_invalid(): void
    {
        $family = $this->familyFor();
        $document = $this->documentFor($family);
        $first = $this->suggestionFor($document);
        $second = $this->suggestionFor($document);
        $second->update(['suggested_owner_type' => 'child', 'suggested_owner_id' => 999]);
        $this->actingAs($family->owner)->post("/imported-event-suggestions/$first->id/accept-all")->assertStatus(422);
        $this->assertDatabaseCount('family_events', 0);
        $this->assertSame('pending', $first->fresh()->status);
    }
}
