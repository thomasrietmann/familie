<x-layouts.app title="Familien">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-semibold tracking-tight">Familien</h1>
        <a class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white" href="{{ route('families.create') }}">Neu</a>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($families as $family)
            <x-card>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <a class="text-lg font-semibold hover:underline" href="{{ route('families.show', $family) }}">{{ $family->name }}</a>
                        <p class="mt-1 text-sm text-stone-600">{{ $family->children_count }} Kinder · {{ $family->events_count }} Termine · {{ $family->document_imports_count }} Dokumente</p>
                    </div>
                    <x-badge :tone="$family->hasPublicToken() ? 'green' : 'stone'">{{ $family->hasPublicToken() ? 'Link aktiv' : 'Link aus' }}</x-badge>
                </div>
            </x-card>
        @empty
            <x-card><p class="text-sm text-stone-500">Noch keine Familie vorhanden.</p></x-card>
        @endforelse
    </div>
</x-layouts.app>
