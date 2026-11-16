# Listen ohne jQuery (userauth, #1356)

Typ: refactoring (Task #1356, Spec-Lücke zu AK8 aus `userauth-admin-views-kit` / AK3 aus `userauth-admin-views-skripte`: dort ist `$('.data-table').DataTable(…)` festgeschrieben)
Risiko: normal
Entscheidung: selbst freigegeben (automode, 2026-11-16)

## Ziel
Die Verwaltungsseiten `/users`, `/roles`, `/permissions` und `permission.js` laufen ohne jQuery. Der Host braucht nur `window.DataTable` (DataTables 2).

## Akzeptanzkriterien
- **AK1** Gegeben die Seiten `user.index`, `role.index`, `permission.index`, wenn sie gerendert werden, dann enthält das Inline-Skript `new DataTable('.data-table', {…})` und keinen Aufruf von `$` oder `jQuery`. Optionen, Spalten (`data`/`name`, Aktionsspalte `orderable: false`, `searchable: false`), `processing`, `serverSide`, `responsive`, `language.url` (Ende `vendor/laravelkit/DataTable_DE.json`) und die Ajax-URL (`route('user'|'role'|'permission')`) bleiben wie bisher.
- **AK2** Gegeben ein Browser ohne jQuery (`window.$` und `window.jQuery` nicht definiert) mit `window.DataTable`, wenn `DOMContentLoaded` eintritt, dann wird `.data-table` genau einmal ohne Fehler initialisiert.
- **AK3** Gegeben die Ajax-Option der Listen, wenn DataTables ohne jQuery lädt, dann trägt die Anfrage den Header `X-Requested-With: XMLHttpRequest` (die Controller prüfen `$request->ajax()`). Die Option ist `ajax: { url: <route>, headers: { 'X-Requested-With': 'XMLHttpRequest' } }`.
- **AK4** Gegeben `permission.js`, wenn es geladen wird, dann ruft es weder `$` noch `jQuery` auf (Vanilla-JS). Verhalten gleich: Änderung von `group_display` setzt `group_name`; `showSinglePermissions`, `newPermission`, `deletePermission`, `editIntern` bleiben globale Funktionen mit derselben Wirkung auf das DOM (Zeile klonen, Nummern, ids/names, `d-none`, `delete_*`-Namen).
- **AK5** Gegeben die README, wenn sie gelesen wird, dann nennt sie keine jQuery-Pflicht mehr; Voraussetzung ist `window.DataTable` (DataTables 2, ohne jQuery), und die Listen rufen `new DataTable('.data-table', …)` auf. Der CHANGELOG hat einen Eintrag `3.4.0`.

## Randfälle
- Hat der Host jQuery trotzdem geladen, funktionieren die Listen unverändert (`new DataTable` ohne jQuery-Wrapper).
- Mehrere neue Zeilen nach Validierungsfehler: `hoechsteNeueNummer` zählt weiter ab der höchsten vorhandenen Nummer.

## Nicht-Ziele
- Keine neue DataTables-Version, keine Änderung an Controllern, Ajax-Antworten oder an `role.js`.

## Entscheidungsspielraum
- Aufbau von `permission.js` (Hilfsfunktionen, Namen) frei, solange die globalen Funktionen bleiben, auf die `onclick`/`onchange` in den Views zeigen. Keine neuen Pakete.
- Release: Minor-Version 3.4.0 vergibt der Moderator beim Merge.

## Rückbau
Rückbau: entfällt (keine Übergangslösung)
