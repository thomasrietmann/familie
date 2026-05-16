<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-3xl font-semibold tracking-tight">Einstellungen</h1>
        <p class="mt-2 text-sm text-stone-600">{{ $family->name }} administrieren.</p>
    </div>
    <a class="rounded-md border border-stone-300 px-4 py-2 text-sm font-medium" href="{{ route('families.edit', $family) }}">Familie bearbeiten</a>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <x-card>
        <h2 class="mb-4 text-lg font-semibold">Familie</h2>
        <div class="space-y-3">
            <div class="rounded-md bg-stone-50 p-3">
                <p class="text-sm text-stone-500">Name</p>
                <p class="font-medium">{{ $family->name }}</p>
            </div>
            <div class="rounded-md bg-stone-50 p-3">
                <p class="text-sm text-stone-500">Notizen</p>
                <p class="text-sm text-stone-700">{{ $family->notes ?: 'Keine Notizen hinterlegt.' }}</p>
            </div>
        </div>
    </x-card>

    <x-card>
        <h2 class="mb-4 text-lg font-semibold">Eltern</h2>
        <div class="space-y-2">
            @foreach($family->activeParents as $parent)
                <div class="rounded-md bg-stone-50 p-3">
                    <p class="font-medium">{{ $parent->name }}</p>
                    <p class="text-xs text-stone-500">{{ $parent->email }} · {{ $parent->pivot->role }}</p>
                </div>
            @endforeach
        </div>
        @can('inviteParent', $family)
            <form method="POST" action="{{ route('families.parents.invite', $family) }}" class="mt-5 space-y-3">
                @csrf
                <input class="block w-full rounded-md border-stone-300 text-sm" name="email" type="email" placeholder="parent@example.com" required>
                <button class="rounded-md bg-stone-900 px-3 py-2 text-sm font-medium text-white">Elternteil einladen</button>
            </form>
        @endcan
    </x-card>

    <x-card>
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold">Kinder</h2>
            <a class="rounded-md bg-stone-900 px-3 py-2 text-sm font-medium text-white" href="{{ route('families.children.create', $family) }}">Kind erfassen</a>
        </div>
        <div class="space-y-2">
            @forelse($family->children as $child)
                <div class="flex flex-col gap-3 rounded-md bg-stone-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium">{{ $child->displayName() }}</p>
                        <p class="text-sm text-stone-600">{{ $child->birthdate ? $child->birthdate->format('d.m.Y') : 'Kein Geburtsdatum' }}</p>
                    </div>
                    <div class="flex gap-2">
                        <a class="rounded-md border border-stone-300 px-3 py-2 text-sm" href="{{ route('families.children.edit', [$family, $child]) }}">Bearbeiten</a>
                        <form method="POST" action="{{ route('families.children.destroy', [$family, $child]) }}">@csrf @method('DELETE')<button class="rounded-md border border-rose-300 px-3 py-2 text-sm text-rose-700">Löschen</button></form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-stone-500">Noch keine Kinder erfasst.</p>
            @endforelse
        </div>
    </x-card>

    <x-card>
        <h2 class="mb-4 text-lg font-semibold">Secret Links</h2>
        <div class="space-y-3">
            <div class="flex items-center justify-between rounded-md bg-stone-50 p-3">
                <div>
                    <p class="font-medium">Heute / Morgen</p>
                    <p class="text-sm text-stone-600">{{ $family->hasPublicToken() ? 'Aktiv' : 'Inaktiv' }}</p>
                </div>
                <x-badge :tone="$family->hasPublicToken() ? 'green' : 'stone'">{{ $family->hasPublicToken() ? 'aktiv' : 'inaktiv' }}</x-badge>
            </div>
            <div class="flex items-center justify-between rounded-md bg-stone-50 p-3">
                <div>
                    <p class="font-medium">Dashboard</p>
                    <p class="text-sm text-stone-600">{{ $family->hasDashboardPublicToken() ? 'Aktiv' : 'Inaktiv' }}</p>
                </div>
                <x-badge :tone="$family->hasDashboardPublicToken() ? 'green' : 'stone'">{{ $family->hasDashboardPublicToken() ? 'aktiv' : 'inaktiv' }}</x-badge>
            </div>
        </div>
        <div class="mt-4">
            <a class="text-sm font-medium hover:underline" href="{{ route('families.public-link.show', $family) }}">Secret Links verwalten</a>
        </div>
    </x-card>
</div>
