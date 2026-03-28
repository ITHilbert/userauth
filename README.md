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
```

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

## Namespace
`ITHilbert\UserAuth`
