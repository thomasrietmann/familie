<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=3">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script defer src="{{ asset('js/app.js') }}"></script>
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <section class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
            <div class="divide-y divide-stone-100">
                @foreach($weekDays as $day)
                    <section class="py-4 first:pt-0 last:pb-0">
                        <h2 class="text-base font-semibold">{{ $day['date_label'] }} - {{ $day['day_label'] }}</h2>
                        <div class="mt-3 space-y-3">
                            @forelse($day['events'] as $event)
                                <div class="rounded-md bg-stone-50 p-3">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="flex items-center gap-2"><x-owner-dot :event="$event" /><p class="font-semibold">{{ $event->title }}</p></div>
                                            <p class="text-sm text-stone-600">{{ $event->dashboardTimeLabel() }} · {{ $event->ownerDisplayName() }}</p>
                                            @if($event->location)
                                                <p class="mt-1 text-sm text-stone-600">{{ $event->location }}</p>
                                            @endif
                                        </div>
                                        <span class="inline-flex w-fit rounded-md bg-stone-100 px-2 py-1 text-xs font-medium text-stone-700">{{ $event->categoryLabel() }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-md bg-stone-50 p-3 text-sm text-stone-500">Kein Termin</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
