# Prüfung: passkey-login (#2220) — 2026-11-26

Geprüft: userauth `702672d..205886b` (Branch `feature/passkey-login`), Host `a197945...stapel/2026-11-26-1520` (composer.json/lock `web-auth/webauthn-lib` 5.3.17, Submodul-Stand). Bibliothek gelesen: `vendor/web-auth/webauthn-lib/src/CeremonyStep/*` (CheckAllowedOrigins, CheckUserHandle, CheckCounter, Reihenfolge in `CeremonyStepManagerFactory::requestCeremony`).

Echtlauf (eigener Headless Chrome, Port 0, eigenes Profil, **virtueller WebAuthn-Authenticator per CDP**, ctap2/internal, resident key, UV): `echtlauf start` im Platz mit `USERAUTH_PASSKEY_ENABLED=true USERAUTH_PASSKEY_RP_ID=localhost USERAUTH_PASSKEY_ORIGINS=http://localhost:8291`, Skript `scratchpad/passkey-cdp.mjs`:
1. Passwort-Anmeldung über das Formular → `/intern`.
2. `/passkey`: Name „Virtuell“, Knopf geklickt → `navigator.credentials.create` → Zeile „Virtuell angelegt am 26.11.2026 · noch nicht benutzt“; Authenticator hält 1 residente Anmeldeinformation.
3. Cookies gelöscht, `/login`, Knopf „Mit Passkey anmelden“ → `POST /passkey/login/options`, `POST /passkey/login` → `/intern`; `/passkey` zeigt „zuletzt benutzt am 26.11.2026“.
4. `navigator.credentials.get` wirft `NotAllowedError` → Meldung „Anmeldung abgebrochen.“ sichtbar, nur `POST …/options`, keine Anmelde-Anfrage, bleibt auf `/login`.
5. `window.PublicKeyCredential` fehlt → Knopf `disabled`, Hinweis „Dieser Browser unterstützt keine Passkeys.“ sichtbar, Passwortfeld nutzbar.
6. Authenticator verweigert Benutzerverifikation → „Anmeldung abgebrochen.“, keine Anmelde-Anfrage.
Konsolenfehler: 0. Danach Neustart ohne Passkey-Variablen (Abschnitt AK1).

