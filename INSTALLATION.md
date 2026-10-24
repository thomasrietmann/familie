# FamilyManager: Installation und Upgrade auf METANET

Diese Anleitung beschreibt das manuelle Deployment. Ein GitHub-Push startet die CI-Prüfungen, aber kein Produktionsdeployment. Alle Serverbefehle im Verzeichnis mit `artisan` ausführen. Bei einem Fehler anhalten; `php artisan up` erst nach erfolgreichen Prüfungen ausführen.

## 1. Hosting und Release vorbereiten

- PHP 8.4 empfohlen, mindestens 8.3, mit Argon2id. CLI und Webserver müssen dieselbe geeignete PHP-Version verwenden. In Plesk den passenden PHP-Interpreter auswählen; gegebenenfalls in den Befehlen `php` durch dessen absoluten Pfad ersetzen.
- PHP-Erweiterungen: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pdo_mysql, session, tokenizer, xml und zip.
- MySQL/MariaDB-Datenbank und einen eigenen Datenbankbenutzer bereitstellen. Composer 2 wird für die Serverinstallation benötigt.
- HTTPS aktivieren. Der Document Root der Domain muss auf das Unterverzeichnis `public/` zeigen. `.env`, `vendor/`, `storage/` und Sicherungen dürfen nicht direkt öffentlich erreichbar sein.
- Der PHP-Benutzer braucht Schreibrechte auf `storage/` und `bootstrap/cache/`. Keine pauschalen 777-Rechte vergeben.
- PHP: `upload_max_filesize=10M`, `post_max_size=12M`, empfohlen `memory_limit=256M`. PHP-/Proxy-Zeitlimits müssen die synchrone KI-Analyse erlauben; `OPENAI_TIMEOUT` ist maximal 90 Sekunden.
- Das PHP-Upload-Temporärverzeichnis muss privat sein, von Backups ausgeschlossen werden und eine Hosting-Bereinigung für liegengebliebene Dateien nach Prozessabbrüchen haben.

Der Frontend-Build ist im Repository enthalten. Auf dem Server genügt für eine neue Installation:

```sh
git clone git@github.com:thomasrietmann/familie.git
cd familie
```

Für ein bereits vorhandenes, sauberes Checkout `git pull --ff-only` verwenden. Den verwendeten Commit mit `git rev-parse HEAD` festhalten. Code, Manifest und CSS-/JavaScript-Dateien werden gemeinsam geliefert; ein separater Build-Upload ist nicht mehr erforderlich. Node.js und npm werden auf dem Server nicht gebraucht.

Bei einer Installation per SFTP den vollständigen Repository-Stand einschliesslich `public/build/` übertragen. `public/build/manifest.json` und seine referenzierten Dateien müssen vorhanden sein. `node_modules/`, lokale `.env`, Testsitzungen, Logs und `public/hot` nicht hochladen.

Beim ersten Wechsel auf eingecheckte Builds können bereits manuell hochgeladene Dateien einen Git-Pull blockieren. Nur wenn Git eine solche Kollision unter `public/build/` meldet: den bisherigen Build-Ordner im Wartungsmodus ausserhalb des App-Verzeichnisses sichern/verschieben und `git pull --ff-only` erneut ausführen. Nicht mit einem pauschalen `git reset --hard` oder `git clean` lokale Konfiguration und Nutzerdaten entfernen.

### Frontend-Änderungen entwickeln

Nur auf dem Entwicklungsrechner oder in CI, mit Node.js 24 und installierten Composer-Abhängigkeiten:

```sh
composer install
npm ci --ignore-scripts
npm run build
git add resources public/build
```

Weitere geänderte Dateien wie `package-lock.json` oder `vite.config.js` ebenfalls zum Commit hinzufügen. Quellen und generierten Build gemeinsam committen, danach `npm run build:check` ausführen. Dieser Befehl baut erneut und prüft geänderte, gelöschte sowie neue Dateien unter `public/build/`. Die CI führt dieselbe Prüfung aus und blockiert einen veralteten Build. Auch geänderte Laravel-Pagination-Views nach einem Composer-Update können einen neuen Build erfordern.

## Unterschiedliche Pfade in SSH und Web-PHP

