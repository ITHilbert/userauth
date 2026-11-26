# Plan: passkey-bestaetigung (#2225)

Marketing: entfällt (keine neue Seite)
Design: entfällt (kein Redesign)
PROJEKT: /home/joerg/projekte/packages_dev/packages/userauth
Branch: `feature/passkey-reauth-2225` (Paket-Repo, von Sammel-Branch `stapel/2026-11-26-2303`)

## T1 [senior] Bestätigung per Passwort und Passkey, Middleware, Impersonate-Sperre Social (AK1–AK7, AK9, AK10, Randfälle 1–3)
— Tests: tests/Feature/Passkey/PasskeyBestaetigungTest.php tests/Feature/Passkey/PasskeyBestaetigungAssertionTest.php tests/Feature/SocialLogin/SocialImpersonateTest.php
Bestehende Tests: tests/Feature/Passkey/PasskeyRegistrierenTest.php tests/Feature/Passkey/PasskeyImpersonateTest.php tests/Feature/Passkey/PasskeySchichtungTest.php tests/Feature/Passkey/PasskeyKonfigurationTest.php tests/Feature/Passkey/PasskeyVerwaltungTest.php

## T2 [pro] Skript und Verwaltungsseite: Passwortabfrage, Passkey-Bestätigung (AK8)
— Tests: tests/Feature/Passkey/PasskeyBestaetigungSeiteTest.php tests/js/passkey-bestaetigung.test.mjs
Bestehende Tests: tests/Feature/Passkey/PasskeyVerwaltungTest.php

## T3 [junior] README, CHANGELOG, Konfiguration, Übersetzungen (AK12, AK1-Meldung de/en)
— Tests: tests/Feature/Passkey/PasskeyBestaetigungDokuTest.php
Bestehende Tests: tests/Feature/Passkey/PasskeyKonfigurationTest.php