| AK / Aussage (Zitat) | Ergebnis | Nachweis |
|---|---|---|
| AK1 „Gegeben `userauth.passkey.enabled = false` (Standard), wenn eine der Routen … aufgerufen wird, dann antwortet die Anwendung mit 404“ | bestanden | `PasskeyKernTest::test_ak1_…` grün; echter Server ohne Passkey: POST login/options, POST login, POST register/options, POST register, GET /passkey, DELETE /passkey/1 → je 404 |
| AK1 „die Login-Seite zeigt keinen Passkey-Knopf“ | bestanden | `PasskeyLoginSeiteTest::test_ak1_…`; curl /login: 0 Treffer „passkey“ |
| AK1 „`GET /login` und `POST /login` verhalten sich wie vor der Änderung“ | bestanden | Test AK1 und `PasswortLoginModusTest::test_ak13_immer_…`; Regressionen (705 Tests) grün |
| AK1 „Die Routen sind immer registriert (404 zur Laufzeit, Wirkung ohne neuen Routen-Cache)“ | bestanden | `Route::has` im Test; Middleware `EnsurePasskeyEnabled` liest die Config zur Request-Zeit |
| AK1 „die Middleware liefert den 404 vor der CSRF-Prüfung“ | bestanden | Echter Server (CSRF aktiv, kein Unit-Test-Bypass): POST ohne Token auf die Passkey-Routen → 404; Gegenprobe POST /login ohne Token → 419. `MiddlewarePrioritaet::SCHALTER` enthält `EnsurePasskeyEnabled` |
| AK2 „zeigt die Seite einen Knopf „Mit Passkey anmelden“ (übersetzt de/en)“ | bestanden | `PasskeyLoginSeiteTest::test_ak2_…` (beide Themes, en anders); Echtlauf Schritt 3 |
| AK2 „das Anmeldeformular bleibt sichtbar, solange `passwort_login` ≠ `nie`“ | bestanden | Test AK2; Echtlauf Schritt 1/5 (Formular nutzbar) |
| AK2 „bei `passwort_login = nie` zeigt die Seite kein Passwortformular (Passkey-Knopf, OIDC- und Social-Knöpfe bleiben)“ | bestanden | `test_ak2_ohne_passwort_login_bleibt_nur_der_passkey_knopf_mit_oidc_und_social`; View `@if ($passwortFormular ?? true)` |
| AK2 „Der Knopf löst das Skript `userauth/assets/passkey.js` aus (Route `userauth.assets.passkey`, ohne Session und Cookies, … nur mit aktivem Passkey erreichbar sonst 404)“ | bestanden | Tests AK2 Skript; curl: 200 `text/javascript`, kein `Set-Cookie`; ohne Passkey 404; Echtlauf: Klick löst die WebAuthn-Zeremonie aus |
| AK3 „antwortet die Anwendung mit JSON der WebAuthn-Optionen (`challenge`, `rpId`, `timeout`, `userVerification = required`, leere `allowCredentials`)“ | bestanden | `test_ak3_optionen_enthalten_…`; Adapter `anmeldeOptionen` (32 Byte Challenge) |
| AK3 „die Challenge liegt in der Sitzung (genau eine, ältere wird ersetzt)“ | bestanden | `test_ak3_zweite_challenge_ersetzt_die_erste` (erste Challenge danach abgelehnt, zweite gültig); `SessionPasskeyVorgaenge::merken` überschreibt den Schlüssel |
| AK3 „`rpId` ist `passkey.rp_id`, sonst der Host aus `app.url`“ | bestanden | Test AK3 (beide Fälle) |
| AK4 „… und die Prüfung gelingt — Challenge aus der Sitzung, Ursprung …, `rpIdHash`, Benutzer-Präsenz und Benutzerverifikation gesetzt, Signatur gültig …“ | bestanden | Echte ES256-Signatur in `PasskeyGeraet`; Bibliothek prüft CheckChallenge, CheckAllowedOrigins, CheckRelyingPartyIdIdHash, CheckUserWasPresent, CheckUserVerification, CheckSignature, CheckUserHandle; Echtlauf mit Chrome-Authenticator Schritt 3 |
| AK4 „dann ist der Besitzer der Anmeldeinformation am Standard-Guard angemeldet (Sitzung neu erzeugt)“ | bestanden | `test_ak4_gueltige_assertion_…` (neue Sitzungs-ID); `GuardSitzungsanmeldung::anmelden` → `SessionGuard::login` (migrate) |
| AK4 „die Antwort ist JSON mit der Zieladresse (`intended`, sonst `userauth.redirect_after_login`)“ | bestanden | `test_ak4_zieladresse_ist_intended_…` und Test AK4; Echtlauf Weiterleitung `/intern` |
| AK4 „`last_used_at` und der Signaturzähler sind gesetzt“ | bestanden | Test AK4 + Klon-Test (Zähler 8 nach 7 gültig); Echtlauf „zuletzt benutzt am“ |
| AK4 „das Login-Ereignis (Audit-Log) entsteht wie bei der Anmeldung mit Passwort“ | bestanden | Test AK4 (Event `Login`, Audit `login`) |
| AK5 „wird kein Zwei-Faktor-Code verlangt; die Anmeldung mit Passwort verlangt weiter den Code“ | bestanden | `test_ak5_…` (beide Hälften); `ohneZweitenFaktor` |
| AK6 „keine Anmeldung, … JSON mit Status 422 und einer allgemeinen übersetzten Meldung (ohne Details der Prüfung)“ | bestanden | `test_ak6_meldung_ist_allgemein_uebersetzt_und_fuer_alle_fehlerarten_gleich` (eine Meldung für alle Fälle, en anders) |
| AK6 „die Challenge der Sitzung ist verbraucht (zweiter Versuch mit derselben Challenge scheitert)“ | bestanden | `test_ak6_fehlerhafte_anmeldung_…` je Fall: zweiter Versuch mit derselben Challenge und gültiger Assertion abgelehnt, Gegenprobe mit neuer Challenge angemeldet; `entnehmen` per `session()->pull` vor jeder Prüfung (auch kein JSON) — Replay ausgeschlossen |
| AK6 Fälle „fehlende oder abgelaufene Challenge, falsche Challenge, falscher Ursprung, falsche `rpId`, ungültige Signatur, unbekannte `credential_id`, Benutzerverifikation nicht gesetzt, … Klon-Verdacht, kein JSON oder unvollständige Felder“ | bestanden | Datenprovider AK6 deckt jeden Fall; `test_ak6_challenge_gilt_fuenf_minuten`; `test_ak6_klon_verdacht_…` (7 und 3 nach 7 abgelehnt, Zähler 0 bleibt gültig); Echtlauf Schritt 6 (UV im Browser verweigert) |
| AK7 „deaktivierten oder gelöschten Benutzer oder einem Selbstregistrierten mit offener E-Mail-Bestätigung, dann keine Anmeldung (422 wie AK6) und kein Eintrag `last_used_at`“ | bestanden | `test_ak7_geloeschter_…`, `test_ak7_selbstregistrierter_…`; „deaktiviert“ = Soft Delete im Paket (kein eigenes Flag; `retrieveById` ohne gelöschte); Prüfung vor `nachAnmeldung` |
| AK7 „Gegenprobe: ein aktiver Benutzer mit gültiger Assertion wird angemeldet (AK4)“ | bestanden | in beiden AK7-Tests |
| AK8 „sieht er seine Passkeys (Name, angelegt am, zuletzt benutzt) und einen Bereich „Passkey hinzufügen“ mit Namensfeld“ | bestanden | `test_ak8_seite_zeigt_nur_eigene_…`; Echtlauf Schritt 2/3 |
| AK8 „Gäste werden wie bei anderen `auth`-Routen auf die Login-Seite geleitet“ | bestanden | `test_ak8_gast_wird_auf_die_login_seite_geleitet` |
| AK8 „Ein Benutzer sieht nie die Passkeys eines anderen.“ | bestanden | Test AK8 (fremder Passkey nicht in der Liste); Repository filtert `user_id` aus der Sitzung |
| AK9 „JSON der Registrierungsoptionen (`rp` …, `user` mit stabiler Benutzer-Handle, die keine E-Mail-Adresse enthält, `challenge`, `pubKeyCredParams` mindestens ES256 und RS256, `authenticatorSelection` …, `excludeCredentials` …, `attestation = none`)“ | bestanden | `test_ak9_optionen_…`, `test_ak9_rp_name_…`; Handle = HMAC-SHA256(app.key, Benutzer-ID) |
| AK9 „die Challenge liegt in der Sitzung“ | bestanden | Registrierung gelingt nur mit dieser Challenge (Test AK10) |
| AK10 „wird der Passkey zum Benutzer gespeichert (Name höchstens 100 Zeichen, leer = „Passkey“), die Challenge ist verbraucht, die Antwort ist JSON mit Erfolg“ | bestanden | `test_ak10_gueltige_antwort_…`, `test_ak10_name_hoechstens_100_…` (über 100 wird abgelehnt — zulässige Lesart); Echtlauf Schritt 2 |
| AK10 „Fehlerfälle (…): nichts gespeichert, 422 mit allgemeiner Meldung“ | bestanden | `test_ak10_fehlerhafte_registrierung_…` (jeder Fall, Tabelle leer) |
| AK10 „Ein Passkey wird immer nur dem angemeldeten Benutzer zugeordnet (nie einer Id aus der Anfrage)“ | bestanden | Test AK10 mit `user_id` im Body ignoriert; Vorgang an Benutzer-ID gebunden (`$vorgang->benutzer !== (string) $userId`). Siehe aber Fund muss 1 (Impersonate) |
| AK11 „dieser Passkey gelöscht und die Weiterleitung geht auf `userauth.passkey.index` mit Meldung“ | bestanden | `test_ak11_loeschen_entfernt_nur_diesen_passkey_…` (Gerät meldet danach nicht mehr an) |
| AK11 „die Id eines Passkeys eines anderen Benutzers liefert 404 und löscht nichts“ | bestanden | `test_ak11_passkey_eines_anderen_benutzers_liefert_404_…`; `loeschen` mit `where user_id` |
| AK11 „Löschen ist auch der letzte Passkey erlaubt; … zeigt die Seite vorher einen Warnhinweis, das Löschen wird nicht verhindert“ | bestanden | `test_ak11_warnhinweis_bei_nie_ohne_oidc_und_social_…` |
| AK12 „keine Anmeldung und dieselbe Meldung wie bei falschen Zugangsdaten für das Feld der Kennung (kein Hinweis, dass das Passwort stimmt); ein Benutzer ohne Passkey meldet sich weiter an“ | bestanden | `test_ak12_…`; Sperre in `MitPasswortAnmelden` vor Bestätigungs-Prüfung und vor 2FA (kein Code-Versand, keine „Bestätigung offen“-Meldung als Orakel) |
| AK12 „Gegenprobe im selben Test: derselbe Benutzer ohne Passkey wird angemeldet“ | bestanden | im selben Test |
| AK13 „keine Anmeldung (Meldung wie bei falschen Zugangsdaten), auch bei richtigen Zugangsdaten und auch für Benutzer ohne Passkey; die Login-Seite zeigt kein Passwortformular“ | bestanden | `test_ak13_nie_sperrt_…` und Test AK2 |
| AK13 „`immer` (Standard) und jeder unbekannte Wert verhalten sich wie vor der Änderung“ | bestanden | `test_ak13_immer_und_jeder_unbekannte_wert_…` |
| AK14 „bleibt er unverändert (er setzt ein Passwort, meldet aber nicht an); die Anmeldung danach unterliegt AK12/AK13“ | bestanden | `test_ak14_…`; `PasswordController::updatewithtoken` meldet nicht an (unverändert). Andere Wege gelesen: Passwort-Anmeldung läuft nur über `LoginController::login` → `MitPasswortAnmelden` (Trait-`attemptLogin` ungenutzt); Social/OIDC laut Spec ausgenommen; Impersonate setzt eine bestehende Admin-Sitzung voraus |
| AK14 „(Keine eigene Wiederherstellung per Mail in dieser Version, siehe Nicht-Ziele.)“ | bestanden | keine neue Mail-Route im Diff |
| AK15 „sind alle Passkeys dieses Benutzers gelöscht und das Audit-Log (wenn aktiv) vermerkt es“ | bestanden | `test_ak15_administrator_loescht_…` (auch ohne Audit kein Eintrag) |
| AK15 „die Bearbeiten-Seite des Benutzers zeigt die Anzahl der Passkeys und den Knopf nur bei aktivem Passkey und mindestens einem Passkey“ | bestanden | `test_ak15_bearbeiten_seite_…` |
| AK15 „Ohne Recht 403 wie bei den anderen Benutzer-Routen, nichts gelöscht“ | bestanden | `test_ak15_ohne_recht_user_edit_antwortet_403_…` (versucht den Zugriff wirklich); Route `hasPermission:user_edit` |
| AK16 „löscht er alle Passkeys des aktiven Benutzers mit dieser E-Mail und meldet die Anzahl; bei unbekannter oder mehrdeutiger E-Mail Fehlercode ungleich 0 und nichts gelöscht“ | bestanden | drei Tests AK16 |
| AK17 „tragen sie die Namen aus AK1“ | bestanden | `test_ak17_routen_tragen_die_namen_und_pfade` |
| AK17 „`passkey/login/options` und `passkey/login` sind für Gäste gedrosselt (`throttle:30,1` …)“ | bestanden | `test_ak17_login_routen_sind_gedrosselt_…` (429 spätestens beim 31.) |
| AK17 „nur für Gäste erreichbar (angemeldeter Benutzer ruft `POST /passkey/login` auf: keine Anmeldung als anderer Benutzer, Weiterleitung bzw. JSON auf `redirect_after_login`)“ | bestanden | `test_ak17_anmeldung_per_passkey_ist_nur_fuer_gaeste_erreichbar` (gültige Assertion eines Fremden, bleibt angemeldet als er selbst; Gegenprobe als Gast) |
| AK17 „die Verwaltungsrouten (`index`, `register*`, `destroy`) verlangen `auth`“ | bestanden | `test_ak17_verwaltungsrouten_verlangen_eine_anmeldung` |
| AK18 „steht weder die Challenge noch die Assertion noch der öffentliche Schlüssel in der Meldung oder im Log“ | bestanden | `test_ak18_…` (Anmeldung, Registrierung); Log-Kontext nur `fehlerart`; Bibliothekstexte weitergereicht sind feste Texte bzw. Algorithmus-Nummern (`CheckAlgorithm`), keine Geheimnisse |
| AK18 „das Log nennt nur die Fehlerart“ | bestanden | Test AK18 (Log nicht leer, Fehlerart enthalten) |
| AK19 „`web-auth/webauthn-lib` (`^5.3`) in `require`, `config.php` … `passkey` und … `passwort_login`, … README … Abschnitt „Anmeldung mit Passkey“ (…), der CHANGELOG nennt die Änderung; die Migration `user_passkeys` liegt im Paket und lädt mit den anderen“ | bestanden | `PasskeyKonfigurationTest` (5 Tests); README Z. 716–756 gelesen (HTTPS, rp_id, Zweitgerät, Artisan, drei Betriebsarten); Migration in `src/Database/Migrations`, `cascadeOnDelete`; Host composer.json/lock 5.3.17 |
| AK20 „Port in `Application`, Adapter mit `web-auth/webauthn-lib` in `Infrastructure`, Anwendungsfälle …, Controller dünn; die Domain und `Application` kennen keine Klassen von `Webauthn\`“ | bestanden | `PasskeySchichtungTest` (3 Tests, inkl. Gegenprobe des Suchmusters); Controller gelesen: nur UseCase-Aufruf und Antwort |
| AK21 „gelten Rechte, `hasPermission` und Impersonate unverändert (die Anmeldung geht über denselben Guard …)“ | bestanden | `test_ak21_…` |
| Randfall 1 „Browser ohne WebAuthn … der Knopf ist deaktiviert mit Hinweistext, das Passwortformular (wenn erlaubt) bleibt nutzbar“ | bestanden | Test nur angenähert (Quelltext); selbst im Browser bestätigt: Echtlauf Schritt 5 |
| Randfall 2 „Abbruch (`NotAllowedError`) → Meldung „Anmeldung abgebrochen“, keine Anfrage an den Server“ | bestanden | Test nur angenähert; Browser: Echtlauf Schritt 4 und 6 (nach dem Abbruch keine Anfrage an den Server) |
| Randfall 3 „Zwei Passkeys eines Benutzers → beide funktionieren, beide stehen in der Liste, die `credential_id` ist je Passkey eindeutig“ | bestanden | `test_randfall3_…`, Test AK8; Unique-Index |
| Randfall 4 „`origins` enthält mehrere Ursprünge → jeder davon ist gültig, jeder andere wird abgelehnt“ | bestanden | `test_randfall4_…`; `CheckAllowedOrigins` (exakter Abgleich Schema/Host/Port, Subdomains abgelehnt); Echtlauf mit Liste `http://localhost:8291` |
| Randfall 5 „Soft Delete oder echt … melden Passkeys den Benutzer nicht mehr an; bei echtem Löschen werden die Zeilen mitgelöscht“ | bestanden | `test_randfall5_…`, Test AK7 |

