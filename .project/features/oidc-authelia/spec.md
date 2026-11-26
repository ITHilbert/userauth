# Spec: oidc-authelia (#2204)

Art: Feature (neue Anmeldeart, zwei neue Routen).
Risiko: kritisch (Anmeldung, Zugänge, neue Routen, Token-Prüfung).
Selbst freigegeben im Automode (Stapel 2026-11-26-1316): Auftrag Joerg (Telegram 870/872, 2026-11-26) steht im Task; Annahmen siehe Protokoll und „Entscheidungsspielraum“.
Marketing: entfällt (keine neue Seite; nur ein Knopf auf der vorhandenen Login-Seite).

## Hintergrund
Der Homeserver-Admin hat Authelia (`https://auth.lan`) als zentralen Anmeldedienst eingerichtet (OpenID Connect, Authorization-Code-Flow, `client_secret_post`, Scopes `openid profile email groups`, RS256, kein Consent-Screen). userauth bekommt OIDC als weitere Anmeldeart neben Passwort und Google. Alle Einstellungen kommen aus der `.env`; ohne `USERAUTH_OIDC_ENABLED` bleibt alles wie heute. Redirect-URI ist fest `https://<app>.lan/auth/oidc/callback`.

Config-Schlüssel `userauth.oidc.*` (Quelle `.env`):

| .env | Config | Standard |
|---|---|---|
| `USERAUTH_OIDC_ENABLED` | `enabled` | `false` |
| `USERAUTH_OIDC_ISSUER` | `issuer` | leer |
| `USERAUTH_OIDC_CLIENT_ID` | `client_id` | leer |
| `USERAUTH_OIDC_CLIENT_SECRET` | `client_secret` | leer |
| `USERAUTH_OIDC_CA_BUNDLE` | `ca_bundle` | leer (Standard-Zertifikate) |
| `USERAUTH_OIDC_BUTTON_TEXT` | `button_text` | `Mit IT-Hilbert anmelden` |
| `USERAUTH_OIDC_AUTO_CREATE` | `auto_create` | `false` |
| `USERAUTH_OIDC_DEFAULT_ROLE` | `default_role` | `user` |
| `USERAUTH_OIDC_GROUP_ROLES` | `group_roles` | leer; Format `gruppe=rolle,gruppe2=rolle2` (z. B. `admins=admin`) |
| `USERAUTH_OIDC_AUTO_REDIRECT` | `auto_redirect` | `false` |

„Konfiguriert“ heißt: `enabled` und `issuer`, `client_id`, `client_secret` nicht leer.

