# Prüfung: passkey-bestaetigung (#2225) — 2026-11-26 (Nachprüfung seit ad7b30d)
Stand: Paket userauth `ad7b30d..400f0d7` (Fix `06a2382` Tests, `ee238dc` Code, Merge `400f0d7`), Review-Platz `/home/joerg/projekte/.worktrees/packages_dev/reviewer`. Spec seit `ad7b30d` unverändert; der Fix berührt nur `passkey.js` (2 Zeilen), zwei Sprachdateien (je 1 Zeile `bestaetigung.hinweis`), CHANGELOG und Tests — keine neuen Klassen, keine Migration, daher Nachprüfung statt vollem Review.

Gelaufen (je einmal, alle grün):
- `node --test packages/userauth/tests/js/*.test.mjs` → 79/79 (davon `passkey-bestaetigung.test.mjs` 10/10, echtes `passkey.js` im vm-Kontext mit DOM-Ersatz)
- `php artisan test --compact packages/userauth/tests/Feature/Passkey` → 151 grün (2203 Assertions), enthält alle `PasskeyBestaetigung*Test.php`

| AK / Aussage (Zitat) | Ergebnis | Nachweis |
|---|---|---|
| Randfall 1 „Das ist gewollt als sicherster Standard; die Meldung nennt dann den Weg „Passwort über ‚Passwort vergessen‘ setzen““ | bestanden | Zwei Wege, beide sichtbar: (1) `passkey.js` reicht bei 403 `fehler.serverMeldung` an `bestaetigungOeffnen()` weiter, `dialogMeldung()` setzt `textContent` und `hidden = false`; die 403-Meldung `fehler.bestaetigung_erforderlich` nennt de „setzen Sie es über „Passwort vergessen““, en „set one via “Forgot password””. JS-Test „Randfall1 die Server-Meldung der 403 bleibt im geoeffneten Dialog sichtbar“ prüft Dialog offen, Meldungselement sichtbar, Text genau einmal. (2) Fester Dialogtext `bestaetigung.hinweis` nennt in de „Passwort vergessen“, in en „Forgot password“, gerendert in beiden Themes (`bootstrap.blade.php:72`, `tailwind.blade.php:83`); `PasskeyBestaetigungSeiteTest::test_randfall1_dialog_nennt_den_weg_passwort_vergessen_in_de_und_en` (je Theme, je Sprache, mit Gegenprobe auf die fremde Sprache). Damit bleibt der Weg auch nach einer 422 sichtbar, wenn die Meldung ersetzt wird |
| 422 ersetzt die 403-Meldung (Auftrag) | bestanden | `bestaetigen()` leert die Dialogmeldung und setzt im Fehlerfall die Server-Meldung; JS-Test „Randfall1 Meldung wird bei neuem Fehler ersetzt …“: nach 422 steht „Passwort falsch.“, der alte Hinweis nicht mehr |
| Dialog schließt nach Erfolg (Auftrag) | bestanden | derselbe Test: nach 204 `dialog.hidden === true`, `credentials.create` genau 1-mal |
| AK8 „und wiederholt danach das Hinzufügen einmal“ — keine Schleife | bestanden | Fix ändert nur das dritte Argument von `bestaetigungOeffnen`; Bedingung `!wiederholung` unverändert; JS-Test „AK8 verlangt die Wiederholung wieder eine Bestaetigung gibt es keine Schleife“ grün |
| AK8 „wenn „Passkey hinzufügen“ eine Antwort 403 mit `bestaetigung_erforderlich` erhält, dann zeigt die Seite eine Passwortabfrage (und bei vorhandenem Passkey zusätzlich „Mit Passkey bestätigen“)“ | bestanden | JS-Tests AK8 1–8 grün, `PasskeyBestaetigungSeiteTest` grün (Fix berührt den Ablauf) |
| AK8 „sendet die Bestätigung“ | bestanden | JS-Tests AK8 2 und 3 grün |
| AK8 „ein Fehler (422/429) zeigt die übersetzte Meldung, ohne dass ein Passkey entsteht“ | bestanden | JS-Tests AK8 4–6 grün |
| AK1–AK7, AK9–AK13, Randfall 1 Satz 1, Randfall 2, Randfall 3, Hintergrund, Nicht-Ziele | bestanden (Prüfung `ad7b30d`) | vom Fix nicht berührt; alle Passkey-Feature-Tests erneut grün |

## Sicherheit
Nur der Diff des Fixes: Die Server-Meldung landet per `textContent` im Dialog, kein `innerHTML` — kein Escaping-Problem. Sprachtexte per `@lang` in Blade (escaped). Keine Änderung an Routen, Middleware, Rechten oder Sitzung.

## Regressionen
- `packages/userauth/tests/js/*.test.mjs` (alle 5 Dateien) — 79 grün
- `packages/userauth/tests/Feature/Passkey` (u. a. `PasskeyBestaetigungTest`, `PasskeyBestaetigungSeiteTest`, `PasskeyBestaetigungDokuTest`, `PasskeyBestaetigungLimitTest`, `PasskeyRegistrierenTest`, `PasskeyVerwaltungTest`) — 151 grün
- Ganze Suite nicht (kein `SUITE: ja`). `storage/logs/laravel-2026-11-26.log` im Platz während der Nachprüfung unverändert (11 Zeilen).

## Funde: 0 muss, 0 sollte, 1 kann
- kann: Der feste Dialogtext verweist auf „Passwort vergessen“ „auf der Anmeldeseite“; der angemeldete Benutzer muss sich dafür erst abmelden. Ein Link auf die Passwort-Reset-Seite im Dialog wäre direkter. Die Spec verlangt nur, dass die Meldung den Weg nennt — erfüllt.
- Die drei kann-Funde aus `ad7b30d` (geteilter Drosselzähler, Eingabetaste im Dialog, Passwort-Bestätigung bei `passwort_login = nie`) bleiben unverändert bestehen.

## Neue Tasks: keine

## Fehlerprotokoll
- keine

## Fazit: freigegeben
Das beanstandete muss (Randfall 1, Satz 2) ist behoben und im Skript (DOM-Ersatz) sowie im Markup beider Themes in de/en nachgewiesen; AK8 ohne Regression.
