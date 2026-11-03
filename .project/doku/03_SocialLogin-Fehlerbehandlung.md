# Social Login — Fehlerbehandlung & Verfügbarkeit

## Überblick

Das Social-Login-System (Google) im Package `ithilbert/userauth` implementiert mehrstufige Fehlerbehandlung und Verfügbarkeitsprüfung, um eine sichere und benutzerfreundliche Authentifizierung zu gewährleisten.

## Architektur

Die Fehlerbehandlung folgt einer klaren Schichtenstruktur:

```
HTTP Layer (Controller)
    ↓ fängt ab & behandelt
Application Layer (UseCase)
    ↓ wirft Projekt-Exceptions
Infrastructure Layer (Adapter)
    ↓ übersetzt Fremd-Exceptions
    Socialite/Guzzle (externe Libs)
```

### Port: SocialIdentityProvider

Der Port `Application/SocialLogin/Contracts/SocialIdentityProvider.php` definiert zwei kritische Methoden:

```php
public function isAvailable(): bool;
public function authenticate(string $code, ?string $state = null): SocialIdentity;
```

**`isAvailable()`:** Prüft, ob Credentials (`google.client_id`, `google.client_secret`) gesetzt sind. Returns `false`, wenn eine Credential fehlt.

**`authenticate()`:** Führt Authentifizierung durch. Wirft **nur** Projekt-Exception `SocialProviderUnavailableException` (keine Fremd-Exceptions).

## Fehlerbehandlung auf Adapter-Ebene

### Konfiguration: DriverMissingConfigurationException

**Problem (vor Bugfix):**
Host-App vergisst `google.redirect` in `config/services.php` → Socialite wirft `DriverMissingConfigurationException` → Exception propagiert unbehandelt bis zur Debug-Seite.

**Lösung (Bugfix):**
`SocialiteIdentityProvider` (Adapter) baut die vollständige Konfiguration selbst:

```php
public function authenticate(string $code, ?string $state = null): SocialIdentity
{
    try {
        $config = [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect' => route('userauth.social.callback', 'google'),
        ];
        
        $provider = $this->socialiteManager->buildProvider('google', $config);
        // ... Authentifizierung
    } catch (DriverMissingConfigurationException $e) {
        throw new SocialProviderUnavailableException('Google');
    }
}
```

**Auswirkung:**
- Host-App **muss kein** `google.redirect` setzen.
- Fehlende `client_id` oder `client_secret` führen zu Projekt-Exception (nicht Debug-Seite).
- Error wird sauber abgefangen und geloggt.

### Verfügbarkeitsprüfung: isAvailable()

```php
public function isAvailable(): bool
{
    return config('services.google.client_id') && config('services.google.client_secret');
}
```

Diese Prüfung wird von der Query `ListAvailableSocialProviders` genutzt:

```php
// Application/SocialLogin/Queries/ListAvailableSocialProviders.php
public function __invoke(): array
{
    $available = [];
    
    if ($this->provider->isAvailable()) {
        $available[] = 'google';
    }
    
    return $available;
}
```

Der View-Composer `AvailableSocialProvidersComposer` nutzt diese Query:

```php
public function compose(View $view)
{
    $view->with('availableSocialProviders', ($this->query)());
}
```

**Auswirkung:**
- Google-Button auf Login-Seite verschwindet, wenn Credentials fehlen.
- Verbinden-Link auf Profil-Seite ist verborgen.
- Keine Error-Meldung für Endnutzer (nur im Log als Fehler-Debug).

## Fehlerbehandlung im Controller

### SocialLoginController::redirect()

Prüft Verfügbarkeit VOR Weiterleitung zu Google:

```php
public function redirect(Request $request, string $provider)
{
    $query = resolve(ListAvailableSocialProviders::class);
    
    if (!in_array($provider, $query())) {
        return redirect()->route('userauth.login')
            ->with('error', trans('userauth::social.errors.provider_unavailable'));
    }
    
    // Weiterleitung zu Google
}
```

Fallback: Falls Credentials nach der Seiten-Anzeige gelöscht werden, zeigt Controller eine Fehlermeldung statt zu crashen.

### SocialLoginController::callback()

Fängt Projekt-Exception ab und behandelt sie benutzerfreundlich:

```php
public function callback(Request $request, string $provider)
{
    try {
        $identity = $this->provider->authenticate($request->input('code'));
        // ... Login-Logik
    } catch (SocialProviderUnavailableException $e) {
        Log::error("Social provider unavailable", [
            'provider' => $provider,
            'message' => $e->getMessage(),
            'timestamp' => now(),
        ]);
        
        return redirect()->route('userauth.login')
            ->with('error', trans('userauth::social.errors.provider_unavailable'));
    }
}
```

**Auswirkung:**
- User sieht benutzersichtbare Meldung (z.B. "Google ist momentan nicht erreichbar").
- Stack Trace landet im Audit-Log, nicht im UI.
- Keine Debug-Seite, auch wenn Google API ausfällt oder fehlkonfiguriert ist.

## Übersetzungsmeldungen

Im `resources/lang/de/social.php`:

