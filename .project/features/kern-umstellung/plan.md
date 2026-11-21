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
Offener Punkt a0ebaff2a3e7f96a4: 1 Spec ergänzt (AK4 um is-active/is-invalid/is-open, Commit 8752aea) | 2 Coder-Vorgabe (Partial loeschen nutzt nur {{ $titel }}, {{ $body }}, {{ $route }}, @csrf, @method, @lang, @json($…), keine @if/json_encode) | 3 Coder-Vorgabe (README: alter Upgrade-Abschnitt behält initTabs/initLoeschen/mountInseln, Beispiel dialog-delete entfällt) | 4 AK-fremd (Test benennt CSS kurz um, Umgebung) | 5 AK-fremd (Testumfang) | 6 AK-fremd (Dateigröße) | 7 AK-fremd (Test entfällt mit der Insel, vom Spec-Ziel gedeckt)
Abweichung a0ebaff2a3e7f96a4: Fehlalarm: keine Abweichung gemeldet
Grünbar: 3 Dateien offiziell (loeschen-dialog.test.mjs 7/7, KernDokuTest 4/4, DatatableSprachdateiTest 3/3); 6 PHP-Dateien (VerwaltungCss, VerwaltungKernMarkup, LoeschDialog, AdminViewsKit, AdminViewsSkripte, PasswortErzeugen) nicht über `gruenbar pruefen` führbar (Classmap des Hosts zeigt auf den Projektordner), vom Test-Writer mit überschriebener Classmap grün nachgewiesen; `gruenbar entfaellt` lehnte der Auto-Modus ab, nicht umgangen. Für Joerg: Task an Projekt claude (host_bootstrap überschreibt Classmap des Pakets).
Stand: Tests rot committet (8752aea), Coder T1 als Nächstes.
