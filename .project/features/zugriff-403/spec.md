# Spec: zugriff-403 (#2183)

Art: Spec-Lücke (Erweiterung von `userauth.redirect_on_no_permission`; bestehende AKs aus „kleine-fehler“ AK3/AK4 und das Verhalten für Standard `login` und `no-permission` bleiben).
Risiko: normal (Rechte/Zugänge berührt: fehlende Rechte liefern weiterhin nie den Inhalt der Route).
Selbst freigegeben im Automode (Stapel 2026-11-25-1926), Annahme siehe Protokoll.

## Hintergrund
`hasRole`, `isAdmin`, `hasPermission`, `hasPermissionAnd`, `hasPermissionOr` antworten bei fehlendem Recht mit einer Umleitung (`ZugriffVerweigert::fehlendesRecht()`). Mit dem Standard `login` landet ein bereits angemeldeter Benutzer auf /login; Clients und Tests, die 403 erwarten, bekommen 302.

## Akzeptanzkriterien
- **AK1** Gegeben `userauth.redirect_on_no_permission = '403'` und ein angemeldeter Benutzer ohne das Recht, wenn er eine Route mit `hasRole`, `isAdmin`, `hasPermission`, `hasPermissionAnd` bzw. `hasPermissionOr` aufruft, dann antwortet die Anwendung mit Status 403 (keine Umleitung) und der Inhalt der Route erscheint nicht.
- **AK2** Gegeben dieselbe Einstellung `'403'`, wenn ein JSON-Client (`Accept: application/json`) abgelehnt wird, dann ist die Antwort Status 403 mit JSON-Body.
- **AK3** Gegeben die Einstellung `'403'`, wenn ein Gast (nicht angemeldet) eine solche Route aufruft, dann wird er wie bisher zur Anmeldung umgeleitet.
- **AK4** Gegeben die Einstellung `'403'` und ein Benutzer mit dem Recht, wenn er die Route aufruft, dann erreicht er den Inhalt (Status 200).
- **AK5** Gegeben der Standard (`login`) bzw. `no-permission`, wenn ein angemeldeter Benutzer ohne Recht abgelehnt wird, dann bleibt das Verhalten unverändert (Umleitung auf `login` bzw. `no-permission`).
- **AK6** Gegeben `config/config.php` und README, dann nennt der Kommentar zu `redirect_on_no_permission` den Wert `'403'`, und die README erklärt ihn im Abschnitt zu den Middlewares bzw. Rechten.

## Randfälle
- Randfall 1: Eine POST-Anfrage ohne Recht mit `'403'` ergibt ebenfalls 403.

## Nicht-Ziele
- Der Standardwert bleibt `login` (Wechsel wäre eine inkompatible Änderung; Entscheidung bei Joerg, im Protokoll offen).
- Keine neue Seite, keine Änderung der Seite `no-permission` (bleibt Status 200).

## Entscheidungsspielraum
Umsetzung in `ZugriffVerweigert` (eine Stelle für alle fünf Middlewares). Fehlerseite: Laravel-Standard `abort(403)` mit Text `userauth::permission.no-permission`.
