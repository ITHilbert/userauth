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
Offener Punkt ad156b0242ddd64f5: 1 AK-fremd (build:css würde userauth.css anders bauen; Spec: userauth.css ändert sich nicht, Datei bleibt zurückgesetzt) | 2 AK-fremd (NODE_PATH im Build-Skript, Entwicklerwerkzeug, README-Hinweis in T4) | 3 AK-fremd (password.edit und nopermission bekommen den CSS-Link ohne Preflight; Sichtprüfung im Echtlauf) | 4 AK-fremd (Wortlaut der Warnung liegt im Entscheidungsspielraum)
Abweichung ad156b0242ddd64f5: Fehlalarm: keine Abweichung gemeldet
Offener Punkt a1bf9001d6dcb2432: 1 AK-fremd (Fußzeile nutzt userauth-spalte statt userauth-zeile, AK5 gilt für Formularzeilen) | 2 AK-fremd (leere Platzhalterzeilen in Rollen-/Rechteformularen, AK5 nur user.create/edit) | 3 AK-fremd (Variante secondary vorhanden) | 4 AK-fremd (style white-space nowrap ist kein Klassen-Token)
Abweichung a1bf9001d6dcb2432: Fehlalarm: keine Abweichung gemeldet
Offener Punkt a7761687222afbeba: keine
Abweichung a7761687222afbeba: Fehlalarm: keine Abweichung gemeldet
Offener Punkt a728bbdb26d647115: 1 AK-fremd (build:css baut userauth.css mit; schon unter ad156b0242ddd64f5 Punkt 1 entschieden) | 2 erledigt vom Moderator (README Punkt 8 auf ^1.10 umgeschrieben) | 3 AK-fremd (Randfall 6, Hinweis an Joerg, Kit-Task) | 4 AK-fremd (Route durch VerwaltungCssTest abgedeckt)
Abweichung a728bbdb26d647115: Fehlalarm: keine Abweichung gemeldet
Offener Punkt a7bfbbb609558e999: 1 erledigt vom Moderator (Nachmessung layout-pruefung 375/1920 px: Überstand der Karte weg; Rest-Befunde SCROLL 791 px und Überdeckung h1/Breadcrumb sind Host-Layout, auch auf /intern ohne userauth) | 2 AK-fremd (overflow-x auto am Kartenbody; Combobox-Liste im Body im Echtlauf unauffällig, Risiko für Dropdowns am Body-Ende vermerkt für den Reviewer)
Abweichung a7bfbbb609558e999: Fehlalarm: keine Abweichung gemeldet
Layout: layout: /users, /users/create, /roles/create, /permissions --breiten 375,1920 --lang .kit-card → 0 Befunde der userauth-Seiten (Rest: Host-Layout, Navbar, Breadcrumb mit Teststring, auch /intern: SCROLL 791 px)
Echtlauf: browser: /users, /roles, /permissions, /users/create, /roles/create, /permissions/create im Host (Port 8225, LARAVELKIT_THEME=kern) — 0 Konsolenfehler, Ajax 200, kit-card/userauth-zeile/kit-tabs__tab sichtbar; Klick auf [data-kit-delete] in /users öffnet den Lösch-Dialog (kit-dialog__overlay ohne hidden)
Review 0/2: 2 muss, 2 sollte, 2 kann (pruefung.md); muss 1 DialogDeleteTitelTest rot → Test umgestellt (aa9daf1), muss 2 Rechtegrenze in der Spec ergänzt (155a226); sollte Combobox abgeschnitten → behoben (overflow nur an dt-container und Tabellen); sollte Dialog volle Breite und Asset-Cookie als Folge-Tasks #1981, #1982
Offener Punkt abf8f617e9f63ee02: 1 AK-fremd (display:block an Tabellen, Layout-Prüfung 375/1920 px ohne Befund der Seiten) | 2 erledigt vom Moderator (Combobox ohne Kartenbody-Overflow, layout-pruefung grün)
Abweichung abf8f617e9f63ee02: Fehlalarm: keine Abweichung gemeldet
Review 1/2: 0 muss, 1 sollte, 0 kann (pruefung-nachpruefung.md, freigegeben); sollte Tabellen-Zeilen volle Breite → Folge-Task #1984
DOKU: entfällt (README „Upgrade auf 4.0“ und CHANGELOG 4.0.0 sind Teil von T4)
Stand: freigegeben (Nachprüfung 1198cbe). Merge in den Sammel-Branch, Suite, Release.

## T5 [pro] Nacharbeit aus den Review-Funden (AK12, AK13, AK14; Tasks #1981, #1982, #1984)
— Tests: tests/Feature/VerwaltungNacharbeitTest.php
Bestehende Tests: tests/Feature/VerwaltungCssTest.php tests/Feature/VerwaltungKernMarkupTest.php tests/Feature/AuthViewAssetsTest.php tests/Feature/LoeschDialogTest.php
Offener Punkt ac46e6f15a7e48852: keine
Abweichung ac46e6f15a7e48852: Fehlalarm: keine Abweichung gemeldet
Layout: layout: /roles/create, /permissions/create --breiten 375,1920 --lang .kit-card → 0 Befunde der userauth-Seiten (Rest Host-Layout)
Echtlauf: browser: /roles/create (Rahmen sichtbar), /permissions/create (Rahmen im HTML, Bereich per d-none ausgeblendet), /users Klick auf Löschen öffnet den Dialog, 0 Konsolenfehler; curl -I auf beide Asset-Routen: 200, kein Set-Cookie
Review: ja, Sicherheitsbezug (Routen ohne web-Middleware)
Stand: T5 umgesetzt (GATE grün, 186 Tests), Echtlauf grün. Review als Nächstes.
Review 0/2 (Nacharbeit): 0 muss, 1 sollte, 1 kann (pruefung-nacharbeit.md, freigegeben); sollte Test is_group=1 → Folge-Task #1985
