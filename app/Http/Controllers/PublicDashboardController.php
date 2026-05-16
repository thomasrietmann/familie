<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyEvent;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PublicDashboardController extends Controller
{
    public function show(string $token): View
    {
        $family = Family::where('dashboard_public_token', $token)
            ->where('dashboard_public_token_enabled', true)
            ->firstOrFail();

        $today = Carbon::today();
        $events = $family->events()
            ->where('visibility', 'family')
            ->where('starts_at', '>=', $today)
            ->orderBy('starts_at')
            ->get();

        return view('public.dashboard', [
            'eventsToday' => $events->filter->isToday(),
            'eventsTomorrow' => $events->filter->isTomorrow(),
            'eventsThisWeek' => $events->filter(fn (FamilyEvent $event) => $event->starts_at->between($today, $today->copy()->endOfWeek())),
            'upcomingEvents' => $events->take(8),
            'nextEvent' => $events->first(),
        ]);
    }
}
