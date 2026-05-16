<x-layouts.app title="{{ $documentImport->title }}">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-3xl font-semibold tracking-tight">{{ $documentImport->title }}</h1><p class="mt-2 text-sm text-stone-600">{{ $documentImport->original_filename }} · {{ $documentImport->file_type }}</p></div>
        <a class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white" href="{{ route('document-imports.review', $documentImport) }}">Vorschläge prüfen</a>
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card><p class="text-sm text-stone-500">Status</p><p class="mt-2 text-xl font-semibold">{{ $documentImport->status }}</p></x-card>
        <x-card><p class="text-sm text-stone-500">Hochgeladen von</p><p class="mt-2 text-xl font-semibold">{{ $documentImport->uploadedBy->name }}</p></x-card>
        <x-card><p class="text-sm text-stone-500">Vorschläge</p><p class="mt-2 text-xl font-semibold">{{ $documentImport->suggestions->count() }}</p></x-card>
    </div>
    <x-card class="mt-6">
        <h2 class="mb-4 text-lg font-semibold">Erkannte Vorschläge</h2>
        <div class="divide-y divide-stone-100">
            @foreach($documentImport->suggestions as $suggestion)
                <div class="py-3"><p class="font-medium">{{ $suggestion->title }}</p><p class="text-sm text-stone-600">{{ $suggestion->starts_at->format('d.m.Y H:i') }} · {{ $suggestion->ownerDisplayName() }} · {{ $suggestion->status }}</p></div>
            @endforeach
        </div>
    </x-card>
</x-layouts.app>
