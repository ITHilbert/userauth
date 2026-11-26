# Prüfung: kern-umstellung (userauth 4.0.0, #1619) — 2026-11-21 (Nachprüfung seit 155a226)
Maßstab: `spec.md` (Risiko kritisch). Seit 155a226 hat sich die Spec nicht geändert: Die Zeile „Rechtegrenze“ kam mit 155a226 selbst. Fix-Diff `155a226..1198cbe`: zwei Testdateien (aa9daf1), `resources-src/verwaltung.css` und die gebaute `userauth-verwaltung.css` (1198cbe), `plan.md`. Der Fix baut keine neuen Klassen, Migrationen oder geteilten Komponenten. Deshalb läuft eine Nachprüfung, kein volles Review.
Platz: `/home/joerg/projekte/.worktrees/packages_dev/reviewer`. Echtlauf: eigener Server `echtlauf start` auf Port 8291 mit `LARAVELKIT_THEME=kern` als Prozessvariable (in `/proc/<pid>/environ` belegt), `.env` unverändert. Der Server auf 8102 läuft seit 2026-11-07 ohne diese Variable und wurde nicht genutzt. Echtlauf-DB mit 12 zusätzlichen Rollen (`echtlauf seed`). Browser: eigene Headless-Chrome-Instanz per CDP (Port 0, Profil im Scratchpad), echte Mausklicks.