## Akzeptanzkriterien
- **AK1** Gegeben `userauth.oidc.enabled = false` (Standard), wenn `GET /auth/oidc/redirect` oder `GET /auth/oidc/callback` aufgerufen wird, dann antwortet die Anwendung mit 404, die Login-Seite zeigt keinen OIDC-Knopf, und `GET /login` und `POST /login` verhalten sich wie vor der Änderung.
- **AK2** Gegeben OIDC aktiv und konfiguriert, wenn ein Gast `GET /login` aufruft, dann zeigt die Seite einen Link auf `userauth.oidc.redirect` mit dem Text aus `button_text` (Standard „Mit IT-Hilbert anmelden“), und das lokale Anmeldeformular bleibt sichtbar. Gegeben OIDC aktiv, aber nicht konfiguriert, dann erscheint kein Knopf, und `GET /auth/oidc/redirect` leitet auf `login?lokal=1` mit einer allgemeinen Fehlermeldung (ohne Wert der Konfiguration).
- **AK3** Gegeben OIDC aktiv und konfiguriert, wenn `GET /auth/oidc/redirect` aufgerufen wird, dann liest die Anwendung die Endpunkte per Discovery (`<issuer>/.well-known/openid-configuration`), leitet auf den `authorization_endpoint` weiter mit den Parametern `response_type=code`, `client_id`, `redirect_uri` (= `route('userauth.oidc.callback')`), `scope=openid profile email groups`, `state` und `nonce`, und legt `state` und `nonce` in der Session ab. Antwortet die Discovery nicht oder ohne `authorization_endpoint`, `token_endpoint`, `jwks_uri`, `userinfo_endpoint`, dann leitet die Anwendung auf `login?lokal=1` mit einer allgemeinen Fehlermeldung.
- **AK4** Gegeben ein Callback, wenn `state` fehlt, nicht zum Wert in der Session passt oder die Session keinen `state` hat, dann erfolgt keine Anmeldung, der Token-Endpunkt wird nicht aufgerufen, und die Anwendung leitet auf `login?lokal=1` mit Fehlermeldung. Der `state` der Session ist nach dem Callback verbraucht (zweiter Callback mit demselben `state` scheitert).
- **AK5** Gegeben ein Callback mit Parameter `error` (z. B. `access_denied`) oder ohne `code`, dann erfolgt keine Anmeldung, der Token-Endpunkt wird nicht aufgerufen, und die Anwendung leitet auf `login?lokal=1` mit Fehlermeldung.
- **AK6** Gegeben ein gültiger Callback, wenn der Code getauscht wird, dann geht ein `POST` an den `token_endpoint` mit den Feldern `grant_type=authorization_code`, `code`, `redirect_uri`, `client_id`, `client_secret` (Form-Body, `client_secret_post`); ist `ca_bundle` gesetzt, dient der Pfad als Guzzle-Option `verify` (für alle Aufrufe an den Anbieter: Discovery, Token, JWKS, Userinfo), sonst gelten die Standard-Zertifikate. Antwortet der Token-Endpunkt mit Fehler, ohne `id_token` oder ohne `access_token`, dann keine Anmeldung, Weiterleitung auf `login?lokal=1` mit allgemeiner Fehlermeldung.
- **AK7** Gegeben ein `id_token`, dann wird es geprüft: Signatur RS256 gegen den Schlüssel aus `jwks_uri` (Auswahl über `kid`), `iss` = `issuer`, `aud` enthält `client_id`, `exp` nicht abgelaufen (Toleranz 60 s), `nonce` = Session-Wert. Jede Verletzung (falsche Signatur, anderer Algorithmus wie `none` oder `HS256`, falscher `iss`, falsche `aud`, abgelaufen, fehlende oder falsche `nonce`, unbekannte `kid`) führt zu keiner Anmeldung und zur Weiterleitung auf `login?lokal=1` mit allgemeiner Fehlermeldung. Die Session-`nonce` ist nach dem Callback verbraucht.
- **AK8** Gegeben ein gültiges `id_token`, wenn die Identität gebildet wird, dann ergänzt die Anwendung die Claims aus dem Userinfo-Endpunkt (`GET` mit `Authorization: Bearer <access_token>`); `sub` des Userinfo muss dem `sub` des `id_token` entsprechen (sonst keine Anmeldung). Claims aus dem `id_token` und dem Userinfo werden zusammengeführt (Userinfo ergänzt, überschreibt `sub` nie). Die Identität nutzt `sub` als Kennung, `email`, Anzeigename aus `name`, sonst `preferred_username`, und `groups`. Fehlt `email` oder ist `email_verified` ausdrücklich `false`, dann keine Anmeldung und Fehlermeldung. Ein fehlendes `email_verified` gilt als bestätigt (der Anbieter ist der eigene Anmeldedienst).
- **AK9** Gegeben `sub` ist in `user_social_accounts` mit Anbieter `oidc` verknüpft und der Benutzer ist aktiv, dann wird dieser Benutzer angemeldet (unabhängig von der E-Mail). Ist der verknüpfte Benutzer deaktiviert oder gelöscht, dann keine Anmeldung und Fehlermeldung.
- **AK10** Gegeben `sub` ist nicht verknüpft und genau ein aktiver Benutzer hat die E-Mail (ohne Beachtung der Groß-/Kleinschreibung wie beim Social Login), dann wird die Verknüpfung in `user_social_accounts` (Anbieter `oidc`) angelegt und der Benutzer angemeldet, ohne Bestätigungsmail (auch wenn der Benutzer lokal nicht als bestätigt gilt). Haben mehrere aktive Benutzer dieselbe E-Mail, dann keine Verknüpfung und keine Anmeldung, Fehlermeldung. Hat der Benutzer schon eine andere `oidc`-Verknüpfung (anderer `sub`), dann keine Anmeldung, Fehlermeldung.
- **AK11** Gegeben `sub` unbekannt und keine passende E-Mail: bei `auto_create = false` (Standard) keine Anmeldung und Fehlermeldung „Konto nicht vorhanden“; bei `auto_create = true` wird ein bestätigter Benutzer mit Name und E-Mail der Identität angelegt, mit `oidc` verknüpft und angemeldet. Gehört die E-Mail einem gelöschten Benutzer, dann wird nichts angelegt und angemeldet (Fehlermeldung, wie „Konto deaktiviert“ beim Social Login).
- **AK12** Gegeben `group_roles = admins=admin`, wenn ein Benutzer per `auto_create` angelegt wird, dessen `groups` `admins` enthalten, dann erhält er die Rolle `admin`; bei mehreren passenden Gruppen gilt die erste Zuordnung in der Reihenfolge von `group_roles`; ohne passende Gruppe oder wenn die Rolle in `roles` nicht existiert, gilt `default_role`. Bei bestehenden Benutzern (AK9, AK10) werden Rollen nie geändert.
- **AK13** Gegeben `userauth.two_factor_enabled = true` und ein Benutzer, der sich per OIDC anmeldet, dann wird kein Zwei-Faktor-Code verlangt und der Benutzer ist sofort angemeldet. Die Anmeldung mit Passwort verlangt weiter den Code.
- **AK14** Gegeben eine erfolgreiche OIDC-Anmeldung, dann ist die Session neu erzeugt (kein Session-Fixation), der Benutzer ist am Standard-Guard angemeldet, und die Weiterleitung geht an die vorgesehene Adresse (`intended`, sonst `userauth.redirect_after_login`). Das Login-Ereignis (Audit-Log) entsteht wie bei der Anmeldung mit Passwort.
- **AK15** Gegeben `auto_redirect = true`, OIDC aktiv und konfiguriert, wenn ein Gast `GET /login` ohne `lokal=1` aufruft, dann leitet die Anwendung auf `userauth.oidc.redirect` weiter; mit `?lokal=1` erscheint das Formular samt Knopf; bei inaktivem oder nicht konfiguriertem OIDC erscheint das Formular. Angemeldete Benutzer und `POST /login` sind nicht betroffen. Alle Fehlerweiterleitungen der AK2 bis AK11 gehen auf `login?lokal=1`, damit keine Weiterleitungsschleife entsteht.
- **AK16** Gegeben ein Fehler im OIDC-Ablauf (AK2 bis AK11), dann steht weder das `client_secret` noch ein Token noch ein Code in der Fehlermeldung, im Log oder in der Antwort. Die Meldungen sind übersetzt (de und en) und allgemein gehalten; das Log nennt die Fehlerart (z. B. „Signatur ungültig“), nie Tokenwerte.
- **AK17** Gegeben die Routen `GET /auth/oidc/redirect` und `GET /auth/oidc/callback`, dann tragen sie die Namen `userauth.oidc.redirect` und `userauth.oidc.callback`, sind gedrosselt (wie die Social-Routen) und gehen den Routen `auth/{provider}/…` vor (Pfad `/auth/oidc/callback` ist fest). Die Routen sind immer registriert und liefern zur Laufzeit 404 bei `enabled = false` (Wirkung ohne neuen Routen-Cache).
- **AK18** Gegeben `composer.json`, `config.php`, README und CHANGELOG, dann steht `firebase/php-jwt` (`^6.4 || ^7.0`) und `guzzlehttp/guzzle` in `require`, `config.php` enthält den Block `oidc` mit allen Schlüsseln der Tabelle, die README hat den Abschnitt „Anmeldung über Authelia“ (alle `.env`-Werte, Redirect-URI, Hinweis zur CA `lan-root-ca`/`USERAUTH_OIDC_CA_BUNDLE`, lokale Anmeldung als Rückfall, `?lokal=1`), der CHANGELOG nennt die Änderung.
- **AK19** Gegeben der Aufbau des Pakets, dann folgt OIDC der Schichtung des Social Logins (Port in `Application`, Adapter in `Infrastructure`, Anwendungsfall `HandleOidcLogin`, Controller dünn); der bestehende Schichtentest `SocialLoginLayerDependencyTest` bleibt grün und deckt die neuen Klassen ab.

