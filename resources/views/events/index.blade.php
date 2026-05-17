<x-layouts.app title="Termine">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-3xl font-semibold tracking-tight">Termine</h1><p class="mt-2 text-sm text-stone-600">{{ $family->name }}</p></div>
        <a class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white" href="{{ route('families.events.create', $family) }}">Termin erfassen</a>
    </div>

    <form class="mb-6 grid gap-3 rounded-lg border border-stone-200 bg-white p-4 md:grid-cols-3" method="GET">
        <select class="rounded-md border-stone-300 text-sm" name="category"><option value="">Alle Kategorien</option>@foreach(\App\Models\FamilyEvent::CATEGORIES as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ \App\Models\FamilyEvent::CATEGORY_LABELS[$category] }}</option>@endforeach</select>
        <select class="rounded-md border-stone-300 text-sm" name="status"><option value="">Alle Status</option><option value="planned" @selected(request('status')==='planned')>Geplant</option><option value="confirmed" @selected(request('status')==='confirmed')>Bestätigt</option><option value="cancelled" @selected(request('status')==='cancelled')>Abgesagt</option></select>
        <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white">Filtern</button>
    </form>

    <div class="space-y-3">
        @forelse($events as $event)
            <div class="rounded-lg border border-stone-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2"><x-owner-dot :event="$event" /><a class="font-semibold hover:underline" href="{{ route('families.events.show', [$family, $event]) }}">{{ $event->title }}</a></div>
                        <p class="mt-1 text-sm text-stone-600">{{ $event->starts_at->format('d.m.Y H:i') }} · {{ $event->ownerDisplayName() }} · {{ $event->location ?: 'Kein Ort' }}</p>
                    </div>
                    <div class="flex gap-2"><x-badge>{{ $event->categoryLabel() }}</x-badge><x-badge>{{ $event->visibilityLabel() }}</x-badge></div>
                </div>
            </div>
        @empty
            <x-card><p class="text-sm text-stone-500">Keine Termine gefunden.</p></x-card>
        @endforelse
    </div>
    <div class="mt-6">{{ $events->links() }}</div>
</x-layouts.app>
