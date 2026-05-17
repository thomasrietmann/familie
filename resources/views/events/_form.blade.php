@csrf
@isset($event->id)
    @method('PUT')
@endisset
@php
    $startValue = old('starts_at', $event->starts_at ? $event->starts_at->format('Y-m-d\TH:i') : '');
    $endValue = old('ends_at', $event->ends_at ? $event->ends_at->format('Y-m-d\TH:i') : '');
    $startDate = $startValue ? \Illuminate\Support\Str::before($startValue, 'T') : '';
    $startTime = $startValue && str_contains($startValue, 'T') ? \Illuminate\Support\Str::after($startValue, 'T') : '08:00';
    $endDate = $endValue ? \Illuminate\Support\Str::before($endValue, 'T') : '';
    $endTime = $endValue && str_contains($endValue, 'T') ? \Illuminate\Support\Str::after($endValue, 'T') : '';
    $timeOptions = [];

    for ($hour = 0; $hour < 24; $hour++) {
        foreach ([0, 15, 30, 45] as $minute) {
            $timeOptions[] = sprintf('%02d:%02d', $hour, $minute);
        }
    }
@endphp
<div class="space-y-5">
    <div>
        <label class="text-sm font-medium" for="title">Titel</label>
        <input class="mt-1 block w-full rounded-md border-stone-300" id="title" name="title" value="{{ old('title', $event->title) }}" required>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="text-sm font-medium" for="owner_type">Person</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="owner_type" name="owner_type" required>
                <option value="family" @selected(old('owner_type', $event->owner_type) === 'family')>Ganze Familie</option>
                <option value="user" @selected(old('owner_type', $event->owner_type) === 'user')>Elternteil</option>
                <option value="child" @selected(old('owner_type', $event->owner_type) === 'child')>Kind</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="text-sm font-medium" for="owner_id">Auswahl</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="owner_id" name="owner_id">
                <option value="">Ganze Familie</option>
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

    <div class="space-y-3 rounded-md border border-stone-200 bg-white p-4" data-event-datetime>
        <input id="starts_at" name="starts_at" type="hidden" value="{{ $startValue }}" data-starts-at>
        <input id="ends_at" name="ends_at" type="hidden" value="{{ $endValue }}" data-ends-at>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="text-sm font-medium" for="start_date">Startdatum</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="start_date" type="date" value="{{ $startDate }}" required data-start-date>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium" for="start_time">Startzeit</label>
                <select class="mt-1 block w-full rounded-md border-stone-300" id="start_time" data-start-time>
                    @foreach($timeOptions as $time)
                        <option value="{{ $time }}" @selected($startTime === $time)>{{ $time }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium" for="end_date">Enddatum</label>
                <input class="mt-1 block w-full rounded-md border-stone-300" id="end_date" type="date" value="{{ $endDate }}" data-end-date>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium" for="end_time">Endzeit</label>
                <select class="mt-1 block w-full rounded-md border-stone-300" id="end_time" data-end-time>
                    <option value="">Keine Endzeit</option>
                    @foreach($timeOptions as $time)
                        <option value="{{ $time }}" @selected($endTime === $time)>{{ $time }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-700">
            <input class="rounded border-stone-300" type="checkbox" name="all_day" value="1" @checked(old('all_day', $event->all_day)) data-all-day>
            Ganztägig
        </label>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="text-sm font-medium" for="category">Kategorie</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="category" name="category">
                @foreach(\App\Models\FamilyEvent::CATEGORIES as $category)
                    <option value="{{ $category }}" @selected(old('category', $event->category) === $category)>{{ \App\Models\FamilyEvent::CATEGORY_LABELS[$category] }}</option>
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
