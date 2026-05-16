@csrf
@isset($child)
    @method('PUT')
@endisset
<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="text-sm font-medium" for="first_name">Vorname</label>
            <input class="mt-1 block w-full rounded-md border-stone-300" id="first_name" name="first_name" value="{{ old('first_name', $child->first_name ?? '') }}" required>
        </div>
        <div>
            <label class="text-sm font-medium" for="last_name">Nachname</label>
            <input class="mt-1 block w-full rounded-md border-stone-300" id="last_name" name="last_name" value="{{ old('last_name', $child->last_name ?? '') }}">
        </div>
    </div>
    <div>
        <label class="text-sm font-medium" for="birthdate">Geburtsdatum</label>
        <input class="mt-1 block w-full rounded-md border-stone-300" id="birthdate" name="birthdate" type="date" value="{{ old('birthdate', isset($child) && $child->birthdate ? $child->birthdate->format('Y-m-d') : '') }}">
    </div>
    <div>
        <label class="text-sm font-medium" for="notes">Notizen</label>
        <textarea class="mt-1 block w-full rounded-md border-stone-300" id="notes" name="notes" rows="4">{{ old('notes', $child->notes ?? '') }}</textarea>
    </div>
    <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white">Speichern</button>
</div>
