<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PublicFamilyController extends Controller
{
    public function show(string $token): View
    {
        $family = Family::where('public_token', $token)
            ->where('public_token_enabled', true)
            ->firstOrFail();

        $events = $family->events()
            ->where('visibility', 'family')
            ->whereBetween('starts_at', [Carbon::today(), Carbon::tomorrow()->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        return view('public.family', [
            'family' => $family,
            'eventsToday' => $events->filter->isToday(),
            'eventsTomorrow' => $events->filter->isTomorrow(),
        ]);
    }
}
