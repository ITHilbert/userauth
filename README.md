# ITHilbert UserAuth

**Beschreibung**: User Auth System mit integrierter Rollenverwaltung, Impersonate-Funktion, erweiterten Passwort-Regeln, Passwort-Reset, Zwei-Faktor-Anmeldung, Social Login (Google) und Mandanten- / Teams-Fähigkeit.

## Dokumentation

Die ausführliche Dokumentation befindet sich im Ordner `docs/`:
- [Kontext & Zielsetzung](docs/00_README_Kontext.md)
- [Architekturübersicht](docs/01_Architekturübersicht.md)
- [Modulstruktur](docs/07_Modulstruktur.md)

## Installation

```bash
composer require ithilbert/userauth
```

### Voraussetzungen und Abhängigkeiten

| Abhängigkeit | Anforderung | Wofür |
|---|---|---|
| PHP | 8.5 | Paket-Code |
| Laravel (`illuminate/*`) | 12 oder 13 | Framework |
| `ithilbert/laravel-kit` | `^1.2` | Trait `VueComboBox`, das `UserAuth` verwendet |
| `laravel/socialite` | `^5.0` (automatisch installiert) | Social Login (Google) |
| `yajra/laravel-datatables-oracle` | `^12.0` oder `^13.0` (automatisch installiert) | Nutzerliste (AJAX) |
| `nesbot/carbon` | `^2.0` oder `^3.0` (automatisch installiert) | Datumsberechnungen |
| Mailserver | in `config/mail.php` konfiguriert und erreichbar | Versand der 2FA-Codes (synchron) und der Reset-Links |
| Cache-Store | nicht `array` (z. B. `file`, `database`, `redis`) | Zähler der 2FA-Fehlversuche, Sperre „Code erneut senden“, Drosselungen |
| Session-Treiber | speichert zwischen Requests (nicht `array`) | Offene Zwei-Faktor-Anmeldung liegt in der Session |

Ein `dev-main` des Pakets ist nicht mehr nötig: `ithilbert/laravel-kit` ist ab Version `1.2` als Release verfügbar.

### Eigene Testumgebung (Paket-Entwicklung)

Das Paket hat eine eigene Testbench-App (`tests/TestCase.php`) und ist unabhängig vom Host testbar:

```bash
cd packages/userauth
composer install
vendor/bin/phpunit
```

- `phpunit.xml.dist`: SQLite `:memory:`, Cache und Session `array`, Mail `array`, Queue `sync`.
- Fixture-User mit Trait `UserAuth`: `tests/Fixtures/User.php`; Paket-Migrationen laufen automatisch.
- Die Laravel-Version kommt aus dem `vendor/` des Pakets. Ein Lauf gegen Laravel 12 und einer gegen Laravel 13 erfolgen je mit eigener `vendor/`.
- `vendor/` und `composer.lock` sind in `.gitignore` des Pakets eingetragen.
- Der Host führt die Paket-Tests über `phpunit.xml` mit aus (Suite `packages/userauth/tests/Feature`).

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

### Pflichten des Hosts

Diese Punkte muss der Host erfüllen, sonst schlagen Login, Passwort-Reset oder Rechteprüfung fehl:

