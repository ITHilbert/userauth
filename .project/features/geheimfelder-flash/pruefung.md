# Prüfung: geheimfelder-flash (Bugfix #1595) — 2026-11-18
Maßstab: Task-Text #1595 (keine Spec). Diff: Host 4eb9bcf...2c1d47f (nur Submodul), userauth 8cc6916..8db8311 (Test d98fde3, Fix 8db8311: `UserAuthServiceProvider::registerGeheimfelder()` ruft `dontFlash(['aktuelles_passwort','pwtoken','two_factor_code'])` am ExceptionHandler).

| Aussage (Zitat Task) | Ergebnis | Nachweis |
|---|---|---|
| „Das aktuelle Passwort gelangt nie in die Sitzung“ – ungültiges neues Passwort | bestanden | `GeheimfelderNichtGeflashtTest::test_passwort_aendern_flasht_aktuelles_passwort_nicht` grün; prüft `getOldInput()` ohne `aktuelles_passwort`, `password`, `password_confirmation` |
| dto. – falsches aktuelles Passwort (`current_password` schlägt fehl) | bestanden | eigene Sonde (Scratchpad, `php artisan test <sonde>`, Projektkonfiguration): `_old_input` = `{"harmlos":"sichtbar"}`, kein `aktuelles_passwort`, Gegenfeld geflasht. Kein Test im Paket dafür (sollte) |
| dto. – andere Wege (`old()`, `withInput`, Exceptions) | bestanden | grep `src/`: einziges `withInput` ist `LoginController:56` mit `only(username)`; kein `old('aktuelles_passwort')`/`old('pwtoken')`/`old('two_factor_code')` in Views; alle drei Felder scheitern über `ValidationException` → `Handler::invalid()` mit `Arr::except($request->input(), $this->dontFlash)` (vendor Handler.php:897) |
| „weitere Geheimfelder des Pakets (z. B. `pwtoken`, `two_factor_code`) prüfen“ – `pwtoken` | bestanden | Test `test_passwort_zuruecksetzen_flasht_pwtoken_nicht` grün; Sonde für ungültigen Token (Controller wirft `ValidationException::withMessages(...)->redirectTo(...)`, PasswordController:99): `_old_input` = `{"email":"max@firma.de"}` |
| dto. – `two_factor_code` | bestanden | Test `test_zwei_faktor_code_wird_bei_validierungsfehler_nicht_geflasht` grün |
| dto. – Vollständigkeit der Liste | bestanden | grep `name="…(pass|token|code|secret)…"` in `src/`: `password`, `password_confirmation` (Laravel-Standard), `aktuelles_passwort`, `pwtoken`, `two_factor_code` (neu); `token` nur in `auth/passwords/reset.blade.php`, die keine Paket-Route nutzt (kann, Altlast); `postcode` kein Geheimnis. Social-Confirm-Token steht im URL-Pfad, nicht im Formular |
| „Abnahme: … `session()->getOldInput()` enthält kein `aktuelles_passwort`“ | bestanden | s. o., Test asserted `assertArrayNotHasKey` |
| „Gegenprobe: normales Feld wird weiter geflasht“ | bestanden | Test-Helfer `normalesFeldWirdGeflasht()` (email) in jedem Test; pwtoken-Test prüft `email` im selben Request; Sonde: Feld `harmlos` geflasht |
| Eingriff in den Handler: Boot-Reihenfolge | bestanden | Handler ist Singleton; Sonde: `dontFlash` = `[current_password, password, password_confirmation, aktuelles_passwort, pwtoken, two_factor_code]`, Laravel-Standard bleibt erhalten (merge). Frühes Auflösen in `boot()` löst `withExceptions`-Callbacks nur früher aus, keine Nebenwirkung gefunden |
| Eingriff in den Handler: Hosts ohne Handler-Methode | teilweise | kein Fehler (`method_exists`-Wächter), aber Schutz fällt still weg. Im Host per tinker (lesend): `app(ExceptionHandler::class)` ist `NunoMaduro\Collision\Adapters\Laravel\ExceptionHandler` ohne `dontFlash`/`$dontFlash` – greift bei `runningInConsole && !runningUnitTests` (z. B. Octane-Worker mit Collision in dev). Für normale HTTP-Anfragen (php-fpm, `artisan serve`-Kindprozess) mit Laravel-Handler wirksam. sollte |

## Sicherheit
- Fix verkleinert die Angriffsfläche (kein Klartext-Passwort, kein Reset-Token, kein 2FA-Code in der Sitzung). Kein neuer Endpunkt, keine Rechteänderung.
- Restrisiko: stiller Wegfall bei dekoriertem Handler ohne `dontFlash()` (s. Tabelle) – sollte, Folge-Task #1596.

## Regressionen
Gelaufen im Platz (`php artisan test <datei> --compact`), alle grün:
- `packages/userauth/tests/Feature/GeheimfelderNichtGeflashtTest.php` (3)
- `packages/userauth/tests/Feature/PasswortAendernAktuellesPasswortTest.php` (6)
- `packages/userauth/tests/Feature/Haertung/PasswortResetTest.php` (53)
- `packages/userauth/tests/Feature/Haertung/ZweiFaktorTest.php` (63)
- `packages/userauth/tests/Feature/PasswortMinLaengeTest.php` (13)
- `packages/userauth/tests/Feature/Anmeldung/AnmeldungPerLoginTest.php` (50)
Ganzes Verzeichnis `packages/userauth/tests/Feature` seriell lief über 170 s und wurde abgebrochen (kein Ergebnis); Suite laut Auftrag nicht Teil des Reviews.

## Funde: 0 muss, 2 sollte, 1 kann
- sollte: Schutz entfällt still, wenn der gebundene ExceptionHandler `dontFlash()` nicht hat (Collision-Adapter) — `UserAuthServiceProvider.php` `registerGeheimfelder()`; tinker im Host (→ Folge-Task #1596)
- sollte: Kein Test für die Fehlerpfade „falsches aktuelles Passwort“ und „ungültiger Reset-Token (Controller-Exception)“ — per Sonde korrekt (→ Folge-Task #1596)
- kann: tote View `src/Resources/views/auth/passwords/reset.blade.php` mit Feld `token` (in #1596 mit aufgeführt)

## Neue Tasks: #1596 userauth Geheimfelder – Nacharbeit aus Review geheimfelder-flash (#1595)
## Fehlerprotokoll
- {"schwere":"sollte","ak":"","fund":"dontFlash nur bei method_exists am gebundenen Handler; Dekorierer wie der Collision-Adapter schalten den Schutz still ab","ursache":"Robustheit gegen fremde Handler-Bindungen nicht bedacht, nur Erfolgspfad mit Standard-Handler betrachtet","anweisung":"Sicherheitsschutz nie still an eine optionale Fremd-API hängen: bei fehlender Methode einen Fallback im eigenen Code oder mindestens eine Warnung vorsehen"}
- {"schwere":"sollte","ak":"","fund":"Tests decken nur ungültiges neues Passwort ab, nicht falsches aktuelles Passwort und ungültigen Reset-Token","ursache":"Test folgt nur dem Abnahmesatz, nicht dem 'nie' der Anforderung","anweisung":"Bei 'nie'-Anforderungen jeden Fehlerweg (jede Validierungsregel, jede vom Controller geworfene Exception) mit eigenem Test abdecken"}
## Fazit: freigegeben
