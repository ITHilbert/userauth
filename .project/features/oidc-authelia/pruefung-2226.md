# Prüfung: oidc-authelia, AK21–AK23 (#2226, T2) — 2026-11-26
Umfang: nur AK21, AK22, AK23. Diff Paket-Repo `aeef2c9...fe30326` (Branch `feature/oidc-auto-redirect`), Host-Stapel `stapel/2026-11-26-1717`. Die übrigen AKs stehen in `pruefung.md` und wurden nicht erneut geprüft.

| AK / Aussage (Zitat) | Ergebnis | Nachweis |
|---|---|---|
| AK21 „Gegeben OIDC aktiv und konfiguriert und `USERAUTH_OIDC_AUTO_REDIRECT` nicht gesetzt (Standard `true`), wenn ein Gast `GET /login` ohne `lokal=1` aufruft, dann leitet die Anwendung auf `userauth.oidc.redirect` weiter“ | bestanden | `test_ak21_standard_leitet_gast_weiter_und_env_false_zeigt_das_formular` grün, Standard kommt aus `src/Config/config.php` (`env(..., true)`), nicht aus dem Testaufbau. Eigener Server Port 8149 mit OIDC-Env ohne `USERAUTH_OIDC_AUTO_REDIRECT`: `GET /login` → 302 `/auth/oidc/redirect` |
| AK21 „ist `USERAUTH_OIDC_AUTO_REDIRECT=false` gesetzt, dann erscheint das Formular samt Knopf“ | bestanden | derselbe Test mit `putenv(...=false)` und Laden der Config-Datei: 200, Link auf `userauth.oidc.redirect` und `name="password"` |
| AK21 „Ohne aktives, konfiguriertes OIDC bleibt `GET /login` unverändert (Formular).“ | bestanden | `test_ak21_ohne_aktives_oidc_bleibt_login_unveraendert_trotz_standard` (enabled aus, Gegenprobe vorher 302); fehlende Pflichtwerte: `test_ak15_auto_redirect_greift_nicht_ohne_aktives_konfiguriertes_oidc` grün; Code `OidcVerfuegbarkeit::automatischWeiterleiten()` = `autoRedirect() && enabled && isConfigured()` |
| AK21 „Die Ziel-Adresse (`intended`) bleibt über die Weiterleitung erhalten: ruft ein Gast eine geschützte Seite auf und meldet sich per OIDC an, dann landet er nach dem Callback auf dieser Seite.“ | bestanden | `test_ak21_ziel_adresse_bleibt_ueber_die_weiterleitung_erhalten`: geschützte Route → /login → OIDC-Start → Callback → 302 auf `/_t/geschuetzt`, angemeldet |
| AK22 „Gegeben Auto-Redirect aktiv …, wenn ein Benutzer sich abmeldet (`POST /logout` …), dann leitet die Anwendung auf `login?abgemeldet=1` weiter statt auf `userauth.redirect_after_logout`.“ | bestanden | `test_ak22_abmelden_fuehrt_mit_auto_redirect_auf_abgemeldet_sonst_wie_bisher` (angemeldet: 302 `login?abgemeldet=1`, danach Gast); Server Port 8149: `POST /logout` → 302 `/login?abgemeldet=1` |
| AK22 „auch als Gast“ | bestanden | derselbe Test, zweiter `POST /logout` ohne Anmeldung → `login?abgemeldet=1`; Server Port 8149 als Gast ebenso |
| AK22 „bzw. beim Abbruch der Zwei-Faktor-Anmeldung“ | bestanden | Der Feature-Test hat keine offene 2FA-Anmeldung. Selbst bestätigt mit Sonde im Scratchpad (Passwort-Login mit `two_factor_enabled` → `verify.index`, dann `POST /logout` → `login?abgemeldet=1`, Gast; danach `?abgemeldet=1` → 200). Abbruch-Knopf in `auth/twoFactor.blade.php:60` postet auf `route('logout')` |
| AK22 „Gegeben ein Gast ruft `GET /login?abgemeldet=1` auf, dann erfolgt keine automatische Weiterleitung zum Anbieter“ | bestanden | `test_ak22_abgemeldet_seite_leitet_nicht_weiter_und_zeigt_knopf_und_formular` (beide Themes, Gegenprobe `/login` → 302); Server: `?abgemeldet=1` → 200, `?abgemeldet=0` → 302 |
| AK22 „die Seite zeigt eine Abmeldemeldung (übersetzt de/en)“ | bestanden | `test_ak22_abmeldemeldung_ist_in_de_und_en_vorhanden_und_verschieden`; `lang/de/oidc.php` „Sie wurden abgemeldet.“, `lang/en/oidc.php` „You have been signed out.“; Server zeigt die de-Meldung |
| AK22 „den Knopf „Wieder anmelden“ (Link auf `userauth.oidc.redirect`, Text aus `button_text`)“ | bestanden | Test prüft `href` und `button_text`-Probe in beiden Themes; Server: Link `/auth/oidc/redirect` mit „Mit IT-Hilbert anmelden“. Lesart: „Wieder anmelden“ ist die Bezeichnung, die Beschriftung kommt laut Spec aus `button_text` (siehe kann) |
| AK22 „und das Anmeldeformular (wie `?lokal=1`)“ | bestanden | Test und Server: `name="password"` auf `?abgemeldet=1` |
| AK22 „Ist der Auto-Redirect nicht aktiv, dann gilt die Abmeldung wie bisher (`redirect_after_logout`).“ | bestanden | Gegenprobe im AK22-Test: `auto_redirect=false` → `/abschied` (angemeldet und Gast); ohne OIDC: Sonde und bestehende Tests (`Nachbesserung/PostRoutenTest`, `Haertung/ZweiFaktorTest`) grün |
| AK23 „README, CHANGELOG und `config.php` … beschreiben den neuen Standard (`USERAUTH_OIDC_AUTO_REDIRECT` = `true`, `false` schaltet ab)“ | bestanden | README Z. 684f. und Abschnitt „Lokale Anmeldung als Rückfall“, CHANGELOG 4.3.0, `config.php:114-117` gelesen; `test_ak23_…` grün |
| AK23 „den Notzugang `/login?lokal=1`“ | bestanden | in allen drei Dateien genannt |
| AK23 „die Abmeldeseite `?abgemeldet=1`“ | bestanden | in allen drei Dateien genannt |
| AK23 „und dass alle Fehler im OIDC-Ablauf auf `login?lokal=1` führen (keine Schleife)“ | bestanden | README Abschnitt „Lokale Anmeldung als Rückfall“ („alle Fehler im OIDC-Ablauf leiten dorthin (keine Weiterleitungsschleife)“); CHANGELOG 4.3.0 und `config.php` nennen es nicht eigens (Lesart: die drei Dateien zusammen, siehe kann). Der Test prüft diese Aussage nicht wörtlich, durch Lesen bestätigt. Verhalten: Server `/auth/oidc/redirect` (Anbieter nicht erreichbar) → 302 `/login?lokal=1` → 200 |
| AK23 „der CHANGELOG nennt Version 4.3.0“ | bestanden | `CHANGELOG.md` „## 4.3.0 – 2026-11-26“ |

