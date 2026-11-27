# Plan: passwort-haeufige

Marketing: entfällt (kein Seitendesign)

## T1 [pro] Regel, Config, Sprachdateien, Liste (AK1–AK8)
— Tests: tests/Feature/PasswortHaeufigeTest.php
Bestehende Tests: tests/Feature/PasswortMinLaengeTest.php tests/Feature/PasswordHistoryRuleTest.php tests/Feature/AdminPasswortTest.php

Stand: Spec geschrieben, Liste angelegt, Test-Writer als Nächstes.
Review: entfällt (kein Sicherheitsrecht-Umbau: Opt-in-Zusatzregel in bestehender Validierung, Standard unverändert, keine Route, keine Migration; unter 50 Zeilen Produktivcode erwartet)
Echtlauf: Request-Validierung per Feature-Tests (Ändern, Reset, Registrierung, Admin) über echten HTTP-Stack, keine Oberfläche geändert
Stand: T1 umgesetzt, GATE grün (110 Tests), NACHWEIS: 64 Tests grün, BenutzerbildTest separat geprüft.
Offener Punkt afbed117dbf605cae: keine
Abweichung afbed117dbf605cae: Fehlalarm: keine Abweichung gemeldet
