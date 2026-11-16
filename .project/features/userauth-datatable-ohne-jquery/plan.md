# Plan: userauth-datatable-ohne-jquery

Stand: Spec geschrieben, Branch `refactor/datatable-ohne-jquery` (Paket userauth), Tests ausstehend.
Marketing: entfällt (kein Seitendesign, nur Skript-Umbau)
Review: entfällt (kein Risiko: keine neue Route, keine Migration, kein Sicherheitsbezug, unter 50 Zeilen Produktivcode je Muster) — Entscheidung Moderator, wird mit dem Diff abgeglichen

## T1 [pro] Listen und permission.js ohne jQuery (AK1–AK5)
— Tests: tests/Feature/AdminViewsKitTest.php tests/Feature/AdminViewsSkripteTest.php (Fixture tests/Fixtures/inline-skript-lauf.mjs mit anpassen)
Bestehende Tests: tests/Feature/RolePermissionTest.php
