<?php

namespace App\Http\Controllers;

use App\Models\FamilyEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PrintController extends Controller
{
    private const COLUMN_COUNT = 3;
    private const EVENTS_PER_COLUMN = 12;

    public function __invoke(Request $request): View
    {
        $family = $request->user()->managedFamily();

        if (! $family) {
            return view('print.index', [
                'family' => null,
                'events' => collect(),
                'eventColumns' => $this->emptyColumns(),
            ]);
        }

        $today = Carbon::today();

        $events = $family->events()
            ->where(function ($query) use ($today): void {
                $query->where('starts_at', '>=', $today)
                    ->orWhere(function ($query) use ($today): void {
                        $query->whereNotNull('ends_at')
                            ->where('ends_at', '>=', $today);
                    });
            })
            ->orderBy('starts_at')
            ->get()
            ->sortBy(fn (FamilyEvent $event) => max($event->starts_at->timestamp, $today->timestamp))
            ->values();
        $printEvents = $events->take(self::EVENTS_PER_COLUMN * self::COLUMN_COUNT);

        return view('print.index', [
            'family' => $family,
            'events' => $printEvents,
            'eventColumns' => $this->eventColumns($printEvents),
        ]);
    }

    private function eventColumns($events)
    {
        return collect(range(0, self::COLUMN_COUNT - 1))
            ->map(fn (int $column) => $events
                ->slice($column * self::EVENTS_PER_COLUMN, self::EVENTS_PER_COLUMN)
                ->values());
    }

    private function emptyColumns()
    {
        return collect(range(1, self::COLUMN_COUNT))->map(fn () => collect());
    }
}
