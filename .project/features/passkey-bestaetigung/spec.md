# Spec: passkey-bestaetigung (#2225)

Art: Feature (neue Routen, Sicherheitsgrenze).
Risiko: kritisch (Anmeldung, Zugänge, neue Routen, Sitzung).
Rechtegrenze: Ein Passkey wird nur hinzugefügt, wenn der Benutzer sich in den letzten `passkey.bestaetigung_minuten` Minuten durch aktuelles Passwort oder einen vorhandenen Passkey bestätigt hat (Tests AK1–AK3).
Selbst freigegeben im Automode (Lauf 2026-11-26-2303): Auftrag Joerg im Task #2225 („ja“ auf beide Punkte, Chat 2026-11-26 22:04); Annahmen siehe „Entscheidungsspielraum“.
Marketing: entfällt (keine neue Seite; eine Bestätigungsabfrage in der vorhandenen Passkey-Verwaltung).

## Hintergrund
Wer eine Sitzung übernimmt (gestohlenes Cookie, offener Rechner), richtet heute mit „Passkey hinzufügen“ einen dauerhaften Zugang ein, bei `passwort_login = ohne_passkey` sperrt das sogar das Passwort des Inhabers. „Passwort ändern“ verlangt das aktuelle Passwort (`PasswortAendernRequest`); das Hinzufügen eines Passkeys ist gleichwertig („sudo mode“). Außerdem sind Social-Verknüpfungen unter Impersonate nicht gesperrt (`VerbietetImpersonate` schützt nur die Passkey-Routen).

Config `userauth.passkey.bestaetigung_minuten` (`.env` `USERAUTH_PASSKEY_BESTAETIGUNG_MINUTEN`), Standard `10`; Werte unter 1 oder nicht numerisch wirken wie `10`.

Die Bestätigung steht in der Sitzung (`userauth.passkey_bestaetigt_bis`, Zeitstempel). Sie gilt nur für diese Sitzung und diesen Benutzer (`userauth.passkey_bestaetigt_fuer` = Benutzer-ID); Anmelden, Abmelden und Sitzungswechsel (`session()->regenerate()` bei Login) löschen sie.

