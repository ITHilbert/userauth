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

Tests: 99 Fälle rot (73 Errors, 19 Failures, Meldungen nennen fehlende Routen, Tabelle, Verzeichnis, Config), Schichtungs-Gegenproben grün; Rot-Nachweis vom Moderator wiederholt 2026-11-26 15:37.
Entscheidung Moderator: T1–T4 laufen in einem Coder-Durchgang [senior] (gemeinsame Dateien Config, Views, Provider; Zeitgrenze des Laufs).

Offener Punkt a169527f199173f00: AK-fremd (Annahme ohne_passkey sperrt auch bei passkey.enabled=false, wörtlich AK12; Admin-Reset/Artisan hebt auf; in README und Für Joerg vermerkt)
Offener Punkt a169527f199173f00: AK-fremd (Name über 100 Zeichen wird abgelehnt statt gekürzt, AK10 lässt beides zu)
Offener Punkt a169527f199173f00: AK-fremd (credential_id base64url string(255), längere Kennungen abgelehnt)
Offener Punkt a169527f199173f00: AK-fremd (Benutzer-Handle HMAC der Benutzer-ID mit app.key, keine E-Mail, AK9)
Offener Punkt a169527f199173f00: AK-fremd (GET-Platzhalter auf passkey/register* wegen bestehendem RegistrierungTest)
Offener Punkt a169527f199173f00: AK-fremd (user.name der Registrierungsoptionen ist die E-Mail, nur für die Kontoauswahl am Gerät, nicht in der Handle)
Offener Punkt a169527f199173f00: AK-fremd (Drossel-Zähler mit OIDC-Routen geteilt, wie Social/OIDC untereinander)
Offener Punkt a169527f199173f00: AK-fremd (gesperrte Passwort-Anmeldung erzeugt kein failed-Audit-Ereignis, Zugangsdaten waren gültig)
Annahme im Code: src/Application/Passkey/PasskeyRegistrierungVorbereiten.php:16 → Test PasskeyRegistrierenTest (Vorgang gilt nur für den Benutzer, AK10)
Annahme im Code: src/Http/ViewComposers/PasskeyKnopfComposer.php:13 → Test PasskeyLoginSeiteTest (Knopf nur bei aktivem Passkey, AK2)
Layout: entfällt (Knopf und Verwaltungsliste mit vorhandenen Klassen, feste Beschriftungen, keine variablen Längen)
Echtlauf: browser: Host-Server Port 8150 mit USERAUTH_PASSKEY_ENABLED=true, Migration user_passkeys auf Entwicklungs-DB vorwärts gelaufen: /login zeigt „Mit Passkey anmelden“ (1 sichtbar, 0 Konsolenfehler), /userauth/assets/passkey.js 200 text/javascript, POST /passkey/login/options ohne CSRF-Token 419 (Seite trägt meta csrf-token, Skript sendet X-CSRF-TOKEN); der native WebAuthn-Dialog (Fingerabdruck) ist ohne Gerät nicht auslösbar, die Kryptoprüfung belegen die 99 Tests mit echter ES256-Signatur.
Review: Pflicht (Risiko kritisch, Anmeldung, neue Routen, Migration)
Stand: T1–T4 umgesetzt, GATE grün (200 Tests), Paket-Suite 2463 grün (Coder), userauth.css neu gebaut.

Abweichung Review aabe08b4a5894439b: Spec ergänzt (AK22, Impersonate-Sperre; Test folgt vom test-writer)
Review 0/2: 1 muss, 0 sollte, 3 kann
Review 0/2 behoben: Impersonate-Sperre (a47044ba629956520, GATE grün), Nachprüfung folgt.
