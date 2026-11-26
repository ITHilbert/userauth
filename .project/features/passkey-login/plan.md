# Plan: passkey-login (#2220)

Marketing: entfällt (keine neue Seite, ein Knopf auf der Login-Seite und eine Verwaltungsseite im vorhandenen Layout)
Design: entfällt (kein Redesign)
PROJEKT: /home/joerg/projekte/packages_dev/packages/userauth
Branch: `feature/passkey-login` (Paket-Repo, von `main`), Host-Stapel `stapel/2026-11-26-1520`
Abhängigkeit: `web-auth/webauthn-lib` ^5.3 muss in `composer.json` des Pakets und im Host (`composer require web-auth/webauthn-lib:^5.3` im Host-Ordner, die Pakete laden dort per Pfad) stehen; in der Laravel-13-Probe ohne Konflikt installierbar.

## T1 [senior] Kern: Tabelle, Port/Adapter, Anmelden und Registrieren (AK1, AK3–AK7, AK9, AK10, AK17, AK18, AK20, AK21, Randfälle 3–5)
— Tests: tests/Feature/Passkey/PasskeyKernTest.php tests/Feature/Passkey/PasskeyAnmeldenTest.php tests/Feature/Passkey/PasskeyRegistrierenTest.php tests/Feature/Passkey/PasskeySchichtungTest.php
Bestehende Tests: tests/Feature/Oidc/OidcAblaufTest.php tests/Feature/SocialLogin/SocialLoginLayerDependencyTest.php tests/Feature/PasswordLoginTwoFactorTest.php

## T2 [pro] Passwort-Modi, Login-Seite, Skript (AK2, AK12, AK13, AK14, Randfälle 1, 2)
— Tests: tests/Feature/Passkey/PasskeyLoginSeiteTest.php tests/Feature/Passkey/PasswortLoginModusTest.php
Bestehende Tests: tests/Feature/PasswordLoginTwoFactorTest.php tests/Feature/Oidc/OidcLoginSeiteTest.php tests/Feature/AuthViewAssetsTest.php

## T3 [pro] Verwaltung: Seite, Löschen, Admin-Reset, Artisan (AK8, AK11, AK15, AK16)
— Tests: tests/Feature/Passkey/PasskeyVerwaltungTest.php tests/Feature/Passkey/PasskeyZuruecksetzenTest.php

## T4 [junior] Config, README, CHANGELOG, composer.json (AK19)
— Tests: tests/Feature/Passkey/PasskeyKonfigurationTest.php

Stand: Spec und Plan geschrieben, selbst freigegeben (Automode).