## Sicherheit
- **Rechtegrenze** (Spec `Risiko: kritisch`): `php .claude/hooks/abdeckung.php grenze …/spec.md` → Exit 0. Tests AK6/AK7 versuchen die Anmeldung wirklich (echte Signatur, fremder Schlüssel, falsche Challenge, Replay) und scheitern; Gegenproben melden an.
- **Replay/Challenge**: `session()->pull` vor jeder Prüfung, 5 Minuten, je Art (Anmeldung/Registrierung) getrennt; die Registrierungs-Challenge ist an die Benutzer-ID gebunden.
- **Ursprung/rpId**: Liste aus `passkey.origins` bzw. Ursprung aus `app.url`, `rpIdHash` gegen `rp_id`; HTTP nur, wenn der Ursprung exakt gelistet ist.
- **Klon-Zähler**: Standard-`ThrowExceptionIfInvalid`, `CounterException` → Ablehnung; Zähler 0/0 erlaubt (Spec).
- **Benutzer-Handle**: `CheckUserHandle` vergleicht die Handle der Antwort mit der gespeicherten (userHandle `null` übergeben → Antwort-Handle Pflicht).
- **Fixation**: `SessionGuard::login` erneuert die Sitzungs-ID (Test AK4).
- **Löschen/Reset**: eigene Passkeys nur mit `user_id` aus der Sitzung (404 sonst); Admin-Reset `hasPermission:user_edit` (403-Test).
- **CSRF**: alle POST/DELETE in der `web`-Gruppe; Skript sendet `X-CSRF-TOKEN`; 404 vor CSRF am echten Server belegt.
- **Escaping**: Views ohne `{!! !!}`, Name per `{{ }}`.
- **Mass-Assignment**: Insert mit festen Spalten, `user_id` nie aus der Anfrage.
- **Fund muss 1** (Impersonate, unten).

