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
                'weekDays' => collect(),
                'openSuggestionsCount' => 0,
                'nextFamilyEvent' => null,
                'nextEventPerChild' => collect(),
                'nextEventPerParent' => collect(),
                'recentDocuments' => collect(),
            ]);
        }

        $today = Carbon::today();
        $weekEnd = $today->copy()->addDays(6)->endOfDay();

        $events = FamilyEvent::with('family')
            ->where('family_id', $family->id)
            ->whereBetween('starts_at', [$today, $weekEnd])
            ->orderBy('starts_at')
            ->get();

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
            'eventsThisWeek' => $events,
            'upcomingEvents' => $events->filter->isUpcoming()->take(8),
            'weekDays' => $this->weekDays($today, $events),
            'openSuggestionsCount' => ImportedEventSuggestion::where('family_id', $family->id)->where('status', 'pending')->count(),
            'nextFamilyEvent' => FamilyEvent::where('family_id', $family->id)->where('owner_type', 'family')->where('starts_at', '>=', $today)->orderBy('starts_at')->first(),
            'nextEventPerChild' => $nextEventPerChild,
            'nextEventPerParent' => $nextEventPerParent,
            'recentDocuments' => $family->documentImports->sortByDesc('created_at')->take(5),
        ]);
    }

    private function weekDays(Carbon $today, $events)
    {
        $eventsByDate = $events->groupBy(fn (FamilyEvent $event) => $event->starts_at->toDateString());

        return collect(range(0, 6))->map(function (int $offset) use ($today, $eventsByDate) {
            $date = $today->copy()->addDays($offset);

            return [
                'date' => $date,
                'date_label' => $date->format('d.m.Y'),
                'day_label' => $offset === 0 ? 'heute' : $this->weekdayLabel($date),
                'events' => $eventsByDate->get($date->toDateString(), collect()),
            ];
        });
    }

    private function weekdayLabel(Carbon $date): string
    {
        return [
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
        ][$date->dayOfWeekIso];
    }
}
