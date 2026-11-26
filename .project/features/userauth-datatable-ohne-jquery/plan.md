# Plan: userauth-datatable-ohne-jquery

Marketing: entfällt (kein Seitendesign, nur Skript-Umbau)
Review: entfällt (Refactoring ohne Verhaltensänderung, Rule agent-lifecycle.md; keine Route, keine Migration, kein Sicherheitsbezug)

## T1 [pro] Listen und permission.js ohne jQuery (AK1–AK5)
— Tests: tests/Feature/AdminViewsKitTest.php tests/Feature/AdminViewsSkripteTest.php tests/js/permission.test.mjs (Fixture tests/Fixtures/inline-skript-lauf.mjs)
Bestehende Tests: tests/Feature/RolePermissionTest.php
Offener Punkt ad26169b8a4e10a4a: 1 plan.md ergänzt | 2 Host-Kopie public/vendor/userauth/js/permission.js zieht der Moderator nach | 3 Coder-Vorgabe | 4 AK-fremd (Aufräum-Task für zu große Testdateien, kein Mangel) | 5 AK-fremd (DataProvider, keine Doppelprüfung) | 6 AK-fremd (Umgebung)
Offener Punkt ac0104fc8f739442e: Host-Kopie permission.js | zurückgebaut nein: Moderator hat Kopie nachgezogen (Host-Commit) ; rechtename.test.mjs widersprach AK4 | Spec ergänzt nein, test-writer ad95b16cbc3ebdb94 hat Test umgestellt (Fehlalarm im Sinne der Spec: gleiche Aussagen, nur Aufbau)
Abweichung ac0104fc8f739442e: Fehlalarm: keine Abweichung gemeldet
Abweichung aae8b240a2d1e14a4: Fehlalarm: keine Abweichung gemeldet
Echtlauf: browser: /users, /roles, /permissions im Host (Port 8225) — 0 Konsolenfehler, Ajax 200, 10/8/8 Zeilen sichtbar. Einschränkung: Host lädt jQuery (DataTables 1.13 im Bundle); Lauf ganz ohne jQuery nur im node-Fixture (AK2), weil der Hook fremder-server.sh meinen Scratch-Server mit DataTables 2 blockte.
Annahme im Code: keine neuen Annahme-Kommentare
Stand: T1 umgesetzt, GATE-Tests grün (PHP 339, JS 62), Echtlauf grün. Host: window.DataTable in resources/js/app.js ergänzt.

## T2 [junior] Listen starten auch nach DOMContentLoaded (AK6, #2267)
— Tests: tests/Feature/AdminViewsSkripteTest.php (Fixture tests/Fixtures/inline-skript-lauf.mjs, neuer Modus „nachtraeglich“)
Bestehende Tests: tests/Feature/AdminViewsKitTest.php tests/Feature/VerwaltungKernMarkupTest.php
Marketing: entfällt (kein Seitendesign)
Review: entfällt (Bugfix, kleiner Skript-Fix, kein Sicherheitsbezug, keine Route)
