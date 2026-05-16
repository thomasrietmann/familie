<?php

namespace App\Services;

use App\Models\DocumentImport;
use Illuminate\Support\Carbon;

class DocumentEventExtractionService
{
    public function extract(DocumentImport $documentImport): array
    {
        $children = $documentImport->family->children()->orderBy('first_name')->get();
        $firstChild = $children->first();
        $secondChild = $children->skip(1)->first();

        return [
            [
                'title' => 'Schulausflug '.($firstChild?->first_name ?? 'Kind'),
                'description' => 'Mock-Erkennung aus '.$documentImport->original_filename,
                'starts_at' => Carbon::now()->addDays(6)->setTime(8, 30),
                'ends_at' => Carbon::now()->addDays(6)->setTime(15, 30),
                'all_day' => false,
                'location' => 'Schulhaus',
                'category' => 'school',
                'suggested_owner_type' => $firstChild ? 'child' : 'family',
                'suggested_owner_id' => $firstChild?->id,
                'confidence' => 0.87,
            ],
            [
                'title' => $secondChild ? 'Sporttag '.$secondChild->first_name : 'Elterninformation Schule',
                'description' => 'Zweiter sinnvoller Beispieltermin aus dem AI Mock-Service.',
                'starts_at' => Carbon::now()->addDays(10)->setTime(18, 0),
                'ends_at' => null,
                'all_day' => false,
                'location' => 'Aula',
                'category' => 'school',
                'suggested_owner_type' => $secondChild ? 'child' : 'family',
                'suggested_owner_id' => $secondChild?->id,
                'confidence' => 0.74,
            ],
        ];
    }
}
