# FamilyManager

Laravel-13-Anwendung für Familien, Termine und die Prüfung von Dokumentimporten. Die Oberfläche besteht aus serverseitigen Blade-Seiten, lokal gebautem Tailwind CSS und Vanilla JavaScript. PHP verarbeitet Dokumente synchron über die OpenAI Responses API. Es werden weder Docker noch Redis noch dauerhafte Queue Worker benötigt.

Die ausführliche [Anleitung für Installation, Upgrade und Produktionsprüfung](INSTALLATION.md) enthält die Schritte für METANET, die Löschung vorhandener Originaldokumente und die Abnahme nach dem Deployment.

## Voraussetzungen

- PHP 8.3 oder neuer, bevorzugt 8.4; Argon2id-Unterstützung für Produktion.
- Extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pdo_mysql, session, tokenizer, xml, zip; pdo_sqlite für lokale Tests.
- MySQL oder MariaDB (CI prüft zusätzlich MariaDB 10.6).
- Composer 2; Node.js 24 und npm für den lokalen Build oder CI. Node ist auf dem Webserver nicht erforderlich.
- Document Root ausschliesslich `public/`, HTTPS in Produktion.

## Lokal starten

```sh
composer install
cp .env.example .env
php artisan key:generate
# MySQL-Verbindung und bei Bedarf OPENAI_API_KEY in .env eintragen.
php artisan migrate
npm ci --ignore-scripts
npm run build
php artisan family:create-account
php artisan serve
```

Alternativ `npm run dev` während der Frontend-Entwicklung starten. Im lokalen Modus ist die CSP für Vite/HMR ausgesetzt; Tests und Produktion verwenden die restriktive CSP. `npm run build` erzeugt versionierte Assets unter `public/build/`. Dieser Ordner inklusive Manifest wird in Git versioniert und mit `git pull --ff-only` ausgeliefert. Nach Änderungen an Blade, CSS, JavaScript oder Build-Abhängigkeiten `npm run build` ausführen und `public/build/` zusammen mit den Quellen committen. Bei Änderungen an der Pagination kann auch ein Composer-Update einen neuen Build erfordern. Die CI führt `npm run build:check` aus und lehnt abweichende, fehlende oder zusätzliche Build-Dateien ab. Tailwind durchsucht dafür ausschliesslich die angegebenen Views, JavaScript-Dateien und Laravel-Pagination-Views, damit lokale Logs und andere Arbeitsdateien das Ergebnis nicht verändern. Die bisherigen Dateien unter `public/css` und `public/js` und das Tailwind-CDN werden nicht mehr verwendet.

`php artisan db:seed` erzeugt ausschliesslich lokale Demo-Daten mit Testpasswörtern. Der Seeder verweigert die Ausführung in Produktion. Demo-Daten enthalten einen beispielhaften Dokumentdatensatz ohne Originaldatei und sind keine Produktionsdaten.

## Rechte und Freigaben

- Ein Account gehört höchstens einer Familie an; aktive Eltern dürfen die Familieninhalte bearbeiten.
- Nur der aktive Owner verwaltet Secret Links, Elternberechtigungen und das Änderungsprotokoll oder löscht die Familie.
- Das Aktivieren/Erneuern öffentlicher Links, Hinzufügen eines Elternteils und Löschen der Familie verlangen das aktuelle Passwort. Das Deaktivieren einer Freigabe bleibt ohne zusätzliche Passwortabfrage möglich.
- Die Elternverwaltung berechtigt bereits vorhandene, noch keiner Familie zugeordnete Accounts. Sie versendet keine E-Mail-Einladungen und legt keine Accounts automatisch an.
- Öffentliche Links sind widerrufbare Zugriffsschlüssel. Sie zeigen nur freigegebene Termine, keine Notizen, Beschreibungen, Dokumente oder Importdaten. Jeder mit dem Link kann diese Ansicht lesen.
- Das Änderungsprotokoll enthält Aktion, Zeit, Nutzer-/Datensatz-ID und geänderte Feldnamen. Es speichert keine Inhalte, Passwörter, Tokens, IP-Adressen oder User-Agents.

## Dokumente und KI

