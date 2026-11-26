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
