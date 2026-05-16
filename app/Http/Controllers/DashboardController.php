<?php

namespace App\Http\Controllers;

use App\Models\FamilyEvent;
use App\Models\ImportedEventSuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $family = $request->user()->managedFamily();

        if (! $family) {
            return view('dashboard', [
                'family' => null,
                'eventsToday' => collect(),
                'eventsTomorrow' => collect(),
                'eventsThisWeek' => collect(),
                'upcomingEvents' => collect(),
                'openSuggestionsCount' => 0,
                'nextFamilyEvent' => null,
                'nextEventPerChild' => collect(),
                'nextEventPerParent' => collect(),
                'birthdaysThisMonthCount' => 0,
                'recentDocuments' => collect(),
            ]);
        }

        $events = FamilyEvent::with('family')
            ->where('family_id', $family->id)
            ->where('starts_at', '>=', Carbon::today()->subDay())
            ->orderBy('starts_at')
            ->get();

        $today = Carbon::today();
        $nextEventPerChild = $family->children->mapWithKeys(function ($child) use ($today) {
            return [$child->id => FamilyEvent::where('family_id', $child->family_id)
                ->where('owner_type', 'child')
                ->where('owner_id', $child->id)
                ->where('starts_at', '>=', $today)
                ->orderBy('starts_at')
                ->first()];
        });

        $nextEventPerParent = $family->activeParents->mapWithKeys(function ($parent) use ($family, $today) {
            return [$parent->id => FamilyEvent::where('family_id', $family->id)
                ->where('owner_type', 'user')
                ->where('owner_id', $parent->id)
                ->where('starts_at', '>=', $today)
                ->orderBy('starts_at')
                ->first()];
        });

        return view('dashboard', [
            'family' => $family,
            'eventsToday' => $events->filter->isToday(),
            'eventsTomorrow' => $events->filter->isTomorrow(),
            'eventsThisWeek' => $events->filter(fn (FamilyEvent $event) => $event->starts_at->between($today, $today->copy()->endOfWeek())),
            'upcomingEvents' => $events->filter->isUpcoming()->take(8),
            'openSuggestionsCount' => ImportedEventSuggestion::where('family_id', $family->id)->where('status', 'pending')->count(),
            'nextFamilyEvent' => FamilyEvent::where('family_id', $family->id)->where('owner_type', 'family')->where('starts_at', '>=', $today)->orderBy('starts_at')->first(),
            'nextEventPerChild' => $nextEventPerChild,
            'nextEventPerParent' => $nextEventPerParent,
            'birthdaysThisMonthCount' => $family->children->filter(fn ($child) => $child->birthdate?->month === now()->month)->count(),
            'recentDocuments' => $family->documentImports->sortByDesc('created_at')->take(5),
        ]);
    }
}