## Randfälle
- Randfall 1: `groups` fehlt oder ist kein Array → leere Gruppenliste, keine Fehlermeldung.
- Randfall 2: `aud` im `id_token` ist ein Array → gültig, wenn `client_id` enthalten ist.
- Randfall 3: `ca_bundle` zeigt auf eine nicht vorhandene Datei → allgemeine Fehlermeldung beim Anmelden (kein Absturz mit 500).
- Randfall 4: Ein Anbieter-Wert `oidc` in `user_social_accounts` erscheint nicht als „Verbinden“-Anbieter auf der Social-Konten-Seite und nicht in `ListAvailableSocialProviders`; die Social-Routen mit `{provider}=oidc` liefern 404.
- Randfall 5: Angemeldeter Benutzer ruft den Callback auf → keine Verknüpfung, Weiterleitung an `redirect_after_login`.

## Nicht-Ziele
- Kein PKCE, kein Caching von Discovery und JWKS, kein Refresh-Token.
- Keine Abmeldung am Anbieter (Single Logout, Back-Channel): Abmelden bleibt lokal.
- Keine Rollenabgleichung bei jeder Anmeldung, nur bei Neuanlage.
- Keine Änderung an Host-Apps und deren `.env`/Zertifikaten: das sind Folge-Tasks je Projekt.
- Keine neue Datenbankmigration (Anbieter `oidc` passt in die vorhandene Spalte, Prüfung im Plan T1).

## Entscheidungsspielraum
Klassennamen, Aufteilung in Klassen (Port `OidcIdentityProvider`, Adapter mit Guzzle und `Firebase\JWT\JWT`/`JWK`), Übersetzungsschlüssel, interne DTOs. Erweiterung von `SocialProvider` um `Oidc` ist erlaubt, wenn Randfall 4 hält. Keine neuen Pakete außer `firebase/php-jwt` (liegt schon über Socialite im Projekt) und Guzzle.
