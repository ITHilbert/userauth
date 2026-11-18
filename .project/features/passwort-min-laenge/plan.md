# Plan: passwort-min-laenge

Marketing: entfällt (kein Seitendesign)
Review: entfällt (Config-Wert in bestehender Validierung, keine Route, keine Migration; Sicherheitsbezug nur Verschärfung per Opt-in, Standard unverändert)

## T1 [pro] Mindestlänge aus Config (AK1–AK5)
— Tests: tests/Feature/PasswortMinLaengeTest.php
Bestehende Tests: tests/Feature/PasswortAendernAktuellesPasswortTest.php tests/Feature/AdminPasswortTest.php tests/Feature/Haertung/PasswortResetTest.php tests/Feature/ErstpasswortTest.php

Stand: Spec geschrieben, Test-Writer als Nächstes.
Offener Punkt aa7bdc8b34d0b6c79: keine
Abweichung aa7bdc8b34d0b6c79: Fehlalarm: keine Abweichung gemeldet
Annahme im Code: keine neuen Annahme-Kommentare
Echtlauf: Request-Validierung per Feature-Tests (Ändern, Reset, Admin, Registrierung) mit echtem HTTP-Stack, keine Oberfläche geändert
Stand: T1 umgesetzt, GATE grün (120 Tests), BenutzerbildTest (GATE-Warnung) separat geprüft.
