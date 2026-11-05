# Changelog

## Unveröffentlicht

### Geändert
- Theme-Wahl der acht Einstiegs-Views (Login, Passwort, Registrierung, Social) an einer Stelle (`ThemeComposer`). Ist `userauth.theme` `null` oder leer, gilt jetzt der Paket-Standard `tailwind` (bisher `bootstrap`). Hosts, die sich auf den alten Fallback verlassen, setzen `USERAUTH_THEME=bootstrap`.