## Akzeptanzkriterien
- **AK1** Gegeben Passkeys aktiv und ein angemeldeter Benutzer ohne gültige Bestätigung, wenn `POST /passkey/register/options` oder `POST /passkey/register` aufgerufen wird, dann antwortet die Anwendung mit 403 und JSON `{"message": <übersetzt de/en>, "bestaetigung_erforderlich": true}`; es entsteht keine Challenge in der Sitzung und kein Passkey.
- **AK2** Gegeben ein angemeldeter Benutzer, wenn er `POST /passkey/bestaetigen` mit dem richtigen aktuellen Passwort (`aktuelles_passwort`) aufruft, dann antwortet die Anwendung mit 204, setzt die Bestätigung bis jetzt + `bestaetigung_minuten`, und `POST /passkey/register/options` und `POST /passkey/register` verhalten sich danach wie vor dieser Änderung.
- **AK3** Gegeben eine Bestätigung, wenn mehr als `bestaetigung_minuten` Minuten vergangen sind, dann verhalten sich beide Registrierungsrouten wie in AK1; bei einer Bestätigung genau innerhalb der Frist gelten sie als bestätigt.
- **AK4** Gegeben ein falsches, leeres oder fehlendes aktuelles Passwort, wenn `POST /passkey/bestaetigen` aufgerufen wird, dann antwortet die Anwendung mit 422 und setzt keine Bestätigung; die Route ist mit `throttle:5,1` gedrosselt (6. Versuch innerhalb einer Minute: 429), und das Passwort steht weder in Antwort noch Log (Feld `aktuelles_passwort` ist ein Geheimfeld, `Geheimfelder::FELDER`).
- **AK5** Gegeben ein Benutzer mit mindestens einem Passkey, wenn er `POST /passkey/bestaetigen/optionen` aufruft, dann antwortet die Anwendung mit JSON der WebAuthn-Anmeldeoptionen, beschränkt auf die Passkeys dieses Benutzers (`allowCredentials`), und merkt die Challenge in der Sitzung (Art `Bestaetigung`, getrennt von Anmeldung und Registrierung).
- **AK6** Gegeben die Challenge aus AK5, wenn `POST /passkey/bestaetigen/assertion` eine gültige Assertion eines Passkeys **dieses** Benutzers liefert, dann antwortet die Anwendung mit 204 und setzt die Bestätigung wie in AK2; die Challenge wird dabei immer verbraucht (auch bei Fehlern). Eine Assertion eines Passkeys eines anderen Benutzers, eine abgelaufene oder fehlende Challenge oder eine ungültige Signatur ergibt 422 ohne Bestätigung. Der Benutzer bleibt angemeldet und wird nicht neu angemeldet (kein `Login`-Ereignis).
- **AK7** Gegeben Passkeys aus, wenn eine der Routen `userauth.passkey.bestaetigen`, `userauth.passkey.bestaetigen.optionen`, `userauth.passkey.bestaetigen.assertion` aufgerufen wird, dann 404 (wie AK1 des Features passkey-login); für Gäste leitet `auth` auf die Anmeldung um.
- **AK8** Gegeben die Passkey-Verwaltung (`GET /passkey`) und das Skript `passkey.js`, wenn „Passkey hinzufügen“ eine Antwort 403 mit `bestaetigung_erforderlich` erhält, dann zeigt die Seite eine Passwortabfrage (und bei vorhandenem Passkey zusätzlich „Mit Passkey bestätigen“), sendet die Bestätigung und wiederholt danach das Hinzufügen einmal; ein Fehler (422/429) zeigt die übersetzte Meldung, ohne dass ein Passkey entsteht.
- **AK9** Gegeben eine Impersonate-Sitzung (`impersonated_by` in der Sitzung), wenn `POST /passkey/bestaetigen`, `…/bestaetigen/optionen` oder `…/bestaetigen/assertion` aufgerufen wird, dann 403 wie bei den übrigen Passkey-Schreibrouten (`VerbietetImpersonate`); eine Bestätigung aus einer früheren Sitzung gilt nicht.
- **AK10** Gegeben eine Impersonate-Sitzung, wenn eine Social-Verknüpfung angelegt (`GET auth/{provider}/callback` mit angemeldetem Benutzer, auch `GET auth/{provider}/redirect`) oder gelöst (`DELETE social-accounts/{provider}`) wird, dann 403 mit der Meldung `userauth::passkey.impersonate_gesperrt`, und es entsteht bzw. verschwindet keine Verknüpfung. Für Gäste (Social-Anmeldung) und ohne Impersonate bleibt das Verhalten unverändert.
- **AK11** Gegeben ein Passkey-Löschen (`DELETE /passkey/{id}`) und das Anlegen weiterer Zugänge, wenn kein Bestätigungs-Nachweis vorliegt, dann bleibt Löschen wie bisher ohne Bestätigung möglich (Nicht-Ziel: Löschen ändert keinen Zugang zugunsten eines Angreifers, nur zugunsten des Inhabers).
- **AK12** Gegeben die README und der CHANGELOG, wenn sie gelesen werden, dann nennt die README `USERAUTH_PASSKEY_BESTAETIGUNG_MINUTEN` mit Standard 10 und das Verhalten „Bestätigung per Passwort oder Passkey“; der CHANGELOG hat einen Eintrag `4.4.0`.

## Randfälle
1. Benutzer ohne nutzbares Passwort und ohne Passkey (z. B. nur OIDC/Social, Zufallspasswort) kann nichts bestätigen und also keinen ersten Passkey hinzufügen. Das ist gewollt als sicherster Standard; die Meldung nennt dann den Weg „Passwort über ‚Passwort vergessen‘ setzen“.
2. Die Bestätigung gilt nicht für andere Benutzer in derselben Browser-Sitzung (nach Impersonate-Ende, Benutzerwechsel): `passkey_bestaetigt_fuer` ≠ aktuelle Benutzer-ID → ungültig.
3. Zwei Registrierungen innerhalb der Frist sind beide erlaubt (Bestätigung wird beim Hinzufügen nicht verbraucht).

## Nicht-Ziele
- Keine Bestätigung für Passkey-Löschen, Passwort ändern (hat sie schon) oder Social-Trennen.
- Keine Änderung an Anmelde-Routen, an `passwort_login` oder an Migrationen.
- Kein „Passwort setzen“ für OIDC-Benutzer.

## Entscheidungsspielraum
- Namen der Klassen, Aufbau der Use-Cases (`PasskeyBestaetigen`, `PasskeyBestaetigungVorbereiten`, Sitzungs-Port), Middleware `VerlangtPasskeyBestaetigung` frei; Schichtung wie in `passkey-login` (Application/Infrastructure, Arch-Test `PasskeySchichtungTest`). Keine neuen Pakete.
- Fristwert Standard 10 Minuten (Vorschlag aus dem Task).
- Release: Minor 4.4.0 (neues Verhalten) vergibt der Moderator beim Merge.

## Rückbau
Rückbau: entfällt (keine Übergangslösung)
