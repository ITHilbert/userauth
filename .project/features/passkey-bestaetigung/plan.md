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

Offener Punkt a7a64d70c8cc6c1bd: 1 Widerspruch Konfigurationstest AK19 (passkey-login) ↔ Standard 10: Spec ergänzt (AK13/Plan T3: test-writer erweitert die Schlüsselliste um bestaetigung_minuten) | 2 Drosselung mit Präfix: AK-fremd (Umsetzungsdetail von AK4, umkehrbar) | 3 Randfall 1 Meldung für alle Benutzer: AK-fremd (Reviewer sichtet Wortlaut) | 4 redirect mit VerbietetImpersonate für Gäste: AK-fremd (greift nur bei impersonated_by)
Abweichung a7a64d70c8cc6c1bd: Spec ergänzt (AK13 in spec.md, Commit mit diesem Stand; Test vom test-writer vor dem Review)
Annahme im Code: src/Routes/web.php:199 → trifft nicht zu, weil nur Erläuterung (Gäste melden sich dort an), Test SocialImpersonateTest Gast-Fall | src/Application/Passkey/Contracts/PasskeyBestaetigungen.php:8 → Test PasskeyBestaetigungTest Randfall 2 | src/Application/Passkey/PasskeyBestaetigungVorbereiten.php:16 → Test PasskeyBestaetigungAssertionTest AK5
Stand: T1 umgesetzt (GATE grün, 104 Tests), T2/T3 offen, AK13 braucht Test.
