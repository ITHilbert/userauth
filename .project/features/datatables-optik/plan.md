# Plan: datatables-optik

Marketing: entfällt (Optik im bestehenden Kit-Design, keine neue Seite)

## T1 [pro] Views, Layout-Option, CSS (AK1–AK5)
— Tests: tests/Feature/DatatablesOptikTest.php
Bestehende Tests: tests/Feature/AdminViewsKitTest.php tests/Feature/AdminViewsSkripteTest.php tests/Feature/VerwaltungNacharbeitTest.php

Stand: Spec geschrieben, Test-Writer als Nächstes.
Rot-Nachweis: 19 rot / 3 grün (AK5 sichert Bestand), Meldungen nennen fehlendes Verhalten (layout topStart null statt search, dom fehlt, kein .userauth-listenkopf, className fehlt)
Echtlauf: browser: Sichtprüfung Desktop und 375 px nach dem Coder
Review: entfällt (nur Optik und Layout-Option, keine Route/Migration/Sicherheit, unter 50 Zeilen PHP/JS erwartet; CSS-Anteil Sichtprüfung)
