<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyEvent;
use App\Models\ImportedEventSuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $families = $request->user()->activeFamilies()->with(['children', 'activeParents', 'documentImports'])->get();
        $familyIds = $families->pluck('id');

        $events = FamilyEvent::with('family')
            ->whereIn('family_id', $familyIds)
            ->where('starts_at', '>=', Carbon::today()->subDay())
            ->orderBy('starts_at')
            ->get();

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $nextEventPerChild = $families->flatMap->children->mapWithKeys(function ($child) use ($today) {
            return [$child->id => FamilyEvent::where('family_id', $child->family_id)
                ->where('owner_type', 'child')
                ->where('owner_id', $child->id)
                ->where('starts_at', '>=', $today)
                ->orderBy('starts_at')
                ->first()];
        });

        $nextEventPerParent = $families->flatMap->activeParents->unique('id')->mapWithKeys(function ($parent) use ($familyIds, $today) {
            return [$parent->id => FamilyEvent::whereIn('family_id', $familyIds)
                ->where('owner_type', 'user')
                ->where('owner_id', $parent->id)
                ->where('starts_at', '>=', $today)
                ->orderBy('starts_at')
                ->first()];
        });

        return view('dashboard', [
            'families' => $families,
            'eventsToday' => $events->filter->isToday(),
            'eventsTomorrow' => $events->filter->isTomorrow(),
            'eventsThisWeek' => $events->filter(fn (FamilyEvent $event) => $event->starts_at->between($today, $today->copy()->endOfWeek())),
            'upcomingEvents' => $events->filter->isUpcoming()->take(8),
            'openSuggestionsCount' => ImportedEventSuggestion::whereIn('family_id', $familyIds)->where('status', 'pending')->count(),
            'nextFamilyEvent' => FamilyEvent::whereIn('family_id', $familyIds)->where('owner_type', 'family')->where('starts_at', '>=', $today)->orderBy('starts_at')->first(),
            'nextEventPerChild' => $nextEventPerChild,
            'nextEventPerParent' => $nextEventPerParent,
            'birthdaysThisMonthCount' => $families->flatMap->children->filter(fn ($child) => $child->birthdate?->month === now()->month)->count(),
            'recentDocuments' => $families->flatMap->documentImports->sortByDesc('created_at')->take(5),
        ]);
    }
}
