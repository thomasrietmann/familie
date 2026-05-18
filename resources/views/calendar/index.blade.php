<x-layouts.app title="Kalender">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Kalender</h1>
            <p class="mt-2 text-sm text-stone-600">{{ $family?->name ?? 'Noch keine Familie verbunden' }}</p>
        </div>
        @if($family)
            <a href="{{ route('families.events.create', $family) }}" class="inline-flex items-center justify-center rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">Termin erfassen</a>
        @endif
    </div>

    @unless($family)
        <x-card>
            <h2 class="text-lg font-semibold">Noch keine Familie verbunden</h2>
            <p class="mt-2 text-sm text-stone-600">Erstelle zuerst eine Familie, danach erscheint hier die Monatsansicht.</p>
            <a href="{{ route('families.create') }}" class="mt-4 inline-flex rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white">Familie erstellen</a>
        </x-card>
    @else
        @php
            $monthNames = [
                1 => 'Januar',
                2 => 'Februar',
                3 => 'März',
                4 => 'April',
                5 => 'Mai',
                6 => 'Juni',
                7 => 'Juli',
                8 => 'August',
                9 => 'September',
                10 => 'Oktober',
                11 => 'November',
                12 => 'Dezember',
            ];
            $weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        @endphp

        <div class="mb-4 flex flex-col gap-3 rounded-lg border border-stone-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <a class="inline-flex items-center justify-center rounded-md border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-50" href="{{ route('calendar', ['month' => $previousMonth]) }}">Zurück</a>
            <h2 class="text-center text-xl font-semibold">{{ $monthNames[$month->month] }} {{ $month->year }}</h2>
            <a class="inline-flex items-center justify-center rounded-md border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-50" href="{{ route('calendar', ['month' => $nextMonth]) }}">Vor</a>
        </div>

        <section class="overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm">
            <div class="hidden grid-cols-7 bg-blue-700 text-sm font-semibold text-white lg:grid">
                @foreach($weekdays as $weekday)
                    <div class="border-r border-blue-500 px-3 py-3 text-center last:border-r-0">{{ $weekday }}</div>
                @endforeach
            </div>

            <div class="hidden lg:block">
                @foreach($calendarWeeks as $week)
                    <div class="grid grid-cols-7 border-t border-stone-200 first:border-t-0">
                        @foreach($week as $day)
                            <div class="min-h-36 border-r border-stone-200 p-3 last:border-r-0 {{ $day['is_current_month'] ? 'bg-white' : 'bg-stone-50 text-stone-400' }}">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-2xl font-semibold {{ $day['is_today'] ? 'text-blue-700' : '' }}">{{ $day['date']->format('d') }}</p>
                                    @if($day['is_today'])
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">heute</span>
                                    @endif
                                </div>
                                <div class="space-y-1.5">
                                    @foreach($day['events'] as $event)
                                        <button
                                            class="flex w-full items-center gap-2 rounded-md px-2 py-1 text-left text-xs hover:bg-stone-100"
                                            type="button"
                                            data-calendar-event
                                            data-calendar-title="{{ $event->title }}"
                                            data-calendar-time="{{ $event->dashboardTimeLabel() }}"
                                            data-calendar-date="{{ $event->starts_at->format('d.m.Y') }}"
                                            data-calendar-person="{{ $event->ownerDisplayName() }}"
                                            data-calendar-location="{{ $event->location }}"
                                            data-calendar-category="{{ $event->categoryLabel() }}"
                                            data-calendar-status="{{ $event->statusLabel() }}"
                                            data-calendar-description="{{ $event->description }}"
                                            data-calendar-notes="{{ $event->notes }}"
                                        >
                                            <x-owner-dot :event="$event" />
                                            <span class="truncate font-medium text-stone-800">{{ $event->title }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="divide-y divide-stone-100 lg:hidden">
                @foreach($calendarWeeks->flatten(1) as $day)
                    <section class="p-4 {{ $day['is_current_month'] ? 'bg-white' : 'bg-stone-50 text-stone-400' }}">
                        <h3 class="font-semibold {{ $day['is_today'] ? 'text-blue-700' : '' }}">{{ $day['date']->format('d.m.Y') }} - {{ $day['is_today'] ? 'heute' : $weekdays[$day['date']->dayOfWeekIso - 1] }}</h3>
                        <div class="mt-3 space-y-2">
                            @forelse($day['events'] as $event)
                                <button
                                    class="flex w-full items-center gap-2 rounded-md bg-stone-50 p-3 text-left text-sm hover:bg-stone-100"
                                    type="button"
                                    data-calendar-event
                                    data-calendar-title="{{ $event->title }}"
                                    data-calendar-time="{{ $event->dashboardTimeLabel() }}"
                                    data-calendar-date="{{ $event->starts_at->format('d.m.Y') }}"
                                    data-calendar-person="{{ $event->ownerDisplayName() }}"
                                    data-calendar-location="{{ $event->location }}"
                                    data-calendar-category="{{ $event->categoryLabel() }}"
                                    data-calendar-status="{{ $event->statusLabel() }}"
                                    data-calendar-description="{{ $event->description }}"
                                    data-calendar-notes="{{ $event->notes }}"
                                >
                                    <x-owner-dot :event="$event" />
                                    <span class="font-medium text-stone-800">{{ $event->title }}</span>
                                </button>
                            @empty
                                <p class="text-sm text-stone-500">Kein Termin</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <div class="fixed inset-0 z-50 hidden items-center justify-center bg-stone-950/40 px-4" data-calendar-modal>
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-stone-500" data-calendar-modal-date></p>
                        <h2 class="mt-1 text-2xl font-semibold" data-calendar-modal-title></h2>
                    </div>
                    <button class="rounded-md px-2 py-1 text-2xl leading-none text-stone-500 hover:bg-stone-100" type="button" data-calendar-modal-close aria-label="Schliessen">&times;</button>
                </div>

                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-medium text-stone-500">Zeit</dt><dd class="mt-1 text-stone-900" data-calendar-modal-time></dd></div>
                    <div><dt class="font-medium text-stone-500">Person</dt><dd class="mt-1 text-stone-900" data-calendar-modal-person></dd></div>
                    <div><dt class="font-medium text-stone-500">Kategorie</dt><dd class="mt-1 text-stone-900" data-calendar-modal-category></dd></div>
                    <div><dt class="font-medium text-stone-500">Status</dt><dd class="mt-1 text-stone-900" data-calendar-modal-status></dd></div>
                    <div class="sm:col-span-2"><dt class="font-medium text-stone-500">Ort</dt><dd class="mt-1 text-stone-900" data-calendar-modal-location></dd></div>
                    <div class="sm:col-span-2"><dt class="font-medium text-stone-500">Beschreibung</dt><dd class="mt-1 text-stone-900" data-calendar-modal-description></dd></div>
                    <div class="sm:col-span-2"><dt class="font-medium text-stone-500">Notizen</dt><dd class="mt-1 text-stone-900" data-calendar-modal-notes></dd></div>
                </dl>
            </div>
        </div>
    @endunless
</x-layouts.app>