## Sicherheit
- Open Redirect über `intended`: kein neuer Eingang. `url.intended` setzt nur Laravels `auth`-Middleware (volle URL der eigenen Anfrage); der neue Code leitet nur auf benannte Routen (`login`, `userauth.oidc.redirect`). `abgemeldet` wird nur als Boolean gelesen.
- Weiterleitungsschleifen: `lokal=1` und `abgemeldet=1` setzen die Auto-Weiterleitung aus; alle Fehlerwege im `OidcLoginController` führen auf `login?lokal=1` (Server-Probe: keine Schleife). Abmelden führt auf `?abgemeldet=1`, damit meldet der Anbieter nicht sofort wieder an.
- Logout: `AbmeldenHandler` läuft unverändert vor der neuen Weiterleitung; nach `POST /logout` ist der Benutzer Gast (Test und Sonde). Single Logout am Anbieter ist Nicht-Ziel.
- Ohne OIDC unverändert: `OidcVerfuegbarkeit` verlangt aktiv und konfiguriert; Logout ohne OIDC → `redirect_after_logout` (Sonde, bestehende Tests).
- Ausgabe-Escaping: Meldung kommt aus der Übersetzung, keine Benutzereingabe in der Ausgabe.
- Rechtegrenze: `php .claude/hooks/abdeckung.php grenze …/spec.md` Exit 0.
- **Passkey-Login beeinträchtigt:** siehe muss.

