# Modulstruktur: ITHilbert UserAuth

```
/src
  /App                      # Hilfsklassen (Classes/) und Artisan-Befehle (Console/Commands/)
  /Application              # Anwendungsfälle je Bereich, Ports in Contracts/
    /Auth                   # Anmeldung und Zwei-Faktor-Anmeldung, mit Contracts/, Events/, Exceptions/
    /Contracts              # MailLinks (Port für Links in Mails)
    /Passwort               # Passwort ändern und zurücksetzen, Wiederverwendung
    /Registrierung          # Selbstregistrierung, mit Contracts/ und Exceptions/
    /SocialLogin            # Verknüpfung und Anmeldung über Social-Anbieter
    /Zugriff                # Rollen- und Rechteprüfung, mit Contracts/
  /Config                   # config.php (Gruppe userauth)
  /Database
    /factories
    /Migrations
    /Seeders
  /Domain                   # Fachobjekte und Regeln je Bereich
    /Auth                   # OffeneAnmeldung, ZweiFaktorCode
    /Passwort               # Exceptions/
    /Registrierung          # BestaetigungsHash, Events/, Exceptions/
    /SocialLogin            # Events/, Exceptions/, SocialProvider, SocialIdentity, AllowedDomainPolicy
    /Zugriff                # Rolle, Namensliste
  /Entities                 # Eloquent-Modelle: Permission, PermissionGroup, Role, Team, UserAuthAuditLog
  /Http
    /Controllers
    /Formular               # Namensprüfungen und alte Eingaben in Verwaltungsformularen
    /Middleware
    /Requests
    /Responses              # ZugriffVerweigert
    /ViewComposers
    MiddlewarePrioritaet.php
    PasswortAblehnung.php
  /Infrastructure           # Adapter zu den Ports, je Bereich
    /Assets                 # Version der vorkompilierten CSS
    /Auth
    /Mail                   # AppUrlMailLinks
    /Passwort
    /Registrierung
    /SocialLogin
    /Zugriff
  /Listeners                # Event-Listener (Anmeldung, Social-Login-Änderungen)
  /Mail                     # Mailables
  /Resources
    /assets                 # CSS, JavaScript, Bilder
    /lang                   # Übersetzungen (de, en, global)
    /schema                 # UI-Schemas (ui/)
    /views                  # Blade-Views
  /Routes                   # web.php
  /Rules                    # PasswordHistoryRule
  /Traits                   # UserAuth, Format
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
