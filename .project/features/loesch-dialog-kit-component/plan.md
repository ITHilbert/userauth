# Plan: loesch-dialog-kit-component (#2449)

Marketing: entfällt (kein Seitenentwurf, Austausch eines Bausteins)

## T1 [senior] Partial, Asset-Routen, CSS, composer, README, CHANGELOG (AK1–AK9)
— Tests: tests/Feature/LoeschDialogKitComponentTest.php tests/js/loeschen-dialog.test.mjs
Bestehende Tests: tests/Feature/AdminViewsKitTest.php tests/Feature/KernDokuTest.php tests/Feature/Nachbesserung/DialogDeleteTitelTest.php tests/Feature/LoeschDialogTest.php tests/Feature/VerwaltungCssTest.php

Stand: T1 umgesetzt, GATE GRÜN (412 PHP + 5 JS), ganze Paket-Suite grün, Review offen.
Offener Punkt torrent-packages_dev: keine
Echtlauf: entfällt (Fake-DOM-Test gegen das echte Kit-Modul, Rendern der Listen per Feature-Test; Browserlauf nicht möglich ohne Host-App mit Verwaltung)
Layout: entfällt (Dialog-Ebene per CSS-Test AK6 belegt, Browserlauf ohne Host-App mit Verwaltung nicht möglich; Sichtprüfung im Review)
Review: ja (neue Asset-Routen), Reviewer nach Umsetzung
