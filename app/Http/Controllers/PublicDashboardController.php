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
            ->where('starts_at', '<=', $weekEnd)
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $today);
            })
            ->orderBy('starts_at')
            ->get();

        return view('public.dashboard', [
            'eventsToday' => $events->filter(fn (FamilyEvent $event) => $this->eventTouchesDate($event, $today))->values(),
            'eventsTomorrow' => $events->filter(fn (FamilyEvent $event) => $this->eventTouchesDate($event, $today->copy()->addDay()))->values(),
            'eventsThisWeek' => $events,
            'upcomingEvents' => $events->take(8),
            'nextEvent' => $events->first(),
            'weekDays' => $this->weekDays($today, $events),
        ]);
    }

    private function weekDays(Carbon $today, $events)
    {
        return collect(range(0, 6))->map(function (int $offset) use ($today, $events) {
            $date = $today->copy()->addDays($offset);

            return [
                'date' => $date,
                'date_label' => $date->format('d.m.Y'),
                'day_label' => $offset === 0 ? 'heute' : $this->weekdayLabel($date),
                'events' => $events->filter(fn (FamilyEvent $event) => $this->eventTouchesDate($event, $date))->values(),
            ];
        });
    }

    private function eventTouchesDate(FamilyEvent $event, Carbon $date): bool
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $eventStart = $event->starts_at->copy();
        $eventEnd = $event->ends_at?->copy() ?? $eventStart->copy();

        if ($event->all_day && $event->ends_at) {
            $eventEnd->subSecond();
        }

        return $eventStart->lessThanOrEqualTo($dayEnd) && $eventEnd->greaterThanOrEqualTo($dayStart);
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