## Regressionen
- Feature: `vendor/bin/phpunit packages/userauth/tests/Feature/Passkey` → OK (99 Tests, 1478 Assertions).
- Bestehende und berührte (`php artisan test --compact`, 705 Tests grün): `tests/Feature/Oidc/` (OidcAblaufTest, OidcIdentityProviderTest, OidcLoginSeiteTest, OidcZuordnungTest), `tests/Feature/SocialLogin/` (alle, inkl. SocialLoginLayerDependencyTest, SocialSchalterVorCsrfTest), `tests/Feature/Registrierung/`, PasswordLoginTwoFactorTest, AuthViewAssetsTest, AdminViewsKitTest, KernDokuTest, PasswortErzeugenTest, ThemeFallbackTest, VerwaltungCssTest, VerwaltungNacharbeitTest, AuditLogListenerTest, AuditLogStrictModeTest, PasswortAendernAktuellesPasswortTest, ErstpasswortTest.
- Ganze Suite nicht (kein `SUITE: ja`).
- Log `storage/logs/laravel-2026-11-26.log` des Platzes: keine Fehler aus dem Echtlauf; Browser-Konsole 0 Fehler.

## Funde: 1 muss, 0 sollte, 3 kann
- muss: **Passkey hinzufügen unter Impersonate.** Wer mit Recht `impersonate_users` einen Benutzer übernimmt, kann über `POST /passkey/register/options` + `POST /passkey/register` einen Passkey auf **eigenem** Gerät zum Konto des Übernommenen eintragen (`PasskeyController::optionen/registrieren` nutzen `$request->user()` = Übernommener; `ImpersonateController.php:42–45` setzt `impersonated_by` und `Auth::login($targetUser)`; kein Code im Passkey-Pfad prüft `isImpersonated()`). Folge: dauerhafter Zugang zum fremden Konto nach „Impersonate verlassen“, ohne Impersonate-Spur (Anmeldung erscheint als normales `login` des Benutzers), und mit `passwort_login = ohne_passkey` ist das Passwort des Inhabers danach gesperrt. Die temporäre Support-Übernahme wird so zu einer dauerhaften Anmeldeinformation — Grenzüberschreitung bei Zugängen. Echte Grenze: `register/options` und `register` lehnen bei Session-Wert `impersonated_by` ab (403 bzw. 422 ohne Speichern), mit Test, der es unter Impersonate wirklich versucht, plus Gegenprobe ohne Impersonate. (Löschen unter Impersonate sinngemäß mit entscheiden.)
- kann: `passwort_login = ohne_passkey` sperrt das Passwort auch bei `passkey.enabled = false` (wörtliche Lesart AK12, im README vermerkt) — wer Passkeys abschaltet, sperrt Benutzer mit Passkey aus, bis Reset.
- kann: Audit-Eintrag `passkeys_reset` nennt nicht, welcher Administrator zurückgesetzt hat (Spec verlangt es nicht).
- kann: Drossel-Zähler `throttle:30,1` teilt sich den Schlüssel mit den OIDC-Routen (wie Social/OIDC untereinander).

## Neue Tasks: #2225 userauth Passkey – Hinzufügen nur nach erneuter Bestätigung (Re-Auth) und Social-Verknüpfung unter Impersonate prüfen (nachfragen, außerhalb der Spec)

## Fehlerprotokoll
- {"schwere":"muss","ak":"AK10","fund":"Unter Impersonate kann der Übernehmende einen eigenen Passkey zum Konto des übernommenen Benutzers registrieren und behält danach dauerhaften Zugang ohne Impersonate-Spur","ursache":"Rechtegrenze nur als 'Benutzer aus der Sitzung' gedacht; der Sonderfall einer übernommenen Sitzung (impersonated_by) wurde bei neuen Anmeldeinformationen nicht betrachtet","anweisung":"Bei jeder Funktion, die eine neue Anmeldeinformation oder einen neuen Anmeldeweg zu einem Konto hinzufügt (Passkey, Social-Verknüpfung, Passwort, Token), prüfen und testen, dass sie in einer Impersonate-Sitzung abgelehnt wird"}

## Fazit: nicht freigegeben (1 muss: Passkey-Registrierung unter Impersonate)
