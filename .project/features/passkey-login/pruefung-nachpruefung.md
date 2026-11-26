# Prüfung: passkey-login (#2220) — 2026-11-26 (Nachprüfung seit 3747e71)

Geprüft: userauth `0dc87c5..4959405` (Fix) und `3747e71..0dc87c5` (Tests AK22), Branch `feature/passkey-login`, Stand 4959405. Spec seit 3747e71 unverändert (AK22 kam mit 3747e71). Der Fix baut nicht über das beanstandete AK hinaus um (eine Middleware, Routengruppe, zwei Übersetzungen) → Nachprüfung, kein volles Review.

Echter Server (`echtlauf start` im Platz, `USERAUTH_PASSKEY_ENABLED=true`, Port 8291, CSRF aktiv): Admin `echtlauf@example.test` (Rolle admin), Zielbenutzer (Rolle user) mit einem Passkey-Eintrag per `echtlauf seed`. `POST /impersonate/3` → 302, danach:

| Aufruf unter Impersonate | Antwort |
|---|---|
| `GET /passkey` | 200, Liste zeigt den Passkey des Ziels |
| `POST /passkey/register/options` (JSON) | 403, `message` „Unter einer Benutzerübernahme ist diese Aktion nicht erlaubt.“ |
| `POST /passkey/register` (JSON) | 403, gleiche Meldung |
| `DELETE /passkey/1` | 403 |
| `POST /passkey/1` mit `_method=DELETE` | 403 |
| `POST /passkey/register` mit `_method=PUT` | 405 (keine Route) |
| `GET`/`HEAD /passkey/register`, `GET /passkey/register/options` | 405 (`nurPost` wirft nur, registriert nichts) |
| `POST /passkey/register/options/` (Schrägstrich am Ende) | 403 |
| `POST /PASSKEY/register/options` | 405 (kein Controller erreicht) |

Danach `POST /impersonate-leave` → 302; Gegenprobe als Admin: `POST /passkey/register/options` → 200 mit Challenge; Passkey-Tabelle weiter 1 Zeile. Log `storage/logs/laravel-2026-11-26.log` des Platzes: 0 ERROR.

Middleware-Reihenfolge (`php artisan route:list --path=passkey -v`): `register/options`, `register`, `{id}` je `web` → `EnsurePasskeyEnabled` → `Authenticate` → `VerbietetImpersonate`. Damit: Passkey aus → 404 vor allem (und vor CSRF, `MiddlewarePrioritaet`); Gast → Anmeldung (bzw. 419 ohne Token); angemeldet unter Impersonate → 403; ohne Impersonate → Controller. Reihenfolge korrekt.

Andere Wege, eine Anmeldeinformation hinzuzufügen: `PasskeyController::optionen/registrieren` und die UseCases `PasskeyRegistrierungVorbereiten`/`PasskeyRegistrieren` werden nur aus diesen Routen erreicht (grep über `src/`); keine Aliase, keine weiteren Routen auf den Controller. Die `nurPost`-Platzhalter werfen nur `MethodNotAllowedHttpException`.

