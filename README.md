# ITHilbert UserAuth

**Beschreibung**: User Auth System mit integrierter Rollenverwaltung, Impersonate-Funktion, erweiterten Passwort-Regeln und Mandanten- / Teams-Fähigkeit.

## Dokumentation

Die ausführliche Dokumentation befindet sich im Ordner `docs/`:
- [Kontext & Zielsetzung](docs/00_README_Kontext.md)
- [Architekturübersicht](docs/01_Architekturübersicht.md)
- [Modulstruktur](docs/07_Modulstruktur.md)

## Installation

```bash
composer require ithilbert/userauth
```

## Konfiguration

### `.env` Optionen prüfen
Um bestimmte Features global in einem Projekt zu aktivieren, füge folgende Zeilen zu deiner `.env` Datei hinzu und passe sie nach deinen Bedürfnissen an:

```dotenv
# Logging
USERAUTH_AUDIT_LOG_ENABLED=false

# Sicherheits-Features
USERAUTH_2FA_ENABLED=false
USERAUTH_PASSWORD_POLICY_ENABLED=true

# Mandantenfähigkeit (Teams)
USERAUTH_TEAMS_ENABLED=true

# Theme der Auth-Views (Login, Passwort-Reset): 'tailwind' (Default) oder 'bootstrap'
# Nur in ÄLTEREN Bootstrap-/Phoenix-Projekten setzen:
USERAUTH_THEME=bootstrap
```

### Auth-View-Theme (Login & Passwort-Reset)

Die Auth-Views (Login, Passwort-vergessen/-zurücksetzen) liegen in zwei Theme-Varianten vor und werden über `config('userauth.theme')` gewählt:

- **`tailwind`** (Default) — Tailwind CSS. Das Package liefert eine vorkompilierte CSS mit (Route `userauth.assets.css`, erreichbar unter `/userauth/assets/userauth.css?v=<hash>`). Der Host-Browser cached diese mit `Cache-Control: immutable`. Neue Projekte brauchen **nichts** zu konfigurieren — weder Tailwind, noch `@source`, noch `vendor:publish`.
- **`bootstrap`** — das ältere Phoenix/Bootstrap-Markup. In bestehenden Bootstrap-Projekten per `USERAUTH_THEME=bootstrap` in der `.env` aktivieren. **Wichtig:** Das Package liefert kein Bootstrap oder Phoenix mit. Daher ist eine Konfiguration erforderlich (siehe unten unter *Auth-Assets-Konfiguration*).

### Auth-Assets-Konfiguration

Beide Layouts (Tailwind und Bootstrap) laden zusätzliche CSS und JavaScript über die Config `auth_assets`. Dies wird v.a. beim Bootstrap-Theme relevant, da das Package selbst kein Bootstrap oder Phoenix mitbringt.

Konfiguriere in deiner veröffentlichten `config/userauth.php`:
```php
'auth_assets' => [
    'css' => [
        'assets/css/theme.min.css',  // z.B. Phoenix Theme
        'assets/css/user.min.css',   // z.B. projektspezifische Styles
    ],
    'js' => [
        // optional: projektspezifische Scripts
    ],
],
```

**Pfade:** Alle Pfade sind relativ zu `public/` — z.B. `'assets/css/theme.min.css'` lädt `/public/assets/css/theme.min.css`.

**Tailwind-Theme:** Das Package-eigene Tailwind wird bereits geladen (Route `userauth.assets.css`). Die `auth_assets` sind hier optional und können für zusätzliche projektspezifische Styles genutzt werden.

**Bootstrap-Theme:** Die `auth_assets` sind **erforderlich**, um Phoenix/Bootstrap und weitere Styles zu laden. Ohne `auth_assets` bleibt die Login-Seite ungestylt.

**Projektspezifisches Design:** Die Tailwind-Views erweitern das Layout `userauth::layouts.auth`. Um das Login-Design an das jeweilige Projekt anzupassen, überschreibe dieses Layout im Projekt:

```
resources/views/vendor/userauth/layouts/auth.blade.php
```

Das Package liefert eine neutrale Default-Hülle; das überschriebene Layout bestimmt Branding, Farben und Hintergrund. So bleibt die Login-*Struktur* im Package, das *Aussehen* pro Projekt.