Originaldateien werden nicht dauerhaft abgelegt. Die Analyse liest direkt den temporären PHP-Upload; ein `finally`-Block löscht ihn nach Verarbeitung auch bei KI- und Datenbankfehlern. DOCX wird direkt aus diesem Upload gelesen, ohne zusätzliche Dateikopie. Es gibt keinen Originaldownload. Importangaben, validierte Vorschläge und übernommene Termine bleiben in der Datenbank. Die Oberfläche erklärt diese Aufbewahrung vor dem Upload.

Die Analyse braucht die ausdrückliche Auswahl im Uploadformular. PDFs und Bilder sowie Zuordnungsnamen werden an OpenAI gesendet; DOCX wird lokal begrenzt entpackt und als Text gesendet. Die API wird mit `store=false` aufgerufen. Dies ersetzt keine Prüfung der beim Anbieter geltenden Datenverarbeitung. KI-Rohantworten werden weder gespeichert noch protokolliert; validierte Vorschläge bleiben zur Prüfung in der Datenbank. Fehler enthalten keine Providerantworten oder Schlüssel. Übernahmen sind transaktional und gegen Wiederholung geschützt.

Die Analyse läuft weiterhin synchron. `OPENAI_TIMEOUT` ist auf maximal 90 Sekunden begrenzt. PHP-/Proxy-Zeitlimits müssen die Analyse zulassen. Uploads sind auf 10 MiB begrenzt; `upload_max_filesize=10M`, `post_max_size=12M` und ausreichend `memory_limit` (empfohlen 256M) einstellen. Externe API-Aufrufe sind in Tests simuliert. Das optionale lokale CLI (`npm run extract:events -- /pfad/dokument.pdf`) sendet PDF-/Bilddaten ebenfalls direkt mit `store=false`, ohne eine Datei beim Anbieter anzulegen oder Rohantworten zu protokollieren. Die lokal ausgewählte Quelldatei ist kein Serverupload und wird vom CLI nicht gelöscht.

## Produktionskonfiguration / METANET

`.env.metanet.example` ist die Vorlage für eine **neue** Installation. Bestehende `.env`-Dateien ergänzen und ihren `APP_KEY` unbedingt behalten.

Wesentliche Werte:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://deine-domain.ch
FORCE_HTTPS=true
SESSION_DRIVER=file
SESSION_ENCRYPT=true
SESSION_EXPIRE_ON_CLOSE=true
SESSION_LIFETIME=60
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
HASH_DRIVER=argon2id
HASH_VERIFY=false
REGISTRATION_ENABLED=false
LOG_LEVEL=warning
```

`HASH_VERIFY=false` ermöglicht den Übergang vorhandener bcrypt-Passwörter zu Argon2id; beim nächsten erfolgreichen Login wird neu gehasht. Es deaktiviert nicht die Passwortprüfung. Der Produktionscheck verlangt Argon2id-Unterstützung. Bestehende Sitzungen werden durch die Umstellung ungültig.

Die öffentliche Registrierung ist produktiv standardmässig geschlossen. Accounts lassen sich interaktiv über `php artisan family:create-account` anlegen. Für offene Registrierung zusätzlich `REGISTRATION_ENABLED=true`, `RECAPTCHA_ENABLED=true`, `RECAPTCHA_SITE_KEY` und `RECAPTCHA_SECRET_KEY` setzen und die Domain bei Google freigeben. Fehlende/ungültige Prüfungen verhindern die Registrierung. Rate Limits und Honeypot gelten zusätzlich.

PHP-Prozess benötigt Schreibrechte nur auf `storage/` und `bootstrap/cache/`; keine pauschalen 777-Rechte. Quellcode, `.env`, Backups und private Dateien gehören ausserhalb des Document Root. Bei Plesk prüfen, dass direkte statische Auslieferung über nginx die Apache-Sperre alter Dokumentpfade nicht umgeht. Falls nginx statische Dateien direkt bedient, vor dem Upgrade dort ebenfalls sperren:

```nginx
location ^~ /storage/document-imports/ { return 403; }
```

Ohne angepasste nginx-Konfiguration die direkte statische Auslieferung dieses Pfades abschalten. Die mitgelieferte `public/.htaccess` sperrt ihn für Apache. Nach `documents:purge` sind vorhandene Originaldateien entfernt.

## Bestehende Installation sicher aktualisieren

1. Datenbank, `.env` und insbesondere den bestehenden `APP_KEY` sichern. Originaldateien werden nicht übernommen und gehören nicht in neue Sicherungen. Wartungsfenster einplanen. **Kein** `key:generate`, `migrate:fresh` oder Produktions-Seeding ausführen.
2. Alten öffentlichen Dokumentpfad auch auf Webserver-/nginx-Ebene sperren (siehe oben) und `php artisan down` ausführen. Laravel-Wartung allein sperrt keine statischen Dateien.
3. Den geprüften Stand mit `git pull --ff-only` holen; Code, Lockfiles und `public/build/` kommen gemeinsam aus Git. Bei Dateiübertragung den vollständigen Stand inklusive `public/build/` übertragen. Den laufenden Webroot auf `public/` belassen. Kein `public/hot` ausliefern.
4. Die bestehende `.env` um die Produktionswerte ergänzen, `APP_KEY` behalten; PHP-Extensions und CLI-/Web-PHP-Version abgleichen.
5. Im App-Verzeichnis ausführen:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan migrate --force
php artisan documents:purge --dry-run
php artisan documents:purge
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan security:check
```

