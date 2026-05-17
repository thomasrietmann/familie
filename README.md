# FamilyManager

FamilyManager ist ein klassisches Laravel-MVC-MVP für eine persönliche Familien-Terminübersicht. Die App nutzt Blade, Tailwind CSS, MySQL und OpenAI für die Dokumentanalyse.

## 1. Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Laravel 13 benötigt PHP 8.3 oder neuer.

Für Metanet kannst du alternativ die Produktionsvorlage verwenden:

```bash
cp .env.metanet.example .env
php artisan key:generate
```

Trage danach die MySQL-Zugangsdaten aus dem Metanet Control Panel in `.env` ein:

```dotenv
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=metanet_db_name
DB_USERNAME=metanet_db_user
DB_PASSWORD=metanet_db_password
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5.4
```

Je nach Metanet-Setup kann `DB_HOST` statt `localhost` ein spezifischer MySQL-Host aus dem Control Panel sein.

## 2. Migration + Seeding

```bash
php artisan migrate:fresh --seed
```

Der Seeder erstellt die Familie Rietmann, zwei Eltern-User, zwei Kinder, Beispieltermine, einen analysierten Dokumentimport und offene Import-Vorschläge.

## 3. Start der App

```bash
php artisan serve
```

Die App nutzt für das MVP statische Assets in `public/css` und `public/js` sowie Tailwind über CDN. Ein Node-/npm-Build ist dadurch für Metanet nicht nötig.

## 4. Test-Login

- E-Mail: `thomas@example.com`
- Passwort: `password`

Neue Accounts können über `/register` erstellt werden. Die Registrierung legt direkt eine Familie an und macht den neuen User zum Owner.

## 5. Familien- und Elternrechte

Ein Login verwaltet genau eine Familie. Jede Familie hat einen Owner. Aktive Eltern sind über `family_users` mit Rolle `owner` oder `parent` berechtigt. Policies schützen Familien, Kinder, Termine, Dokumentimporte und Import-Vorschläge. Ein Eltern-Login kann im MVP nicht mehreren Familien zugeordnet werden.

## 6. Termine

Beim Erfassen eines Termins wird direkt gewählt, für wen der Termin ist: ganze Familie, Elternteil oder Kind. Dokument-Uploads haben dieselbe Auswahl, damit Import-Vorschläge bereits einer Familie oder Einzelperson zugeordnet werden können.

In den Einstellungen kann für jedes Elternteil und jedes Kind eine von 20 festen Farben gewählt werden. Diese Farbe wird vor Terminen im privaten Dashboard, in der Terminübersicht und in den öffentlichen Secret-Link-Ansichten angezeigt. Familientermine erhalten einen Regenbogen-Punkt.

## 7. Dokument-Import Ablauf

1. Familie auswählen
2. PDF, DOCX oder Bild hochladen
3. `DocumentImport` wird gespeichert
4. `DocumentEventExtractionService` analysiert das Dokument
5. `ImportedEventSuggestion`-Einträge werden erstellt
6. Vorschläge prüfen, bearbeiten, übernehmen oder ablehnen
7. Übernommene Vorschläge werden als `FamilyEvent` mit `source = import` gespeichert

## 8. Hinweise zum OpenAI Service

`app/Services/DocumentEventExtractionService.php` nutzt die OpenAI Responses API mit Structured Outputs. PDFs und Bilder werden direkt an OpenAI übergeben und dort gelesen. DOCX-Dateien werden serverseitig ausgelesen und als Text analysiert.

In `.env` muss ein API-Key gesetzt sein:

```dotenv
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5.4
OPENAI_TIMEOUT=60
```

Wenn DOCX-Extraktion oder die OpenAI-Analyse fehlschlägt, erhält der Dokumentimport den Status `failed` und die Fehlermeldung wird im Import gespeichert.

Für einen SDK-basierten TypeScript-Extractor liegt zusätzlich `resources/ai/familyCalendarExtractor.ts` bereit. Er lädt PDF/Bild-Dateien mit dem OpenAI SDK hoch, nutzt die Responses API mit `temperature: 0`, strict Structured Outputs und validiert die Rückgabe nach dem Modellaufruf.

Lokaler Test nach Installation der Node-Abhängigkeiten:

```bash
npm install
npm run extract:events -- /pfad/zum/dokument.pdf
```

## 9. Secret Link Funktion

In der Familienverwaltung kann der Secret Link aktiviert, deaktiviert oder neu generiert werden. Die öffentliche Route lautet:

```text
GET /public/family/{token}
```

Die Ansicht funktioniert ohne Login und zeigt nur Termine von heute und morgen. `parents_only` Termine, Dokumente, Notizen, Importdaten, interne IDs und Bearbeitungsaktionen werden nicht angezeigt.

Zusätzlich kann ein separater Dashboard Secret Link aktiviert werden. Dieser zeigt ohne Login nur öffentliche Termin-Kennzahlen und nächste sichtbare Termine, ebenfalls ohne Dokumente, Notizen, Importdaten oder Admin-Aktionen.

## 10. Storage Link

Für hochgeladene Dokumente:

```bash
php artisan storage:link
```

Uploads werden unter `storage/app/public/document-imports` gespeichert.

## Metanet Deployment Hinweise

- Die App ist standardmässig auf MySQL konfiguriert.
- Setze im Hosting die Document Root auf den Laravel-Ordner `public`.
- Falls die Document Root nicht direkt auf `public` zeigen kann, lege die Laravel-App ausserhalb des Webroots ab und verweise die Domain/Subdomain auf den `public`-Ordner.
- Führe `composer install --no-dev --optimize-autoloader` auf dem Zielsystem oder lokal für das Deployment aus.
- Führe nach dem Upload `php artisan migrate --force` aus.
- Führe `php artisan storage:link` aus oder lege den Symlink `public/storage -> ../storage/app/public` an.
- Setze `APP_ENV=production`, `APP_DEBUG=false` und `APP_URL` auf deine echte Domain.
- Für dieses MVP ist kein `npm install` und kein Vite-Build auf Metanet nötig.

## 11. Nächste mögliche Features

- Kalenderansicht
- Monatsansicht
- iCal Export
- Google Calendar Sync
- Outlook Calendar Sync
- E-Mail Einladungen für Eltern
- Erinnerungen
- Push Notifications
- Wiederkehrende Termine
- Anhänge pro Termin
- Kommentare pro Termin
- feinere Rollen und Berechtigungen
- Queue-basierte Dokumentanalyse
- Optionale externe OCR-Integration
- WhatsApp-kompatible Tagesübersicht
- Public View mit optionalem Zeitraum
- Familien-Checkliste
- Packlisten für Ausflüge
