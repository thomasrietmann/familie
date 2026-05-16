@csrf
@isset($family)
    @method('PUT')
@endisset
<div class="space-y-5">
    <div>
        <label class="text-sm font-medium" for="name">Name</label>
        <input class="mt-1 block w-full rounded-md border-stone-300" id="name" name="name" value="{{ old('name', $family->name ?? '') }}" required>
    </div>
    <div>
        <label class="text-sm font-medium" for="notes">Notizen</label>
        <textarea class="mt-1 block w-full rounded-md border-stone-300" id="notes" name="notes" rows="4">{{ old('notes', $family->notes ?? '') }}</textarea>
    </div>
    <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700" type="submit">Speichern</button>
</div>
