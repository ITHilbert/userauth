# Prüfung: loesch-dialog-kit-component — 2026-11-27

Geprüfter Bereich: userauth `git diff ba32225..feb640a` (3 Commits), laravel-kit 1.14.0 (e663d624) im Platz unter `packages/laravel-kit`.

| AK / Aussage (Zitat) | Ergebnis | Nachweis |
|---|---|---|
| AK1 „steht der Dialog aus `<x-kit::dialog-delete>`: Wurzel `[data-kit-delete-dialog]`“ | bestanden | `LoeschDialogKitComponentTest::test_ak1_liste_enthaelt_den_dialog_der_component_auf_deutsch` grün (3 Listen, je genau eine Wurzel); Partial rendert nur noch `<x-kit::dialog-delete>` |
| AK1 „mit `data-route` = Lösch-Route mit ID 0 der jeweiligen Liste (`user.delete`, `role.delete`, `permission.delete`)“ | bestanden | Test vergleicht `data-route` mit `route(<name>, 0)` je Liste |
| AK1 „Überschrift mit dem bisherigen Titel („Benutzer löschen“ usw.)“ | bestanden | Test prüft `.kit-dialog__title` = „Benutzer löschen“/„Rolle löschen“/„Recht löschen“ |
| AK1 „und dem bisherigen Text“ | bestanden | Views unverändert (übergeben `body`), Component gibt `{{ $body }}` aus; AK1-Test: nicht leer und nicht Kit-Vorgabe; `LoeschDialogTest` (unverändert, grün) prüft den übergebenen Text wörtlich |
| AK1 „zwei Buttons „Abbrechen“ und „Löschen“ (Sprache `en`: „Cancel“, „Delete“)“ | bestanden | AK1-Tests de und en grün (Texte aus `laravelkit::dialog`) |
| AK1 „genau ein Formular `#formDelete` (Methode POST, `_method=DELETE`, `_token`) — aus der Component, nicht aus dem Partial“ | bestanden | AK1-Test: genau ein `form#formDelete`, post, DELETE, Token; Partial enthält kein `<form` mehr (Diff) |
| AK2 „kein Inline-Skript mehr für den Lösch-Dialog (kein `data-kit-delete-bestaetigen`-Klick-Handler im Seitenquelltext, kein `routeMitId`)“ | bestanden | `test_ak2_seite_hat_ein_modul_skript_und_keinen_inline_handler` grün, prüft alle `<script>`-Blöcke der drei Listen |
| AK2 „genau ein `<script type="module">`, das `initLoeschDialog` aus der Asset-Route `userauth.assets.kit.loesch-dialog` importiert und aufruft“ | bestanden | AK2-Test: genau ein Modul-Skript, Import-URL der Route, Aufruf `initLoeschDialog(`; `@json` mit `\/` ist gültiges JS-Stringliteral. Ausführung im echten Browser nicht verlangt und nicht geprüft (Host ohne Verwaltungsrouten) |
| AK3 „antworten sie mit 200, `Content-Type: text/javascript`“ | bestanden | AK3-Test grün; selbst: `curl http://127.0.0.1:8102/userauth/assets/kit/loesch-dialog.js` und `/loeschen.js` → 200, `text/javascript; charset=utf-8` |
| AK3 „dem Inhalt der gleichnamigen Datei aus `js/` des Pakets laravel-kit“ | bestanden | Test vergleicht mit Datei über ReflectionClass des Providers; selbst: `diff` Antwort vs. `packages/laravel-kit/js/loeschen.js` gleich |
| AK3 „ohne Session-Cookie (Middleware-Gruppe `web` nicht angewendet, wie `userauth.assets.css`)“ | bestanden | Routen in `src/Routes/web.php:47-48` außerhalb von `Route::middleware(['web'])`; Test: keine Cookies; curl: nur `PHPDEBUGBAR_STACK_DATA` (Debugbar des Hosts, kein Session-Cookie) |
| AK3 „mit `Cache-Control: public, max-age=300` (nicht `immutable` …)“ | bestanden | Test und curl: `max-age=300, public`, ohne `immutable` |
| AK4 „eine andere Datei aus dem Kit-`js/`-Ordner (z. B. `mount-inseln.js`, `tabs.js`) oder ein Pfad mit `..` angefragt wird, Dann 404“ | bestanden | AK4-Test grün (u. a. `combobox.js`, `..%2F`, `%2e%2e%2f`, `../kit/tabs.js`); selbst per curl `--path-as-is`: `tabs.js`, `mount-inseln.js`, `..%2Floeschen.js`, `../kit/tabs.js` → 404 |
| AK4 „es gibt nur diese zwei Routen mit fester Whitelist, kein Dateiname-Parameter“ | bestanden | Diff: zwei feste Routen, Methoden mit festem Dateinamen (`AuthAssetVersion::kitLoeschDialogJsPath`/`kitLoeschenJsPath`), kein Parameter |
| AK5 „enthält es kein eigenes `<form id="formDelete"`“ | bestanden | Partial gelesen (10 Zeilen); AK5-Test grün |
| AK5 „und keinen `@push('userauth-scripts')` mit Klick-Handler mehr (nur die Component und das Modul-Skript aus AK2)“ | bestanden | Push enthält nur das Modul-Skript (Import + Aufruf) |
| AK6 „`.kit-dialog[hidden]` ist unsichtbar (`display:none`)“ | bestanden | AK6-Test (vorkompiliert und Quelle) grün; im kompilierten CSS `.kit-dialog[hidden]{display:none}` gefunden |
| AK6 „`.kit-dialog:not([hidden])` liegt als feste, überdeckende Ebene (`position:fixed`, `inset:0`) mit zentriertem Inhalt“ | bestanden | Regel mit `position:fixed;inset:0;display:flex;align-items/justify-content:center` in beiden Dateien; Kit setzt Inhalt zusätzlich fest mittig |
| AK6 „die Dialogbreite bleibt auf 32rem begrenzt“ | bestanden | `.kit-dialog__content{max-width:min(32rem,100vw - 2rem)}` steht nach der Kit-Regel `max-width:100vw` |
| AK6 „Die Quelle `resources-src/verwaltung.css` trägt dieselben Regeln.“ | bestanden | Diff `resources-src/verwaltung.css:103-118`; Test über Data-Provider `quelle` |
| AK7 „`composer.json` verlangt `ithilbert/laravel-kit` `^1.14`“ | bestanden | Diff composer.json; AK7-Test, `KernDokuTest` grün |
| AK7 „die README beschreibt den Lösch-Dialog als `<x-kit::dialog-delete>` mit Modul-Skript über die beiden Asset-Routen (Zeilen „Kit-Components und Skripte“, „Lösch-Buttons“, Upgrade-Hinweis Punkt 3 und Abschnitt zum Lösch-Dialog)“ | bestanden | README-Diff gelesen: alle vier Stellen (inkl. Punkt 6 und Abschnitt unter „Lösch-Dialog“/Punkt 9) nennen Component und beide Routen bzw. das Modul-Skript; kein „Inline-Skript“ mehr in den Zeilen |
| AK7 „und nennt, dass die Texte über die Sprachdateien des Kits kommen“ | bestanden | README „Kit-Components und Skripte“, Upgrade Punkt 3 (`laravelkit::dialog`) |
| AK7 „der CHANGELOG hat einen Abschnitt `4.7.0` (#2449)“ | bestanden | CHANGELOG `## 4.7.0 – 2026-11-27`, vier Einträge mit #2449; stimmig mit Code (Routen, Cache, CSS, `^1.14`) |
| AK8 „Wenn ein Lösch-Button der Liste (`data-kit-delete="5"`) geklickt und „Löschen“ bestätigt wird, Dann setzt `initLoeschDialog()` aus dem Kit die Action des `#formDelete` auf die Route mit ID 5 und sendet es“ | bestanden | `node --test tests/js/loeschen-dialog.test.mjs` 5/5 grün; Test importiert das echte `packages/laravel-kit/js/loesch-dialog.js` mit Markup wie die Component; prüft Action `/user/delete/5`, genau ein Senden, Doppelklick sendet nicht erneut (Fake-DOM ist laut Spec die verlangte Nachweisart) |
| AK8 „Abbrechen, Overlay-Klick und Escape schließen ohne Senden“ | bestanden | drei Fälle (`abbrechen`/`overlay`/`escape schließt ohne zu senden`) grün, mit Gegenprobe „war offen“ |
| AK8 „(die bisherigen Fälle der Datei …, jetzt gegen das Kit-Modul statt gegen das Inline-Skript)“ | bestanden | Alt-Datei (ba32225) hatte 7 Fälle: Senden, Abbrechen, Overlay, Escape, später eingefügter Button, Randfall 3 (Query + Sonderzeichen), Randfall 4 (Doppelklick). Neu: 5 Fälle, Doppelklick im ersten, Query im fünften. Sonderzeichen-ID nur noch im Kit: `laravel-kit/tests/js/loeschDialog.test.mjs` „eine ID mit Sonderzeichen wird kodiert“ (`a/b?c` → `a%2Fb%3Fc`, mit Query) und `loeschen.test.mjs` „loeschRoute kodiert die ID“ — 11/11 grün, gegen dasselbe Modul. Leerzeichen, `&`, `#` aus dem Alt-Fall nicht mehr einzeln, aber `encodeURIComponent` deckt sie (siehe kann) |
| AK9 „sind sie grün bzw. an die Component angepasst“ | bestanden | `AdminViewsKitTest` (191), `KernDokuTest` (4), `DialogDeleteTitelTest` (6), `LoeschDialogTest` (4) grün; `AdminViewsKitTest`, `DialogDeleteTitelTest`, `LoeschDialogTest` unverändert |
| AK9 „jede Anpassung bleibt in der Aussage gleich (Dialog vorhanden, Titel/Text, Formular, kein Inline-Handler)“ | bestanden | `KernDokuTest`: Version `^1.14` statt `^1.10`; README-Regel lässt zusätzlich `<x-kit::dialog-delete>` zu (Aussage „Vue-Insel nicht Voraussetzung“ bleibt). `AdminViewsSkripteTest`: überspringt nur das Modul-Skript mit `initLoeschDialog` im vm-Lauf; die Prüfung „genau ein Inline-Skript mit DataTable“ bleibt; das Modul-Skript prüft AK2. `OidcLoginSeiteTest`/`OidcAutoRedirectStandardTest`: CHANGELOG-Prüfung vom Dateianfang auf den Abschnitt der Einführungsversion (4.1.0/4.3.0) umgestellt — gleiche Aussage, nicht mehr abhängig von der Zahl neuer Abschnitte |

## Sicherheit
- Asset-Routen: kein Nutzerinput im Pfad; Dateiname je Methode fest; Kit-Ordner über `ReflectionClass(LaravelKitServiceProvider)->getFileName()`, `dirname(..., 2).'/js/'` — im Platz `packages/laravel-kit/js/`. Keine Traversal-Möglichkeit (Router matcht nur die zwei festen Pfade; curl-Sonde mit `--path-as-is` → 404).
- Ohne `web`-Gruppe: keine Session, kein CSRF nötig (nur GET, statische Datei). `abort_unless(is_file)` → 404, falls die Kit-Datei fehlt.
- Ausgabe: `data-route="{{ $route }}"` escaped; Import-URL über `@json` (HEX-Flags) — kein Ausbruch aus dem Stringliteral.
- Risiko `normal` passt: keine Rechte-, Lösch- oder Datenlogik geändert; Lösch-Route und Backend unverändert.

## Regressionen
Gelaufen im Platz (`vendor/bin/phpunit -c packages/userauth/phpunit.xml.dist --bootstrap vendor/autoload.php --filter <Klasse>`), alle grün:
- `LoeschDialogKitComponentTest` (16), `LoeschDialogTest` (4), `AdminViewsKitTest` (191), `KernDokuTest` (4), `VerwaltungCssTest` (39), `AdminViewsSkripteTest` (106), `Nachbesserung/DialogDeleteTitelTest` (6), `Oidc/OidcLoginSeiteTest` (17), `Oidc/OidcAutoRedirectStandardTest` (8)
- `node --test packages/userauth/tests/js/loeschen-dialog.test.mjs` 5/5; `node --test packages/laravel-kit/tests/js/loeschDialog.test.mjs` 11/11
- Ganze Suite nicht gelaufen (kein `SUITE: ja`, läuft laut Auftrag später in Schritt 5).
- Log (`storage/logs/laravel*.log`): keine neuen Einträge aus den Läufen.

## Funde: 0 muss, 1 sollte, 3 kann
- sollte: CSS-Regel `.userauth-dialog-aktionen` (`resources-src/verwaltung.css:45`, auch im kompilierten CSS) ist tot — die Component setzt die Knöpfe ohne diesen Wrapper direkt in `.kit-dialog__content`. Damit fehlen Lücke, rechte Ausrichtung und Abstand oben, die die Knöpfe vorher hatten. Sichtprüfung nicht möglich (Host ohne Verwaltungsrouten), Layout-Zeile im Plan sagt „Sichtprüfung im Review“ (→ Folge-Task #2454)
- kann: Import über absolute URL (`route('userauth.assets.kit.loesch-dialog')`). Bei `URL::forceRootUrl`/abweichendem Host hinter Proxy wird der Modul-Import cross-origin und braucht CORS; `route(..., absolute: false)` wäre robuster.
- kann: `.kit-dialog[hidden]` und `.kit-dialog:not([hidden])` stehen schon in `laravel-kit/vue3/kit.css`; userauth dupliziert sie (verlangt von AK6) und ergänzt nur die Flex-Zentrierung, die wegen `position:fixed; transform` des Inhalts wirkungslos bleibt.
- kann: Sonderzeichen Leerzeichen, `&`, `#` aus dem alten Randfall 3 sind nicht mehr einzeln getestet (Kit testet `/` und `?`); Kodierung über `encodeURIComponent` ist dieselbe.

## Neue Tasks: #2454 Verwaltung/Lösch-Dialog – Nacharbeit aus Review loesch-dialog-kit-component

## Fehlerprotokoll
- {"schwere":"sollte","ak":"","fund":"CSS-Regel .userauth-dialog-aktionen nach Austausch des Markups tot, Knopf-Layout im Dialog geändert","ursache":"Beim Ersetzen des Markups durch die Component wurden die Klassen des alten Markups nicht gegen das CSS abgeglichen","anweisung":"Beim Ersetzen von Markup jede entfallene Klasse im CSS des Pakets suchen und die Regel entfernen oder auf das neue Markup übertragen"}

## Fazit: freigegeben
