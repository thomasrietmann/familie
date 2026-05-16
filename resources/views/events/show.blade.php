<x-layouts.app title="{{ $event->title }}">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $event->title }}</h1>
            <p class="mt-2 text-sm text-stone-600">{{ $event->starts_at->format('d.m.Y H:i') }}{{ $event->ends_at ? ' - '.$event->ends_at->format('d.m.Y H:i') : '' }}</p>
        </div>
        <div class="flex gap-2">
            <a class="rounded-md border border-stone-300 px-4 py-2 text-sm font-medium" href="{{ route('families.events.edit', [$family, $event]) }}">Bearbeiten</a>
            <form method="POST" action="{{ route('families.events.destroy', [$family, $event]) }}">@csrf @method('DELETE')<button class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700">Löschen</button></form>
        </div>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card><h2 class="mb-3 text-lg font-semibold">Übersicht</h2><p class="text-sm text-stone-700">{{ $event->description ?: 'Keine Beschreibung.' }}</p><div class="mt-4 flex gap-2"><x-badge>{{ $event->category }}</x-badge><x-badge>{{ $event->status }}</x-badge><x-badge>{{ $event->visibility }}</x-badge></div></x-card>
        <x-card><h2 class="mb-3 text-lg font-semibold">Zugehörigkeit</h2><p>{{ $event->ownerDisplayName() }}</p><p class="mt-1 text-sm text-stone-500">{{ $event->owner_type }}</p></x-card>
        <x-card><h2 class="mb-3 text-lg font-semibold">Ort</h2><p>{{ $event->location ?: 'Kein Ort hinterlegt.' }}</p></x-card>
        <x-card><h2 class="mb-3 text-lg font-semibold">Notizen</h2><p class="text-sm text-stone-700">{{ $event->notes ?: 'Keine Notizen.' }}</p></x-card>
        <x-card class="lg:col-span-2"><h2 class="mb-3 text-lg font-semibold">Quelle / Import-Dokument</h2><p class="text-sm text-stone-700">{{ $event->source === 'import' ? 'Importiert aus Dokument #'.$event->document_import_id : 'Manuell erfasst' }}</p></x-card>
    </div>
</x-layouts.app>
