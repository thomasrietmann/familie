<x-layouts.app title="E-Mail-Termine">
    <div class="mb-6">
        <a class="text-sm underline" href="{{ route('families.show', $family) }}">Zurück zu den Einstellungen</a>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">E-Mail-Termine</h1>
        <p class="mt-2 text-stone-600">Outlook-Einladungen für {{ $family->name }} an <strong>{{ config('calendar_mail.address') }}</strong> senden.</p>
    </div>
    @unless(config('calendar_mail.enabled'))
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">Der Postfachabruf ist noch nicht aktiviert. Bestätigte Absenderadressen sind nach der Einrichtung verwendbar.</div>
    @endunless
    @unless(config('calendar_mail.require_authentication', true))
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">Die Mailserver-Prüfung ist deaktiviert. Es werden bestätigte Absenderadressen verwendet, die Absenderangabe eingehender Nachrichten kann jedoch gefälscht sein.</div>
    @endunless
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card>
            <h2 class="text-lg font-semibold">Meine Absenderadressen</h2>
            <p class="mt-2 text-sm text-stone-600">Auch deine Login-Adresse muss hier bestätigt werden. Eine Adresse kann nur einem Account zugeordnet sein. Entfernen beendet den Import von dieser Adresse sofort.</p>
            <div class="mt-4 space-y-4">
                @forelse($addresses as $address)
                    <div class="rounded-md border border-stone-200 p-4">
                        <p class="break-all font-medium">{{ $address->email }}</p>
                        <p class="mt-1 text-sm text-stone-600">{{ $address->verified_at ? 'Bestätigt und aktiv' : 'Bestätigung ausstehend' }}</p>
                        @unless($address->verified_at)
                            <form method="POST" action="{{ route('calendar-mail.verify', $address) }}" class="mt-3 flex flex-wrap gap-2">
                                @csrf
                                <label class="sr-only" for="code-{{ $address->id }}">Bestätigungscode für {{ $address->email }}</label>
                                <input id="code-{{ $address->id }}" class="rounded-md border-stone-300 text-sm" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{8}" maxlength="8" placeholder="8-stelliger Code" required>
                                <button class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white">Bestätigen</button>
                            </form>
                            <form method="POST" action="{{ route('calendar-mail.resend', $address) }}" class="mt-3">
                                @csrf
                                <button class="text-sm underline">Neuen Code senden</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('calendar-mail.destroy', $address) }}" class="mt-3">
                            @csrf @method('DELETE')
                            <button class="text-sm text-rose-700 underline">Adresse entfernen</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Noch keine Absenderadressen hinterlegt.</p>
                @endforelse
            </div>
        </x-card>
        <x-card>
            <h2 class="text-lg font-semibold">Absenderadresse hinzufügen</h2>
            <form method="POST" action="{{ route('calendar-mail.store') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium" for="sender-email">E-Mail-Adresse</label>
                    <input id="sender-email" class="mt-1 block w-full rounded-md border-stone-300" type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="sender-password">Aktuelles Passwort</label>
                    <input id="sender-password" class="mt-1 block w-full rounded-md border-stone-300" type="password" name="current_password" autocomplete="current-password" required>
                </div>
                <label class="flex items-start gap-2 text-sm text-stone-700">
                    <input class="mt-1 rounded border-stone-300" type="checkbox" name="consent" value="1" required>
                    <span>Ich erlaube den automatischen Import von dieser Adresse. Betreff, Nachrichtentext, Terminbeschreibung und Namen meiner Familienmitglieder werden für die Personenzuordnung an OpenAI übermittelt.</span>
                </label>
                <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white">Bestätigungscode senden</button>
            </form>
        </x-card>
        <x-card class="lg:col-span-2">
            <h2 class="text-lg font-semibold">So funktioniert der Import</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-stone-600">
                <li>Sende eine Einladung oder eine Nachricht mit genau einer .ics-Kalenderdatei. Dein Account wird über die bestätigte Absenderadresse oder, bei Versand durch einen Kalenderdienst, über die bestätigte „Antwort an“-Adresse erkannt. Unterschiedliche Accounts in diesen beiden Feldern werden abgewiesen.</li>
                <li>OpenAI sucht eine eindeutige Person in deiner Familie. Ohne eindeutige Zuordnung entsteht ein Familientermin. Du kannst die Zuordnung danach bearbeiten.</li>
                <li>Neue Termine sind zunächst „Nur Eltern“ sichtbar. Die Freigabe für Familienansichten kannst du am Termin ändern.</li>
                <li>Aktualisierungen und Absagen derselben Einladung werden übernommen. Manuell gelöschte Termine bleiben gelöscht. Weitere Einladungen können Titel, Zeit und Personenzuordnung aktualisieren.</li>
                <li>Übliche endliche Serien mit bis zu 366 Terminen werden unterstützt. Unbegrenzte oder komplexe Serien sowie Änderungen „dieser und aller folgenden Termine“ werden abgewiesen. Sende dafür einzelne .ics-Einladungen; Outlook-Anhänge im Format winmail.dat werden nicht unterstützt.</li>
                <li>Die Anwendung speichert keine Originalmails, Anhänge oder KI-Rohantworten. Importierte Termine und technische Importnachweise bleiben gespeichert; die Aufbewahrung im Postfach wird separat verwaltet. OpenAI wird mit deaktivierter Antwortspeicherung aufgerufen.</li>
            </ul>
        </x-card>
        <x-card class="lg:col-span-2">
            <h2 class="text-lg font-semibold">Meine letzten Eingänge</h2>
            <p class="mt-2 text-sm text-stone-600">Hier erscheinen Nachrichten deiner bestätigten Absenderadressen. Unbekannte Absender werden abgewiesen. Bei aktivierter Mailserver-Prüfung muss zusätzlich die Authentifizierung erfolgreich sein.</p>
            <div class="mt-4 space-y-2">
                @forelse($receipts as $receipt)
                    <div class="flex flex-wrap justify-between gap-2 rounded-md bg-stone-50 p-3 text-sm">
                        <span>{{ $receipt->created_at->format('d.m.Y H:i') }}</span>
                        <span>{{ $receipt->resultLabel() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Noch keine zugeordneten Nachrichten empfangen.</p>
                @endforelse
            </div>
        </x-card>
    </div>
</x-layouts.app>
