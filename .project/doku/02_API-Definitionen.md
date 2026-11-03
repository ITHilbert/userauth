# API-Definitionen: ITHilbert UserAuth

## Traits
- `HasRoles`: Trait für das User-Model.

## Methoden
- `hasRole($role)`: Prüft auf Rolle.
- `hasPermission($permission)`: Prüft auf Berechtigung.

## Selbstregistrierung

Alle Routen sind immer registriert. Ist `userauth.registration.enabled` aus, antworten sie zur Laufzeit mit 404 – für jede Methode, vor der CSRF-Prüfung (Details: `01_Architekturübersicht.md`, Abschnitt Selbstregistrierung).

| Methode | Pfad | Name | Middleware | Antwort |
|---|---|---|---|---|
| GET | `/register` | `register` | `guest` | Formular mit Name, E-Mail, Passwort und Passwort-Wiederholung |
| POST | `/register` | `register.store` | `guest`, Drossel `userauth-registrieren` (5 je Minute und IP), `InvokeDeferredCallbacks` | Gültige Daten: 302 auf `register.bestaetigung` mit „Wir haben Ihnen eine E-Mail geschickt …“. Vergebene Adresse: dieselbe Antwort. Ungültige Eingabe: zurück zum Formular mit Feldfehlern. |
| GET | `/register/bestaetigung` | `register.bestaetigung` | `guest` | Hinweisseite mit Formular „Link erneut anfordern“; zeigt Status bzw. Fehler aus der Session |
| POST | `/register/bestaetigung` | `register.erneut` | `guest`, Drossel `userauth-registrierung-link` (5 je Minute und IP), `InvokeDeferredCallbacks` | 302 auf `register.bestaetigung` mit „Falls zu dieser E-Mail-Adresse …“. Für jede Eingabe dieselbe Antwort. |
| GET | `/register/bestaetigen/{id}/{hash}` | `register.bestaetigen` | keine; die Signatur prüft der Controller (`hasValidSignature`) | Gültiger Link: 302 auf `login` mit Erfolgsmeldung. Ungültig, abgelaufen oder zu keinem Konto passend: 302 auf `register.bestaetigung` mit „Link ungültig oder abgelaufen …“. Kein 403, kein 500. |
| POST | `/register/bestaetigen/{id}/{hash}` | – | keine | Registrierung aus: 404. Registrierung an: 405. |

Überschreitet eine IP die Drossel, antwortet die Anwendung mit 429 und legt kein Konto an.

Verhalten von `register.erneut`:

- Eine Mail geht nur an ein nicht gelöschtes Konto mit „Bestätigung offen“.
- Höchstens ein Link je E-Mail-Adresse (Schreibweise egal) pro 60 Sekunden. Die Sperre gilt für jede Eingabe, auch für unbekannte Adressen.

Konfiguration (`config/userauth.php`, Paket-Default in `src/Config/config.php`):

```php
'registration' => [
    'enabled' => env('USERAUTH_REGISTRATION', false),
    'role' => env('USERAUTH_REGISTRATION_ROLE'),
    'verify_ttl_minutes' => 60,
],
```

- `enabled`: Schalter für Registrierung, Bestätigungslink und „Link erneut anfordern“. Standard aus.
- `role`: Rollen-Schlüssel (`roles.role`) für neue Konten. Leer heißt: keine Rolle (`role_id` null). `dev` und `admin` sind nicht erlaubt; ein unbekannter oder privilegierter Schlüssel führt zu `RegistrierungFalschKonfiguriertException` mit dem Config-Schlüssel im Text, und es entsteht kein Konto.
- `verify_ttl_minutes`: Gültigkeit des Bestätigungslinks in Minuten. Fehlt der Wert, ist er keine Zahl oder kleiner als 1, gilt 60.
