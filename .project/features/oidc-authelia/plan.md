# Plan: oidc-authelia (#2204)

Marketing: entfällt (keine neue Seite, nur ein Knopf auf der vorhandenen Login-Seite)
Design: entfällt (kein Redesign)
PROJEKT: /home/joerg/projekte/packages_dev/packages/userauth
Branch: `feature/oidc-authelia` (Paket-Repo, von `feature/kern-umstellung`), Host-Stapel `stapel/2026-11-26-1316`

## T1 [senior] OIDC-Anmeldung (AK1–AK19, Randfälle 1–5)
— Tests: tests/Feature/Oidc/OidcLoginSeiteTest.php tests/Feature/Oidc/OidcAblaufTest.php tests/Feature/Oidc/OidcIdentityProviderTest.php tests/Feature/Oidc/OidcZuordnungTest.php
Bestehende Tests: tests/Feature/SocialLogin/SocialLoginLayerDependencyTest.php tests/Feature/SocialLogin/SocialLoginAvailabilityTest.php tests/Feature/SocialLogin/SocialLoginRoutesTest.php tests/Feature/SocialLogin/SocialAccountManagementTest.php tests/Feature/PasswordLoginTwoFactorTest.php

Stand: Spec und Plan geschrieben, selbst freigegeben (Automode).

Abweichung a674a96613dabafad: Spec ergänzt (b74fdb7, AK20; Test folgt vom test-writer)
Offener Punkt a674a96613dabafad: AK-fremd (Info-Mail „Konto verknüpft“ entfällt bei oidc, vom Test AK13 so verlangt; Audit-Eintrag bleibt)
Offener Punkt a674a96613dabafad: AK-fremd (fehlende default_role bei auto_create → allgemeine Fehlermeldung statt 500, gewollt)
Offener Punkt a674a96613dabafad: AK-fremd (gelöschter Benutzer mit gleicher E-Mail wird abgelehnt, wörtlich AK11)
Offener Punkt a674a96613dabafad: AK-fremd (Drossel-Zähler mit Social-Routen geteilt, wie diese untereinander)
Offener Punkt a674a96613dabafad: AK-fremd (Discovery-issuer nicht gegen Config verglichen; id_token-iss wird geprüft, AK7)
Offener Punkt a674a96613dabafad: AK-fremd (Kontenseite zeigt oidc-Verknüpfung nicht an, Randfall 4)
Annahme im Code: src/Http/ViewComposers/OidcKnopfComposer.php:12 → Test OidcLoginSeiteTest (Knopf nur bei aktiv und konfiguriert, AK2)
Layout: entfällt (nur ein Link-Knopf mit vorhandenen Klassen, feste Beschriftung; Hinweis des GATE betrifft afterLogin.blade.php, nicht geändert)
Echtlauf: browser: Host-Server auf Port 8147 mit OIDC-Env und CA-Bundle (mkcert): /login zeigt „Mit IT-Hilbert anmelden“ mit Link auf /auth/oidc/redirect; Redirect liefert 302 auf https://auth.lan/api/oidc/authorization mit state, nonce, scope; Callback mit falschem state → 302 auf /login?lokal=1. Vollständiger Anmeldelauf gegen Authelia nicht möglich (kein Client-Secret).
Review: Pflicht (Risiko kritisch, Anmeldung, neue Routen)
Stand: T1 umgesetzt, GATE grün (164 Tests), AK20 wartet auf Test.
Review 0/2: 1 muss (formal, Rechtegrenze-Zeile in der Spec, behoben), 0 sollte, 3 kann; Nachprüfung: Hook grenze Exit 0

## T2 [pro] Auto-Redirect als Standard, Abmeldeseite (AK21–AK23, #2226)
— Tests: tests/Feature/Oidc/OidcAutoRedirectStandardTest.php
Bestehende Tests: tests/Feature/Oidc/OidcLoginSeiteTest.php tests/Feature/Oidc/OidcAblaufTest.php tests/Feature/PasswordLoginTwoFactorTest.php
Hinweis: `test_ak18_config_enthaelt_den_block_oidc…` in OidcLoginSeiteTest prüft Standard `auto_redirect = false` und muss vom test-writer auf `true` angepasst werden (Test gehört T2 mit).
Branch: `feature/oidc-auto-redirect` (Paket-Repo, von main), Host-Stapel `stapel/2026-11-26-1717`. Spec-Lücke zu oidc-authelia, selbst freigegeben (Automode, Auftrag Joerg Telegram 922/924).
Review: Pflicht (Anmeldung, Sicherheitsbezug)
Stand: Spec ergänzt (AK21–AK23), Tests folgen.
Annahme im Code: src/Config/config.php:114 → Test OidcAutoRedirectStandardTest (Standard true wirkt nur bei aktivem, konfiguriertem OIDC, AK21)
Layout: entfällt (nur Hinweiskasten mit vorhandenen Klassen, feste Beschriftung)
Echtlauf: browser: nicht möglich (kein Echtlauf-Login im Paket-Host); ersatzweise Host-Server Port 8148 mit OIDC-Env per curl: GET /login → 302 /auth/oidc/redirect; /login?lokal=1 → 200 mit Knopf und Passwortfeld; /login?abgemeldet=1 → 200 mit „Sie wurden abgemeldet“, Knopf und Formular; /auth/oidc/redirect (auth.lan hier nicht erreichbar) → 302 /login?lokal=1 (keine Schleife).
Abweichung aa84aca11dae2da93: keine
Offener Punkt aa84aca11dae2da93: keine
Stand: T2 umgesetzt, GATE grün (105 Tests), Suite Oidc/SocialLogin 181 grün. Review am Ende des Stapels.
Review 0/2 (#2226): 1 muss (Passkey-Test rot durch neuen Standard), 0 sollte, 3 kann — pruefung-2226.md
Entscheidung (selbst, Automode): Auto-Redirect hat Vorrang vor dem Passkey-Knopf; mit OIDC und Passkey erreicht man den Passkey-Knopf über /login?lokal=1 (oder ?abgemeldet=1); Betreiber stellen USERAUTH_OIDC_AUTO_REDIRECT=false, wenn Passkey direkt auf /login stehen soll. Umkehrbar per Env. Test-Aufbau PasskeyLoginSeiteTest ausdrücklich auf auto_redirect=false (test-writer ab73f399f7b38a999).
Stand: Muss-Fund behoben (nur Test-Aufbau), ganze Suite läuft einmal im Schritt 5; keine zweite Nachprüfung (reine Testanpassung, Produktivcode unverändert).
