<x-layouts.app title="Dokument hochladen">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-3xl font-semibold tracking-tight">Dokument hochladen</h1>
        <form method="POST" action="{{ route('families.document-imports.store', $family) }}" enctype="multipart/form-data" class="space-y-5 rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
            @csrf
            <div>
                <label class="text-sm font-medium" for="title">Titel</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="title" name="title" value="{{ old('title') }}" required>
            </div>
            <div>
                <label class="text-sm font-medium" for="document">PDF oder Word-Dokument</label>
                <input class="mt-1 block w-full rounded-md border-stone-300 text-sm" id="document" name="document" type="file" accept=".pdf,.docx" required>
            </div>
            <div>
                <label class="text-sm font-medium" for="notes">Notizen</label>
                <textarea class="mt-1 block w-full rounded-md border-stone-300" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
            </div>
            <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white">Upload und Analyse starten</button>
        </form>
    </div>
</x-layouts.app>
