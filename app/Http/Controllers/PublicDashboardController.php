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
        $weekEnd = $today->copy()->addDays(6)->endOfDay();
        $events = $family->events()
            ->where('visibility', 'family')
            ->whereBetween('starts_at', [$today, $weekEnd])
            ->orderBy('starts_at')
            ->get();

        return view('public.dashboard', [
            'eventsToday' => $events->filter->isToday(),
            'eventsTomorrow' => $events->filter->isTomorrow(),
            'eventsThisWeek' => $events,
            'upcomingEvents' => $events->take(8),
            'nextEvent' => $events->first(),
            'weekDays' => $this->weekDays($today, $events),
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
