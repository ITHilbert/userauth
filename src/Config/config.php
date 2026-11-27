<?php

declare(strict_types=1);

use ITHilbert\UserAuth\Http\Middleware\TwoFactorMiddleware;

return [
    'routes' => [
        'users_prefix' => 'users',
        'users_middleware' => ['auth', TwoFactorMiddleware::class],
        'roles_prefix' => 'roles',
        'roles_middleware' => ['auth', 'hasPermission:role_read', TwoFactorMiddleware::class],
        'permissions_prefix' => 'permissions',
        'permissions_middleware' => ['auth', 'hasRole:admin', TwoFactorMiddleware::class],
    ],

    // Pfad für Redirect nach erfolgreichem Login
    'redirect_after_login' => '/',
    // Pfad für Redirect nach dem Logout
    'redirect_after_logout' => '/',
    // Route wenn Rechte fehlen: Weiterleitung zur Login-Seite ('login'), zur Fehlerseite ('no-permission') oder HTTP 403 ('403')
    'redirect_on_no_permission' => 'login',

    // Adresse (absolut oder ab /) eines Passwort-Generators des Hosts für den Button „Passwort erzeugen“ in der
    // Benutzerverwaltung; Antwort: JSON {"passwort": "…"}. Leer = kein Button.
    'passwort_generator_url' => env('USERAUTH_PASSWORT_GENERATOR_URL'),

    // Theme-Auswahl der Auth-Views: 'tailwind' (Default) oder 'bootstrap' (Phoenix/Bootstrap).
    // Neue Projekte brauchen nichts zu setzen. Ältere Bootstrap-Projekte setzen
    // USERAUTH_THEME=bootstrap in der .env.
    'theme' => env('USERAUTH_THEME', 'tailwind'),

    // Zusätzliche Assets für die Auth-Views (beide Themes), Pfade relativ zu public/, geladen via asset().
    // z. B. Phoenix-Theme für das Bootstrap-Theme: 'css' => ['assets/css/theme.min.css'], 'js' => ['assets/js/config.js']
    'auth_assets' => [
        'css' => [],
        'js' => [],
    ],

    // Vite-Einträge des Hosts, die die Auth-Views laden (Pfade wie im Vite-Manifest). Geladen werden nur Einträge,
    // die im Manifest (public/build/manifest.json) stehen; bei laufendem Dev-Server (public/hot) alle.
    // Ohne passenden Eintrag wird kein @vite ausgegeben (kein 500).
    'vite_entries' => ['resources/css/app.css', 'resources/js/app.js'],

    // Name einer View des Hosts, die in den <head> der Auth-Seiten mit eigenem <head> eingebunden wird
    // (z. B. Favicon, Meta-Tags). Leer/null = nichts. Fehlt die View, wird sie ignoriert.
    'head_view' => env('USERAUTH_HEAD_VIEW') ?: null,

    // Passwort des Admin-Kontos, das der UsersTableSeeder bei leerer Tabelle unter einer .lan-Domain oder einem lokalen Host anlegt.
    // Leer oder nicht gesetzt: Der Seeder erzeugt ein Zufallspasswort und gibt es einmal auf der Konsole aus.
    'admin_passwort' => env('USERAUTH_ADMIN_PASSWORT'),

    // E-Mail eines gelöschten Benutzers bei einer Neuanlage (Admin-Anlage, Social-Registrierung):
    // 'sperren' (Default) = Neuanlage abgelehnt, 'reaktivieren' = gelöschtes Konto wiederhergestellt und bereinigt,
    // 'neu' = gelöschtes Konto endgültig entfernt, danach normale Neuanlage. Jeder andere Wert wirkt wie 'sperren'.
    'geloeschte_email' => env('USERAUTH_GELOESCHTE_EMAIL', 'sperren'),

    // Womit sich Benutzer anmelden (Anmeldeseite, „Passwort vergessen“): 'email' (Default) = E-Mail-Adresse,
    // 'benutzername' = nur Benutzername (Spalte users.benutzername, mehrere Konten dürfen eine E-Mail teilen),
    // 'beides' = Benutzername oder – bei genau einem Konto – E-Mail. Groß-/Kleinschreibung und Randleerzeichen
    // zählen nicht, jeder andere Wert wirkt wie 'email'. 'benutzername'/'beides' brauchen die Paket-Migration
    // add_benutzername_to_users_table; gemeinsame E-Mails zusätzlich den Rückbau des Unique-Index (README).
    'anmeldung_per' => env('USERAUTH_ANMELDUNG_PER', 'email'),

    // Passwort-Regeln für neue Passwörter (Ändern, Zurücksetzen, Admin, Registrierung).
    // min_laenge: Mindestlänge in Zeichen (Standard 8); ungültige Werte (nicht ganzzahlig, kleiner 1, leer) wirken wie 8.
    // haeufige_ablehnen: true = Passwörter aus der mitgelieferten Liste häufiger Passwörter werden abgelehnt
    // (ohne Beachtung von Groß-/Kleinschreibung und Randleerzeichen); Standard false.
    'passwort' => [
        'min_laenge' => env('USERAUTH_PASSWORT_MIN_LAENGE', 8),
        'haeufige_ablehnen' => env('USERAUTH_PASSWORT_HAEUFIGE_ABLEHNEN', false),
    ],

    // Aktiviert das Audit Log
    'audit_log_enabled' => env('USERAUTH_AUDIT_LOG_ENABLED', false),
    // Aktiviert 2-Faktor-Authentifizierung
    'two_factor_enabled' => env('USERAUTH_2FA_ENABLED', false),
    // Darf ein Admin (Rolle "admin") sich temporär als ein anderer Nutzer anmelden?
    'impersonate_enabled' => true,

    // Social Login (OAuth). Zugangsdaten liegen in der Host-App unter config('services.google.*')
    // (client_id, client_secret). Die Redirect-URL setzt das Package selbst (Route userauth.social.callback).
    'social' => [
        // Schaltet alle Social-Login-Routen und -Buttons ein/aus (aus = Routen liefern 404)
        'enabled' => env('USERAUTH_SOCIAL_ENABLED', false),
        // Aktivierte Anbieter (aktuell unterstützt: 'google')
        'providers' => ['google'],
        // Dürfen unbekannte Nutzer beim ersten Social-Login automatisch ein Konto erhalten?
        'allow_registration' => env('USERAUTH_SOCIAL_REGISTRATION', false),
        // Rollen-Key (roles.role) für automatisch angelegte Konten
        'default_role' => env('USERAUTH_SOCIAL_DEFAULT_ROLE', 'user'),
        // Erlaubte E-Mail-/Workspace-Domains (z. B. ['firma.de']); leer = alle Domains
        'allowed_domains' => [],
        // Gültigkeit des Bestätigungslinks für das Verknüpfen unbestätigter Konten
        'pending_link_ttl_minutes' => 60,
    ],

    // Anmeldung über den eigenen Anmeldedienst (OpenID Connect, z. B. Authelia unter https://auth.lan).
    // Redirect-URI beim Anbieter: https://<app>/auth/oidc/callback (Route userauth.oidc.callback).
    // „Konfiguriert“ = enabled und issuer, client_id, client_secret nicht leer; sonst kein Knopf, keine Weiterleitung.
    'oidc' => [
        // Schaltet die Routen /auth/oidc/… und den Knopf ein/aus (aus = Routen liefern 404)
        'enabled' => env('USERAUTH_OIDC_ENABLED', false),
        // Issuer des Anbieters, z. B. https://auth.lan (Discovery unter <issuer>/.well-known/openid-configuration)
        'issuer' => env('USERAUTH_OIDC_ISSUER', ''),
        'client_id' => env('USERAUTH_OIDC_CLIENT_ID', ''),
        'client_secret' => env('USERAUTH_OIDC_CLIENT_SECRET', ''),
        // Pfad zum CA-Bundle für alle Aufrufe an den Anbieter (z. B. lan-root-ca); leer = Standard-Zertifikate
        'ca_bundle' => env('USERAUTH_OIDC_CA_BUNDLE', ''),
        'button_text' => env('USERAUTH_OIDC_BUTTON_TEXT', 'Mit IT-Hilbert anmelden'),
        // Unbekannte Benutzer (kein verknüpfter sub, keine passende E-Mail) automatisch anlegen?
        'auto_create' => env('USERAUTH_OIDC_AUTO_CREATE', false),
        // Rollen-Key (roles.role) für automatisch angelegte Benutzer ohne passende Gruppe
        'default_role' => env('USERAUTH_OIDC_DEFAULT_ROLE', 'user'),
        // Gruppen des Anbieters => Rolle bei Neuanlage, z. B. 'admins=admin,devs=dev' (erste passende gilt)
        'group_roles' => env('USERAUTH_OIDC_GROUP_ROLES', ''),
        // Login-Seite leitet Gäste direkt zum Anbieter weiter (Standard: true; nur wenn OIDC aktiv und konfiguriert ist).
        // Notzugang: /login?lokal=1 zeigt weiter das Formular; false schaltet die Weiterleitung ab.
        // Nach dem Abmelden zeigt /login?abgemeldet=1 eine Meldung mit Knopf und Formular statt sofort weiterzuleiten.
        'auto_redirect' => env('USERAUTH_OIDC_AUTO_REDIRECT', true),
    ],

    // Anmeldung mit Passkey (WebAuthn: Fingerabdruck, Gesichtserkennung, Geräte-PIN, Sicherheitsschlüssel).
    // HTTPS ist Pflicht (außer localhost). rp_id nicht mehr ändern, sobald Passkeys eingetragen sind – sie gelten
    // nur für diese Domain.
    'passkey' => [
        // Schaltet die Routen /passkey/… und den Knopf auf der Login-Seite ein/aus (aus = Routen liefern 404)
        'enabled' => env('USERAUTH_PASSKEY_ENABLED', false),
        // Domain der Passkeys (Relying Party ID), leer = Host aus app.url
        'rp_id' => env('USERAUTH_PASSKEY_RP_ID', ''),
        // Name der Anwendung, den das Gerät beim Anlegen zeigt, leer = app.name
        'rp_name' => env('USERAUTH_PASSKEY_RP_NAME', ''),
        // Erlaubte Ursprünge, kommagetrennt (https://a.lan,https://b.lan), leer = Ursprung aus app.url
        'origins' => env('USERAUTH_PASSKEY_ORIGINS', ''),
        // Minuten, die eine Bestätigung (Passwort oder Passkey) vor „Passkey hinzufügen“ gilt; unter 1 oder nicht numerisch = 10
        'bestaetigung_minuten' => env('USERAUTH_PASSKEY_BESTAETIGUNG_MINUTEN', 10),
    ],

    // Anmeldung mit Passwort: 'immer' (Standard) | 'ohne_passkey' (gesperrt für Benutzer mit mindestens einem
    // Passkey) | 'nie' (für alle gesperrt, z. B. nur OIDC); jeder andere Wert wirkt wie 'immer'
    'passwort_login' => env('USERAUTH_PASSWORT_LOGIN', 'immer'),

    // Selbstregistrierung per E-Mail und Passwort (Routen /register …; aus = Routen liefern 404)
    'registration' => [
        // Schaltet Registrierung, Bestätigungslink und „Link erneut anfordern“ ein/aus
        'enabled' => env('USERAUTH_REGISTRATION', false),
        // Rollen-Key (roles.role) für neue Konten; leer = keine Rolle. Nie 'admin' (privilegiert) oder 'dev'.
        'role' => env('USERAUTH_REGISTRATION_ROLE'),
        // Gültigkeit des Bestätigungslinks in Minuten (leer = 60)
        'verify_ttl_minutes' => 60,
    ],

    // Erweiterte Passwort-Sicherheitsrichtlinien
    'password_policy' => [
        'enabled' => env('USERAUTH_PASSWORD_POLICY_ENABLED', false), // Gibt an, ob Ablauf und Historie forciert werden
        'require_change_every_days' => 90, // Anzahl Tage, nach denen das Passwort geändert werden muss
        'prevent_reuse_last_passwords' => 3, // Anzahl historischer Passwörter, die nicht wiederverwendet werden dürfen
    ],

    // Mandanten / Teams
    'teams' => [
        'enabled' => env('USERAUTH_TEAMS_ENABLED', false),
    ],

    // Feld 'name' befüllen
    'name' => 0,  // 0 = Manuell 1 = Vorname Nachname 2 = Nachname, Vorname 3 = Nachname 4 = Vorname
    // Sollen die Views von ressources or vendor verwendet werden
    'views' => 'vendor',
    // View welche Felder anzeigen
    'user' => [
        'anrede' => false,
        'title' => false,
        'firstname' => false,
        'lastname' => false,
        'smallname' => false,
        'name' => true,
        'role' => true,
        'email' => true,
        'private_email' => false,
        'street' => false,
        'postcode' => false,
        'city' => false,
        'country' => false,
        'signature_rule' => false,
        'ustid' => false,
        'phone' => false,
        'phone2' => false,
        'mobile' => false,
        'fax' => false,
        'skype' => false,
        'hourly_rate' => false,
        'birthday' => false,
        'comment' => false,
        'image' => true,
    ],
    'signature_rules' => [
        1 => [
            'id' => 1,
            'signature_rule' => 'i.A.',
            'meaning' => 'im Auftrag (i.A.)',
        ],
        2 => [
            'id' => 2,
            'signature_rule' => 'i.V.',
            'meaning' => 'in Vollmacht (i.V.)',
        ],
        3 => [
            'id' => 3,
            'signature_rule' => 'ppa.',
            'meaning' => 'Mit Prokura (ppa.)',
        ],
        4 => [
            'id' => 4,
            'signature_rule' => '',
            'meaning' => 'Geschäftsführer',
        ],
    ],
    'anrede' => [
        [
            'id' => '1',
            'anrede' => 'keine',
        ],
        [
            'id' => '2',
            'anrede' => 'Frau',
        ],
        [
            'id' => '3',
            'anrede' => 'Herr',
        ],
    ],

];
