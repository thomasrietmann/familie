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
                <label class="text-sm font-medium" for="document">PDF, Word-Dokument oder Bild</label>
                <input class="mt-1 block w-full rounded-md border-stone-300 text-sm" id="document" name="document" type="file" accept=".pdf,.docx,.jpg,.jpeg,.png,.webp,.tif,.tiff" required>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-sm font-medium" for="target_type">Für wen?</label>
                    <select class="mt-1 block w-full rounded-md border-stone-300" id="target_type" name="target_type" required>
                        <option value="family" @selected(old('target_type', 'family') === 'family')>Ganze Familie</option>
                        <option value="user" @selected(old('target_type') === 'user')>Elternteil</option>
                        <option value="child" @selected(old('target_type') === 'child')>Kind</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium" for="target_id">Person</label>
                    <select class="mt-1 block w-full rounded-md border-stone-300" id="target_id" name="target_id">
                        <option value="">Ganze Familie / keine Person</option>
                        <optgroup label="Elternteile">
                            @foreach($family->activeParents as $parent)
                                <option value="{{ $parent->id }}" @selected((string) old('target_id') === (string) $parent->id && old('target_type') === 'user')>{{ $parent->name }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Kinder">
                            @foreach($family->children as $child)
                                <option value="{{ $child->id }}" @selected((string) old('target_id') === (string) $child->id && old('target_type') === 'child')>{{ $child->displayName() }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-sm font-medium" for="notes">Notizen</label>
                <textarea class="mt-1 block w-full rounded-md border-stone-300" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
            </div>
            <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white">Upload und Analyse starten</button>
        </form>
    </div>
</x-layouts.app>