## Regressionen
Gelaufen im Platz mit `vendor/bin/phpunit -c phpunit.xml`:
- `packages/userauth/tests/Feature/Oidc` (107 Tests, grün, enthält `OidcAutoRedirectStandardTest`, `OidcLoginSeiteTest`, `OidcAblaufTest`)
- `packages/userauth/tests/Feature/PasswordLoginTwoFactorTest.php` (3, grün)
- `packages/userauth/tests/Feature/SocialLogin` (71, grün)
- Tests der berührten Dateien (`git grep` auf LoginController/logout/auto_redirect/Login-Views/oidc.php) plus Login-nahe Ordner: `AuditLogListenerTest`, `AuditLogStrictModeTest`, `GeloeschteEmailTest`, `Haertung/ZweiFaktorTest`, `Nachbesserung/PostRoutenTest`, `NurAdminTest`, `Registrierung/BestaetigungTest`, `Passkey/`, `Anmeldung/` — 511 Tests, **2 rot**: `Passkey/PasskeyLoginSeiteTest::test_ak2_ohne_passwort_login_bleibt_nur_der_passkey_knopf_mit_oidc_und_social` (@tailwind, @bootstrap), „Expected response status code [200] but received 302“ (Zeile 38/150). Einzellauf der Datei bestätigt (12 Tests, 2 rot).
- Sonde: Scratchpad `SondeZweiFaktorAbbruchTest.php` grün.
- Log: aktuelle `storage/logs/laravel*.log` im Platz ohne ERROR.

## Funde: 1 muss, 0 sollte, 3 kann
- muss: Regression im Passkey-Login. `PasskeyLoginSeiteTest::test_ak2_…_mit_oidc_und_social` (beide Themes) ist rot. Ursache: Der Test konfiguriert OIDC ohne `auto_redirect`; durch den neuen Standard `true` leitet `GET /login` auf den Anbieter weiter (302), die Passkey-Seite mit `passwort_login = nie` erscheint nicht. Fachlich heißt das: In jedem Projekt mit OIDC und Passkey ist der Passkey-Knopf auf `/login` nur noch über `?lokal=1`/`?abgemeldet=1` erreichbar — passkey-login AK2 („ohne Passwort-Login bleibt nur der Passkey-Knopf mit OIDC und Social“) und AK21 hier widersprechen sich, sobald beides an ist. Zu klären: entweder Test im Aufbau auf `auto_redirect=false` setzen (Lesart: Auto-Redirect geht vor Passkey) oder Auto-Redirect bei aktivem Passkey aussetzen — fachliche Entscheidung (Joerg), danach test-writer bzw. Coder.
- kann: Spec AK22 nennt den Knopf „Wieder anmelden“, die Beschriftung kommt aber aus `button_text` („Mit IT-Hilbert anmelden“). Umgesetzt nach dem Klammerzusatz; die Bezeichnung in der Spec könnte man klarer fassen.
- kann: AK23 „README, CHANGELOG und `config.php` beschreiben …“: Den Satz zu den Fehlerwegen („alle Fehler … auf `login?lokal=1`, keine Schleife“) nennt nur die README. Gelesen als „die drei zusammen“; bei strenger Lesart fehlt er in CHANGELOG 4.3.0 und `config.php`. Der AK23-Test prüft diesen Satz nicht.
- kann: Die Meldung „Sie wurden abgemeldet.“ erscheint bei jedem Aufruf von `/login?abgemeldet=1`, auch ohne vorherige Abmeldung (etwa als Lesezeichen). Harmlos, eine Session-Flash-Markierung wäre genauer.

## Neue Tasks: keine
(Der muss-Fund gehört in dieses Review; die kann-Funde stehen nur hier.)

## Fehlerprotokoll
- {"schwere":"muss","ak":"AK21","fund":"Neuer Standard auto_redirect=true macht PasskeyLoginSeiteTest AK2 rot; mit OIDC und Passkey leitet /login weiter, der Passkey-Knopf erscheint nicht","ursache":"Regressionslauf auf Oidc/SocialLogin beschränkt, Tests anderer Features, die OIDC konfigurieren (git grep oidc -- tests), liefen nicht; Wechselwirkung mit passkey-login AK2 nicht gegen dessen Spec geprüft","anweisung":"Beim Ändern eines Config-Standards alle Tests laufen lassen, die den Schlüssel oder den Block konfigurieren (git grep auf den Block in tests), und die Specs der betroffenen Features auf Widersprüche prüfen"}

## Fazit: nicht freigegeben