1. **User-Model:** verwendet den Trait `ITHilbert\UserAuth\Traits\UserAuth` und implementiert `Illuminate\Contracts\Auth\CanResetPassword` (z. B. durch Erweiterung von `Illuminate\Foundation\Auth\User`). Ohne `CanResetPassword` wirft der Passwort-Reset `UserModelMisconfiguredException`.
2. **Model-Zuordnung:** `auth.providers.users.model` zeigt auf das User-Model. Fehlt der Eintrag, wirft das Paket `UserModelMisconfiguredException`; es fällt nicht auf `App\Models\User` zurück.
3. **Standard-Guard:** `auth.defaults.guard` ist ein Session-Guard (Treiber `session`). Sonst wirft das Paket `AuthGuardMisconfiguredException`.
4. **Cache-Store:** `CACHE_STORE` ist nicht `array`. Mit `array` gelten die Zähler (2FA-Fehlversuche, 60-Sekunden-Sperre, Reset-Drosselung) nur für einen Request.
5. **Absender:** `mail.from` ist gesetzt. Reset-Link und 2FA-Code gehen an diesen Absender.
6. **Eigene Routen auf den Passwort-Reset:** Die Route `password.sendtocken` trägt die Middleware `InvokeDeferredCallbacks` selbst. Eigene Routen, die den Reset-UseCase aufrufen, brauchen sie ebenfalls, sonst wird die Reset-Mail nie verschickt.
7. **Rechte vergeben:** `user_read` für die Nutzerliste, `user_create`/`user_edit`/`role_create`/`role_edit` nur an Vertrauenspersonen (siehe [Rollen und Rechte](#rollen-und-rechte)).
8. **APP_KEY:** Der Schlüssel für die 2FA-Codes ist `app.key`. Ein Wechsel des `APP_KEY` macht alle offenen Zwei-Faktor-Anmeldungen ungültig.
9. **Veröffentlichte Views und Sprachdateien:** Hosts mit veröffentlichten Dateien übernehmen die Änderungen (siehe [Upgrade-Hinweise](#upgrade-hinweise-300)).
10. **Migrationen:** `php artisan migrate` ausführen.

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
Zwingender Wechsel von Passwörtern bei Bedarf aktivieren. Verhindert auch, dass die X letzten Passwörter einfach wiederverwendet werden. Die Passwort-Historie wird nur geführt, solange `enabled` aktiv ist; das gilt auch beim Zurücksetzen per Reset-Link. Der Ablauf (`require_change_every_days`) greift erst nach der Zwei-Faktor-Anmeldung.
```php
    'password_policy' => [
        'enabled' => env('USERAUTH_PASSWORD_POLICY_ENABLED', false),
        'require_change_every_days' => 90,
        'prevent_reuse_last_passwords' => 3,
    ],
```

#### Impersonate
System-Admins können temporär ("Impersonate") die Perspektive eines beliebigen Endnutzers einnehmen. Ein Button kann mit `@if(auth()->user()->isImpersonated()) ... @endif` überwacht oder ein "Zurück"-Button mit Link auf `/impersonate/leave` gebaut werden. Impersonation durchläuft keine Zwei-Faktor-Anmeldung. Die Rechte prüft das Paket mit der Rolle des Handelnden (siehe [Rollen und Rechte](#rollen-und-rechte)).
```php
    'impersonate_enabled' => true,
```

#### Multi-Tenancy (Teams)
Erlaubt eine Mandanten-Trennung, bei der User zu Teams gehören (inklusive Team-eigenen Rollen).
Wird das Flag `USERAUTH_TEAMS_ENABLED=true` gesetzt, liest das System Rechte (hasRole, hasPermission) dynamisch vom aktuell aktiven `current_team_id` des Users aus, statt nur von seiner globalen Rolle. Hat der Nutzer im aktiven Team keine Teamrolle, gilt seine globale `role_id`.
```php
    'teams' => [
        'enabled' => env('USERAUTH_TEAMS_ENABLED', false),
    ],
```

## Rollen und Rechte

### Privilegierte Rollen

Eine Rolle ist privilegiert (hat jedes Recht, erfüllt jede Gate-Prüfung) nur in diesen Fällen:

| Rolle | Bedingung | Bedeutung |
|---|---|---|
| Dev | Rolle mit ID `1` **und** Schlüssel `dev` | Erfüllt jede Rolle und jedes Recht |
| Admin | Rolle mit ID `2` **und** Schlüssel `admin` | Erfüllt jedes Recht und jede Rolle außer `dev` |

- Eine umbenannte Rolle 1 oder 2 ist nicht privilegiert. Eine Rolle mit Schlüssel `admin` und anderer ID ist ebenfalls nicht privilegiert.
- Mit Teams gilt die Rolle des **aktiven Teams** für alle Prüfungen (Gate, Middleware, `isAdmin`/`isDev`, Impersonation). Ohne Teamrolle gilt die globale `role_id`.
- Fehlt die Rolle (`role_id` ist `null`), ist ungültig (`0`, negativ) oder zeigt auf eine gelöschte oder nicht vorhandene Rolle, ist der Nutzer **nicht** privilegiert und hat keine Rechte (fail-closed).

### Vergabe von Rollen und Rechten ohne Hierarchie

Entscheidung 0006 (Projekt `packages_dev`, Ordner `.project/doku/decisions/`):

- Wer `user_create` oder `user_edit` hat, kann **jede** existierende Rolle vergeben, auch Dev und Admin, auch an sich selbst.
- Wer `role_create` oder `role_edit` hat, kann einer Rolle **beliebige** Rechte geben, auch solche, die er selbst nicht hat.
- Es gibt keine Hierarchie-Prüfung.
- Ausgewählt werden können nur existierende, nicht gelöschte Rollen. Rechte-IDs müssen existieren. Eine Rolle wird mit ihren Rechten in einer Transaktion gespeichert: alles oder nichts.

**Konsequenz:** Ein Inhaber von `user_edit` oder `role_edit` kann sich selbst zum Dev machen und das Passwort jedes Nutzers setzen. Diese Rechte wie Admin-Rechte behandeln und nur Vertrauenspersonen geben.

### Rechte in Routen und Views

| Prüfung | Verhalten |
|---|---|
| Route `hasPermission:a` | Recht `a` oder Dev/Admin |
| Route `hasPermissionAnd:a,b` | Rechte `a` **und** `b` (Dev/Admin: immer erlaubt) |
| Route `hasPermissionOr:a,b` | Recht `a` **oder** `b` |
| Route `hasRole:x` | Rollenname `x` (Dev erfüllt jede Rolle, Admin jede außer `dev`) |
| Blade `@hasPermissionAnd('a,b')` / `@hasPermissionOr(['a','b'])` | Komma-Liste und Array sind gleichwertig |
| Gate `@can('a')`, `Gate::allows('a')` | wie `hasPermission` |

Leere, `null` oder fehlende Rechtenamen gelten als nicht vorhandenes Recht. Sie führen zur Ablehnung, nie zu einem Serverfehler.

Die Route `/users` verlangt `user_read` an der Route selbst. Das gilt unabhängig von `routes.users_middleware` in der veröffentlichten Config. Ohne Recht erhält der Nutzer dieselbe Ablehnung wie bei jeder anderen fehlenden Berechtigung (abhängig von `userauth.redirect_on_no_permission`: Anmeldung oder Seite `no-permission`).

### Nutzerliste (JSON)

Die AJAX-Liste liefert pro Zeile nur: `id`, `name`, `email`, `RoleName` und die Spalte `action` (HTML der Aktionen). Passwort-Hash, Reset-Token, 2FA-Code und 2FA-Ablaufzeit gehören nicht zur Ausgabe. Eigene Erweiterungen der Liste müssen die Felder explizit auswählen, nicht das ganze Model serialisieren.

## Passwort zurücksetzen

Entscheidung 0008 (Projekt `packages_dev`, Ordner `.project/doku/decisions/`). Der Ablauf läuft über den **Laravel-Password-Broker**.

1. **Anfordern:** `POST password/sendtocken` (Name `password.sendtocken`). Der Nutzer gibt seine E-Mail ein.
2. **Antwort:** Bekannte Adresse, unbekannte Adresse und gelöschtes Konto erhalten dieselbe Weiterleitung und dieselbe Meldung. Es entsteht kein Serverfehler.
3. **Versand:** Die Mail geht an die **in der Datenbank gespeicherte** Adresse, unabhängig von der Schreibweise der Eingabe. Der Versand erfolgt **nach der HTTP-Antwort** (`defer()`). Ein Queue-Worker ist dafür nicht nötig. Die Route trägt `InvokeDeferredCallbacks` (siehe Pflichten des Hosts). Läuft PHP ohne Server, der `defer()` nach der Antwort ausführt (z. B. CLI, Tests), sendet Laravel beim Beenden der Anwendung.
4. **Token:** Der Broker erzeugt das Token und speichert nur den Hash in der Tabelle `password_reset_tokens`. Tabellenname aus `auth.passwords.<broker>.table`. Die Migration legt die Tabelle nur an, wenn sie fehlt.
5. **Ablauf:** `auth.passwords.<broker>.expire` (Default 60 Minuten). Der Reset-Link ist danach ungültig.
6. **Zurücksetzen:** `POST password/updatewithtoken` (Name `password.updatewithtoken`) mit Token, E-Mail und neuem Passwort. Bei Erfolg:
   - das Passwort ist geändert, `password_changed_at` ist gesetzt,
   - die Passwort-Historie wird fortgeschrieben (nur bei aktiver `userauth.password_policy.enabled`),
   - das Token ist verbraucht; eine zweite Nutzung wird abgelehnt.
   Falsches, abgelaufenes, fremdes oder nicht-textuelles Token: Hinweis, Passwort bleibt unverändert.

**Drosselung:**

| Grenze | Wert | Schlüssel |
|---|---|---|
| Reset-Link je E-Mail | höchstens 1 pro 60 Sekunden | eigene Sperre im Paket, unabhängig von `auth.passwords.*.throttle` |
| Anfordern je IP | 5 pro Minute | Limiter `userauth-passwort-anfordern` |
| Zurücksetzen je IP | 5 pro Minute | Limiter `userauth-passwort-zuruecksetzen` |

**Mail:** `ITHilbert\UserAuth\Mail\PasswortZuruecksetzenLink`, Absender aus `mail.from`.

## Zwei-Faktor-Anmeldung

Entscheidung 0009 (Projekt `packages_dev`, Ordner `.project/doku/decisions/`). Aktivierung: `USERAUTH_2FA_ENABLED=true`. Die Zwei-Faktor-Anmeldung gilt für Passwort-Login und Social Login gleichermaßen.

### Ablauf

1. **Passwort oder Google korrekt:** Der Nutzer ist **noch nicht angemeldet**. Das Paket legt eine offene Anmeldung in der Session (Schlüssel `userauth.2fa`) an und regeneriert die Session-ID. Es erzeugt einen 6-stelligen Code aus `random_int` und versendet ihn **synchron** per Mail. Die Antwort wartet also auf den Mailserver.
2. **Code-Eingabe:** `GET verify` (Name `verify.index`) zeigt das Formular, `POST verify` (Name `verify.store`) prüft den Code.
   - Gültig: 10 Minuten ab Versand.
   - Abgelaufen oder falsch: Ablehnung mit Hinweis. Ein abgelaufener Code zählt als Fehlversuch.
   - Nach 5 Fehlversuchen ist der Code ungültig, die offene Anmeldung wird verworfen, der Nutzer landet auf der Anmeldung.
3. **Richtiger Code:** Der Nutzer ist angemeldet (neue Session-ID). Er wird zum ursprünglich gewünschten Ziel geleitet. Der Code ist verbraucht. Das Cookie „Angemeldet bleiben“ wird **erst jetzt** gesetzt. Das Audit-Ereignis `login` wird erst jetzt protokolliert.
4. **Code erneut senden:** Nur `POST verify/resend` (Name `verify.resend`), höchstens einmal pro 60 Sekunden. Der neue Code ersetzt den alten.
5. **Abbrechen oder Abmelden** (auch als Gast): Route `logout`. Die offene Anmeldung ist verworfen.
6. **Versandfehler** (Mailserver nicht erreichbar): kein Serverfehler. Der Nutzer landet auf der Anmeldung mit dem Hinweis, dass der Code nicht versendet werden konnte. Er ist nicht angemeldet.

Vor bestandener 2FA sind Seiten hinter `auth` nicht erreichbar. Die Routen `verify.*` sind nur für Gäste erreichbar.

### Speicherung der Codes

- Der Code liegt nirgends im Klartext: nicht in der Datenbank, nicht in der Session.
- Gespeichert wird ein HMAC-SHA256 über die zufällige Kennung der offenen Anmeldung und den Code, mit dem Schlüssel `app.key`.
- Der Vergleich ist zeitkonstant (`hash_equals`).
- Ein Code gilt nur in der Sitzung, für die er erzeugt wurde. Eine zweite Sitzung mit offener Anmeldung bleibt unangemeldet, wenn die erste bestätigt.
- Fehlversuche und die 60-Sekunden-Sperre zählt der `RateLimiter` im **Cache** je offener Anmeldung. Der Zähler übersteht „Code erneut senden“.

### Audit-Log (falls `USERAUTH_AUDIT_LOG_ENABLED=true`)

- `login`: erst nach bestandener 2FA.
- `two_factor_failed`: abgelehnter Code. Eigenes Ereignis, kein `login`.

### Bekannte Grenzen

- Fehlversuche zählen je offener Anmeldung. Wer das Passwort kennt, kann pro Anmeldung 5 Codes raten, jeweils mit einer Mail an das Konto. Eine Drosselung je Nutzer über mehrere Anmeldungen hinweg ist nicht umgesetzt.
- Der Versand ist synchron. Ein langsamer Mailserver verzögert die Antwort nach dem Passwort.

## Migrationen

| Migration | Wirkung | `down()` |
|---|---|---|
| `2019_10_12_000000_create_my_users_table` | legt `users` an, wenn sie fehlt; sonst ergänzt sie nur fehlende Spalten. Bestehende Nutzer bleiben erhalten. | ohne Wirkung |
| `2026_10_23_000000_make_role_id_nullable_on_users_table` | `role_id` nullable, ohne Standardwert Admin. Nutzer ohne Rolle sind nicht privilegiert. Bestehende Zuordnungen bleiben. | ohne Wirkung |
| `2026_10_23_100000_create_password_reset_tokens_table` | legt `password_reset_tokens` an, wenn sie fehlt | ohne Wirkung |
| `2026_10_23_200000_clear_remember_tokens_on_users_table` | leert **einmalig** `remember_token` aller Nutzer, auch gelöschter | ohne Wirkung |

**Keine Rückbau-Migrationen:** Ob das Paket eine Tabelle angelegt oder nur ergänzt hat, ist beim Zurückrollen nicht feststellbar. Ein Löschen würde die Nutzer des Hosts vernichten. Der alte Zustand (`role_id` mit Admin als Default) wäre die geschlossene Sicherheitslücke. Die geleerten Angemeldet-bleiben-Tokens lassen sich nicht wiederherstellen (Entscheidungen 0007 und 0009, Projekt `packages_dev`).

## Abhängigkeiten im Überblick

Siehe [Voraussetzungen und Abhängigkeiten](#voraussetzungen-und-abhängigkeiten). Zusätzlich:

| Abhängigkeit | Dev / Laufzeit | Wofür |
|---|---|---|
| `orchestra/testbench` `^10.0` oder `^11.0` | Dev | eigene Testumgebung |
| `phpunit/phpunit` `^11.5` oder `^12.0` | Dev | Tests |
| Google OAuth | extern, nur bei `USERAUTH_SOCIAL_ENABLED=true` | Social Login |

## Upgrade-Hinweise (3.0.0)

Diese Punkte gelten für Hosts, die auf 3.0.0 aktualisieren. Sie sind nach dem Code geprüft.

Unterstützt werden Laravel 12 und 13. `ithilbert/laravel-kit` wird ab `^1.2` benötigt.

**Berechtigungen und Daten**
1. `/users` verlangt `user_read` (an der Route). Rollen ohne dieses Recht verlieren den Zugriff auf die Nutzerliste.
2. Die JSON-Nutzerliste enthält nur noch `id`, `name`, `email`, `RoleName` und `action`.
3. Model-Serialisierung (`toArray`, `toJson`) blendet `password`, `remember_token`, `two_factor_code`, `two_factor_expires_at`, `edit_pw_token` und `edit_pw_token_end` aus. Ein eigenes `$hidden` des Hosts bleibt erhalten.
4. Dev und Admin gelten nur noch mit ID 1 + Schlüssel `dev` bzw. ID 2 + Schlüssel `admin`. Umbenannte Rollen 1 oder 2 verlieren ihre Privilegien. Mit Teams gilt die Rolle des aktiven Teams.
5. `role_id` ist nullable, ohne Default Admin. Neue Nutzer ohne Rolle haben keine Rechte.
6. Trait-Methoden `hasPermissionAnd` und `hasPermissionOr` erwarten `string|array`. Mehrere Argumente (`('a', 'b')`) sind weiterhin möglich.

**Migrationen**
7. Die vier Migrationen aus [Migrationen](#migrationen) haben eine leere `down()`. `migrate:rollback` entfernt durch sie keine Spalten und Tabellen. Ältere Paket-Migrationen (2FA-Spalten, Passwort-Richtlinie, Teams, Audit-Log, Social Login) behalten ihren bisherigen Rückbau.
8. Neu hinzugekommen: `2026_10_23_000000_make_role_id_nullable_on_users_table`, `2026_10_23_100000_create_password_reset_tokens_table` (nur, wenn die Tabelle fehlt) und `2026_10_23_200000_clear_remember_tokens_on_users_table`.
9. Die Migration `clear_remember_tokens` leert `remember_token` aller Nutzer einmalig. Alle Nutzer, die „Angemeldet bleiben“ genutzt hatten, sind danach einmal abgemeldet. Alte Cookies melden niemanden mehr an.
10. Sitzungen, die vor dem Update über den alten 2FA-Ablauf entstanden sind, werden einmal abgemeldet. Voraussetzung: 2FA ist aktiv und der Nutzer hat noch einen offenen Code in `users.two_factor_code`. Der Nutzer meldet sich neu an.

**Passwort-Reset**
11. Die Mail-Klasse `App\Mail\ForgottenPassword` und ihre View sind entfernt. Den Reset-Link versendet `ITHilbert\UserAuth\Mail\PasswortZuruecksetzenLink`.
12. Alte Reset-Links (aus `edit_pw_token`) sind ungültig. Betroffene fordern einen neuen Link an.
13. Der Absender kommt aus `mail.from`.
14. Das User-Model braucht `CanResetPassword` (siehe Pflichten des Hosts).

**Zwei-Faktor-Anmeldung und Routen**
15. `verify.resend` ist nur noch per POST erreichbar. Die veröffentlichte View `resources/views/vendor/userauth/auth/twoFactor.blade.php` muss "Code erneut senden" als POST-Formular haben. Ein GET-Link bricht.
16. `verify.*` ist nur für Gäste erreichbar. `logout` ist ohne `auth`-Middleware erreichbar.
17. Das Mailable `TwoFactorCode` nimmt `(code, gueltigMinuten)`. Host-Overrides des Mailables anpassen.
18. Das Event `Login` feuert erst nach bestandener 2FA. Listener auf `Login` werden bei offener 2FA nicht mehr ausgelöst.
19. Der Versand des 2FA-Codes ist synchron. Ein Versandfehler zeigt einen Hinweis auf der Anmeldung statt eines Serverfehlers.

**Entfernt**
20. Trait `TwoFactorAuthenticatable` sowie die Methoden `generateTwoFactorCode()` und `resetTwoFactorCode()` von `UserAuth`.
21. Contract `AuthSession` und Klasse `SessionAuth`.
22. Hook `authenticated()` im `LoginController`. Die Anmeldung läuft über den Controller und den UseCase `CompleteAuthentication`.

**Konfiguration, Views und Sprache**
23. Das User-Model wird über `auth.providers.users.model` aufgelöst. Fehlt der Eintrag, wirft das Paket `UserModelMisconfiguredException`.
24. Der Standard-Guard muss ein Session-Guard sein (siehe Pflichten des Hosts).
25. Der Cache-Store darf nicht `array` sein.
26. Neue Sprachdateien `zwei_faktor.php` (de und en) und Schlüssel in `password.php` (de und en). Hosts mit veröffentlichten Sprachdateien unter `lang/vendor/userauth/{de,en}/` übernehmen sie.
27. Nach dem Update `php artisan view:clear` ausführen.

---

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
   - Bereits verknüpft → **Login**, danach 2FA, falls aktiv (Nutzer ist vor dem Code nicht angemeldet).
   - Nicht verknüpft, aber lokale E-Mail vorhanden:
     - E-Mail ist lokal bestätigt (`email_verified_at` gesetzt) → **sofort verknüpfen + Login** (danach 2FA, falls aktiv).
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
Wenn der User `two_factor_enabled` hat, wird nach dem Google-Login der Code-Flow ausgelöst. Das ist dieselbe Logik wie beim Passwort-Login (siehe [Zwei-Faktor-Anmeldung](#zwei-faktor-anmeldung)).

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

**Queue-Worker** für Social-Login-Mails:
Die Bestätigungsmails und Info-Mails sind als `ShouldQueue` definiert. Damit sie versendet werden, muss ein Queue-Worker laufen:
```bash
php artisan queue:work
```
Ohne laufenden Worker werden die Mails in der Queue verbleiben und nicht versendet. Passwort-Reset und 2FA-Codes brauchen keinen Worker (siehe oben).

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

Zusätzlich für Anmeldung und Passwort:

| Methode | Route | Name | Beschreibung |
|---------|-------|------|-------------|
| GET | `verify` | `verify.index` | Code-Eingabe (nur Gäste mit offener Anmeldung) |
| POST | `verify` | `verify.store` | Code prüfen |
| POST | `verify/resend` | `verify.resend` | Code erneut senden (1× je 60 Sekunden) |
| ANY | `logout` | `logout` | Abmelden bzw. offene 2FA abbrechen |
| POST | `password/sendtocken` | `password.sendtocken` | Reset-Link anfordern (5/Min. je IP) |
| POST | `password/updatewithtoken` | `password.updatewithtoken` | Passwort mit Token setzen (5/Min. je IP) |

## Namespace
`ITHilbert\UserAuth`
