# Prüfung: geheimfelder-flash – Bugfix #1596 — 2026-11-18
Maßstab: Task-Text #1596 (keine Spec), Nacharbeit zu `pruefung.md` (#1595). Diff: Host 2c1d47f...5660741 (nur Submodul), userauth 0c85664..3d64e77 (Test 60d13da, Fix 3d64e77: `src/Http/Geheimfelder.php`, `failedValidation()` in `PasswortAendernRequest`, `PasswortZuruecksetzenRequest`, `ZweiFaktorCodeRequest`, `PasswordController::updatewithtoken`, `UserAuthServiceProvider`).

Sonde: `/tmp/claude-1000/-home-joerg-projekte-packages-dev/ca734ce6-8f7f-4cde-a8d0-972aedbf2732/scratchpad/sonde/SondeGeheimfelderTest.php`, gelaufen mit `php artisan test <sonde>` (Projektkonfiguration), Handler-Dekorierer ohne `dontFlash()` wie im Test des Fixes, je Fall `_old_input` ausgegeben.

| Aussage (Zitat Task) | Ergebnis | Nachweis |
|---|---|---|
| 1. „Schutz der Felder `aktuelles_passwort`, `pwtoken`, `two_factor_code` auch dann, wenn der gebundene ExceptionHandler kein `dontFlash()` hat“ – Validierungsfehler der drei FormRequests | bestanden | `GeheimfelderFehlerwegeTest`: `test_aktuelles_passwort_…`, `test_pwtoken_…`, `test_zwei_faktor_code_wird_trotz_dekoriertem_handler_nicht_geflasht` grün, Aufbau belegt (`dekoriererIstGebunden()`), Gegenprobe `email` geflasht. Sonde: falsches aktuelles Passwort (`current_password`) → `_old_input={"harmlos":"sichtbar"}` |
| dto. – Controller-Fehlerweg ungültiger Reset-Token | bestanden | Sonde mit Dekorierer: `_old_input={"email":"max@firma.de"}`, kein `pwtoken` |
| dto. – `request()` (Kernel-Request) erfasst, auch bei Query und JSON | bestanden | FormRequest ist eine Kopie (`createFrom`: eigene `query`/`request`-Bags, geteilte `json`-Bag) – der Fix entfernt aus beiden. Sonde: `?pwtoken=…` → kein `pwtoken`; `?two_factor_code=…` → kein `two_factor_code`; JSON-Body mit `Accept: text/html` (Redirect statt JSON-Antwort, `invalid()` flasht) → `_old_input={"harmlos":"sichtbar"}` |
| dto. – Fehlerweg „Passwort bereits verwendet“ (`PasswortAblehnung::alsValidierungsfehler`, nach bestandener Validierung) | **nicht bestanden** | Sonde, Policy an, neues = aktuelles Passwort: Passwort ändern → `_old_input={"aktuelles_passwort":"Aktuelles-Passwort-1!","harmlos":"sichtbar"}`; Reset mit gültigem Token → `_old_input={"email":"max@firma.de","pwtoken":"51d59c9f…"}`. Ursache: `PasswordController::update()` (Zeile 44) und `updatewithtoken()` (Zeile 92) werfen die `ValidationException` aus `src/Http/PasswortAblehnung.php:30`, ohne vorher `Geheimfelder::entfernen` aufzurufen. Beim Reset bleibt das Token laut `PasswortZuruecksetzen` gültig – ein nutzbares Reset-Token liegt dann in der Sitzung |
| dto. – `ServiceProvider` nutzt `Geheimfelder::FELDER` für `dontFlash` | bestanden | Diff; `GeheimfelderNichtGeflashtTest` (3) grün |
| 2. „Tests der Fehlerwege falsches aktuelles Passwort und ungültiger Reset-Token“ | teilweise | `test_falsches_aktuelles_passwort_flasht_es_nicht`, `test_ungueltiger_token_flasht_pwtoken_nicht` grün, mit Gegenprobe – aber mit dem echten Handler, d. h. nur `dontFlash` geprüft. Die neue Zeile `Geheimfelder::entfernen` in `updatewithtoken` hat keinen Test (Sonde grün). sollte |
| 3. „Tote View auth/passwords/reset.blade.php: bewusst unverändert“ | bestanden | Diff berührt die View nicht |
| Prüfauftrag: „Entfernt das Entfernen der Felder vor dem Wurf irgendwo Daten, die danach noch gebraucht werden (`validated()`, Rate-Limiter, Audit-Log, 2FA-Ablauf)?“ | bestanden | Entfernt wird nur unmittelbar vor `throw` (failedValidation wirft danach immer; Controller wirft direkt danach). Kein `validated()` danach. Rate-Limiter `throttle:userauth-passwort-zuruecksetzen` zählt vor dem Controller; Login-Throttle liest nur die Kennung. Keine Middleware/Listener liest diese Felder (grep `input(`/`only(` in `src/Http/Middleware`, `src/Listeners`, `src/Traits`). 2FA-Tests (63 + 9) grün |

