<x-layouts.app title="{{ $family->name }}">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $family->name }}</h1>
            <p class="mt-2 text-sm text-stone-600">{{ $family->notes ?: 'Familienverwaltung, Elternrechte und Schnellzugriffe.' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="rounded-md border border-stone-300 px-4 py-2 text-sm font-medium" href="{{ route('families.edit', $family) }}">Bearbeiten</a>
            <a class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white" href="{{ route('families.events.create', $family) }}">Termin erfassen</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card>
            <h2 class="mb-4 text-lg font-semibold">Eltern</h2>
            <div class="space-y-2">
                @foreach($family->activeParents as $parent)
                    <div class="rounded-md bg-stone-50 p-3">
                        <p class="font-medium">{{ $parent->name }}</p>
                        <p class="text-xs text-stone-500">{{ $parent->email }} · {{ $parent->pivot->role }}</p>
                    </div>
                @endforeach
            </div>
            @can('inviteParent', $family)
                <form method="POST" action="{{ route('families.parents.invite', $family) }}" class="mt-5 space-y-3">
                    @csrf
                    <input class="block w-full rounded-md border-stone-300 text-sm" name="email" type="email" placeholder="parent@example.com" required>
                    <button class="rounded-md bg-stone-900 px-3 py-2 text-sm font-medium text-white">Elternteil einladen</button>
                </form>
            @endcan
        </x-card>

        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Kinder</h2>
                <a class="text-sm font-medium hover:underline" href="{{ route('families.children.index', $family) }}">Alle</a>
            </div>
            <div class="space-y-2">
                @forelse($family->children as $child)
                    <div class="rounded-md bg-stone-50 p-3">{{ $child->displayName() }}</div>
                @empty
                    <p class="text-sm text-stone-500">Noch keine Kinder erfasst.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <h2 class="mb-4 text-lg font-semibold">Secret Link</h2>
            <x-badge :tone="$family->hasPublicToken() ? 'green' : 'stone'">{{ $family->hasPublicToken() ? 'aktiv' : 'inaktiv' }}</x-badge>
            <div class="mt-4">
                <a class="text-sm font-medium hover:underline" href="{{ route('families.public-link.show', $family) }}">Secret Link verwalten</a>
            </div>
        </x-card>
    </div>

    <x-card class="mt-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold">Nächste Termine</h2>
            <a class="text-sm font-medium hover:underline" href="{{ route('families.events.index', $family) }}">Alle Termine</a>
        </div>
        <div class="divide-y divide-stone-100">
            @forelse($family->events as $event)
                <div class="flex items-center justify-between py-3">
                    <div><p class="font-medium">{{ $event->title }}</p><p class="text-sm text-stone-600">{{ $event->starts_at->format('d.m.Y H:i') }} · {{ $event->ownerDisplayName() }}</p></div>
                    <x-badge>{{ $event->status }}</x-badge>
                </div>
            @empty
                <p class="py-4 text-sm text-stone-500">Keine Termine vorhanden.</p>
            @endforelse
        </div>
    </x-card>
</x-layouts.app>
