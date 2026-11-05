# Changelog

## 3.2.1 – 2026-11-05

### Behoben
- Die Rolle `admin` lässt sich über `role.edit`/`role.update` nicht mehr ändern. Der Aufruf leitet auf die Rollenliste zurück, mit der Meldung „Die Rolle Admin kann nicht geändert werden.“ (Sprachschlüssel `MsgEditRoot`, #662).
- Benutzerformular: Der Passwortfehler erscheint nur noch einmal (#660).

## 3.2.0 – 2026-11-05

### Hinzugefügt
- E-Mail eines gelöschten Benutzers: Die Option `USERAUTH_GELOESCHTE_EMAIL` (`sperren` als Standard, `reaktivieren`, `neu`) regelt Admin-Anlage und Social-Registrierung, wenn zur E-Mail nur ein gelöschter Benutzer existiert. Bei `neu` zeigt die Login-Seite bei den Social-Schaltflächen einen Hinweis. Audit-Ereignisse `user_reactivated` und `user_purged` (bei aktivem Audit-Log). Details: README, Abschnitt „E-Mail eines gelöschten Benutzers“, Upgrade-Hinweise (nach 3.0.0, unveröffentlicht), Punkte 22–24.
- Passwort erzeugen in der Benutzerverwaltung (Anlegen und Bearbeiten): Die Buttons „Passwort erzeugen“ und „Kopieren“ erscheinen am Feld „Passwort“, wenn `USERAUTH_PASSWORT_GENERATOR_URL` gesetzt ist. Neues veröffentlichtes Asset `public/vendor/userauth/js/passwort-erzeugen.js`. Ohne die Option ändert sich nichts. Details: README, Abschnitt „Passwort erzeugen (Benutzerverwaltung)“, Upgrade-Hinweise (nach 3.0.0, unveröffentlicht), Punkt 25.

### Geändert
- Theme-Wahl der acht Einstiegs-Views (Login, Passwort, Registrierung, Social) an einer Stelle (`ThemeComposer`). Ist `userauth.theme` `null` oder leer, gilt jetzt der Paket-Standard `tailwind` (bisher `bootstrap`). Hosts, die sich auf den alten Fallback verlassen, setzen `USERAUTH_THEME=bootstrap`.
- **Breaking:** Privilegiert ist nur noch die Rolle mit dem Schlüssel `admin`, unabhängig von ihrer ID (Entscheidung 0011). Die Rolle `dev` hat keine Sonderstellung mehr. `isDev` wirkt wie `isAdmin`, `routes.permissions_middleware` verlangt `hasRole:admin`, und die Seeder legen nur noch `admin` und `user` an. Die Rolle `admin` lässt sich über ihren Schlüssel nicht löschen.
  - Hosts, die `dev` als privilegiert nutzen, führen `php artisan migrate` aus. Die Migration `2026_11_05_000000_move_dev_users_to_admin_role` stellt Nutzer und Team-Mitgliedschaften von `dev` auf `admin` um. Bis dahin haben diese Nutzer keine Privilegien.
  - Veröffentlichte Config: `hasRole:dev` in `routes.permissions_middleware` auf `hasRole:admin` umstellen.
  - Details: README, Upgrade-Hinweise (nach 3.0.0, unveröffentlicht), Block „Nur noch `admin` ist privilegiert“, Punkte 1–6.
- Admin-Anlage mit der E-Mail eines gelöschten Benutzers: Bei `sperren` (Standard) lautet die Meldung am Feld `email` jetzt „Diese E-Mail-Adresse gehört zu einem gelöschten Benutzer.“ statt des allgemeinen Fehlers „bereits vergeben“. Sonst ändert sich am Verhalten nichts. Details: README, Upgrade-Hinweise (nach 3.0.0, unveröffentlicht), Punkt 22.
