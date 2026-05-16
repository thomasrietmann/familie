<x-layouts.app title="Kinder">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Kinder</h1>
            <p class="mt-2 text-sm text-stone-600">{{ $family->name }}</p>
        </div>
        <a class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white" href="{{ route('families.children.create', $family) }}">Kind erfassen</a>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($children as $child)
            <x-card>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $child->displayName() }}</h2>
                        <p class="mt-1 text-sm text-stone-600">{{ $child->birthdate ? $child->birthdate->format('d.m.Y') : 'Kein Geburtsdatum' }} · {{ $child->events_count }} Termine</p>
                    </div>
                    <div class="flex gap-2">
                        <a class="rounded-md border border-stone-300 px-3 py-2 text-sm" href="{{ route('families.children.edit', [$family, $child]) }}">Bearbeiten</a>
                        <form method="POST" action="{{ route('families.children.destroy', [$family, $child]) }}">@csrf @method('DELETE')<button class="rounded-md border border-rose-300 px-3 py-2 text-sm text-rose-700">Löschen</button></form>
                    </div>
                </div>
            </x-card>
        @empty
            <x-card><p class="text-sm text-stone-500">Noch keine Kinder erfasst.</p></x-card>
        @endforelse
    </div>
</x-layouts.app>