| AK / Aussage (Zitat) | Ergebnis | Nachweis |
|---|---|---|
| AK10 „prüfen sie das Theme `kern` statt `tailwind`/`bootstrap5` und sind grün“ | bestanden | `Nachbesserung/DialogDeleteTitelTest` prüft jetzt mit `laravelkit.theme=kern` das Blade-Markup: genau ein `div.kit-dialog__content[role=dialog]`, Titel in `.kit-dialog__title` je Liste („Benutzer/Rolle/Recht löschen“), kein Attribut `title`, kein `<dialog-delete`. 6/6 grün. Gegenprobe im selben Test: Der Dialog muss existieren. `PasswortFehlerEinmalTest` sucht die Zeile jetzt über `userauth-zeile` statt `row`, die Gegenprobe `assertInstanceOf(DOMElement)` greift. Grün. `AdminViewsKitTest` grün |
| AK10 „die Prüfungen der Auth-Seiten (Login, Registrierung, Passwort, Social) und `userauth.css` bleiben unverändert grün“ | bestanden (Prüfung 155a226) | Der Fix berührt keine Auth-Seite und nicht `userauth.css` |
| AK1 „enthält sie Regeln für `.kit-card`, … `.userauth-zeile__feld` und `.d-none`“ | bestanden | `VerwaltungCssTest` grün. Die gebaute Datei enthält die neuen Regeln `.kit-card__body .dt-container{overflow-x:auto}` und `table.userauth-tabelle{max-width:100%;display:block;overflow-x:auto}`, `.kit-card__body` hat kein `overflow` mehr. `?v=4f36af359e01` im Link von /users/create = sha256 der Datei |
| AK1 „aber nicht Tailwinds Preflight (kein `::file-selector-button`)“ | bestanden | `VerwaltungCssTest` grün |
| AK1 „`resources-src/verwaltung.css` bindet `vue3/kit.css` und `vue3/themes/tailwind.css` des Kits ein“ | bestanden (Prüfung 155a226) | Importzeilen im Fix unverändert |
| AK1 „`package.json` baut beide Dateien mit `build:css`“ | teilweise (Prüfung 155a226) | Im Platz fehlt weiter `node_modules` des Pakets. Dass die Quelle neu gebaut wurde, ist nur über die übereinstimmenden Regeln belegt, nicht über einen eigenen Build |
| AK3, AK4, AK5, AK6 (Markup, Einbindung, Klassen, Zeilenbuttons) | bestanden | Views unverändert. `VerwaltungKernMarkupTest` (inkl. AK4 Klassenliste) und `AdminViewsKitTest` grün |
| AK7 „`div.kit-dialog__overlay` (versteckt), `div.kit-dialog__content[role="dialog"][aria-modal="true"]` …“, AK8 (Verhalten des Lösch-Dialogs) | bestanden | `LoeschDialogTest` grün. Dialog-Markup und Skript im Fix unverändert, das Verhalten im Browser übernommen aus Prüfung 155a226 |
| AK2, AK9, AK11, Randfälle 1–6 | bestanden (Prüfung 155a226) | Vom Fix nicht berührt |
| Rechtegrenze (Risiko kritisch) | bestanden | `php .claude/hooks/abdeckung.php grenze packages/userauth/.project/features/kern-umstellung/spec.md` → Exit 0. Spec-Kopf Zeile 5: „Rechtegrenze: entfällt — …“. Die Begründung stimmt mit dem Code überein: Die Route liefert nur eine feste, öffentliche CSS-Datei (Prüfung 155a226), Rechte je Zeile unverändert (AK6) |
| Sollte-Fund 155a226 „Combobox-Liste wird abgeschnitten“ | behoben | Browser /users/create, 14 Rollen, Klick auf `.kit-combobox__trigger`: Liste `display:block`, `position:absolute`, 513–769 px. Unter der Eingabe (475–513) endet der Kartenkörper bei 621 px mit `overflow: visible/visible`, kein Vorfahr schneidet ab. Erste („Admin“) und letzte Option („Prüfrolle Nummer 12“, nach Scrollen in der Liste 736–768 px) sind per `elementFromPoint` erreichbar. 0 Konsolenfehler |
| Fix 1198cbe: statische Tabellen /roles/create, /permissions/create „Spaltenausrichtung von Kopf und Körper“ | bestanden | /roles/create (CRUD-Tabelle, 5 Spalten) und /permissions/create (Häkchen Einzelrechte per Klick an, 4 Spalten): Kopf- und Körperzellen je Spalte an derselben x-Position, Abweichung 0 px. Die einzige abweichende Zeile in /permissions/create ist die Vorlage `#vorlage.d-none` (`display:none`, gewollt) |
| Fix 1198cbe: „kein Auseinanderfallen durch `display:block`“ | nicht bestanden (sollte) | Mit `display:block` wirkt `w-full` nur auf den Block, die Zeilen bilden eine anonyme Tabelle in Inhaltsbreite. /roles/create: Tabellenbox 647 px, Kopf- und Datenzeilen nur 371 px (560–931). /permissions/create: 657 px gegen 484 px. Gegenprobe im selben Browser mit `display:table`: Zeilen füllen 647 bzw. 657 px. Die Trennlinien enden mitten in der Karte. Kein AK verlangt volle Breite → sollte (#1984) |
| Fix 1198cbe: DataTables-Listen /users, /roles, /permissions (ebenfalls `table.userauth-tabelle`) | bestanden | Kopf und Körper deckungsgleich (z. B. /roles 295–780, 10 Zeilen, Abweichung 0 px). Messung mit und ohne `display:block` identisch, 0 Konsolenfehler |

## Sicherheit
Diff des Fixes: nur CSS und Tests. Keine Route, keine Rechte, keine Ausgabe von Nutzerdaten. Die Spec-Zeile „Rechtegrenze: entfällt“ ist sachlich gedeckt (siehe Tabelle). Kein Fund.

## Regressionen
Gelaufen im Platz (`php artisan test --compact <dateien>`, Test-DB des Platzes): `tests/Feature/VerwaltungCssTest.php`, `tests/Feature/VerwaltungKernMarkupTest.php`, `tests/Feature/PasswortFehlerEinmalTest.php`, `tests/Feature/AdminViewsKitTest.php`, `tests/Feature/LoeschDialogTest.php`, `tests/Feature/Nachbesserung/DialogDeleteTitelTest.php` → 263 grün (1828 Assertions), 0 rot. `git grep` in `tests/` nach `userauth-tabelle`, `dt-container`, `overflow`, `display: block`: keine weiteren Tests berührt.
Im Theme kern stehen im Tageslog seit Start des Echtlaufs keine Warnungen „laravelkit.theme=kern“. Die 4 Zeilen von 08:34 stammen aus dem Tailwind-Lauf der Prüfung 155a226.
`layout-pruefung` auf /roles/create, /users/create, /permissions/create (375/1920 px, `--lang .kit-card`): Alle 18 Befunde liegen im Host-Layout (Navbar-Toggler, Breadcrumb und h1 mit Teststring, Seitenscroll durch die Breadcrumb). Keiner liegt in `.kit-card` der userauth-Seiten, das deckt sich mit `plan.md`.
Ganze Suite nicht (kein `SUITE: ja`).

## Funde: 0 muss, 1 sollte, 0 kann
- sollte: `table.userauth-tabelle { display: block }` (`resources-src/verwaltung.css` Z. 93–97) lässt die Zeilen der statischen Tabellen in /roles/create und /permissions/create nur in Inhaltsbreite laufen (371/647 px bzw. 484/657 px), die Trennlinien enden mitten in der Karte. Vorschlag: Overflow an einen eigenen Rahmen `userauth-…` um die Tabelle statt an die Tabelle selbst (→ Folge-Task #1984)
- offen aus 155a226, unverändert in #1981: Lösch-Dialog in voller Fensterbreite, nicht Teil dieser Nachprüfung

## Neue Tasks: #1984 userauth Verwaltungs-CSS – statische Tabellen wieder volle Kartenbreite (Nacharbeit aus Nachprüfung kern-umstellung)

## Fehlerprotokoll
- {"schwere":"sollte","ak":"AK1","fund":"display:block an table.userauth-tabelle lässt die Zeilen statischer Tabellen nur in Inhaltsbreite laufen, Trennlinien enden mitten in der Karte","ursache":"Fix für Overflow nur auf Spaltenausrichtung und Seitenbreite geprüft, nicht auf die Breite der Zeilen gegenüber der Tabellenbox","anweisung":"Scroll-Overflow für Tabellen an einen umschließenden Rahmen setzen, nie display:block an die Tabelle selbst; nach CSS-Änderungen an Tabellen die Breite von Zeilen und Tabellenbox im Browser vergleichen"}

## Fazit: freigegeben
Beide muss-Funde aus 155a226 sind behoben: DialogDeleteTitelTest grün, `abdeckung.php grenze` mit Exit 0. Der Combobox-Fund ist behoben. Neu ist ein sollte-Fund (#1984), ohne muss.
