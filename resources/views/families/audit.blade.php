<x-layouts.app title="Änderungsprotokoll">
    <h1 class="mb-6 text-3xl font-semibold">Änderungsprotokoll</h1>
    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th>Zeit</th><th>Aktion</th><th>Bereich</th><th>Nutzer-ID</th><th>Geänderte Felder</th></tr></thead>
                <tbody>@foreach($entries as $entry)
                    <tr class="border-t border-stone-200"><td class="py-3">{{ $entry->created_at->format('d.m.Y H:i:s') }}</td><td>{{ $entry->action }}</td><td>{{ $entry->entity_type }} #{{ $entry->entity_id }}</td><td>{{ $entry->user_id ?? '–' }}</td><td>{{ implode(', ', $entry->changed_fields ?? []) }}</td></tr>
                @endforeach</tbody>
            </table>
        </div>
        <div class="mt-4">{{ $entries->links() }}</div>
    </x-card>
</x-layouts.app>
