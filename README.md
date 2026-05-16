# FamilyManager

FamilyManager ist ein klassisches Laravel-MVC-MVP für eine persönliche Familien-Terminübersicht. Die App nutzt Blade, Tailwind CSS, MySQL und einen austauschbaren Mock-Service für Dokumentanalyse.

## 1. Installation

```bash
composer install
npm install
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
```

Je nach Metanet-Setup kann `DB_HOST` statt `localhost` ein spezifischer MySQL-Host aus dem Control Panel sein.

## 2. Migration + Seeding

```bash
php artisan migrate:fresh --seed
```

Der Seeder erstellt die Familie Rietmann, zwei Eltern-User, zwei Kinder, Beispieltermine, einen analysierten Dokumentimport und offene Import-Vorschläge.

## 3. Start der App

```bash
npm run build
php artisan serve
```

Für aktive Vite-Entwicklung:

```bash
npm run dev
```

## 4. Test-Login

- E-Mail: `thomas@example.com`
- Passwort: `password`

## 5. Familien- und Elternrechte

Jede Familie hat einen Owner. Aktive Eltern sind über `family_users` mit Rolle `owner` oder `parent` berechtigt. Policies schützen Familien, Kinder, Termine, Dokumentimporte und Import-Vorschläge.

## 6. Termin-Zugehörigkeit

Ein Termin gehört immer zu genau einer Ebene:

- `owner_type = family`, `owner_id = null`: ganze Familie
- `owner_type = user`, `owner_id = User-ID`: berechtigtes Elternteil
- `owner_type = child`, `owner_id = Child-ID`: Kind der Familie

Die Form Requests prüfen, dass Eltern Zugriff auf die Familie haben und Kinder zur Familie gehören.

## 7. Dokument-Import Ablauf

1. Familie auswählen
2. PDF oder DOCX hochladen
3. `DocumentImport` wird gespeichert
4. `DocumentEventExtractionService` analysiert das Dokument
5. `ImportedEventSuggestion`-Einträge werden erstellt
6. Vorschläge prüfen, bearbeiten, übernehmen oder ablehnen
7. Übernommene Vorschläge werden als `FamilyEvent` mit `source = import` gespeichert

## 8. Hinweise zum AI Mock-Service

`app/Services/DocumentEventExtractionService.php` erzeugt aktuell sinnvolle Beispieltermine. Die Klasse ist bewusst schmal gehalten, damit später OpenAI, echte PDF-Textextraktion oder DOCX-Textextraktion ergänzt werden können.

## 9. Secret Link Funktion

In der Familienverwaltung kann der Secret Link aktiviert, deaktiviert oder neu generiert werden. Die öffentliche Route lautet:

```text
GET /public/family/{token}
```

Die Ansicht funktioniert ohne Login und zeigt nur Termine von heute und morgen. `parents_only` Termine, Dokumente, Notizen, Importdaten, interne IDs und Bearbeitungsaktionen werden nicht angezeigt.

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
- Baue die Assets lokal mit `npm run build` und deploye den Ordner `public/build`.

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
- Import echter PDF-Textextraktion
- Import echter DOCX-Textextraktion
- OpenAI Integration für Dokumentanalyse
- WhatsApp-kompatible Tagesübersicht
- Public View mit optionalem Zeitraum
- Familien-Checkliste
- Packlisten für Ausflüge