## Sicherheit
- Diff verkleinert die Angriffsfläche, kein neuer Endpunkt, keine Rechteänderung, kein Mass-Assignment.
- Lücke bleibt im Weg „Passwort bereits verwendet“ (s. Tabelle): Klartext des aktuellen Passworts bzw. ein weiter gültiges Reset-Token landen bei Handler ohne `dontFlash()` in `_old_input`. Das ist genau der Fall, den #1596 schließen soll → muss.

## Regressionen
Gelaufen im Platz (`php artisan test <datei> --compact`), alle grün:
- `packages/userauth/tests/Feature/GeheimfelderFehlerwegeTest.php` (5)
- `packages/userauth/tests/Feature/GeheimfelderNichtGeflashtTest.php` (3)
- `packages/userauth/tests/Feature/AdminPasswortTest.php` (29)
- `packages/userauth/tests/Feature/Anmeldung/AnmeldungPerLoginTest.php` (50)
- `packages/userauth/tests/Feature/Anmeldung/AnmeldungPerPasswortTest.php` (16)
- `packages/userauth/tests/Feature/ErstpasswortTest.php` (19)
- `packages/userauth/tests/Feature/Haertung/PasswortResetTest.php` (53)
- `packages/userauth/tests/Feature/Haertung/ZweiFaktorTest.php` (63)
- `packages/userauth/tests/Feature/Haertung/ZweiFaktorVersandfehlerTest.php` (9)
- `packages/userauth/tests/Feature/PasswortAendernAktuellesPasswortTest.php` (6)
- `packages/userauth/tests/Feature/PasswortMinLaengeTest.php` (13)
- `packages/userauth/tests/Feature/Registrierung/BestaetigungTest.php` (58)
Auswahl: Testdateien, die eine geänderte Datei oder Route nennen (`git grep` in `packages/userauth/tests`). Suite laut Auftrag nicht Teil des Reviews.

## Funde: 1 muss, 1 sollte, 1 kann
- muss: Fehlerweg „Passwort bereits verwendet“ flasht bei Handler ohne `dontFlash()` weiter `aktuelles_passwort` (Passwort ändern) und `pwtoken` (Reset, Token bleibt gültig) — `PasswordController.php:44` und `:92` über `PasswortAblehnung.php:30` ohne `Geheimfelder::entfernen`; Sonde `test_dekoriert_passwort_aendern_wiederverwendet`, `test_dekoriert_reset_wiederverwendet` rot (Ausgabe s. Tabelle)
- sollte: Fehlerwege ungültiger Token / falsches aktuelles Passwort nur mit echtem Handler getestet; `Geheimfelder::entfernen` in `updatewithtoken` ohne Test (→ Folge-Task #1597)
- kann: Das Entfernen hängt an jeder Wurfstelle einzeln (3 × `failedValidation`, 1 × Controller). Eine zentrale Stelle (z. B. Middleware an den drei Routen, die die Felder nach dem Controller-Durchlauf bzw. im `catch` aus `request()` entfernt, oder ein `renderable`/`reportable`-unabhängiger Weg) würde neue Wurfstellen wie `PasswortAblehnung` automatisch abdecken – Entscheidung beim Fix

## Neue Tasks: #1597 userauth Geheimfelder – Nacharbeit aus Review #1596 (Tests mit Dekorierer)
## Fehlerprotokoll
- {"schwere":"muss","ak":"1","fund":"PasswortAblehnung wirft nach bestandener Validierung eine ValidationException ohne Geheimfelder::entfernen; aktuelles_passwort und gültiges pwtoken landen bei Handler ohne dontFlash in _old_input","ursache":"Nur die Wurfstellen aus dem Task-Text (failedValidation, ungültiger Token) betrachtet, nicht alle Stellen, an denen in diesen Routen eine ValidationException entsteht","anweisung":"Bei 'Feld X darf nie geflasht werden' jede Stelle suchen, an der in der Route eine ValidationException entstehen kann (grep ValidationException und Helfer wie PasswortAblehnung im Aufrufpfad), und jede mit einem Test im Fehlerfall abdecken"}
- {"schwere":"sollte","ak":"2","fund":"Tests der Fehlerwege falsches aktuelles Passwort und ungültiger Reset-Token laufen mit dem echten Handler und prüfen damit nur dontFlash, nicht das neue Entfernen","ursache":"Test prüft den Fehlerweg, aber nicht unter der Bedingung, die der Bugfix adressiert","anweisung":"Tests eines Bugfixes unter genau der Bedingung laufen lassen, die den Fehler auslöst (hier: Dekorierer-Handler), sonst ist die neue Codezeile ungetestet"}
## Fazit: nicht freigegeben