**Globale Auth-Übersetzungen:** Die Standard-Laravel-Auth-Keys (`auth.failed`, `auth.password`, `auth.throttle`) werden vom Package namespace-los registriert und sind damit in jedem Projekt übersetzt (de/en), ohne dass eine `lang/`-Datei im Projekt nötig ist.

### Für Package-Entwickler

Nach Änderungen an den Tailwind-Views im Package (z.B. neue Klassen hinzufügen) musst du die Package-CSS neu bauen:

```bash
cd packages/userauth
npm install
npm run build:css
```

Dies kompiliert `resources-src/userauth.css` zu `src/Resources/assets/css/userauth.css`. **Wichtig:** Die gebaute `src/Resources/assets/css/userauth.css` muss zusammen mit deinen View-Änderungen committed werden. Clients erhalten die neue CSS dann automatisch via Asset-Route mit aktualisiertem Versions-Hash.

### Config-Optionen
Nach der Paket-Installation können die Details auch in der veröffentlichten Config-Datei unter `config/userauth.php` angepasst werden:

#### Password Policy / Ablauf
Zwingender Wechsel von Passwörtern bei Bedarf aktivieren. Verhindert auch, dass die X letzten Passwörter einfach wiederverwendet werden.`
```php
    'password_policy' => [
        'enabled' => env('USERAUTH_PASSWORD_POLICY_ENABLED', false), 
        'require_change_every_days' => 90, 
        'prevent_reuse_last_passwords' => 3,
    ],
```

#### Impersonate
System-Admins können temporär ("Impersonate") die Perspektive eines beliebigen Endnutzers einnehmen. Ein Button kann mit `@if(auth()->user()->isImpersonated()) ... @endif` überwacht oder ein "Zurück"-Button mit Link auf `/impersonate/leave` gebaut werden.
```php
    'impersonate_enabled' => true,
```

#### Multi-Tenancy (Teams)
Erlaubt eine Mandanten-Trennung, bei der User zu Teams gehören (inklusive Team-eigenen Rollen).
Wird das Flag `USERAUTH_TEAMS_ENABLED=true` gesetzt, liest das System Rechte (hasRole, hasPermission) dynamisch vom aktuell aktiven `current_team_id` des Users aus, statt nur von seiner globalen Rolle.
```php
    'teams' => [
        'enabled' => env('USERAUTH_TEAMS_ENABLED', false),
    ],
```

## Social Login (Google)

Das Package unterstützt Social Login über Google mit sicherer Account-Verknüpfung für bestehende Nutzer.

### Installation & Vorbereitung

**Abhängigkeiten** sind bereits in der `composer.json` des Packages enthalten:
```bash
composer require laravel/socialite
```

**Datenbank:** Führe folgende Migration aus, um die neuen Tabellen zu erstellen:
```bash
php artisan migrate
```

Dies erzeugt zwei neue Tabellen:
- `user_social_accounts` — verknüpfte Social-Konten pro User
- `user_pending_social_links` — Bestätigungstokens für unverifiziierte Accounts

**Betrieb — Abgelaufene Bestätigungstokens löschen:**

Das Package registriert automatisch einen täglich geplanten Job (`userauth:prune-social-links`), der abgelaufene Einträge aus `user_pending_social_links` löscht. Damit dieser Job ausgeführt wird, benötigst du einen laufenden **Laravel Scheduler**:

```bash
* * * * * php artisan schedule:run >> /dev/null 2>&1
```

Diese Cron-Zeile sollte in dein System-Crontab eingetragen werden (typischerweise `crontab -e` oder per Hoster-Control-Panel). Sie ruft den Scheduler jede Minute auf; der Scheduler bestimmt selbst, welche Jobs fällig sind.

Ohne laufenden Scheduler können alte Bestätigungstokens in der Datenbank verbleiben. Die Tokens selbst sind mit konfigurierbarer TTL (Default: 60 Minuten) weiterhin ungültig; das Löschen ist eine Datenbankwartung.

### Google Cloud Console Setup

1. Gehe zu [Google Cloud Console](https://console.cloud.google.com) und erstelle einen neuen OAuth 2.0 Client (Typ: Web Application).
2. Konfiguriere die **Redirect-URI** exakt als:
   ```
   {APP_URL}/auth/google/callback
   ```
3. Aktiviere die **Scopes** (minimal erforderlich):
   - `openid`
   - `email`
   - `profile`
4. Konfiguriere den OAuth Consent Screen mit deinen Datenschutzerklärung- und Impressum-Links (erforderlich für Production).

### Host-App Konfiguration

Füge deine Google OAuth-Credentials in die `.env` Datei ein:
```dotenv
GOOGLE_CLIENT_ID=your-client-id-here
GOOGLE_CLIENT_SECRET=your-client-secret-here
```

Registriere diese in deiner `config/services.php`:
```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
],
```

**Wichtig:** Die Callback-URL (`redirect`-Key) ist **nicht** erforderlich. Das Package registriert die Route `userauth.social.callback` selbstständig und verwendet sie als Callback-URL gegenüber Google.

### Feature-Konfiguration

Aktiviere Social Login in `.env`:
```dotenv
# Social Login Master-Schalter
USERAUTH_SOCIAL_ENABLED=true

