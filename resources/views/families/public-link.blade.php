<x-layouts.app title="Secret Link">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-3xl font-semibold tracking-tight">Secret Link</h1>
        <p class="mt-2 text-sm text-stone-600">{{ $family->name }} · read-only Ansicht für heute und morgen.</p>

        <x-card class="mt-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-stone-500">Status</p>
                    <p class="mt-1 text-xl font-semibold">{{ $family->hasPublicToken() ? 'aktiv' : 'inaktiv' }}</p>
                </div>
                <x-badge :tone="$family->hasPublicToken() ? 'green' : 'stone'">{{ $family->hasPublicToken() ? 'aktiv' : 'inaktiv' }}</x-badge>
            </div>
            @if($family->hasPublicToken())
                <div class="mt-5 rounded-md bg-stone-50 p-3 text-sm break-all">
                    <a class="font-medium hover:underline" href="{{ $family->publicUrl() }}" target="_blank">{{ $family->publicUrl() }}</a>
                </div>
            @endif
            <div class="mt-5 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('families.public-link.enable', $family) }}">@csrf<button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white">Aktivieren</button></form>
                <form method="POST" action="{{ route('families.public-link.regenerate', $family) }}">@csrf<button class="rounded-md border border-stone-300 px-4 py-2 text-sm font-medium">Neu generieren</button></form>
                <form method="POST" action="{{ route('families.public-link.disable', $family) }}">@csrf<button class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700">Deaktivieren</button></form>
            </div>
        </x-card>
    </div>
</x-layouts.app>
