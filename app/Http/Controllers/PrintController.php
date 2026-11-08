<?php

namespace App\Http\Controllers;

use App\Models\FamilyEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        $family = $request->user()->managedFamily();
        $month = $this->requestedMonth($request);
        $periodEnd = $month->copy()->addMonths(2);

        $events = $family ? $family->events()
            ->where('starts_at', '<', $periodEnd)
            ->where(function ($query) use ($month): void {
                $query->where('ends_at', '>=', $month)
                    ->orWhere(function ($query) use ($month): void {
                        $query->whereNull('ends_at')->where('starts_at', '>=', $month);
                    });
            })
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get() : collect();

        $eventsByDate = collect();

        foreach ($events as $event) {
            $lastDay = $event->ends_at?->copy() ?? $event->starts_at->copy();

            // All-day imports can use midnight of the following day as an exclusive end.
            if ($event->all_day && $event->ends_at && $lastDay->greaterThan($event->starts_at)) {
                $lastDay->subSecond();
            }

            $cursor = $event->starts_at->copy()->startOfDay()->max($month->copy());
            $lastDay = $lastDay->min($periodEnd->copy()->subSecond())->startOfDay();

            while ($cursor->lessThanOrEqualTo($lastDay)) {
                $key = $cursor->toDateString();
                if (! $eventsByDate->has($key)) {
                    $eventsByDate->put($key, collect());
                }
                $eventsByDate->get($key)->push($event);
                $cursor->addDay();
            }
        }

        return view('print.index', [
            'family' => $family,
            'month' => $month,
            'previousMonth' => $month->copy()->subMonths(2)->format('Y-m'),
            'nextMonth' => $periodEnd->format('Y-m'),
            'months' => collect([0, 1])->map(fn (int $offset) => $this->calendarMonth($month->copy()->addMonths($offset), $eventsByDate)),
            'eventOwners' => $events->mapWithKeys(fn (FamilyEvent $event) => [$event->id => [
                'name' => $event->ownerDisplayName(),
                'color' => $event->ownerColorHex(),
            ]]),
        ]);
    }

    private function requestedMonth(Request $request): Carbon
    {
        $value = $request->query('month');

        if (is_string($value) && preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $value)
            && (int) substr($value, 0, 4) >= 1 && (int) substr($value, 0, 4) <= 9998) {
            return Carbon::createFromFormat('!Y-m', $value);
        }

        return Carbon::today()->startOfMonth();
    }

    private function calendarMonth(Carbon $month, Collection $eventsByDate): array
    {
        $cursor = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $days = collect();

        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $isCurrentMonth = $cursor->isSameMonth($month);
            $days->push([
                'date' => $cursor->copy(),
                'is_current_month' => $isCurrentMonth,
                'events' => $isCurrentMonth ? $eventsByDate->get($cursor->toDateString(), collect()) : collect(),
            ]);
            $cursor->addDay();
        }

        return ['date' => $month, 'weeks' => $days->chunk(7)];
    }
}
