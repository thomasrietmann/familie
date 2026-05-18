<?php

namespace App\Http\Controllers;

use App\Models\FamilyEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $family = $request->user()->managedFamily();

        if (! $family) {
            return view('calendar.index', [
                'family' => null,
                'month' => Carbon::today()->startOfMonth(),
                'previousMonth' => Carbon::today()->subMonth()->format('Y-m'),
                'nextMonth' => Carbon::today()->addMonth()->format('Y-m'),
                'calendarWeeks' => collect(),
            ]);
        }

        $month = $this->requestedMonth($request);

        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $events = $family->events()
            ->where('starts_at', '<=', $gridEnd->copy()->endOfDay())
            ->where(function ($query) use ($gridStart): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $gridStart);
            })
            ->orderBy('starts_at')
            ->get();

        return view('calendar.index', [
            'family' => $family,
            'month' => $month,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'calendarWeeks' => $this->calendarWeeks($gridStart, $gridEnd, $month, $events),
        ]);
    }

    private function calendarWeeks(Carbon $gridStart, Carbon $gridEnd, Carbon $month, $events)
    {
        $weeks = collect();
        $cursor = $gridStart->copy();

        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $days = collect();

            foreach (range(1, 7) as $day) {
                $date = $cursor->copy();

                $days->push([
                    'date' => $date,
                    'is_current_month' => $date->month === $month->month && $date->year === $month->year,
                    'is_today' => $date->isToday(),
                    'events' => $events->filter(fn (FamilyEvent $event) => $this->eventTouchesDate($event, $date))->values(),
                ]);

                $cursor->addDay();
            }

            $weeks->push($days);
        }

        return $weeks;
    }

    private function requestedMonth(Request $request): Carbon
    {
        if (! $request->filled('month')) {
            return Carbon::today()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', (string) $request->string('month'))->startOfMonth();
        } catch (\Throwable) {
            return Carbon::today()->startOfMonth();
        }
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
}
