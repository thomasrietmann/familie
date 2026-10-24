<?php

namespace Tests\Concerns;

use App\Models\DocumentImport;
use App\Models\Family;
use App\Models\ImportedEventSuggestion;
use App\Models\User;

trait CreatesFamily
{
    protected function familyFor(?User $user = null): Family
    {
        $user ??= User::factory()->create();
        $family = Family::factory()->create(['owner_user_id' => $user->id]);
        $family->users()->attach($user, ['role' => 'owner', 'status' => 'active']);

        return $family;
    }

    protected function documentFor(Family $family): DocumentImport
    {
        return $family->documentImports()->create([
            'uploaded_by_user_id' => $family->owner_user_id,
            'title' => 'Schulplan', 'original_filename' => 'schule.pdf', 'file_type' => 'pdf',
            'file_path' => null, 'target_type' => 'family', 'status' => 'analyzed',
        ]);
    }

    protected function suggestionFor(DocumentImport $document): ImportedEventSuggestion
    {
        return $document->suggestions()->create([
            'family_id' => $document->family_id, 'title' => 'Elternabend',
            'starts_at' => now()->addDay()->setTime(19, 0), 'all_day' => false,
            'category' => 'school', 'suggested_owner_type' => 'family', 'status' => 'pending',
        ]);
    }

    protected function eventData(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Ausflug', 'starts_at' => '2026-11-01 10:00:00', 'all_day' => false,
            'category' => 'excursion', 'visibility' => 'family', 'owner_type' => 'family', 'status' => 'planned',
        ], $overrides);
    }
}
