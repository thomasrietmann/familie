<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $family->name }} · Heute und morgen</title>
    <link rel="icon" type="image/png" href="{{ asset('images/familymanager-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/familymanager-icon.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script defer src="{{ asset('js/app.js') }}"></script>
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <div class="mb-8">
            <img src="{{ asset('images/familymanager-logo.png') }}" alt="FamilyManager" class="mb-6 h-14 w-auto max-w-full object-contain">
            <p class="text-sm text-stone-500">Öffentliche Familienübersicht</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $family->name }}</h1>
        </div>

        @foreach(['Heute' => $eventsToday, 'Morgen' => $eventsTomorrow] as $label => $events)
            <section class="mb-6 rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-xl font-semibold">{{ $label }}</h2>
                <div class="divide-y divide-stone-100">
                    @forelse($events as $event)
                        <div class="py-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold">{{ $event->title }}</p>
                                    <p class="text-sm text-stone-600">{{ $event->all_day ? 'Ganztägig' : $event->starts_at->format('H:i') }} · {{ $event->ownerDisplayName() }}</p>
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
        @endforeach
    </main>
</body>
</html>
