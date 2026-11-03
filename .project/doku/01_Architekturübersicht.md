# Architekturübersicht: ITHilbert UserAuth

## Aufbau
Nutzt Middleware und Policies zur Rechtekontrolle.

## Wichtige Klassen
- `ITHilbert\UserAuth\UserAuthServiceProvider`
- `ITHilbert\UserAuth\Models\User` (Erweiterung)
- `ITHilbert\UserAuth\Models\Role`

## Selbstregistrierung

Schalter: `userauth.registration.enabled` (env `USERAUTH_REGISTRATION`, Standard `false`). Die Routen sind immer registriert, damit Ein- und Ausschalten ohne neuen Routen-Cache wirkt. `EnsureRegistrationEnabled` prüft den Schalter zur Request-Zeit und antwortet bei `false` mit 404. Der Link „Registrieren“ auf der Login-Seite hängt am selben Schalter (`RegistrierungComposer` setzt `registrierungAktiv`).

Schichten (Abhängigkeiten zeigen nach innen; Pfade relativ zu `src/`):

| Schicht | Klassen |
|---|---|
| HTTP | `Http/Controllers/RegistrierungController`, `Http/Requests/RegistrierenRequest`, `Http/Requests/BestaetigungsLinkAnfordernRequest`, `Http/Middleware/EnsureRegistrationEnabled`, `Http/ViewComposers/RegistrierungComposer` |
| Application | UseCases in `Application/Registrierung/`: `Registrieren`, `EmailBestaetigen`, `BestaetigungsLinkSenden`, `BestaetigungsLinkErneutSenden`; `RegistrierungsEinstellungen` (liest `userauth.registration.*` zur Laufzeit); DTO `RegistriertesKonto`; `Exceptions/RegistrierungFalschKonfiguriertException` |
| Application, Ports | `Contracts/RegistrierungsKonten`, `Contracts/RegistrierungsRollen`, `Contracts/BestaetigungsLinkVersand`; außerdem genutzt der Port `Application/Contracts/MailLinks` (Methode `signiertFuerRoute`) |
| Domain | `Domain/Registrierung/BestaetigungsHash`, Events `KontoRegistriert` und `EmailBestaetigt`, Exception `Exceptions/EmailNochNichtBestaetigtException` |
| Infrastructure | `Infrastructure/Registrierung/EloquentRegistrierungsKonten`, `EloquentRegistrierungsRollen`, `NachAntwortBestaetigungsLinkVersand`; `Infrastructure/Mail/AppUrlMailLinks` (Implementierung von `MailLinks`); das Mailable `Mail/RegistrierungBestaetigen` liegt außerhalb von `Infrastructure/` unter `src/Mail/` |

Anbindung an bestehende Teile:

- **Login:** `Application/Auth/MitPasswortAnmelden` prüft nach den Zugangsdaten, ob „Bestätigung offen“ gilt, und wirft dann `EmailNochNichtBestaetigtException`. Das passiert vor der Anmeldung und vor dem 2FA-Schritt, es wird also kein Code versendet. `LoginController` zeigt die Meldung auf der Login-Seite mit dem Weg zum erneuten Anfordern.
- **Social Login:** `Infrastructure/SocialLogin/EloquentSocialUserRepository::markEmailVerified` setzt bei einem Konto mit „Bestätigung offen“ Passwort und `remember_token` neu (AK17). Einziger Aufrufer ist `Application/SocialLogin/ConfirmPendingSocialLink`.
- **Audit:** `Listeners/LogAuthenticationAttempt` schreibt die Ereignisse `registered` und `email_verified` nach `user_auth_audit_logs`, nur bei `audit_log_enabled`. Das Paket löst kein Laravel-Event `Illuminate\Auth\Events\Registered` aus: Daran hängt Laravel den Versand der Standard-Bestätigungsmail, bei einem Host-Model mit `MustVerifyEmail` käme eine zweite Mail.
- **Middleware-Priorität:** `MiddlewarePrioritaet::SCHALTER` enthält `EnsureRegistrationEnabled` und `EnsureSocialLoginEnabled`. Diese Schalter laufen vor der CSRF-Prüfung der Gruppe `web`. Ist die Funktion aus, antwortet jede Methode mit 404 statt mit 419.