# Registrierung von neuen Usern per Social Login erlauben (Default: aus)
USERAUTH_SOCIAL_REGISTRATION=false

# Standard-Rolle für neue Social-Nutzer (Rollen-Key, z.B. 'user')
USERAUTH_SOCIAL_DEFAULT_ROLE=user

# Optionale Domain-Beschränkung (Komma-getrennt, leer = alle erlaubt)
# USERAUTH_SOCIAL_ALLOWED_DOMAINS=company.com,partner.com

# Gültigkeitsdauer von Bestätigungstokens (Minuten)
# USERAUTH_SOCIAL_PENDING_LINK_TTL=60
```

Oder verwende die veröffentlichte `config/userauth.php`:
```php
'social' => [
    'enabled' => env('USERAUTH_SOCIAL_ENABLED', false),
    'allow_registration' => env('USERAUTH_SOCIAL_REGISTRATION', false),
    'default_role' => env('USERAUTH_SOCIAL_DEFAULT_ROLE', 'user'),
    'allowed_domains' => explode(',', env('USERAUTH_SOCIAL_ALLOWED_DOMAINS', '')),
    'pending_link_ttl_minutes' => env('USERAUTH_SOCIAL_PENDING_LINK_TTL', 60),
],
```

### Verfügbarkeit von Social-Login Providern

Der Google-Login-Button (auf der Login-Seite) und der Verbinden-Link (auf der Profil-/Kontenseite) erscheinen **nur wenn folgende Bedingungen erfüllt sind:**
1. `USERAUTH_SOCIAL_ENABLED=true` ist aktiviert **UND**
2. `GOOGLE_CLIENT_ID` und `GOOGLE_CLIENT_SECRET` in `.env` gesetzt sind

Fehlen die Credentials, werden folgende Fehler geloggt (ohne Werte, nur Feldnamen):
```
Missing configuration keys: google.client_id, google.client_secret
```

Dies hilft bei der Fehlersuche. Die UI blendet den Button/Link aus und zeigt keinen visuellen Fehler.

**Technische Fehler (z.B. Google nicht erreichbar)** während des Login/Verbinden führen den User zurück zur Login-Seite (oder Profil-Seite) mit der Meldung `userauth::social.errors.provider_unavailable` (**benutzersichtbar**). Debug-Details landen nur im Log (nicht in der UI).

**Views müssen Variable `availableSocialProviders` prüfen:**
Die Views (Login, Profil) erhalten vom View-Composer `AvailableSocialProvidersComposer` die Variable `availableSocialProviders` (Array von Provider-Keys). Nutze diese, um zu prüfen, ob ein Provider verfügbar ist:

```blade
@if(in_array('google', $availableSocialProviders))
    <a href="{{ route('userauth.social.redirect', 'google') }}">
        Mit Google anmelden
    </a>
