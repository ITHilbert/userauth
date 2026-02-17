# ITHilbert UserAuth

**Beschreibung**: User Auth system with Roles.

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

### Audit Logs (Optional)
Um das Audit-Logging für Login-Versuche zu aktivieren, fügen Sie folgende Zeile zu Ihrer `.env` Datei hinzu:

```dotenv
USERAUTH_AUDIT_LOG_ENABLED=false
USERAUTH_2FA_ENABLED=false
```

## Namespace
`ITHilbert\UserAuth`