```php
'errors' => [
    'provider_unavailable' => 'Der Login-Provider ist momentan nicht verfügbar. Bitte versuchen Sie es später erneut oder kontaktieren Sie den Support.',
],
```

Diese Meldung wird verwendet:
- Wenn `isAvailable()` false zurückgibt.
- Wenn `authenticate()` `SocialProviderUnavailableException` wirft.
- Bei Google-API-Ausfällen oder Netzwerkfehlern.

## Audit-Log & Logging

Alle relevanten Fehler werden ins Audit-Log geloggt (falls `USERAUTH_AUDIT_LOG_ENABLED=true`):

| Ereignis | Log-Level | Inhalt |
|----------|-----------|--------|
| Credentials fehlen | `warning` | Feldnamen: `google.client_id`, `google.client_secret` (keine Werte) |
| Provider nicht verfügbar | `error` | Provider-Name, Fehlermeldung, Timestamp |
| Google API-Fehler | `error` | Stacktrace, Request-Details, User-ID (falls authentifiziert) |

**DSGVO:** Werte von Secrets werden niemals geloggt — nur Feldnamen/Schlüssel.

## Sicherheitsaspekte

### Keine Debug-Informationen in der UI

Beispiel **FALSCH:**
```blade
@if($error)
    {{ $error->getMessage() }} {{-- Zeigt Stack Trace! --}}
@endif
```

Beispiel **RICHTIG:**
```blade
@if(session('error'))
    {{ session('error') }} {{-- Zeigt nur die Meldung aus Meldungen --}}
@endif
```

### Identifikation über provider_user_id

Auch wenn Google die E-Mail-Adresse ändert, bleibt die Verknüpfung stabil über `(provider, provider_user_id)`:

```php
$account = UserSocialAccount::where([
    'user_id' => $user->id,
    'provider' => 'google',
    'provider_user_id' => $identity->provider_user_id,
])->first();
```

**Vorteile:**
- E-Mail-Änderungen bei Google setzen keine neuen Konten auf.
- Keine Account-Übernahme durch E-Mail-Hijacking.

### Token-Management für Bestätigungslinks

Bestätigungstokens werden als SHA-256-Hash persistent gemacht:

```php
'confirmation_token_hash' => Hash::make($token)  // oder SHA-256
```

**Fallstricke:**
- Token ist einmalig → nach Bestätigung muss er aus DB gelöscht werden (verhindert Wiederverwendung).
- `expires_at` wird geprüft → abgelaufene Tokens sollten täglich gelöscht werden (via `userauth:prune-social-links`).
- Race Condition: Wenn User doppelt auf Link klickt:
  - POST liest Token mit `lockForUpdate()` (Datenbank-Lock).
  - Zweiter Request wartet.
  - Nach Bestätigung ist Token weg → zweiter Request erhält "ungültig".

## Besonderheiten & Fallstricke

### 1. View-Composer wird auf jedem Request ausgeführt
**Fallstrick:** Wenn `isAvailable()` teuer ist (z.B. DB-Abfrage), wird Seite langsam.  
**Best Practice:** `isAvailable()` nur Config-Werte prüfen, keine DB-Queries.

### 2. Config-Cache ignorieren?
Wenn Host-App `php artisan config:cache` nutzt, werden Änderungen an `.env` **nicht** übernommen bis zum `config:clear`.  
**Best Practice:** Nach `.env`-Änderungen immer `config:clear` ausführen.

### 3. Externe Exceptions übersetzen
Wenn Adapter neue Fremd-Exceptions nicht übersetzt (z.B. neue Socialite-Exceptions in zukünftigen Versionen), landen sie auf der Debug-Seite.  
**Best Practice:** Adapter mit `catch (\Throwable $e)` bewehren, alles zu Projekt-Exception übersetzen.

### 4. Email-Verification & Double-Verify
**Fallstrick:** Wenn User E-Mail nach Google-Login ändert und beide E-Mails unterschiedlich bei Google/lokal verifiziert sind.  
**Lösung:** Verknüpfung über `provider_user_id`, nicht E-Mail. `email_verified_at` spielt nur beim initialen Verknüpfen eine Rolle.

## Test-Strategie

### Unit: Port prüfen
```php
public function testIsAvailableReturnsFalseWhenCredentialsMissing()
{
    Config::set('services.google.client_id', null);
    
    $this->assertFalse($this->provider->isAvailable());
}
```

### Feature: Exception-Handling im Controller
```php
public function testCallbackShowsUserErrorWhenProviderUnavailable()
{
    Config::set('services.google.client_secret', null);
    
    $response = $this->get('/auth/google/callback?code=xyz');
    
    $this->assertRedirect(route('userauth.login'));
    $this->assertEquals('provider_unavailable', session('error'));
}
```

### Integration: End-to-End mit Mock Google API
```php
public function testSuccessfulSocialLogin()
{
    $this->mockSocialiteUser([
        'email' => 'user@example.com',
        'email_verified' => true,
    ]);
    
    $response = $this->get('/auth/google/callback?code=xyz');
    
    $this->assertAuthenticated();
}
```
