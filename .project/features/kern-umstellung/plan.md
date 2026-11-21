# Plan: kern-umstellung (userauth 4.0.0, #1619)

Marketing: entfällt (kein Seitendesign im Sinn von Landingpage, Optik folgt dem Kit-Theme)
Review: ja (neue Route, Darstellungs-AK, Major-Version)

## T1 [senior] Verwaltungs-CSS, Route, Einbindung, Theme-Hinweis (AK1, AK2, AK3, AK9)
— Tests: tests/Feature/VerwaltungCssTest.php
Bestehende Tests: tests/Feature/AuthViewAssetsTest.php tests/Feature/AdminViewsSkripteTest.php

## T2 [pro] Verwaltungsseiten auf kit-/userauth-Klassen, Zeilenbuttons, Passwort-Knöpfe (AK4, AK5, AK6, AK10)
— Tests: tests/Feature/VerwaltungKernMarkupTest.php
Bestehende Tests: tests/Feature/AdminViewsKitTest.php tests/Feature/AdminViewsSkripteTest.php tests/Feature/PasswortErzeugenTest.php tests/Feature/RolePermissionTest.php tests/Feature/DeleteAnzeigeTest.php

## T3 [pro] Lösch-Dialog ohne Vue-Insel (AK7, AK8)
— Tests: tests/Feature/LoeschDialogTest.php tests/js/loeschen-dialog.test.mjs
Bestehende Tests: tests/Feature/AdminViewsSkripteTest.php

## T4 [junior] composer.json, README, CHANGELOG (AK11)
— Tests: tests/Feature/KernDokuTest.php
Bestehende Tests: tests/Feature/DatatableSprachdateiTest.php

Reihenfolge: T1 → T2 → T3 → T4. Der test-writer schreibt alle Testdateien in einem Lauf (Spec fertig, AK-Sätze nicht nachträglich ändern).
Stand: Spec geschrieben, Test-Writer als Nächstes.
