# Sicherheitsarchitektur

## Grenzen und Schutzmassnahmen

| Grenze | Schutz |
| --- | --- |
| Browser → Anwendung | HTTPS, vertrauenswürdiger Host aus APP_URL, HSTS, CSRF ohne Ausnahmen, CSP ohne Inline-JavaScript, No-Store, No-Referrer und No-Index |
| Nutzer → Familie | Aktive Mitgliedschaft, Policies, verschachtelte Route-Bindings und familienbezogene Personenvalidierung |
| Owner → Freigaben | Owner-Policy, aktuelles Passwort für Freigabe/Erneuerung und Berechtigungserteilung, zusätzliche Rate Limits |
| Unbekannt → Registrierung/Login | Geschlossene Registrierung als Standard, bei Freigabe reCAPTCHA mit Score/Action/Hostname/Alter, Honeypot, Mindestdauer, IP- und Login-Limits mit HMAC-Cache-Schlüsseln |
| Dateiupload → Speicher | MIME-/Grössenprüfung, nur temporärer PHP-Upload, Löschung nach Analyse auch bei Fehlern, keine dauerhafte Dateiablage oder Originaldownloads |
| Anwendung → KI | Upload-Zustimmung, HTTPS, begrenzte Verbindungs-/Antwortzeit, store=false, serverseitige Typ-/Längenprüfung und Personenprüfung vor Übernahme |
| Vorschlag → Termin | Transaktion und Zeilensperre, Zustandsprüfung, Wiederholungen ohne doppelte Termine |
| Anwendung → Protokoll | Feldnamen statt Inhalte, keine IPs/User-Agents/Tokens, Owner-Zugriff auf eigene Familie, konfigurierbare Aufbewahrung |

## Daten und Schlüssel

Originaldateien werden nach der Analyse gelöscht; die Anwendung erzeugt keine dauerhaften Dokumentkopien. Importangaben und validierte Vorschläge bleiben erhalten. Sessiondaten sind mit Laravel Crypt (AES-256-CBC mit Integritätsprüfung) unter Verwendung des APP_KEY verschlüsselt und JSON-serialisiert. Passwörter werden produktiv mit Argon2id gehasht; ältere bcrypt-Hashes werden beim Login migriert. Die eigentlichen Familien-/Termindaten und freigegebene Linktokens liegen weiterhin in der durch Datenbankrechte und Backups zu schützenden Datenbank. Das ist keine Ende-zu-Ende-Verschlüsselung.

APP_KEY und Datenbanksicherungen getrennt schützen. Nicht im normalen Upgrade neu generieren. Schlüsselrotation benötigt einen eigenen getesteten Plan mit APP_PREVIOUS_KEYS für vorhandene Sessions.

Öffentliche Links sind Bearer-Zugangsdaten. Besitzer können die freigegebenen Termine ohne Login lesen. Die Anwendung sendet No-Referrer/No-Store/No-Index; Webserver-Zugriffslogs können URLs dennoch protokollieren. Für diese Pfade Log-Auslassung/-Redaktion im Hosting konfigurieren und Links nicht in fremde Dienste einbetten. Deaktivierung oder Rotation entzieht weiteren Zugriff.

DOCX wird direkt im temporären Upload gelesen; die ZIP-Datei wird auch bei Fehlern geschlossen. Die Entpackung begrenzt XML auf 2 MiB und lehnt DOCTYPE ab. Ein äusserer finally-Block löscht den Upload auch bei Datenbankfehlern; vorzeitig abgelehnte HTTP-Uploads räumt PHP am Request-Ende auf. Das Upload-Temporärverzeichnis darf nicht öffentlich zugänglich oder Teil von Backups sein. Für harte Prozess-/Serverabbrüche ist zusätzlich die Bereinigung temporärer Dateien durch das Hosting erforderlich.

Vorhandene Originaldateien werden beim Upgrade mit documents:purge endgültig gelöscht, ohne Migration; Vorschläge und Termine bleiben erhalten. Der Produktionscheck lehnt verbliebene Originale und alte Dateipfade ab. Kopien in früheren Backups liegen ausserhalb dieser Bereinigung.

## Betrieb und verbleibende Grenzen

- Freigabe erst nach dem Upgrade-Verfahren in README und erfolgreichem security:check. Laravel-Wartungsmodus schützt keine alten statischen Dateien; Apache/nginx separat absichern.
- Ein kompromittiertes Hostingkonto mit APP_KEY und Dateien/Datenbank kann Inhalte lesen. Backups, Betreiberzugang und Endgeräte gehören zum Schutzkonzept.
- Keine Zwei-Faktor-Authentifizierung und keine automatische Passwort-Zurücksetzung implementiert. Elternberechtigung erfordert einen vorhandenen Account ohne andere Familienzuordnung.
- KI-Verarbeitung bleibt synchron. Rate Limits begrenzen Aufrufe, ersetzen aber kein Ausgabenlimit beim Anbieter. store=false ersetzt keine Prüfung von Anbieteraufbewahrung und Vertrag.
- Audit-Einträge sind innerhalb der Anwendung schreibgeschützt, aber für Datenbankadministratoren veränderbar. Logs verwenden keine Ereignisinhalte; technische Ausnahmeprotokolle und frühere Logs müssen ebenfalls geschützt und begrenzt aufbewahrt werden.
- Alltägliche Termindaten werden nicht vollständig feldweise verschlüsselt, damit Datenbankabfragen funktionieren. Sessions sind verschlüsselt; Originalanhänge werden nicht aufbewahrt.
- Die Test-Suite und Dependency-Audits sind technische Nachweise, keine Garantie für Fehlerfreiheit oder ein externes Penetrationstest-Zertifikat.
