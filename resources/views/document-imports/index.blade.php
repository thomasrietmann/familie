<x-layouts.app title="Dokumente">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-3xl font-semibold tracking-tight">Dokumente / Import</h1><p class="mt-2 text-sm text-stone-600">{{ $family->name }}</p></div>
        <a class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white" href="{{ route('families.document-imports.create', $family) }}">Dokument hochladen</a>
    </div>
    <div class="overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm">
        <div class="divide-y divide-stone-100">
            @forelse($documentImports as $documentImport)
                <div class="grid gap-3 p-4 md:grid-cols-6 md:items-center">
                    <div class="md:col-span-2"><a class="font-semibold hover:underline" href="{{ route('families.document-imports.show', [$family, $documentImport]) }}">{{ $documentImport->title }}</a><p class="text-sm text-stone-500">{{ $documentImport->original_filename }}</p></div>
                    <p class="text-sm">{{ $documentImport->file_type }} · {{ $documentImport->targetDisplayName() }}</p>
                    <p class="text-sm">{{ $documentImport->uploadedBy->name }}</p>
                    <p><x-badge>{{ $documentImport->status }}</x-badge></p>
                    <p class="text-sm">{{ $documentImport->suggestions_count }} Vorschläge · {{ $documentImport->created_at->format('d.m.Y') }}</p>
                </div>
            @empty
                <p class="p-6 text-sm text-stone-500">Noch keine Dokumente hochgeladen.</p>
            @endforelse
        </div>
    </div>
    <div class="mt-6">{{ $documentImports->links() }}</div>
</x-layouts.app>
