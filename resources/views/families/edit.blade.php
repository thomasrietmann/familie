<x-layouts.app title="Familie bearbeiten">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-3xl font-semibold tracking-tight">Familie bearbeiten</h1>
        <form method="POST" action="{{ route('families.update', $family) }}" class="rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
            @include('families._form', ['family' => $family])
        </form>
    </div>
</x-layouts.app>