Bei getrennten SSH-/Web-Pfadansichten sieht SSH die Anwendung beispielsweise unter `/familie.git`, Web-PHP dagegen unter `/home/httpd/vhosts/example.ch/familie.git`. Deshalb hier **keinen Konfigurationscache über SSH erzeugen**. `config:cache` speichert unter anderem absolute Pfade für Logs, Sessions, Datei-Cache, Views und Dateispeicher. Ein Cache aus der SSH-Umgebung kann im Webprozess unbrauchbar sein, obwohl `security:check` in der Shell erfolgreich ist.

Diese Anleitung verwendet deshalb `config:clear` und `view:clear`. Web-PHP ermittelt die Pfade selbst und kompiliert benötigte Views beim Aufruf. `route:cache` kann weiter verwendet werden. Auch `php artisan optimize` vermeiden, da es wieder einen Konfigurationscache erzeugt. Konfigurationscaching erst verwenden, wenn SSH und Web-PHP nachweislich dieselben absoluten Anwendungspfade sehen und ein Browser-Test erfolgreich war.

Falls bereits HTTP 500 mit einem falschen Logpfad auftritt, im App-Verzeichnis ausführen:

```sh
php artisan config:clear
php artisan view:clear
php artisan security:check
```

Danach die Seite neu laden und die neuesten Webserverfehler prüfen. Keine Verzeichnisse am falschen absoluten Pfad anlegen und keine pauschalen 777-Rechte setzen. Falls der falsche Pfad weiterhin auftaucht, Hosting-Umgebungsvariablen wie `APP_BASE_PATH`, `LARAVEL_STORAGE_PATH` und `VIEW_COMPILED_PATH` sowie den PHP-Prozess-/OPcache-Neustart im Hosting prüfen. Der Logfehler kann eine weitere ursprüngliche Ausnahme verdecken; nach seiner Behebung ist gegebenenfalls die neue Fehlermeldung erforderlich.

## 2. Neuinstallation

Nur bei einer neuen Installation mit leerer Datenbank:

```sh
cp .env.metanet.example .env
```

Vor dem Fortfahren `.env` auf dem Server bearbeiten:

| Wert | Einstellung |
| --- | --- |
| `APP_URL` | Tatsächliche HTTPS-URL ohne Pfad, z. B. `https://familie.example.ch` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Daten des Hosting-Datenbankzugangs |
| `OPENAI_API_KEY` | Schlüssel für die Dokumentanalyse, nur in der Server-`.env` speichern |
| `OPENAI_MODEL` | Im verwendeten API-Projekt verfügbares Modell für die Dokumentanalyse |
| `APP_ENV`, `APP_DEBUG`, `FORCE_HTTPS` | `production`, `false`, `true` |
| `REGISTRATION_ENABLED` | `false`; der erste Account wird per CLI erstellt |

