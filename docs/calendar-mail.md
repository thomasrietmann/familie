# E-Mail-Termine: IMAP und SMTP

## Bereitstellung

Die Funktion ist im Code vorbereitet und standardmässig deaktiviert. Das Repository richtet weder das reale Postfach noch DNS oder Hosting-Cron automatisch ein. Benötigt werden ein dediziertes IMAP-Postfach `familie@aufbollen.ch`, SMTP für Bestätigungscodes und der bestehende OpenAI-Zugang. Die tatsächlichen Servernamen und Zugangsdaten aus dem METANET-/Mailprovider-Kontrollpanel verwenden. Keine Passwörter, Mailtexte, Codes oder produktiven Rohheader in Tickets, Screenshots oder Git übernehmen.

1. Quellcode und den gebauten Ordner `public/build` ausliefern. Im üblichen Wartungsfenster `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`, `php artisan migrate --force`, `php artisan config:clear`, `php artisan route:cache` und `php artisan view:clear` ausführen. Bestehenden `APP_KEY` behalten. Die drei neuen Tabellen speichern Absenderfreigaben, knappe Zustellnachweise und Terminidentitäten. Die Pakete `webklex/php-imap`, `zbateson/mail-mime-parser` und `sabre/vobject` übernehmen IMAP, MIME und iCalendar; die PHP-IMAP-Erweiterung ist nicht erforderlich.
2. Die untenstehenden Werte in die bestehende `.env` ergänzen. Das bestehende OpenAI-Modell wird wiederverwendet. Zunächst `CALENDAR_MAIL_ENABLED=false` lassen.
3. Die unten beschriebene Mailauthentifizierung mit dem Provider klären und anhand synthetischer Testmails prüfen. Erst danach die vertrauenswürdigen Serverkennungen konfigurieren und den Import aktivieren.
4. SMTP testen: Im angemeldeten Account **Einstellungen → E-Mail-Termine** öffnen, eigene Absenderadresse mit aktuellem Passwort und Zustimmung hinzufügen und den zugestellten Code eingeben. Bestätigungscodes gelten 30 Minuten, sind in der Datenbank nur als HMAC gespeichert und nach fünf Fehlversuchen gesperrt. Der Versand ist auf fünf Vorgänge pro Account und Stunde begrenzt. Der `log`-Mailer wird für Codes ausdrücklich abgewiesen.
5. `CALENDAR_MAIL_ENABLED=true` setzen, `php artisan config:clear` und `php artisan security:check` ausführen. Dann `php artisan calendar-mail:poll` mit einer synthetischen Einladung testen. Erst nach der Abnahme den Cronjob aktivieren.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=SERVERNAME_AUS_DEM_PROVIDER_PANEL
MAIL_PORT=465
MAIL_USERNAME=familie@aufbollen.ch
MAIL_PASSWORD="HIER_LOKAL_EINTRAGEN"
MAIL_FROM_ADDRESS=familie@aufbollen.ch
MAIL_FROM_NAME=FamilyManager

