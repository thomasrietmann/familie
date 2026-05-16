<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\DocumentImport;
use App\Models\Family;
use App\Models\FamilyEvent;
use App\Models\ImportedEventSuggestion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $thomas = User::create([
            'name' => 'Thomas Rietmann',
            'email' => 'thomas@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $parent = User::create([
            'name' => 'Weiteres Elternteil',
            'email' => 'parent@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $family = Family::create([
            'name' => 'Familie Rietmann',
            'owner_user_id' => $thomas->id,
            'notes' => 'Beispiel-Familie fuer das FamilyManager MVP.',
        ]);
        $family->generatePublicToken();

        $family->users()->attach($thomas->id, [
            'role' => 'owner',
            'status' => 'active',
            'accepted_at' => now(),
        ]);
        $family->users()->attach($parent->id, [
            'role' => 'parent',
            'status' => 'active',
            'invited_at' => now()->subDays(2),
            'accepted_at' => now()->subDay(),
        ]);

        $childOne = Child::create([
            'family_id' => $family->id,
            'first_name' => 'Kind 1',
            'birthdate' => now()->subYears(8)->startOfMonth()->addDays(4),
        ]);

        $childTwo = Child::create([
            'family_id' => $family->id,
            'first_name' => 'Kind 2',
            'birthdate' => now()->subYears(5)->startOfMonth()->addDays(14),
        ]);

        $events = [
            ['Familienausflug Zoo Zuerich', 'family_trip', 'family', null, 'confirmed', 'family', now()->addDays(3)->setTime(9, 0), 'Zoo Zuerich'],
            ['Kindergeburtstag Kind 1', 'birthday', 'child', $childOne->id, 'planned', 'family', now()->addDays(12)->setTime(14, 0), 'Zuhause'],
            ['Spielnachmittag mit anderer Familie', 'playdate', 'family', null, 'planned', 'family', now()->addDays(5)->setTime(15, 0), 'Spielplatz'],
            ['Arzttermin Kind 1', 'medical', 'child', $childOne->id, 'confirmed', 'parents_only', now()->addDays(2)->setTime(10, 30), 'Kinderarzt'],
            ['Sportkurs Kind 2', 'sport', 'child', $childTwo->id, 'planned', 'family', now()->addDays(4)->setTime(17, 0), 'Turnhalle'],
            ['Elternabend', 'school', 'user', $thomas->id, 'planned', 'family', now()->addDays(7)->setTime(19, 30), 'Schule'],
            ['Familienfruehstueck morgen', 'other', 'family', null, 'confirmed', 'family', Carbon::tomorrow()->setTime(9, 0), 'Zuhause'],
        ];

        foreach ($events as [$title, $category, $ownerType, $ownerId, $status, $visibility, $startsAt, $location]) {
            FamilyEvent::create([
                'family_id' => $family->id,
                'title' => $title,
                'starts_at' => $startsAt,
                'ends_at' => null,
                'all_day' => false,
                'location' => $location,
                'category' => $category,
                'visibility' => $visibility,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'status' => $status,
                'source' => 'manual',
            ]);
        }

        $documentImport = DocumentImport::create([
            'family_id' => $family->id,
            'uploaded_by_user_id' => $thomas->id,
            'title' => 'Schultermine Beispiel',
            'file_path' => 'document-imports/schultermine-beispiel.pdf',
            'original_filename' => 'schultermine-beispiel.pdf',
            'file_type' => 'pdf',
            'status' => 'analyzed',
            'raw_ai_result' => [
                'provider' => 'mock',
                'note' => 'Beispieldaten aus dem Seeder.',
            ],
        ]);

        ImportedEventSuggestion::create([
            'document_import_id' => $documentImport->id,
            'family_id' => $family->id,
            'title' => 'Schulausflug Kind 1',
            'description' => 'Aus dem Beispielimport erkannt.',
            'starts_at' => now()->addDays(9)->setTime(8, 15),
            'ends_at' => now()->addDays(9)->setTime(16, 0),
            'all_day' => false,
            'location' => 'Bahnhof',
            'category' => 'school',
            'suggested_owner_type' => 'child',
            'suggested_owner_id' => $childOne->id,
            'confidence' => 0.87,
            'status' => 'pending',
        ]);

        ImportedEventSuggestion::create([
            'document_import_id' => $documentImport->id,
            'family_id' => $family->id,
            'title' => 'Elterninformation Schule',
            'description' => 'Allgemeine Information fuer die ganze Familie.',
            'starts_at' => now()->addDays(11)->setTime(18, 0),
            'all_day' => false,
            'location' => 'Schulhaus',
            'category' => 'school',
            'suggested_owner_type' => 'family',
            'suggested_owner_id' => null,
            'confidence' => 0.74,
            'status' => 'pending',
        ]);
    }
}
