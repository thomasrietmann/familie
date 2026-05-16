<x-layouts.app title="Einstellungen">
    @if($family)
        @include('families._show', ['family' => $family->loadMissing(['children', 'activeParents', 'events' => fn ($query) => $query->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(8)])])
    @else
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Familie erstellen</h1>
                <p class="mt-2 text-sm text-stone-600">Dieser Login verwaltet genau eine Familie.</p>
            </div>
            <a class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white" href="{{ route('families.create') }}">Erstellen</a>
        </div>
        <x-card><p class="text-sm text-stone-500">Noch keine Familie vorhanden.</p></x-card>
    @endif
</x-layouts.app>