| AK / Aussage (Zitat) | Ergebnis | Nachweis |
|---|---|---|
| AK22 „Gegeben eine Impersonate-Sitzung (Sitzung enthält `impersonated_by`), wenn `POST /passkey/register/options`, `POST /passkey/register` oder `DELETE /passkey/{id}` aufgerufen wird, dann wird abgelehnt (JSON-Routen 403 mit allgemeiner Meldung, `DELETE` 403)“ | bestanden | `PasskeyImpersonateTest` (3 Tests, Impersonate über die echte Route, Aufbau mit Prüfung auf `impersonated_by`); echter Server: alle drei 403, JSON mit übersetzter Meldung; auch Methoden-Spoofing 403 |
| AK22 „es wird kein Passkey gespeichert oder gelöscht“ | bestanden | Test registrieren: gültige Antwort (echte Signatur) unter Impersonate → 403, Tabelle 0; Test löschen: 403, Passkey des Ziels bleibt; echter Server: Tabelle danach 1 Zeile |
| AK22 „und keine Challenge angelegt“ | bestanden | Test Optionen: `session('userauth.passkey')` null; zusätzlich nach Verlassen scheitert eine Registrierung ohne ausgegebene Challenge (422, 0 gespeichert) |
| AK22 „ohne Impersonate-Sitzung funktionieren dieselben Aufrufe (Gegenprobe)“ | bestanden | Gegenprobe je Test (Optionen mit Challenge, Registrierung erfolgreich, Löschen leitet auf `userauth.passkey.index`); echter Server: Optionen nach Verlassen 200 |
| AK22 „`GET /passkey` bleibt lesbar.“ | bestanden | `test_ak22_liste_bleibt_unter_impersonate_lesbar`; echter Server 200 mit Liste |
| AK22 „(Ergänzt im Automode … kein neuer Anmeldeweg über eine übernommene Sitzung.)“ | nicht prüfbar | Herkunftsvermerk, keine Aussage |
| AK10 „Ein Passkey wird immer nur dem angemeldeten Benutzer zugeordnet (nie einer Id aus der Anfrage)“ (Fund muss 1 der Erstprüfung) | bestanden | Erstprüfung plus Sperre unter Impersonate (oben); Fund muss 1 behoben |
| AK1–AK21, Randfälle 1–5 (alle übrigen Aussagen) | bestanden (Prüfung 3747e71) | vom Fix nicht berührt; Feature-Tests weiter grün (unten) |

## Sicherheit (Diff des Fixes)
- **Rechtegrenze:** `php .claude/hooks/abdeckung.php grenze packages/userauth/.project/features/passkey-login/spec.md` → Exit 0. Die AK22-Tests versuchen Registrieren und Löschen unter Impersonate wirklich (echte WebAuthn-Antwort, echte Impersonate-Route) — ohne die Middleware wären alle drei rot (200 bzw. 422 bzw. Weiterleitung statt 403).
- Sperre per Session-Schlüssel `impersonated_by`, derselbe Schlüssel, den `ImpersonateController` setzt und `isImpersonated()` liest — kein zweiter Merker.
- Prüfung vor Controller und vor `PasskeyRegistrierungVorbereiten` → keine Challenge in der Sitzung.
- Meldung ist ein fester übersetzter Text (de/en), keine Daten.

## Regressionen
- `vendor/bin/phpunit -c phpunit.xml packages/userauth/tests/Feature/Passkey` → OK (103 Tests, 1534 Assertions), inkl. `PasskeyImpersonateTest`.
- Berührte Stellen (`Routes/web.php`, Impersonate): `php artisan test --compact` mit `tests/Feature/Haertung/PrivilegierteRollenTest.php`, `tests/Feature/Nachbesserung/PostRoutenTest.php`, `tests/Feature/NurAdminTest.php`, `tests/Feature/Oidc/`, `tests/Feature/SocialLogin/` → 277 grün.
- Ganze Suite nicht (kein `SUITE: ja`).

## Funde: 0 muss, 0 sollte, 2 kann
- kann: Unter Impersonate bleibt `POST /users/passkeys/reset/{id}` (AK15) erreichbar, wenn die Rolle des Übernommenen `user_edit` hat (privilegierte Rollen sind nicht übernehmbar). Löscht nur, fügt nichts hinzu; AK22 nennt die Route nicht. Gehört thematisch zu #2225 (Impersonate-Grenzen allgemein).
- kann: `abort_if(…, 403, …)` liefert bei `APP_DEBUG=true` die JSON-Antwort mit Stacktrace (nur Entwicklungsumgebung, normales Laravel-Verhalten).
- Die drei kann-Funde der Erstprüfung bleiben bestehen.

## Neue Tasks: keine (Impersonate-Grenzen außerhalb der Spec: #2225 besteht schon)

## Fehlerprotokoll
- keine

## Fazit: freigegeben
