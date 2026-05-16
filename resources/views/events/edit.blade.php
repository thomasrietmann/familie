<x-layouts.app title="Termin bearbeiten">
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-6 text-3xl font-semibold tracking-tight">Termin bearbeiten</h1>
        <form method="POST" action="{{ route('families.events.update', [$family, $event]) }}" class="rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
            @include('events._form')
        </form>
    </div>
</x-layouts.app>
