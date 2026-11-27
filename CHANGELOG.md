# Changelog

## 4.6.0 – 2026-11-27

### Geändert
- Optik der DataTables in der Verwaltung (Benutzer, Rollen, Rechte): Steuerleiste mit Suchfeld links und Anzahl-Auswahl rechts, kompakte Tabelle mit Sortier-Icons, Zeilen-Hover und Paginierung als Buttons im Kit-Stil; „Neu“-Button steht im Listenkopf über der Tabelle statt in der Tabellenkopfzeile; Spalte `id` rechtsbündig (#2369).

## 4.5.0 – 2026-11-27

### Hinzugefügt
- Neue Passwörter können gegen eine mitgelieferte Liste häufiger Passwörter geprüft werden: Config `userauth.passwort.haeufige_ablehnen` (Standard `false`, `USERAUTH_PASSWORT_HAEUFIGE_ABLEHNEN`); Groß-/Kleinschreibung und Randleerzeichen zählen nicht, keine Abfrage bei einem Drittdienst (#2342).

## 4.4.0 – 2026-11-26

### Hinzugefügt
- Passkey hinzufügen verlangt eine Bestätigung per aktuellem Passwort oder vorhandenem Passkey; sie gilt `USERAUTH_PASSKEY_BESTAETIGUNG_MINUTEN` Minuten (Standard 10, Config `userauth.passkey.bestaetigung_minuten`). Die Verwaltungsseite fragt über einen Dialog ab und wiederholt das Hinzufügen einmal (#2225). Der Dialog nennt den Weg „Passwort vergessen“ und zeigt die Meldung des Servers an (#2225).

### Geändert
- Unter Impersonate sind die Passkey-Bestätigung sowie das Anlegen und Lösen von Social-Verknüpfungen gesperrt (403) (#2225).

## 4.3.3 – 2026-11-26

### Behoben
- Optik der Verwaltungslisten (Benutzer, Rollen, Rechte) bei schmalen Breiten: Spaltenköpfe brechen nicht mehr im Wort um (z. B. „E-Mail“ bei 375 px), der Knopf „Neuer Benutzer“ bleibt einzeilig; die feste Breite der Knopfspalte entfällt (#2178).

## 4.3.2 – 2026-11-26

### Behoben
- Listen (Benutzer, Rollen, Rechte) starten auch, wenn Host-Skripte erst nach `DOMContentLoaded` eingefügt werden: Ist `document.readyState` schon `interactive` oder `complete`, initialisiert das Inline-Skript sofort; sonst wie bisher nach `DOMContentLoaded` (#2267).

## 4.3.1 – 2026-11-26

### Geändert
- `userauth-verwaltung.css` mit laravel-kit 1.12.1 neu gebaut: Knöpfe „Neu“ und „Speichern“ sind Navy (Primär), „Bearbeiten“ ist Sekundär (weiß mit blauem Rahmen), „Löschen“ bleibt rot (#2265).

## 4.3.0 – 2026-11-26

### Geändert
- Auto-Redirect ist jetzt Standard (#2226): `USERAUTH_OIDC_AUTO_REDIRECT` steht auf `true`. Ist OIDC aktiv und konfiguriert, leitet `/login` Gäste direkt zum Anbieter weiter; `USERAUTH_OIDC_AUTO_REDIRECT=false` schaltet das ab, `/login?lokal=1` bleibt der Notzugang mit Formular.
- Abmelden führt bei aktivem Auto-Redirect auf `/login?abgemeldet=1` statt auf `redirect_after_logout`: Abmeldemeldung (de und en), Knopf und Formular, keine Weiterleitung.

## 4.2.0 – 2026-11-26

### Hinzugefügt
- Anmeldung mit Passkey (WebAuthn, #2220): Knopf „Mit Passkey anmelden“ auf der Login-Seite (beide Themes), discoverable Passkeys mit Pflicht zur Benutzerverifikation, kein Zwei-Faktor-Code. Routen `userauth.passkey.login.options`, `userauth.passkey.login`, `userauth.passkey.index` (`/passkey`), `userauth.passkey.register.options`, `userauth.passkey.register`, `userauth.passkey.destroy` und Skript-Route `userauth.assets.passkey`; alle antworten mit 404, solange `USERAUTH_PASSKEY_ENABLED` aus ist (Standard).
- Config-Block `userauth.passkey` (`USERAUTH_PASSKEY_ENABLED`, `USERAUTH_PASSKEY_RP_ID`, `USERAUTH_PASSKEY_RP_NAME`, `USERAUTH_PASSKEY_ORIGINS`) und `userauth.passwort_login` (`USERAUTH_PASSWORT_LOGIN`: `immer`, `ohne_passkey`, `nie`).
- Verwaltungsseite der eigenen Passkeys, „Passkeys zurücksetzen“ in der Benutzerverwaltung (Route `user.passkeys.reset`, Recht `user_edit`) und Befehl `userauth:passkeys-zuruecksetzen {email}`; Audit-Ereignis `passkeys_reset`.
- Migration `user_passkeys`, Abhängigkeit `web-auth/webauthn-lib` (`^5.3`), Sprachdateien `passkey.php` (de und en), README-Abschnitt „Anmeldung mit Passkey“.

## 4.1.1 – 2026-11-26

### Geändert
- `guzzlehttp/guzzle` erlaubt jetzt `^7.8 || ^8.0` (#2218). Apps mit Guzzle 8 werden bei `composer update` nicht mehr auf Guzzle 7 zurückgestuft. Die OIDC-Tests laufen mit Guzzle 7.17 und 8.6 grün.

## 4.1.0 – 2026-11-26

### Hinzugefügt
- Anmeldung über den eigenen Anmeldedienst per OpenID Connect (Authelia, #2204): Knopf auf der Login-Seite, Routen `userauth.oidc.redirect` (`/auth/oidc/redirect`) und `userauth.oidc.callback` (`/auth/oidc/callback`), Config-Block `userauth.oidc` (`USERAUTH_OIDC_*`, Standard aus). Verknüpfung über `sub` bzw. eindeutige E-Mail in `user_social_accounts` (Anbieter `oidc`), Neuanlage mit Rolle aus Gruppen nur mit `auto_create`, kein Zwei-Faktor-Code, optional Auto-Redirect mit `/login?lokal=1` als Rückfall. README-Abschnitt „Anmeldung über Authelia“.
- Abhängigkeiten `firebase/php-jwt` (`^6.4 || ^7.0`) und `guzzlehttp/guzzle` (`^7.8`).
- Neue Sprachdateien `oidc.php` (de und en).

## 4.0.0 – 2026-11-21

### Geändert (inkompatibel)
- Die Verwaltungsseiten (Benutzer, Rollen, Rechte, Benutzerbild) brauchen das Kit-Theme `kern` (`LARAVELKIT_THEME=kern`, Einstellung `laravelkit.theme`). Mit einem anderen Theme rendern sie, schreiben aber je Request eine Warnung ins Log.
- Ihre Optik kommt vom Paket: vorkompiliertes CSS mit Kit-Basis und Tailwind-Theme des Kits, ohne Preflight. Die Klassen sind `kit-…` und `userauth-…` statt Bootstrap-Klassen. Die Auth-Seiten (Login, Registrierung, Passwort, Social) bleiben unverändert.
- Der Lösch-Dialog ist Blade plus Inline-Skript statt Vue-Insel `dialog-delete`. `initLoeschen()` und `mountInseln()` sind dafür nicht mehr nötig.
- Die Zeilenbuttons der Listen (Bearbeiten, Löschen) sind Kit-Components (`button-edit`, `button-delete`) statt `HButton`.
- `ithilbert/laravel-kit` ab `^1.10` (erster Release mit Theme `kern`).
- Veröffentlichte Verwaltungs-Views müssen neu veröffentlicht werden (siehe README, „Upgrade auf 4.0“).

### Hinzugefügt
- `userauth.redirect_on_no_permission` kennt den Wert `'403'`: fehlendes Recht antwortet mit HTTP 403 statt Weiterleitung (Standard bleibt `login`).
- Route `userauth.assets.verwaltung` (`/userauth/assets/userauth-verwaltung.css`) mit dem CSS der Verwaltungsseiten.
- Quelle `resources-src/verwaltung.css` und Skript `npm run build:css`, das die CSS-Dateien baut.
- Die Asset-Routen laufen ohne Session-Cookie (ohne Middleware-Gruppe `web`).
- Der Lösch-Dialog ist auf 32rem Breite begrenzt; die statischen Tabellen der Rollen- und Rechteformulare stehen in `userauth-tabelle-rahmen` und scrollen dort.

## 3.5.3 – 2026-11-21

### Behoben
- Sprachdatei der Listen (`vendor/laravelkit/DataTable_DE.json`): Paket verlangt `ithilbert/laravel-kit` ab 1.6.1, weil nur dort die Datei per Publish-Tag `laravelkit-assets` geliefert wird; README nennt den Publish-Befehl (#661).

## 3.5.2 – 2026-11-20

### Behoben
- Auth-Seiten liefern keinen 500 mehr, wenn das Vite-Manifest des Hosts andere Einträge hat: geladen werden nur die Einträge aus der neuen Config `userauth.vite_entries` (Standard `resources/css/app.css`, `resources/js/app.js`), die im Manifest stehen (#1938).

## 3.5.1 – 2026-11-18

### Sicherheit
- Geheimfelder `aktuelles_passwort`, `pwtoken` und `two_factor_code` werden bei Validierungsfehlern nicht mehr in die Sitzung geflasht (#1595).
- Der Schutz greift auch, wenn der gebundene ExceptionHandler keine Methode `dontFlash` hat (Dekorierer): die Felder werden zusätzlich vor dem Fehlerwurf aus der Anfrage entfernt, auch beim ungültigen Reset-Token (#1596).

## 3.5.0 – 2026-11-18

### Hinzugefügt
- Mindestlänge neuer Passwörter einstellbar über `userauth.passwort.min_laenge` (Env `USERAUTH_PASSWORT_MIN_LAENGE`, Standard 8), gilt für Ändern, Zurücksetzen, Admin und Registrierung (#1593).

## 3.4.2 – 2026-11-18

### Behoben
- Audit-Log setzt Zeitstempel selbst (unter `shouldBeStrict` kein 500 mehr, #1588).

## 3.4.1 – 2026-11-17

### Sicherheit
- Passwort ändern (`POST /password/update`) verlangt das aktuelle Passwort im Feld `aktuelles_passwort`; ohne oder mit falschem Wert Validierungsfehler, Hash bleibt unverändert. Passwort vergessen (Token) und Admin-Änderung bleiben unverändert (#1496).

## 3.4.0 – 2026-11-16

### Geändert
- Listen (Benutzer, Rollen, Rechte): `new DataTable('.data-table', …)` statt jQuery-Plugin, DataTable 2 braucht kein jQuery mehr. Ajax sendet den Header `X-Requested-With`.
- `permission.js` ohne jQuery (Vanilla-JS, gleiches Verhalten). Der Host muss die veröffentlichte `permission.js` neu veröffentlichen (`vendor:publish`).

## 3.3.2 – 2026-11-16

### Geändert
- Schema `user/create.json` und `user/edit.json`: Das Feld `benutzername` trägt `nurModi: ["benutzername", "beides"]`, es gilt im Modus `email` nicht (#1323).
- README und CHANGELOG 3.3.0: Nur `auth/login.json` hat den Block `anmeldungPer`; `password/forgotten.json` und `password/editwithtoken.json` kennen weiter nur `email` (#1323).

## 3.3.1 – 2026-11-16

### Behoben
- Login-Ansicht (Theme bootstrap): Kennungs- und Passwortfeld hatten je zwei `class`-Attribute, sodass die Fehlermarkierung `is-invalid` nie erschien. Jetzt ein `class`-Attribut je Feld (#1324).

## 3.3.0 – 2026-11-16

### Hinzugefügt
- Anmeldung per E-Mail, Benutzername oder beides: Die Config `anmeldung_per` (env `USERAUTH_ANMELDUNG_PER`, Standard `email`) steuert Anmeldeseite, Drosselung, „Passwort vergessen“, Reset-Link (Token je Konto), Audit-Log und Benutzerverwaltung. Modus `email` bleibt unverändert. Neue Migration für `users.benutzername`. Details: README, Abschnitt „Anmeldung per E-Mail, Benutzername oder beides“ (#1311).
- Benutzerverwaltung: In den Modi `benutzername`/`beides` gibt es das Pflichtfeld „Benutzername“ (Blade und Schema `user/create.json`, `user/edit.json`); die E-Mail muss dort nicht eindeutig sein.
- Social Login: Gehören mehrere aktive Konten zur E-Mail des Anbieters, wird nichts verknüpft oder angelegt; die Anmeldeseite zeigt eine Meldung mit Hinweis auf den Benutzernamen.

### Geändert
- Selbstregistrierung: In den Modi `benutzername`/`beides` antworten die Registrierungsrouten mit 404, weil die Registrierung keinen Benutzernamen erfasst.
- Veröffentlichte Host-Views (login, password/*) und die statische Schema-JSON `auth/login.json` müssen nach dem Update neu veröffentlicht werden, sonst bleibt es beim Feld `email`.

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
