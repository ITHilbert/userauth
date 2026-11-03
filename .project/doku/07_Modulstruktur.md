# Modulstruktur: ITHilbert UserAuth

```
/src
  /Database
  /Http
    /Middleware
  /Models
  UserAuthServiceProvider.php
```

## Selbstregistrierung

Schichten und Ablauf stehen in `01_Architekturübersicht.md`, Routen in `02_API-Definitionen.md`. Dateien (Pfade relativ zu `src/`):

| Pfad | Inhalt |
|---|---|
| `Http/Controllers/RegistrierungController.php` | Formular, Anlegen, Hinweisseite, Bestätigen, erneut anfordern |
| `Http/Requests/RegistrierenRequest.php`, `Http/Requests/BestaetigungsLinkAnfordernRequest.php` | Validierung |
| `Http/Middleware/EnsureRegistrationEnabled.php` | 404 bei ausgeschalteter Registrierung |
| `Http/ViewComposers/RegistrierungComposer.php` | Link „Registrieren“ auf der Login-Seite |
| `Application/Registrierung/` | UseCases, Ports in `Contracts/`, Config-Lesung (`RegistrierungsEinstellungen`), DTO `RegistriertesKonto`, `Exceptions/` |
| `Domain/Registrierung/` | `BestaetigungsHash`, Events `KontoRegistriert` und `EmailBestaetigt`, `Exceptions/` |
| `Infrastructure/Registrierung/` | Eloquent-Adapter für Konten und Rollen, Versand nach der Antwort |
| `Mail/RegistrierungBestaetigen.php` | Mailable für den Bestätigungslink |
| `Database/Migrations/2026_11_03_000000_add_self_registered_to_users_table.php` | Spalte `users.self_registered` (Standard `false`); `down()` ohne Wirkung |
| `Resources/views/registrierung/` | `formular.blade.php` und `bestaetigung.blade.php`; sie binden je nach `userauth.theme` die Variante aus den Unterordnern `tailwind` oder `bootstrap` ein |
| `Resources/views/emails/registrierung_bestaetigen.blade.php` | Text der Bestätigungsmail (Markdown) |
| `Resources/lang/de/registrierung.php`, `Resources/lang/en/registrierung.php` | Texte der Registrierung, Mail eingeschlossen |
| `Config/config.php` | Gruppe `registration` |