`documents:purge` löscht alle Originaldateien unter `document-imports/` auf den bisherigen öffentlichen und privaten Dokumentdatenträgern, einschliesslich verwaister Uploads. Es findet keine Dokumentmigration statt. Der Befehl setzt alte Dateipfade und KI-Rohantworten in der Datenbank auf `NULL`; Importangaben, Vorschläge und Termine bleiben erhalten. Er ist wiederholbar, toleriert bereits fehlende Originale und meldet Löschfehler. `--dry-run` zählt nur die Dateien. Alte Originale in externen Backups und frühere Inhaltslogs müssen zusätzlich nach der geltenden Aufbewahrungsregel entfernt werden. Die neue Datenbankmigration macht lediglich die nicht mehr benötigten Dateipfade optional.

6. Nur bei erfolgreichen Prüfungen `php artisan up` ausführen. Danach Login, Kalender, Upload mit Testdokument inklusive Löschung des temporären Uploads und anschliessender Vorschlagsübernahme, Secret-Link-Widerruf und direkte Ablehnung einer alten `/storage/document-imports/...`-URL prüfen. Ein Mitglied einer anderen Familie muss am Zugriff scheitern.

`security:check` prüft Produktionswerte, Extensions, Argon2id, Build, Datenbank/Audit-Tabelle, öffentliche/private Originaldateireste, dauerhafte Dateipfade und gespeicherte KI-Rohantworten. Webserver-/Proxy-Konfiguration und echte Anbieterzugänge müssen zusätzlich auf dem Zielsystem geprüft werden. Der Befehl sendet keine Dokumente an externe Dienste.

Das Löschen der Originale ist endgültig. Ein Code-Rollback stellt sie nicht wieder her; alten Code mit dauerhafter Dokumentablage nicht erneut freigeben. `APP_KEY` getrennt und sicher sichern, damit verschlüsselte Sitzungen weiter lesbar bleiben. PHP-Upload-Temporärverzeichnis ausserhalb des Webroots belassen und aus Backups ausschliessen; die Hosting-Bereinigung muss auch Reste nach einem harten Prozess-/Serverabbruch entfernen.

## Tests und laufender Betrieb

```sh
npm ci --ignore-scripts
npm run build
npm run typecheck
npm test
composer install
vendor/bin/phpunit
vendor/bin/pint --test
composer audit
npm audit
```

Die Tests prüfen Authentifizierung, CSRF, Rate Limits, Familienisolation, Mass Assignment, HTML-Escaping, Freigaben, Header, Upload-Löschung bei Erfolg und Fehlern, Bereinigung alter Originale, KI-Fehlerfälle, Audit und transaktionale Importübernahme. Sie verwenden isolierte Datenbanken und simulierte externe Dienste. Die GitHub-Actions-Pipeline führt Tests mit PHP 8.3 und 8.4 sowie SQLite und MariaDB 10.6 aus und prüft, dass ein frischer Frontend-Build exakt dem eingecheckten Stand entspricht. Sie deployt nicht automatisch.

Für Aufbewahrung regelmässig `php artisan audit:prune` ausführen (z. B. täglich per Plesk-Aufgabe); Standard 180 Tage, konfigurierbar mit `AUDIT_RETENTION_DAYS`, mindestens 30 Tage. Datenbank und Schlüssel sichern und Wiederherstellung testen. Der Audit-Log ist kein gegen Datenbankadministratoren manipulationssicheres Archiv. Weitere Grenzen stehen in [SECURITY.md](SECURITY.md).