CALENDAR_MAIL_ENABLED=false
CALENDAR_MAIL_ADDRESS=familie@aufbollen.ch
CALENDAR_MAIL_HOST=SERVERNAME_AUS_DEM_PROVIDER_PANEL
CALENDAR_MAIL_PORT=993
CALENDAR_MAIL_USERNAME=familie@aufbollen.ch
CALENDAR_MAIL_PASSWORD="HIER_LOKAL_EINTRAGEN"
CALENDAR_MAIL_FOLDER=INBOX
CALENDAR_MAIL_TRUSTED_AUTHSERV_IDS=
```

IMAP verwendet ausschliesslich implizites TLS mit Zertifikatsprüfung. SMTP verwendet ebenfalls Zertifikatsprüfung und verlangt TLS; für einen Provider mit Port 587 `MAIL_SCHEME=smtp` und `MAIL_PORT=587` setzen (STARTTLS bleibt verpflichtend). Keine Zertifikatsprüfungen deaktivieren. SMTP-Zugang auf die benötigte Versandadresse beschränken und IMAP möglichst auf das dedizierte Postfach. Die Anwendung braucht keine Rechte zum Löschen, Verschieben oder Setzen von Gelesen-Markierungen. MAIL_FROM muss beim Provider für den Versand autorisiert sein; dessen dokumentierte SPF-/DKIM-Konfiguration und DMARC einrichten, statt DNS-Werte zu erraten.

## Vertrauenswürdige Absenderprüfung

Die Mailserver-Prüfung kann ausdrücklich mit `CALENDAR_MAIL_REQUIRE_AUTHENTICATION=false` in der bestehenden `.env` deaktiviert werden. Danach `php artisan config:clear` ausführen. In diesem Modus darf `CALENDAR_MAIL_TRUSTED_AUTHSERV_IDS` leer bleiben. Persönliche Absenderadressen müssen weiterhin per Code bestätigt sein und einem aktiven Familienaccount gehören. Die From-Adresse einer eingehenden Mail ist dann jedoch nicht authentifiziert und kann gefälscht werden. `security:check` zeigt deshalb einen Hinweis. Standardmässig bleibt die Prüfung aktiviert (`true`); die folgenden Anforderungen gelten für diesen Standardmodus.

Ein sichtbares `From` und die einmalige Adressbestätigung allein verhindern keine gefälschten Nachrichten. Der Import verlangt deshalb **DMARC=pass** mit genau der Domain des einzigen äusseren From-Absenders aus einem vertrauenswürdigen `Authentication-Results`-Header. SPF allein, fremde Authentication-Results, mehrere From-Adressen sowie widersprüchliche/doppelte lokale Authentifizierungsergebnisse werden abgewiesen. DMARC authentifiziert die Domain; die Durchsetzung zulässiger Absender innerhalb dieser Domain liegt zusätzlich beim sendenden Provider.

Beim empfangenden Provider ausdrücklich bestätigen lassen:

- Der Server prüft eingehende Nachrichten auf DMARC und setzt `Authentication-Results` mit `dmarc=pass` und `header.from=...`.
- Er entfernt vorab von aussen eingeschleuste Header, die eine seiner eigenen Authentifizierungskennungen behaupten. Auch lokale Einlieferungen und alle vorgeschalteten Weiterleitungswege müssen dieselbe Vertrauensgrenze einhalten.
- Welche exakten `authserv-id`-Kennungen dieser Infrastruktur verwendet werden. Diese als kommagetrennte Liste in `CALENDAR_MAIL_TRUSTED_AUTHSERV_IDS` eintragen; nicht aus beliebigen eingehenden Nachrichten ungeprüft übernehmen.

Die Liste bleibt ohne diese Bestätigung leer. Der Import verweigert dann den Betrieb. Bei einem Provider ohne entsprechend bereinigte, vertrauenswürdige Authentifizierungsergebnisse muss zuerst eine geeignete empfangende Mailinfrastruktur bereitgestellt werden. Ein IMAP-Login oder TLS allein beweist die Absenderauthentizität nicht. Hintergrund ist die Vertrauensgrenze aus [RFC 8601](https://www.rfc-editor.org/rfc/rfc8601.html#section-7.1).

Kein Catch-all aktivieren, keine automatische Antwort an unbekannte/abgewiesene Absender und keine automatische Weiterleitungsschleife einrichten. Spam- und Grössenfilter beim empfangenden Provider ebenfalls aktivieren. Weitergeleitete Einladungen funktionieren, wenn die äussere, bestätigte From-Adresse die DMARC-Prüfung besteht; aus zitiertem Text, Reply-To oder der ICS-Organisatoradresse wird nie ein Account abgeleitet.

## Cron und Fehlerbehandlung

Im Hosting einen Scheduler-Aufruf pro Minute mit dem richtigen PHP-Interpreter und dem absoluten App-Pfad anlegen:

```cron
* * * * * cd /ABSOLUTER/APP/PFAD && /PFAD/ZU/php artisan schedule:run
```

Der Scheduler ruft den Import alle fünf Minuten und die Aufbewahrungsbereinigung täglich auf. Alternativ direkt alle fünf Minuten `calendar-mail:poll` und täglich `calendar-mail:prune` planen; nicht beide Varianten gleichzeitig einrichten. Keine dauerhaften Queue Worker oder Redis erforderlich. Der persistente Datei-/Datenbank-Cache muss funktionieren. Scheduler und direkter Aufruf sind gegen parallele Läufe gesperrt; bei mehreren App-Servern müssen sie einen gemeinsamen Lock-Speicher verwenden.

Ein Lauf verarbeitet höchstens zehn Nachrichten aus den letzten 30 Tagen (IMAP-Zustelldatum). Er liest Nachrichten auch dann, wenn sie bereits als gelesen markiert wurden. Nachrichten dürfen vor erfolgreichem Import nicht aus dem konfigurierten Ordner entfernt werden. Die technische Zustellidentität kombiniert Postfach, UIDVALIDITY und UID; der Kalenderschlüssel verhindert erneute Termine auch nach einer UIDVALIDITY-Änderung. Ein geplanter Ausfall von mehr als 30 Tagen erfordert eine bewusste Nachlieferung der betroffenen Einladungen.

Bei OpenAI-/Verbindungs-/Datenbankfehlern bleibt die Nachricht im Postfach. Die Anwendung versucht höchstens fünfmal mit wachsendem Abstand erneut (5, 25, 125, danach maximal 360 Minuten). Vorübergehende Fehler liefern Exit-Code 1 und eine inhaltsfreie Warnung mit Nachweis-ID. Nach dem letzten Fehlversuch muss die Einladung erneut gesendet werden. Eine erfolgreiche OpenAI-Analyse ohne eindeutige Person erzeugt dagegen sofort einen Familientermin. Authentifizierungsfehler, unbekannte Absender, nicht unterstützte Formate und zu grosse Mails werden nicht automatisch erneut versucht; erst Ursache beheben und erneut senden. Der Cron-/Hostingstatus sollte auf Fehler überwacht werden.

Unter **Meine letzten Eingänge** sieht jeder Benutzer seine letzten 20 erfolgreich authentifizierten Eingänge und deren Ergebnis, ohne vollständige Mailtexte. Administratoren können die `result_code`-Zähler in `calendar_mail_receipts` prüfen; die App-Logs enthalten keine Absender, Betreffe, Zugangsdaten oder Providerantworten. Unbekannte oder nicht authentifizierte Absender erzeugen keinen zugeordneten Benutzereintrag.

## Kalenderumfang und Datenschutz

- Unterstützt: `text/calendar`, .ics als `application/ics`/`application/icalendar` oder `application/octet-stream`, MIME-Base64 und Quoted-Printable, gefaltete iCalendar-Zeilen, UTC, bekannte IANA-/Outlook-Windows-Zeitzonen, ganztägige Termine mit exklusivem Enddatum sowie REQUEST/PUBLISH/CANCEL. Floating Times werden als Europe/Zurich interpretiert. Externe Anhänge, Bilder, URLs und Alarme werden nicht geladen oder ausgeführt. Maximal 2 MiB pro Mail und 256 KiB pro Kalenderdatei; genau ein Kalender mit einer Termin-UID pro Nachricht.
- Endliche Serien: maximal 366 Instanzen mit DAILY/WEEKLY/MONTHLY/YEARLY, COUNT oder UNTIL (UNTIL höchstens fünf Jahre nach Beginn), EXDATE und einzelnen RECURRENCE-ID-Ausnahmen. Der Parser erlaubt eine begrenzte Menge sicher expandierbarer Kombinationen: täglich ohne Zusatzfilter oder mit Wochentagen bei INTERVAL=1, wöchentlich mit Wochentagen, monatlich/jährlich mit Monatstagen ±1–28 oder Wochentagen/Positionen ±1–4; jährliche Zusatzfilter erfordern BYMONTH. Einfache Serien ohne solche Zusatzfilter sind ebenfalls möglich. RDATE, EXRULE, unbegrenzte Serien, THISANDFUTURE, nicht unterstützte Regelkombinationen und TNEF/winmail.dat werden ausdrücklich abgewiesen. Es wird nie still nur die erste Instanz einer nicht unterstützten Serie importiert. In Outlook dafür einzelne iCalendar-Einladungen oder eine unterstützte endliche Serie senden.
- Identität: Familie + gehashte UID/Organisator + Wiederholungsinstanz. Ein bereits importierter Termin kann per Mail nur vom ursprünglich zugeordneten Account über eine aktuell bestätigte Absenderadresse geändert werden. SEQUENCE hat Vorrang vor DTSTAMP; ältere oder identische Revisionen ändern nichts. Absagen vor der Einladung werden als technischer Lösch-/Absagenachweis behalten. Benutzerseitig gelöschte Termine werden durch spätere Einladungen nicht wiederhergestellt. Bei einer neuen Einladung mit neuer UID entsteht ein neuer Termin.
- OpenAI erhält einmal pro Einladung nur begrenzten Klartext aus Betreff, Nachricht, Titel/Beschreibung und die bekannten Personennamen/-IDs dieser Familie. Zeiten, Orte und Absenderadressen werden nicht als eigene Analysefelder übertragen; im Freitext enthaltene Angaben können dennoch an OpenAI gelangen. Die Analyse ist strikt strukturiert und die Rückgabe wird erneut gegen die Familienmitglieder geprüft. Nachrichtenanweisungen berechtigen weder zu Aktionen noch zu Zugriffen auf andere Familien. Die [Responses API und Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs) werden mit `store=false` eingesetzt. Dies ist keine Zusage, dass beim Anbieter keinerlei Datenaufbewahrung stattfindet; dessen Datenverarbeitung muss zum Einsatz passen.
- Termine werden automatisch angelegt, zunächst mit **Nur Eltern**. Der Benutzer kann Zuordnung, Sichtbarkeit, Kategorie und Notizen bearbeiten. Neue Einladungsrevisionen aktualisieren Titel, Beschreibung, Zeit, Ort, Status und Personenzuordnung; Sichtbarkeit, Kategorie und Notizen bleiben erhalten. Serientermine werden als einzelne Kalendereinträge dargestellt, nicht als in der Oberfläche editierbare Wiederholungsregel.
- Die App legt keine Originalmails, Anhänge oder KI-Rohantworten dauerhaft ab. Das Postfach selbst behält die Originalmails: dort eine getrennte Aufbewahrungsregel einrichten (beispielsweise 30 Tage, passend zum Abruffenster und zur Fehlerbehandlung); die App löscht sie nicht. Zustellnachweise werden nach 90 Tagen, unbestätigte Adressen nach einem Tag ohne Änderung gelöscht. Bestätigte Adressen bleiben bis zum Widerruf/Account-/Familienlöschen, importierte Termine und technische Kalenderidentitäten bis zur jeweiligen Löschung bzw. zum Löschen der Familie. Kalenderidentitäten bleiben auch nach Terminlöschung als Schutz gegen erneuten Import erhalten. Backups müssen dieselbe Datenlebensdauer berücksichtigen.

## Abnahme nach Einrichtung

1. Eigene Testadresse bestätigen; mit einem anderen Account dürfen weder ihre Bestätigung noch ihr Widerruf möglich sein.
2. Outlook-Einladung mit bekanntem Familiennamen senden: genau ein Termin, richtige Schweizer Ortszeit, richtige Person und Sichtbarkeit Nur Eltern. Ohne eindeutige Person muss die Zuordnung Ganze Familie entstehen.
3. Dieselbe Einladung nochmals zustellen, anschliessend Zeit ändern und absagen: ein Termin, jeweils neuester Stand. Ältere Revision darf eine Absage nicht rückgängig machen. Eine endliche Serie mit Ausnahme und ganztägigem Termin prüfen.
4. Nachricht von unbestätigter Adresse und einen gemeinsam mit dem Provider erzeugten negativen DMARC-/Header-Manipulationstest senden: kein Termin, keine OpenAI-Anfrage. Danach Absenderadresse widerrufen und den Import erneut negativ testen.
5. SMTP-/IMAP-/OpenAI-Ausfall mit synthetischen Daten prüfen: nachvollziehbarer Status, erneuter Versuch, kein doppelter Termin. IMAP-Nachrichten dürfen weder gelöscht noch als gelesen markiert werden.

Automatisierte Tests verwenden simulierte OpenAI-/Maildienste und eine lokale Testdatenbank. Sie ersetzen die Abnahme der realen Provider-Header, TLS-Verbindung, SMTP-Zustellung und Cron-Ausführung nicht.
