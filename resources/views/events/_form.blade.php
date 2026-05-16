@csrf
@isset($event->id)
    @method('PUT')
@endisset
<div class="space-y-5">
    <div>
        <label class="text-sm font-medium" for="title">Titel</label>
        <input class="mt-1 block w-full rounded-md border-stone-300" id="title" name="title" value="{{ old('title', $event->title) }}" required>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="text-sm font-medium" for="owner_type">Zugehörigkeit</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="owner_type" name="owner_type" required>
                <option value="family" @selected(old('owner_type', $event->owner_type) === 'family')>Ganze Familie</option>
                <option value="user" @selected(old('owner_type', $event->owner_type) === 'user')>Elternteil</option>
                <option value="child" @selected(old('owner_type', $event->owner_type) === 'child')>Kind</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="text-sm font-medium" for="owner_id">Person</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="owner_id" name="owner_id">
                <option value="">Ganze Familie / keine Person</option>
                <optgroup label="Elternteile">
                    @foreach($family->activeParents as $parent)
                        <option value="{{ $parent->id }}" @selected((string) old('owner_id', $event->owner_id) === (string) $parent->id && old('owner_type', $event->owner_type) === 'user')>{{ $parent->name }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Kinder">
                    @foreach($family->children as $child)
                        <option value="{{ $child->id }}" @selected((string) old('owner_id', $event->owner_id) === (string) $child->id && old('owner_type', $event->owner_type) === 'child')>{{ $child->displayName() }}</option>
                    @endforeach
                </optgroup>
            </select>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm font-medium" for="starts_at">Startdatum / Startzeit</label>
            <input class="mt-1 block w-full rounded-md border-stone-300" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $event->starts_at ? $event->starts_at->format('Y-m-d\TH:i') : '') }}" required>
        </div>
        <div>
            <label class="text-sm font-medium" for="ends_at">Enddatum / Endzeit</label>
            <input class="mt-1 block w-full rounded-md border-stone-300" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $event->ends_at ? $event->ends_at->format('Y-m-d\TH:i') : '') }}">
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-stone-700"><input class="rounded border-stone-300" type="checkbox" name="all_day" value="1" @checked(old('all_day', $event->all_day))> Ganztägig</label>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="text-sm font-medium" for="category">Kategorie</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="category" name="category">
                @foreach(\App\Models\FamilyEvent::CATEGORIES as $category)
                    <option value="{{ $category }}" @selected(old('category', $event->category) === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium" for="visibility">Sichtbarkeit</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="visibility" name="visibility">
                <option value="family" @selected(old('visibility', $event->visibility) === 'family')>Familie</option>
                <option value="parents_only" @selected(old('visibility', $event->visibility) === 'parents_only')>Nur Eltern</option>
            </select>
        </div>
        <div>
            <label class="text-sm font-medium" for="status">Status</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="status" name="status">
                <option value="planned" @selected(old('status', $event->status) === 'planned')>Geplant</option>
                <option value="confirmed" @selected(old('status', $event->status) === 'confirmed')>Bestätigt</option>
                <option value="cancelled" @selected(old('status', $event->status) === 'cancelled')>Abgesagt</option>
            </select>
        </div>
    </div>

    <div>
        <label class="text-sm font-medium" for="location">Ort</label>
        <input class="mt-1 block w-full rounded-md border-stone-300" id="location" name="location" value="{{ old('location', $event->location) }}">
    </div>
    <div>
        <label class="text-sm font-medium" for="description">Beschreibung</label>
        <textarea class="mt-1 block w-full rounded-md border-stone-300" id="description" name="description" rows="3">{{ old('description', $event->description) }}</textarea>
    </div>
    <div>
        <label class="text-sm font-medium" for="notes">Notizen</label>
        <textarea class="mt-1 block w-full rounded-md border-stone-300" id="notes" name="notes" rows="3">{{ old('notes', $event->notes) }}</textarea>
    </div>
    <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white">Speichern</button>
</div>
