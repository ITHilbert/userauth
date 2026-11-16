# Plan: userauth-datatable-ohne-jquery

Stand: Tests rot committet (test-writer), Coder T1 offen.
Marketing: entfällt (kein Seitendesign, nur Skript-Umbau)
Review: entfällt (kein Risiko: keine neue Route, keine Migration, kein Sicherheitsbezug, unter 50 Zeilen Produktivcode je Muster) — Entscheidung Moderator, wird mit dem Diff abgeglichen

## T1 [pro] Listen und permission.js ohne jQuery (AK1–AK5)
— Tests: tests/Feature/AdminViewsKitTest.php tests/Feature/AdminViewsSkripteTest.php tests/js/permission.test.mjs (Fixture tests/Fixtures/inline-skript-lauf.mjs)
Bestehende Tests: tests/Feature/RolePermissionTest.php
Offener Punkt ad26169b8a4e10a4a: 1 plan.md ergänzt | 2 Host-Kopie public/vendor/userauth/js/permission.js zieht der Moderator nach | 3 Coder-Vorgabe | 4 AK-fremd (Aufräum-Task für zu große Testdateien, kein Mangel) | 5 AK-fremd (DataProvider, keine Doppelprüfung) | 6 AK-fremd (Umgebung)
