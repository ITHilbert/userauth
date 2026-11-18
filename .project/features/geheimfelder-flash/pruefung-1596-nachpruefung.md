# Prüfung: geheimfelder-flash – Bugfix #1596 — 2026-11-18 (Nachprüfung seit 6e9330c)
Maßstab: Task-Text #1596 (keine Spec), vorherige Prüfung `pruefung-1596.md` (nicht freigegeben, 1 muss). Diff des Fixes: userauth 3d64e77..6e9330c (Test f237ad3: `tests/Feature/GeheimfelderFehlerwegeTest.php` +4 Tests; Fix 6e9330c: `src/Http/PasswortAblehnung.php` +`Geheimfelder::entfernen(request())` vor dem Wurf). Host: 2c1d47f...50e3845 (nur Submodul-Stand). Kein volles Review nötig: keine Spec, Fix ändert 2 Zeilen in einer bestehenden Klasse, keine neue Klasse, keine Migration.

Sonde: `/tmp/claude-1000/-home-joerg-projekte-packages-dev/ca734ce6-8f7f-4cde-a8d0-972aedbf2732/scratchpad/sonde/SondeGeheimfelderTest.php` (Handler-Dekorierer ohne `dontFlash()`), unverändert gegenüber der ersten Prüfung, gelaufen im Platz mit `php artisan test <sonde>`: 7/7 grün, Ausgabe je Fall `_old_input`.

| Aussage (Zitat Task) | Ergebnis | Nachweis |
|---|---|---|
| 1. „Schutz der Felder `aktuelles_passwort`, `pwtoken`, `two_factor_code` auch dann, wenn der gebundene ExceptionHandler kein `dontFlash()` hat“ – Validierungsfehler der drei FormRequests | bestanden (Prüfung 6e9330c) | Sonde erneut grün: falsches aktuelles Passwort → `_old_input={"harmlos":"sichtbar"}` |
| dto. – Controller-Fehlerweg ungültiger Reset-Token | bestanden | Sonde: `_old_input={"email":"max@firma.de"}`; neuer Test `test_ungueltiger_token_flasht_pwtoken_bei_dekoriertem_handler_nicht` grün, mit Gegenprobe `email` |
| dto. – `request()` (Kernel-Request) erfasst, auch bei Query und JSON | bestanden (Prüfung 6e9330c) | Sonde erneut grün: Query `pwtoken`/`two_factor_code` und JSON-Body nicht in `_old_input` |
| dto. – Fehlerweg „Passwort bereits verwendet“ (`PasswortAblehnung::alsValidierungsfehler`, nach bestandener Validierung) | bestanden | Fix `src/Http/PasswortAblehnung.php:30`: `Geheimfelder::entfernen(request())` im `catch (PasswortBereitsVerwendetException)` unmittelbar vor `throw`. Sonde (vorher rot mit `aktuelles_passwort` bzw. gültigem `pwtoken` in `_old_input`): Passwort ändern → `_old_input={"harmlos":"sichtbar"}`; Reset mit gültigem Token → `_old_input={"email":"max@firma.de"}`; beide prüfen die Meldung `bereits_verwendet`, also den richtigen Weg. Neue Tests `test_wiederverwendetes_passwort_flasht_aktuelles_passwort_bei_dekoriertem_handler_nicht`, `test_wiederverwendetes_passwort_flasht_pwtoken_bei_dekoriertem_handler_nicht` grün, mit Gegenprobe (`harmlos` bzw. `email` geflasht). Der Handler flasht über den Kernel-Request (`request()`), die Sonde belegt, dass das Entfernen dort genügt |
| dto. – `ServiceProvider` nutzt `Geheimfelder::FELDER` für `dontFlash` | bestanden (Prüfung 6e9330c) | – |
| 2. „Tests der Fehlerwege falsches aktuelles Passwort und ungültiger Reset-Token“ | bestanden | Vorher „teilweise“ (nur mit echtem Handler). Jetzt zusätzlich `test_falsches_aktuelles_passwort_flasht_es_bei_dekoriertem_handler_nicht` und `test_ungueltiger_token_flasht_pwtoken_bei_dekoriertem_handler_nicht` mit Dekorierer (`dekoriererIstGebunden()`), je mit Gegenprobe; grün |
| 3. „Tote View auth/passwords/reset.blade.php: bewusst unverändert“ | bestanden (Prüfung 6e9330c) | Fix-Diff berührt die View nicht |
| Prüfauftrag: „Entfernt das Entfernen der Felder vor dem Wurf irgendwo Daten, die danach noch gebraucht werden?“ – neue Stelle `PasswortAblehnung` | bestanden | Entfernt wird nur im `catch`, direkt vor `throw`; danach läuft kein Controller-Code mehr. `PasswortAblehnung` wird auch in `UserController.php:121` und `:267` (Benutzerverwaltung) genutzt: dort entfernt der Aufruf nur nicht vorhandene bzw. nicht mehr gebrauchte Felder vor dem Wurf; `AdminPasswortTest`, `GeloeschteEmailTest`, `PasswordHistoryRuleTest` grün |

## Sicherheit
- Fix-Diff (2 Zeilen) schließt die beanstandete Lücke; kein neuer Endpunkt, keine Rechteänderung, kein Mass-Assignment, keine Ausgabeänderung.
- `Geheimfelder::entfernen` bekommt hier nur `request()`, nicht zusätzlich die FormRequest-Kopie (anders als in `PasswordController.php:100`). Für das Flashen reicht das (Handler nutzt den Kernel-Request, Sonde grün); nach dem Wurf liest niemand die Kopie.

## Regressionen
Gelaufen im Platz in einem Lauf (`php artisan test --compact <dateien>`), 318 Tests, alle grün:
- `packages/userauth/tests/Feature/GeheimfelderFehlerwegeTest.php`
- `packages/userauth/tests/Feature/GeheimfelderNichtGeflashtTest.php`
- `packages/userauth/tests/Feature/AdminPasswortTest.php`
- `packages/userauth/tests/Feature/BenutzerbildTest.php`
- `packages/userauth/tests/Feature/GeloeschteEmailTest.php`
- `packages/userauth/tests/Feature/Haertung/PasswortResetTest.php`
- `packages/userauth/tests/Feature/PasswordHistoryRuleTest.php`
- `packages/userauth/tests/Feature/PasswortAendernAktuellesPasswortTest.php`
- `packages/userauth/tests/Feature/PasswortFehlerEinmalTest.php`
- `packages/userauth/tests/Feature/ErstpasswortTest.php`
- `packages/userauth/tests/Feature/NurAdminTest.php`
- `packages/userauth/tests/Feature/Nachbesserung/PostRoutenTest.php`
Auswahl: Testdateien, die `PasswortAblehnung`, `bereits_verwendet`, `Geheimfelder` oder `password_policy` nennen (`git grep` in `packages/userauth/tests`), ohne Migrations-Tests. Suite laut Auftrag nicht Teil des Reviews.

## Funde: 0 muss, 0 sollte, 1 kann
- kann: (aus der ersten Prüfung, weiter offen) Das Entfernen hängt an jeder Wurfstelle einzeln (3 × `failedValidation`, `PasswordController::updatewithtoken`, jetzt `PasswortAblehnung`). Eine zentrale Stelle (z. B. Middleware an den betroffenen Routen) deckte künftige Wurfstellen automatisch ab.
- Der sollte-Fund der ersten Prüfung (Tests nur mit echtem Handler, Folge-Task #1597) ist durch f237ad3 erledigt.

## Neue Tasks: keine
## Fehlerprotokoll
- keine
## Fazit: freigegeben
