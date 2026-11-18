# Passwort-Mindestlänge per Config (userauth, #1593)

Typ: feature (Task #1593)
Risiko: normal
Entscheidung: selbst freigegeben (automode, 2026-11-18)

## Ziel
Die Mindestlänge neuer Passwörter ist über `userauth.passwort.min_laenge` einstellbar (Standard 8). Sie gilt überall, wo `NeuesPasswortRegeln` greift: Ändern, Zurücksetzen mit Token, Anlegen und Ändern durch den Admin, Selbstregistrierung.

## Akzeptanzkriterien
- **AK1** Gegeben `userauth.passwort.min_laenge` = 12, wenn `POST /password/update` ein Passwort mit 11 Zeichen bekommt, dann Validierungsfehler im Feld `password` und der Hash bleibt unverändert; mit 12 Zeichen geht es durch.
- **AK2** Gegeben `userauth.passwort.min_laenge` = 12, wenn `POST /password/updatewithtoken` (Zurücksetzen mit gültigem Token) ein Passwort mit 11 Zeichen bekommt, dann Validierungsfehler im Feld `password`; mit 12 Zeichen geht es durch.
- **AK3** Gegeben kein Config-Wert (Schlüssel fehlt), wenn ein Passwort mit 7 Zeichen gesetzt wird, dann Validierungsfehler; 8 Zeichen gehen durch (Standard 8, bestehende Hosts unverändert).
- **AK4** Gegeben `userauth.passwort.min_laenge` = 12, wenn ein Admin ein Konto anlegt oder das Passwort ändert und die Selbstregistrierung ein Passwort mit 11 Zeichen bekommt, dann Validierungsfehler im Feld `password`.
- **AK5** Gegeben `src/Config/config.php` des Pakets, wenn sie gelesen wird, dann enthält sie `passwort.min_laenge` mit Standard 8 (Env `USERAUTH_PASSWORT_MIN_LAENGE`); README nennt die Einstellung, CHANGELOG hat einen Eintrag `3.5.0`.

## Randfälle
- Ungültiger Wert (nicht ganzzahlig, < 1, leer): es gilt 8.
- Die Meldung nennt die tatsächliche Mindestlänge (Laravel-Standardmeldung `min`).

## Nicht-Ziele
- Keine weiteren Passwortregeln (Zeichenklassen, Wiederverwendung), keine Änderung am Login.

## Entscheidungsspielraum
- Lesen des Werts in `NeuesPasswortRegeln::regeln()` je Aufruf (keine Zwischenspeicherung). Keine neuen Pakete.
- Release: Minor 3.5.0 vergibt der Moderator beim Merge.

## Rückbau
Rückbau: entfällt (keine Übergangslösung)
