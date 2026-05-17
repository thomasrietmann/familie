<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="icon" type="image/png" href="{{ asset('images/familymanager-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/familymanager-icon.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script defer src="{{ asset('js/app.js') }}"></script>
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-stone-500">Heute</p>
                <p class="mt-2 text-3xl font-semibold">{{ $eventsToday->count() }}</p>
            </div>
            <div class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-stone-500">Morgen</p>
                <p class="mt-2 text-3xl font-semibold">{{ $eventsTomorrow->count() }}</p>
            </div>
            <div class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-stone-500">Diese Woche</p>
                <p class="mt-2 text-3xl font-semibold">{{ $eventsThisWeek->count() }}</p>
            </div>
            <div class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-stone-500">Nächster Termin</p>
                <p class="mt-2 text-base font-semibold">{{ $nextEvent?->title ?? 'Kein Termin' }}</p>
                @if($nextEvent)
                    <p class="mt-1 text-sm text-stone-600">{{ $nextEvent->starts_at->format('d.m.Y H:i') }} · {{ $nextEvent->ownerDisplayName() }}</p>
                @endif
            </div>
        </div>

        <section class="mt-6 rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
            <h1 class="mb-4 text-xl font-semibold">Nächste Termine</h1>
            <div class="divide-y divide-stone-100">
                @forelse($upcomingEvents as $event)
                    <div class="py-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2"><x-owner-dot :event="$event" /><p class="font-semibold">{{ $event->title }}</p></div>
                                <p class="text-sm text-stone-600">{{ $event->all_day ? 'Ganztägig' : $event->starts_at->format('d.m.Y H:i') }} · {{ $event->ownerDisplayName() }}</p>
                            </div>
                            <span class="inline-flex w-fit rounded-md bg-stone-100 px-2 py-1 text-xs font-medium text-stone-700">{{ $event->categoryLabel() }}</span>
                        </div>
                        @if($event->location)
                            <p class="mt-2 text-sm text-stone-600">{{ $event->location }}</p>
                        @endif
                    </div>
                @empty
                    <p class="py-4 text-sm text-stone-500">Keine sichtbaren Termine.</p>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
