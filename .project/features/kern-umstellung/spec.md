# userauth auf Theme kern und Vue-3-Kern des laravel-kit umstellen (userauth 4.0.0, #1619)

Typ: feature (Task #1619)
Risiko: kritisch (GATE stuft das Layout afterLogin als Auth-Datei ein; neue Route, Darstellungs-AK, Major-Version)
Rechtegrenze: entfällt — keine neue Rechtegrenze: die neue Route liefert nur eine feste, öffentliche CSS-Datei, die Rechteprüfung der Verwaltungsseiten und Zeilenbuttons bleibt in den bestehenden Middlewares und Controllern (Review 2026-11-21, muss-Fund 2)
Entscheidung: freigegeben durch Joerg (Task-Chat #473, Telegram 2026-11-21 07:26): 1. userauth liefert `kit.css` und das Tailwind-Theme des Kits vorkompiliert mit (wie Entscheidung 0005), das einbindende Projekt stylt nichts. 2. Neue Major-Version. Spec selbst freigegeben (automode 2026-11-21, Moderator in Vollmacht).
Rückbau: entfällt (kein Übergangsstand)

## Ziel
Die Verwaltungsseiten von userauth (Benutzer, Rollen, Rechte) nutzen die Blade-Components des Kits im Theme `kern` (`<x-kit::…>`, Klassen `kit-…`) und brauchen keine Bootstrap-Klassen des Hosts mehr. Ihre Optik liefert userauth selbst vorkompiliert aus (Kit-Basis `vue3/kit.css` plus Tailwind-Theme `vue3/themes/tailwind.css`), das Host-Projekt stylt nichts. Der Lösch-Dialog braucht keine Vue-Insel des alten Kits mehr.

## Ist-Stand (geprüft 2026-11-21)
- Kit-Components stehen nur in `user/*`, `role/*`, `permission/*` (siehe Zählung im Task). Login, Registrierung, Passwort und Social-Seiten haben eigenes Tailwind- bzw. Bootstrap-Markup (`userauth.theme`) und nutzen keine Kit-Components.
- Das Blade-Theme der Kit-Components ist global (`laravelkit.theme`); userauth kann es nicht je Seite wählen.
- Die Verwaltungsseiten laufen im Layout des Hosts (`layouts.app`) mit dessen Bootstrap. `userauth.css` (Route `userauth.assets.css`) enthält Tailwinds Preflight und darf dort nicht geladen werden (würde den Host zurücksetzen).
- Die Seiten tragen Bootstrap-Klassen (`form-group row`, `col-md-*`, `table …`, `float-end`, `btn …`) und den Lösch-Dialog als Vue-Insel `dialog-delete` (`vue/tailwind`). Die Zeilenbuttons der Listen kommen aus `HButton` (`btn …`).

## Vertrag
- **Neue CSS-Datei `src/Resources/assets/css/userauth-verwaltung.css`**, vorkompiliert, **ohne Preflight**: Quelle `resources-src/verwaltung.css` (Tailwind v4 mit `theme.css` und `utilities.css`, ohne `preflight.css`), bindet `../../laravel-kit/vue3/kit.css` und `../../laravel-kit/vue3/themes/tailwind.css` ein und enthält die Klassen `userauth-…` (unten). `npm run build:css` baut beide Dateien (`userauth.css` wie bisher und die neue). `userauth.css` selbst ändert sich nicht.
- **Route** `GET userauth/assets/userauth-verwaltung.css`, Name `userauth.assets.verwaltung`, öffentlich wie `userauth.assets.css`, `Cache-Control: public, max-age=31536000, immutable`, `?v=<Hash>` aus `AuthAssetVersion` (neue Methoden `verwaltungCssPath()`, `verwaltungCssVersion()`). Fehlt die Datei: 404.
- **Einbindung:** Das Layout `userauth::layouts.afterLogin` legt den `<link>` einmal in den Stack `styles` und in die Section `css` des Host-Layouts (wie die Skripte in `scripts`/`js`; kennt das Layout beides, steht er genau einmal in der Seite).
- **Klassen der Seiten:** Jedes Element der Verwaltungsseiten trägt nur Klassen `kit-…` (Components), `userauth-…` (eigene Layout-Klassen) und die Zustands-/Hook-Klassen `d-none` (Skripte `permission.js`), `data-table` (DataTables-Haken) sowie die Zustände der Kit-Components `is-active`, `is-invalid`, `is-open` (Spec-Lücke aus dem Test-Writer-Bericht, Moderator 2026-11-21). Keine Bootstrap-Klassen, keine Tailwind-Utilities.
- **Eigene Layout-Klassen:** `userauth-zeile` (Formularzeile, mobil untereinander, ab 768 px Label links), `userauth-zeile__label`, `userauth-zeile__feld`, `userauth-spalte` (Fußzeile), `userauth-rechts`, `userauth-mitte`, `userauth-fehler`, `userauth-tabelle`, `userauth-container`. `d-none` und `data-table` sind in der Datei als Regel (`d-none`: `display: none`) enthalten.
- **Zeilenbuttons der Listen:** Die DataTable-Spalten `action` (Benutzer, Rollen, Rechte) rendern Edit und Löschen als Blade-Components (`button-edit`, `button-delete` mit `value="<id>"`) statt `HButton`; die Berechtigungsprüfung je Zeile bleibt.
- **Lösch-Dialog (`partials/loeschen.blade.php`):** kein `data-vue`. Markup: `div.kit-dialog__overlay` (versteckt) und `div.kit-dialog__content` mit `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, Titel `kit-dialog__title`, Text, Knöpfe Abbrechen und Löschen (`kit-button`); das Formular `#formDelete` (POST, `@method('DELETE')`, `@csrf`) bleibt. Ein Inline-Skript (Stack `userauth-scripts`) öffnet den Dialog beim Klick auf `[data-kit-delete]` (Delegation am `document`, auch auf Buttons, die DataTables später einsetzt), setzt beim Bestätigen `#formDelete.action` auf die Route mit der ID als letztem Pfadsegment (URL-kodiert, Query bleibt) und sendet das Formular. Abbrechen, Klick auf das Overlay und Escape schließen ohne Absenden. Nutzt kein Global des Hosts und keine Vue-Insel.
- **Passwort-Knöpfe** (`user/formularfelder`): `kit-button` statt `btn btn-outline-secondary btn-sm`, `data-passwort-erzeugen`/`data-passwort-kopieren` bleiben.
- **Theme-Hinweis:** Rendert eine Verwaltungsseite bei `laravelkit.theme` ≠ `kern`, schreibt userauth einmal je Request eine Warnung ins Log (`userauth: Verwaltungsseiten brauchen laravelkit.theme=kern …`); die Seite rendert trotzdem ohne Fehler.
- **Version:** `composer.json` verlangt `ithilbert/laravel-kit` `^1.10` (erster Release mit Theme `kern`; solange er fehlt, siehe Randfall 6). CHANGELOG `4.0.0`.

## Akzeptanzkriterien
- **AK1** Gegeben `src/Resources/assets/css/userauth-verwaltung.css`, wenn sie gelesen wird, dann enthält sie Regeln für `.kit-card`, `.kit-button`, `.kit-input-text__input`, `.kit-tabs__tab`, `.kit-dialog__content`, `.kit-combobox__list`, `.userauth-zeile`, `.userauth-zeile__label`, `.userauth-zeile__feld` und `.d-none`, aber nicht Tailwinds Preflight (kein `::file-selector-button`); `resources-src/verwaltung.css` bindet `vue3/kit.css` und `vue3/themes/tailwind.css` des Kits ein, `package.json` baut beide Dateien mit `build:css`.
- **AK2** Gegeben die Route `userauth.assets.verwaltung`, wenn sie aufgerufen wird, dann antwortet sie 200 mit `Content-Type: text/css`, `Cache-Control` mit `immutable` und dem Inhalt der Datei; `AuthAssetVersion::verwaltungCssVersion()` ist der 12-stellige Hash dieser Datei; `userauth.assets.css` liefert weiter `userauth.css`.
- **AK3** Gegeben jede der Seiten `user`, `user.create`, `user.edit`, `user.icon`, `role`, `role.create`, `role.edit`, `permission`, `permission.create`, `permission.edit` in einem Host-Layout mit Stack `styles`, mit Section `css` oder mit beidem, wenn sie rendern, dann steht genau ein `<link rel="stylesheet">` auf `userauth.assets.verwaltung` mit `?v=<Hash>` in der Seite; die Login-Seite enthält ihn nicht.
- **AK4** Gegeben dieselben zehn Seiten mit `laravelkit.theme = kern`, wenn sie rendern, dann trägt kein Element ein `class`-Token außer `kit-…`, `userauth-…`, `d-none`, `data-table`, `is-active`, `is-invalid` und `is-open` (Includes des Hosts wie `include.breadcrumb` zählen nicht dazu).
- **AK5** Gegeben `user.create` und `user.edit` mit `laravelkit.theme = kern`, wenn sie rendern, dann steht jede Formularzeile als `div.userauth-zeile` mit `userauth-zeile__label` und `userauth-zeile__feld`, die Felder sind `kit-input-text`, `kit-input-email`, `kit-input-password`, `kit-input-date`, `kit-checkbox`, `kit-combobox` bzw. `kit-textarea`, und alle Feldnamen, `id`s und `data-`-Haken der Skripte (`data-passwort-erzeugen`, `data-passwort-kopieren`, `#group_permissions_show`, `#vorlage`) sind unverändert; die zwei Passwort-Knöpfe sind `kit-button`.
- **AK6** Gegeben die DataTable-Antworten von `/users`, `/roles` und `/permissions` (Ajax), wenn eine Zeile die Rechte `…_edit` und `…_delete` hat, dann enthält `action` `a.kit-button[data-variant="edit"]` mit der Edit-Route und `button.kit-button[data-variant="delete"][data-kit-delete="<id>"]`, aber keine Klasse `btn`; ohne das jeweilige Recht fehlt der jeweilige Button.
- **AK7** Gegeben die Listen mit Lösch-Dialog, wenn sie rendern, dann enthalten sie `div.kit-dialog__overlay` (versteckt), `div.kit-dialog__content[role="dialog"][aria-modal="true"]` mit Titel und Text aus `titel`/`body`, zwei Knöpfe `kit-button` (Abbrechen, Löschen), das Formular `#formDelete` (POST, `_method` = `DELETE`, `_token`) und kein Attribut `data-vue`.
- **AK8** Gegeben der Inline-Skript-Lauf des Lösch-Dialogs, wenn ein Klick auf `[data-kit-delete="7"]` kommt, dann ist der Dialog sichtbar; „Löschen“ setzt `#formDelete.action` auf `<route ohne letztes Segment>/7` (Query der Route bleibt, ID URL-kodiert) und sendet es einmal; „Abbrechen“, Klick auf das Overlay und Escape blenden den Dialog aus, ohne zu senden; ein später eingefügter `[data-kit-delete]`-Button löst ebenfalls aus.
- **AK9** Gegeben `laravelkit.theme` ≠ `kern` (auch der Standard `tailwind`), wenn eine Verwaltungsseite rendert, dann steht im Log genau eine Warnung mit „userauth“ und „laravelkit.theme=kern“ je Request und die Seite antwortet 200; mit `kern` gibt es keine solche Warnung.
- **AK10** Gegeben die Prüfungen der Verwaltungsseiten (`AdminViewsKitTest`, `AdminViewsSkripteTest`, `PasswortErzeugenTest` u. a.), wenn sie laufen, dann prüfen sie das Theme `kern` statt `tailwind`/`bootstrap5` und sind grün; die Prüfungen der Auth-Seiten (Login, Registrierung, Passwort, Social) und `userauth.css` bleiben unverändert grün.
- **AK11** Gegeben `composer.json`, README und CHANGELOG, wenn gelesen wird, dann verlangt `composer.json` `ithilbert/laravel-kit` `^1.10`; die README hat den Abschnitt „Upgrade auf 4.0“ (Theme `kern` setzen, CSS kommt vom Paket, `initLoeschen()`/`mountInseln()` mit `dialog-delete` sind nicht mehr nötig, `userauth.assets.verwaltung`, Build der CSS) und nennt nirgends mehr die Insel `dialog-delete` als Voraussetzung; der CHANGELOG hat `4.0.0` mit „Geändert (inkompatibel)“.

## Randfälle
1. Theme ≠ `kern`: Seiten rendern ohne Exception (AK9), Optik nicht zugesagt.
2. `userauth-verwaltung.css` fehlt: Route 404, `verwaltungCssVersion()` = `0`, Seiten rendern weiter.
3. Lösch-Route mit Query (`/user/0?x=1`) und ID mit Sonderzeichen: Query bleibt, ID kodiert (AK8).
4. Zwei Klicks hintereinander auf „Löschen“ senden einmal (Dialog schließt vor dem Senden).
5. `{{ 7*7 }}` und HTML in Titeln, Namen oder Dialogtext erscheinen als Text (bestehendes Verhalten der Seiten bleibt).
6. Solange das Kit `1.10.0` nicht veröffentlicht ist, lässt sich userauth 4.0.0 nur gegen den Stand `dev-main` bzw. den Workspace auflösen; das gehört in die Hinweise für Joerg (Task im Kit-Projekt).

## Nicht-Ziele
- Login, Registrierung, Passwort-Vergessen/-Ändern und Social-Seiten umbauen (eigene Themes, keine Kit-Components); `userauth.css` neu bauen.
- Das Kit ändern, Vue-3-Inseln in userauth einführen (Spec-Entscheidung: kein Vue nötig, siehe Entscheidungsspielraum), DataTables-Optik.
- Das Blade-Theme des Hosts umstellen (Host setzt `LARAVELKIT_THEME=kern` selbst).

## Entscheidungsspielraum
Aufbau und Farben der `userauth-…`-Regeln (schlicht, Tailwind-Standardwerte wie das Kit-Theme), Name der Stack-/Section-Partials, Wortlaut der Log-Warnung im Rahmen von AK9, Reihenfolge der Bauschritte. Keine neuen Composer-Pakete; npm nur die vorhandenen Tailwind-Pakete.

## Annahmen (selbst entschieden, umkehrbar)
- Der Lösch-Dialog ist Blade plus Inline-Skript statt Vue-3-Insel, weil der Vue-3-Kern keinen fertigen Lösch-Dialog hat und userauth sonst `mountInseln` im Host voraussetzt. Alternative: eigene Vue-3-Komponente im Kit (Folge-Task).
- Auth-Seiten bleiben unverändert (nutzen keine Kit-Components); Joergs Antwort „wie heute nach 0005“ gilt für `userauth.css`.
- Kit-Mindestversion `^1.10` (nächster Minor nach 1.9.0 mit neuem Theme).
