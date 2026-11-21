# DataTable-Sprachdatei: eine Quelle (userauth, #661)

Typ: Bugfix, Spec-Lücke (Task #661)
Risiko: normal
Entscheidung: selbst freigegeben (automode, 2026-11-21)

## Ziel
Die Listen /users, /roles, /permissions laden `vendor/laravelkit/DataTable_DE.json`. laravel-kit liefert die Datei seit 1.6.1 wieder (Publish-Tag `laravelkit-assets`, #1344). userauth verlangt deshalb laravel-kit ab 1.6.1 und nennt in der README den Publish-Befehl.

## Akzeptanzkriterien
- **AK1** Gegeben `composer.json` des Pakets, wenn die Anforderung an `ithilbert/laravel-kit` gelesen wird, dann erlaubt sie keine Version unter 1.6.1 (`^1.6.1`).
- **AK2** Gegeben die README, wenn nach `DataTable_DE.json` gesucht wird, dann steht dort der Befehl `php artisan vendor:publish --tag=laravelkit-assets`, und der Satz „kommt aus“ ohne Befehl ist weg.
- **AK3** Gegeben CHANGELOG.md, wenn gelesen, dann hat die oberste Version `3.5.3` einen Eintrag mit `#661`.

## Nicht-Ziele
- Keine eigene Kopie der Datei in userauth, keine neue Route.

## Entscheidungsspielraum
- Wortlaut der README. Keine neuen Pakete.
