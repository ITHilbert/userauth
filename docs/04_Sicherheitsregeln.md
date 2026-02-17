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
- Registrierung (5 Versuche pro Minute)
- Passwort Reset Anforderung (5 Versuche pro Minute)

## Audit Logging

Alle Authentifizierungs-Versuche (Login erfolgreich, fehlgeschlagen, Logout) werden in der Tabelle `user_auth_audit_logs` protokolliert.