@endif
```

### Ablauf & Sicherheit (für Integratoren)

#### Gast-Flow (Neuer Login per Google)
1. **Domain-Prüfung:** Falls `allowed_domains` gesetzt, wird die E-Mail-Domain + Google `hd`-Claim geprüft.
2. **E-Mail-Verifikation:** Google muss `email_verified = true` melden, sonst wird der Login mit Begründung abgelehnt.
3. **Erkennung:** System prüft über `(provider, provider_user_id)` (Google `sub`), nicht über E-Mail.
4. **Bestehendes Konto:**
   - Bereits verknüpft → **sofort Login** (2FA greift, falls aktiv).
   - Nicht verknüpft, aber lokale E-Mail vorhanden:
     - E-Mail ist lokal bestätigt (`email_verified_at` gesetzt) → **sofort verknüpfen + Login**.
     - E-Mail ist NOT bestätigt → **Bestätigungsmail** an die lokale E-Mail. User klickt Link, wird verknüpft, `email_verified_at` wird gesetzt. Kein automatischer Login nach Bestätigung.
   - Keine lokale E-Mail:
     - `allow_registration = true` → **neuer User** (zufälliges Passwort-Hash, Standard-Rolle, Login).
     - `allow_registration = false` → **Fehler** mit verständlicher Meldung.
5. **Gelöschter User:** Hat jemand einen Account mit dieser E-Mail gelöscht, wird ein neuer Social-Nutzer nicht automatisch wiederverknüpft.

#### Bestätigungsablauf (Unverifiziierte E-Mail)
- **GET** `auth/social/confirm/{token}` — zeigt Bestätigungsseite (keine Login).
- **POST** `auth/social/confirm/{token}` — verknüpft das Konto, setzt `email_verified_at`, loggt NICHT ein.
- Token ist einmalig gültig, mit konfigurierbarer Gültigkeitsdauer (Default: 60 Minuten).

#### 2FA nach Social Login
Wenn der User `two_factor_enabled` hat, wird der Mail-Code-Flow auch nach Google-Login ausgelöst. Das ist die gleiche Logik wie beim Passwort-Login.

#### Konto-Verwaltung
- **GET** `social-accounts` — listet alle verknüpften Social-Konten (nur für eingeloggte User).
- **DELETE** `social-accounts/{provider}` — entfernt eine Verknüpfung. Passwort bleibt erhalten als Fallback-Methode.

### Benachrichtigungen & Audit

**E-Mails (queued):**
- `SocialLinkConfirmation` — Bestätigung für unverifiziierte Konten (GET-Link mit Token).
- `SocialAccountLinked` — Info-Mail bei jeder neuen Verknüpfung (auch per Bestätigungsmail).

**Audit-Log** (falls `USERAUTH_AUDIT_LOG_ENABLED=true`):
- Event `social_linked` — neue Verknüpfung.
- Event `social_unlinked` — Verknüpfung entfernt.

### Wichtig: Queue-Worker & Scheduler

**Queue-Worker** für Mails:
Die Bestätigungsmails und Info-Mails sind als `ShouldQueue` definiert. Damit sie versendet werden, muss ein Queue-Worker laufen:
```bash
php artisan queue:work
```
Ohne laufenden Worker werden die Mails in der Queue verbleiben und nicht versendet.

**Scheduler** für Wartung:
Abgelaufene Bestätigungstokens werden durch den eingebauten Job `userauth:prune-social-links` täglich gelöscht (siehe Abschnitt *Installation & Vorbereitung* oben).

### DSGVO / Datenschutz

- **Externe Service:** Google wird als Authentifizierungsprovider genutzt. Dies muss in deiner Datenschutzerklärung dokumentiert sein (Zweck: Authentifizierung, Datenempfänger: Google LLC).
- **User-Kontrolle:** Verknüpfungen können jederzeit im Profil unter `social-accounts` entfernt werden.
- **Audit-Trail:** Alle Verknüpfungen und Entfernungen werden im Audit-Log protokolliert (wenn aktiv).

### Routen-Referenz

| Methode | Route | Name | Beschreibung |
|---------|-------|------|-------------|
| GET | `auth/{provider}/redirect` | `userauth.social.redirect` | Weiterleitung zu Google OAuth |
| GET | `auth/{provider}/callback` | `userauth.social.callback` | Callback nach Google OAuth |
| GET | `auth/social/confirm/{token}` | `userauth.social.confirm` | Bestätigungsseite anzeigen |
| POST | `auth/social/confirm/{token}` | `userauth.social.confirm.store` | Bestätigung verarbeiten |
| GET | `social-accounts` | `userauth.social.accounts` | Verknüpfte Konten auflisten |
| DELETE | `social-accounts/{provider}` | `userauth.social.disconnect` | Verknüpfung entfernen |

Alle Routen sind unter `throttle`-Middleware geschützt. Falls `USERAUTH_SOCIAL_ENABLED=false`, geben alle Social-Routen einen 404-Fehler zurück.

## Namespace
`ITHilbert\UserAuth`
