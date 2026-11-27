# datatables-optik (#2369)

Risiko: normal. Optik der DataTables in der userauth-Verwaltung (Benutzer, Rollen, Rechte) im Corporate Design, nur Kit-Tokens (`--ith-*`), keine eigenen Farbwerte.

## Akzeptanzkriterien
- **AK1** Gegeben eine Listenseite (`user.index`, `role.index`, `permission.index`), Wenn sie gerendert wird, Dann übergibt die DataTable-Initialisierung ein Layout mit Suchfeld links (`topStart: 'search'`) und Anzahl-Auswahl rechts (`topEnd: 'pageLength'`); für DataTables 1.x (`DataTable.version` < 2) gleichwertig per `dom` mit `f` vor `l`.
- **AK2** Gegeben dieselben Seiten, Wenn gerendert, Dann steht der Neu-Button (`kit::button-create`, nur mit Recht) vor der Tabelle in `.userauth-listenkopf`, nicht im `thead`; der `thead` hat genau eine Kopfzelle je Spalte, die Aktionszelle ohne Button.
- **AK3** Gegeben die Initialisierung, Dann hat die Spalte `id` `className: 'userauth-zahl'`.
- **AK4** Gegeben `resources-src/verwaltung.css` und die kompilierte `userauth-verwaltung.css`, Dann enthalten sie Regeln für Zeilen-Hover, Steuerleiste (`.userauth-listenkopf`, Suchfeld, Auswahl), Paginierung und `.userauth-zahl`; in den neuen Regeln kommen keine Hex-Farbwerte vor (nur `--ith-*`).
- **AK5** Gegeben die Initialisierung, Dann bleiben `serverSide`, `processing`, `responsive`, Sprachdatei und `ajax` unverändert.

## Nicht-Ziele
Keine Änderung der Spalten, Routen oder Zeilenbuttons.

## Entscheidungsspielraum
Konkrete Abstände, Pfeilzeichen und Breakpoints (mobil 375 px: Steuerleiste bricht um, Tabelle scrollt horizontal).
