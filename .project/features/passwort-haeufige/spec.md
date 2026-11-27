# Häufige Passwörter ablehnen (userauth, #2342)

Typ: feature (Task #2342, Bedarf corlog-online.de #2334)
Risiko: normal
Entscheidung: selbst freigegeben (automode, 2026-11-27)

## Ziel
Per Config `userauth.passwort.haeufige_ablehnen` (Standard `false`) lehnt `NeuesPasswortRegeln::regeln()['password']` Passwörter aus einer mitgelieferten Liste häufiger Passwörter ab (NIST SP 800-63B). Gilt überall, wo `NeuesPasswortRegeln` greift: Ändern, Zurücksetzen mit Token, Admin anlegen/ändern, Registrierung. Kein Drittdienst, das Passwort verlässt den Server nicht.

## Akzeptanzkriterien
- **AK1** Gegeben `userauth.passwort.haeufige_ablehnen` = true und `min_laenge` = 12, wenn `POST /password/update` das Passwort „password1234“ bekommt, dann Validierungsfehler im Feld `password` und der Hash bleibt unverändert.
- **AK2** Gegeben `haeufige_ablehnen` = true, wenn das Passwort „Password1234“ oder „ password1234 “ (Randleerzeichen) lautet, dann Validierungsfehler im Feld `password` (Schreibweise und Randleerzeichen zählen nicht).
- **AK3** Gegeben `haeufige_ablehnen` = true und `min_laenge` = 12, wenn das Passwort „blauerhafenkran“ lautet, dann wird es angenommen.
- **AK4** Gegeben `haeufige_ablehnen` = false oder der Schlüssel fehlt, wenn das Passwort „password1234“ lautet, dann wird es angenommen wie bisher (Standard unverändert).
- **AK5** Gegeben `haeufige_ablehnen` = true, wenn „password1234“ beim Zurücksetzen mit Token (`POST /password/updatewithtoken`), bei der Selbstregistrierung oder beim Anlegen bzw. Ändern durch den Admin eingegeben wird, dann Validierungsfehler im Feld `password`.
- **AK6** Gegeben `haeufige_ablehnen` = true, wenn ein häufiges Passwort abgelehnt wird, dann lautet die Meldung in `de` „Dieses Passwort ist zu verbreitet. Bitte wählen Sie ein anderes.“ und in `en` eine englische Entsprechung (Schlüssel `userauth::password.haeufig`); die Meldung enthält nie das eingegebene Passwort.
- **AK7** Gegeben `haeufige_ablehnen` = true, wenn Passwörter geprüft werden, dann findet keine Netzabfrage statt (`Http::fake()` sieht keine Anfrage) und die Liste wird je Prozess nur einmal geladen und als Hash-Set durchsucht (1000 Prüfungen in unter 1 Sekunde).
- **AK8** Gegeben das Paket, wenn die Config gelesen wird, dann enthält `src/Config/config.php` `passwort.haeufige_ablehnen` mit Standard `false` (Env `USERAUTH_PASSWORT_HAEUFIGE_ABLEHNEN`); die README nennt Einstellung, Quelle (SecLists `Pwdb_top-100000.txt`) und Lizenz (MIT), der CHANGELOG hat einen Eintrag `4.5.0`.

## Randfälle
- Ungültiger Wert (kein Bool-Wert, z. B. „abc“): wirkt wie `false`; Strings „true“, „1“, „on“ wirken wie `true` (Env liefert Strings).
- Passwort kein String: die Regel `string` greift, die Listenprüfung überspringt es.
- Liste: Kleinbuchstaben, eindeutig, je Zeile ein Eintrag, Datei `src/Resources/daten/haeufige-passwoerter.txt` (aus SecLists `Pwdb_top-100000.txt`, MIT, bereinigt: getrimmt, klein, ohne Duplikate; bereits im Repo angelegt).
- Fehlt die Datei oder ist unlesbar, lehnt die Regel nichts ab (kein Absturz).

## Nicht-Ziele
- Keine Abfrage bei haveibeenpwned, keine weiteren Passwortregeln (Zeichenklassen), keine Änderung am Login.

## Entscheidungsspielraum
- Regel als eigene Klasse `src/Rules/HaeufigesPasswortRule.php` (Laravel `ValidationRule`), eingehängt in `NeuesPasswortRegeln::regeln()` nur bei aktiver Config. Hash-Set per `static`-Property (`array_flip`). Keine neuen Pakete.
- Release: Minor 4.5.0 vergibt der Moderator beim Merge.

## Rückbau
Rückbau: entfällt (keine Übergangslösung)
