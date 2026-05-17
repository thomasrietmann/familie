<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'family_trip' => 'excursion',
            'playdate' => 'other',
            'childcare' => 'other',
            'medical' => 'doctor',
            'sport' => 'sports',
            'meeting' => 'parent_evening',
        ];

        foreach ($map as $old => $new) {
            DB::table('family_events')->where('category', $old)->update(['category' => $new]);
            DB::table('imported_event_suggestions')->where('category', $old)->update(['category' => $new]);
        }
    }

    public function down(): void
    {
        $map = [
            'excursion' => 'family_trip',
            'doctor' => 'medical',
            'sports' => 'sport',
            'parent_evening' => 'meeting',
        ];

        foreach ($map as $old => $new) {
            DB::table('family_events')->where('category', $old)->update(['category' => $new]);
            DB::table('imported_event_suggestions')->where('category', $old)->update(['category' => $new]);
        }
    }
};