Die übrigen Sicherheitswerte aus der Vorlage übernehmen. Insbesondere verschlüsselte Datei-Sessions, sichere Cookies, Datei-Cache und Argon2id beibehalten. Bei geschlossener Registrierung werden keine reCAPTCHA-Schlüssel benötigt. Für öffentliche Registrierung siehe [README](README.md#produktionskonfiguration--metanet).

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan migrate --force
php artisan family:create-account
php artisan config:clear
php artisan route:cache
php artisan view:clear
php artisan security:check
```

`family:create-account` fragt Name, E-Mail, Familienname und Passwort interaktiv ab und erstellt eine Familie mit Owner. Kein Produktions-Seeding ausführen. Den erzeugten `APP_KEY` zusammen mit der Konfiguration sicher verwahren, getrennt von Datenbanksicherungen. Anschliessend die Abnahme aus Abschnitt 4 durchführen.

## 3. Bestehende Installation aktualisieren

Familien, Accounts, Kalendertermine und Importvorschläge bleiben erhalten. Originaldateien werden endgültig gelöscht und **nicht migriert**. Die Datenbankmigration ergänzt das Audit-Protokoll und erlaubt leere Dokumentpfade.

### Vor dem Austausch des Codes

1. Verwendeten bisherigen Commit und PHP-Version dokumentieren. Datenbank über das Hosting sichern; bestehende `.env`, `APP_KEY` und bisherigen Code ausserhalb des Webroots sichern. Keine neue Sicherung der Originaldateien anlegen. Frühere Backups können noch Originale oder KI-Rohantworten enthalten; diese separat bereinigen.
2. Alten statischen Pfad `/storage/document-imports/` auf Webserver-Ebene sperren. Laravel-Wartungsmodus allein schützt statische Dateien nicht. Die neue `.htaccess` sperrt den Pfad für Apache. Wenn nginx statische Dateien direkt ausliefert, in der Hostingkonfiguration ergänzen:

   ```nginx
   location ^~ /storage/document-imports/ { return 403; }
   ```

   Falls diese Konfiguration nicht verfügbar ist, die direkte statische Auslieferung dieses Pfades im Hosting abschalten lassen, bevor die Anwendung aktualisiert wird.
3. Mit dem bisherigen Code Wartungsmodus aktivieren:

   ```sh
   php artisan down
   ```

4. Bei einem sauberen Server-Git-Checkout `git pull --ff-only` ausführen. Das aktualisiert den Code samt Lockfiles und dem passenden `public/build/` gemeinsam. Bei SFTP stattdessen den vollständigen Repository-Stand übertragen. Bestehende `.env` und `storage/` erhalten. Ein eventuell vorhandenes `public/hot` entfernen. Alte `public/css/app.css` und `public/js/app.js` werden nicht mehr verwendet und können entfernt werden.
5. Bestehende `.env` anhand von `.env.metanet.example` ergänzen. **Den bestehenden `APP_KEY` behalten; weder `key:generate` noch `migrate:fresh` ausführen.** `APP_URL` muss die echte Domain enthalten.

Besonders prüfen:

```dotenv
APP_ENV=production
APP_DEBUG=false
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

`HASH_VERIFY=false` ermöglicht die Prüfung bestehender bcrypt-Hashes und deren Umstellung beim nächsten erfolgreichen Login. Es schaltet die Passwortprüfung nicht ab. Bestehende Sitzungen werden ungültig; die Nutzer müssen sich neu anmelden.

### Migration, Löschung und Freigabe

Jeden Befehl einzeln ausführen und dessen Erfolg prüfen:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan migrate --force
php artisan documents:purge --dry-run
php artisan documents:purge
php artisan config:clear
php artisan route:cache
php artisan view:clear
php artisan security:check
```

`--dry-run` zählt vorhandene Originale. Ohne diese Option löscht `documents:purge` alle Dateien unter `document-imports/` auf den bisherigen öffentlichen und privaten Dokumentdatenträgern, auch verwaiste Dateien. Alte Dateipfade und KI-Rohantworten werden aus den Importdatensätzen entfernt. Importangaben, Vorschläge und Termine bleiben bestehen. Fehlende Originaldateien sind kein Fehler. Bei einem Löschfehler Rechte/Speicherzugriff korrigieren und den Befehl erneut ausführen; er ist wiederholbar.

Nur wenn `security:check` ausschliesslich `OK` meldet:

```sh
php artisan up
```

## 4. Abnahme nach dem Deployment

Mit einem eigenen Testaccount und einer Testfamilie prüfen. Keine privaten Dokumente für Funktionstests verwenden.

| Prüfung | Erwartetes Ergebnis |
| --- | --- |
| HTTP-Adresse öffnen | Weiterleitung auf die konfigurierte HTTPS-Adresse |
| Login mit vorhandenem Account | Anmeldung funktioniert; Kalender und vorhandene Termine werden angezeigt |
| Darstellung auf Desktop und Smartphone | CSS und JavaScript laden; Navigation und Kalender sind nutzbar |
| Fremde Familien-URL ohne Berechtigung | Zugriff abgelehnt |
| Öffentlicher Link | Nur freigegebene Termine; nach Widerruf kein Zugriff mehr |
| Alte Original-URL `/storage/document-imports/...` | HTTP 403 oder 404, niemals Dokumentinhalt |
| Registrierung bei `REGISTRATION_ENABLED=false` | Keine öffentliche Accountanlage möglich |

### Upload und Dokumentlöschung nachweisen

1. Ein synthetisches PDF mit einem eindeutigen zukünftigen Termin erstellen, etwa «Testtermin, 15. Januar 2027, 10:00–11:00». In die Testfamilie hochladen und der Analyse zustimmen. Dies führt einen echten, gegebenenfalls kostenpflichtigen API-Aufruf aus.
2. Die Analyse muss zur Vorschlagsprüfung führen. Den Vorschlag prüfen und einmal übernehmen: genau ein Termin soll entstehen. Eine wiederholte Übernahme darf keinen zweiten Termin erzeugen.
3. Die Importansicht muss die Löschung erklären und darf keinen Originaldownload anbieten.
4. Auf dem Server erneut ausführen:

   ```sh
   php artisan documents:purge --dry-run
   php artisan security:check
   ```

   Erwartet: **0 Originaldateien** und alle Prüfungen `OK`. Insbesondere dürfen weder öffentliche/private Originale noch dauerhafte Dokumentpfade oder KI-Rohantworten vorhanden sein. Der Dry-Run löscht nichts und verdeckt dadurch keine fehlerhafte Aufbewahrung.
5. Für einen zusätzlichen Nachweis der PHP-Temporärdatei den tatsächlichen `upload_tmp_dir` des Web-PHP über das Hosting prüfen. Mit Hosting-Zugriff die zum synthetischen Upload gehörende temporäre Datei während der Verarbeitung beobachten; nach Request-Ende darf sie nicht mehr vorhanden sein. `security:check` kontrolliert die App-Speicher, **nicht** das systemweite PHP-Temporärverzeichnis.
6. Den Testtermin und die Testfamilie anschliessend über die Oberfläche entfernen.

Die automatisierten Tests prüfen zusätzlich KI-Fehler, fehlgeschlagenes Anlegen des Importdatensatzes, Fehler beim Speichern der Vorschläge und beim Speichern des Fehlerstatus. In allen Fällen wird der temporäre Upload gelöscht. Diese Tests mit einer isolierten Testdatenbank ausführen, niemals mit der Produktionsdatenbank. Einen absichtlichen Anbieter-/Datenbankausfall nur auf Staging simulieren; dafür keine globalen Produktionszugänge verändern.

Die Löschung betrifft lokale Serveroriginale. `store=false` verhindert keine unabhängig davon geltenden Aufbewahrungsregeln beim KI-Anbieter. Der FamilyManager bietet keine Wiederherstellung gelöschter Originale; für eine erneute Analyse muss das Dokument nochmals hochgeladen werden.

## 5. Fehlerbehebung und laufender Betrieb

| Problem | Vorgehen |
| --- | --- |
| Frontend-Build fehlt oder wurde lokal verändert | Im Wartungsmodus lokale Build-Abweichungen sichern und `git restore --source=HEAD --staged --worktree -- public/build` ausführen; das stellt die versionierten Build-Dateien des aktuellen Commits wieder her |
| CLI-Check grün, Browser meldet Fehler | Zuerst unterschiedliche SSH-/Web-Pfade und einen aus SSH erzeugten Konfigurationscache prüfen (siehe oben); danach Web-PHP-Version, Extensions, Schreibrechte, Domain und Proxy-Einstellungen vergleichen |
| Login nach Upgrade scheitert | Cookies entfernen und neu anmelden; `APP_KEY`, HTTPS-Domain, Sessionrechte und Argon2id prüfen |
| Analyse fehlgeschlagen | API-Zugang, Modellfreigabe, Zeitlimits und zip-Erweiterung prüfen; Original erneut hochladen |
| Alte Dokumentreste gemeldet | Statische Sperre beibehalten, `documents:purge` nach Behebung der Speicherfehler wiederholen |
| Migration oder Sicherheitscheck schlägt fehl | Wartungsmodus beibehalten und Ursache beheben; keine Freigabe mit Debug-Modus |

Ein Code-Rollback stellt gelöschte Originale nicht wieder her. Bevorzugt den Fehler im Wartungsmodus beheben. Eine Wiederherstellung der Datenbank benötigt den passenden gesicherten Code-/Datenstand; sie darf alte Dokumentpfade oder Rohantworten nicht erneut öffentlich freigeben. Nach jeder Wiederherstellung erneut bereinigen und prüfen.

Täglich eine Hosting-Aufgabe für `php artisan audit:prune` im App-Verzeichnis einrichten; Standardaufbewahrung 180 Tage. Datenbank und Schlüssel sicher sichern und die Wiederherstellung testen. Keine Originale oder Upload-Temporärdateien in neue Backups aufnehmen.

Weitere Details: [README](README.md), [Sicherheitsarchitektur](SECURITY.md).
