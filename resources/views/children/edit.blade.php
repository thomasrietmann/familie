<x-layouts.app title="Kind bearbeiten">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-3xl font-semibold tracking-tight">Kind bearbeiten</h1>
        <form method="POST" action="{{ route('families.children.update', [$family, $child]) }}" class="rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
            @include('children._form', ['child' => $child])
        </form>
    </div>
</x-layouts.app>
