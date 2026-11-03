# 04. Sicherheitsregeln

## Session Sicherheit

Um die Sicherheit der User-Sessions zu gewährleisten, müssen folgende Einstellungen in der `.env` Datei der Hauptanwendung gesetzt werden:

### HTTPS Umgebung (Produktion)
Wenn die Anwendung über HTTPS läuft (was dringend empfohlen wird), muss folgendes gesetzt sein:

```dotenv
SESSION_SECURE_COOKIE=true
```

Dies verhindert, dass Session-Cookies über unsichere HTTP-Verbindungen gesendet werden.

### HTTP Only
Die Einstellung `http_only` ist in der `config/session.php` dieses Pakets (oder der Hauptanwendung) bereits standardmäßig auf `true` gesetzt. Dies verhindert den Zugriff auf Session-Cookies per JavaScript (Schutz gegen XSS).

## Rate Limiting

Das Paket verwendet Standard-Laravel Rate Limiting für:
- Login (5 Versuche pro Minute)
- Registrierung: `POST /register` und `POST /register/bestaetigung` je 5 Anfragen pro Minute und IP. „Link erneut anfordern“ zusätzlich höchstens ein Link je E-Mail-Adresse pro 60 Sekunden (siehe Abschnitt Selbstregistrierung)
- Passwort Reset Anforderung (5 Versuche pro Minute)

## Audit Logging

Alle Authentifizierungs-Versuche (Login erfolgreich, fehlgeschlagen, Logout) werden in der Tabelle `user_auth_audit_logs` protokolliert.

Zusätzlich die Selbstregistrierung: Ereignis `registered` (Konto angelegt) und `email_verified` (E-Mail über den Link bestätigt). Bei doppelter E-Mail gibt es kein `registered`. Nur bei `audit_log_enabled`.

## Selbstregistrierung

Gilt, wenn `userauth.registration.enabled` an ist. Ist sie aus, antworten alle Registrierungsrouten mit 404 (siehe `01_Architekturübersicht.md`).

### Doppelte E-Mail
- Eine Adresse ist vergeben, wenn ein Konto sie hat – auch ein soft-gelöschtes, unabhängig von Groß-/Kleinschreibung.
- Die Antwort ist dieselbe wie bei einem neuen Konto (Status, Weiterleitung, Meldung). Es entstehen kein Konto, keine Mail und kein Audit-Eintrag `registered`.
- Das Passwort wird trotzdem gehasht, damit die Antwortzeit nicht verrät, ob die Adresse vergeben ist.
- Regeln für E-Mails gelöschter Konten sind offen (#523); bis dahin gilt diese Regel.

### Bestätigungslink
- Signiert und zeitlich begrenzt (`userauth.registration.verify_ttl_minutes`). Der Link wird aus `app.url` gebildet, nicht aus dem Host-Header der Anfrage.
- Gebunden an die Konto-ID und an `sha256` der E-Mail-Adresse (`Domain/Registrierung/BestaetigungsHash`). Ändert sich die Adresse, passt der Link nicht mehr. Der Vergleich läuft über `hash_equals`.
- Ungültige Links (Signatur, Ablauf, Hash, unbekanntes oder gelöschtes Konto) bestätigen nichts. Ein zweiter Aufruf eines gültigen Links ändert nichts und gilt als Erfolg.
- Die Mail geht erst nach der HTTP-Antwort raus (`defer()`, wie beim Passwort-Reset).

### Erneut anfordern
- Die Antwort ist für jede Eingabe gleich: bekannte, unbekannte, bestätigte und gelöschte Adressen.
- Eine Mail geht nur an ein nicht gelöschtes Konto mit „Bestätigung offen“.
- Die Sperre von 60 Sekunden gilt je E-Mail-Adresse, Schreibweise egal.

### Login-Sperre
- Gesperrt sind Konten mit `self_registered` = 1 und leerem `email_verified_at` („Bestätigung offen“). Die Sperre greift nach der Passwortprüfung: Es erfolgt keine Anmeldung und kein 2FA-Code wird versendet. Der Login zeigt den Hinweis mit dem Weg zum erneuten Anfordern.
- Nicht betroffen sind Konten ohne `self_registered` (Admin, Seeder, Social Login), auch wenn `email_verified_at` leer ist.

### Pre-Account-Takeover über Social Login (AK17)
- Wer eine fremde Adresse vorab registriert, soll das Konto des echten Inhabers nicht über den Social Login übernehmen können.
- Bestätigt der Social Login die Adresse eines Kontos mit „Bestätigung offen“, verfallen das bei der Registrierung gesetzte Passwort und die Angemeldet-bleiben-Tokens (`EloquentSocialUserRepository::markEmailVerified`). Danach funktionieren Social Login und „Passwort vergessen“ wie gewohnt.
- Konten ohne „Bestätigung offen“ behalten ihr Passwort.

### Anlegen und Rollen
- Das Konto wird nur mit festen Feldern angelegt (`forceFill`): Name, E-Mail, Passwort-Hash, `password_changed_at`, Rolle aus der Config, `self_registered` = 1, `email_verified_at` = null. Felder aus der Eingabe werden nicht übernommen; `role_id`, `self_registered` und `email_verified_at` sind nicht per Eingabe setzbar.
- Name und E-Mail werden vor der Validierung getrimmt. Das Passwort wird nach `NeuesPasswortRegeln` geprüft.
- Mit aktiver `password_policy` wird `password_changed_at` gesetzt, damit das Konto nicht sofort abläuft. Eine Historienzeile entsteht nicht.
- `userauth.registration.role` darf nie `dev` oder `admin` sein, weder als Name noch als privilegierte Rolle. Sonst wirft die Anwendung `RegistrierungFalschKonfiguriertException`, und es entsteht kein Konto.
