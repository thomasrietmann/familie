<x-layouts.app title="Dashboard">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $family?->name ?? 'FamilyManager' }}</h1>
            <p class="mt-2 text-sm text-stone-600">Termine, Kinder, Dokumente und offene Import-Vorschläge auf einen Blick.</p>
        </div>
        @if($family)
            <a href="{{ route('families.events.create', $family) }}" class="inline-flex items-center justify-center rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">Termin erfassen</a>
        @else
            <a href="{{ route('families.create') }}" class="inline-flex items-center justify-center rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">Familie erstellen</a>
        @endif
    </div>

    @unless($family)
        <x-card>
            <h2 class="text-lg font-semibold">Noch keine Familie verbunden</h2>
            <p class="mt-2 text-sm text-stone-600">Dieser Login verwaltet genau eine Familie. Erstelle die Familie, danach erscheinen Dashboard, Termine, Kinder und Dokumente hier.</p>
        </x-card>
    @else

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card><p class="text-sm text-stone-500">Termine heute</p><p class="mt-2 text-3xl font-semibold">{{ $eventsToday->count() }}</p></x-card>
        <x-card><p class="text-sm text-stone-500">Termine morgen</p><p class="mt-2 text-3xl font-semibold">{{ $eventsTomorrow->count() }}</p></x-card>
        <x-card><p class="text-sm text-stone-500">Diese Woche</p><p class="mt-2 text-3xl font-semibold">{{ $eventsThisWeek->count() }}</p></x-card>
        <x-card><p class="text-sm text-stone-500">Offene Import-Vorschläge</p><p class="mt-2 text-3xl font-semibold">{{ $openSuggestionsCount }}</p></x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Nächste Termine</h2>
            </div>
            <div class="divide-y divide-stone-100">
                @forelse($upcomingEvents as $event)
                    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2"><x-owner-dot :event="$event" /><a class="font-medium hover:underline" href="{{ route('families.events.show', [$event->family, $event]) }}">{{ $event->title }}</a></div>
                            <p class="text-sm text-stone-600">{{ $event->dashboardDateLabel() }} · {{ $event->ownerDisplayName() }}</p>
                        </div>
                        <x-badge>{{ $event->categoryLabel() }}</x-badge>
                    </div>
                @empty
                    <p class="py-6 text-sm text-stone-500">Noch keine kommenden Termine.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold">Nächster Familientermin</h2>
            @if($nextFamilyEvent)
                <p class="mt-4 font-medium">{{ $nextFamilyEvent->title }}</p>
                <p class="text-sm text-stone-600">{{ $nextFamilyEvent->dashboardDateLabel() }}</p>
            @else
                <p class="mt-4 text-sm text-stone-500">Kein Familientermin geplant.</p>
            @endif
            <div class="mt-6 border-t border-stone-100 pt-4">
                <p class="text-sm text-stone-500">Geburtstage diesen Monat</p>
                <p class="mt-1 text-2xl font-semibold">{{ $birthdaysThisMonthCount }}</p>
            </div>
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-4 text-lg font-semibold">Kinder mit nächsten Terminen</h2>
            <div class="space-y-3">
                @foreach($family->children as $child)
                    @php($event = $nextEventPerChild[$child->id] ?? null)
                    <div class="rounded-md bg-stone-50 p-3">
                        <p class="font-medium">{{ $child->displayName() }}</p>
                        <p class="text-sm text-stone-600">{{ $event ? $event->title.' · '.$event->dashboardDateLabel().' · '.$event->ownerDisplayName() : 'Kein kommender Termin' }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>
        <x-card>
            <h2 class="mb-4 text-lg font-semibold">Elternteile mit nächsten Terminen</h2>
            <div class="space-y-3">
                @foreach($family->activeParents as $parent)
                    @php($event = $nextEventPerParent[$parent->id] ?? null)
                    <div class="rounded-md bg-stone-50 p-3">
                        <p class="font-medium">{{ $parent->name }}</p>
                        <p class="text-sm text-stone-600">{{ $event ? $event->title.' · '.$event->dashboardDateLabel().' · '.$event->ownerDisplayName() : 'Kein kommender Termin' }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>
    @endunless
</x-layouts.app>
