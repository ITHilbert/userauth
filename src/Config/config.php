<?php

return [
    'routes' => [
        'users_prefix' => 'users',
        'users_middleware' => ['auth', \ITHilbert\UserAuth\Http\Middleware\TwoFactorMiddleware::class],
        'roles_prefix' => 'roles',
        'roles_middleware' => ['auth', 'hasPermission:role_read', \ITHilbert\UserAuth\Http\Middleware\TwoFactorMiddleware::class],
        'permissions_prefix' => 'permissions',
        'permissions_middleware' => ['auth', 'hasRole:dev', \ITHilbert\UserAuth\Http\Middleware\TwoFactorMiddleware::class],
    ],

    // Pfad für Redirect nach erfolgreichem Login
    'redirect_after_login' => '/home',
    // Pfad für Redirect nach dem Logout
    'redirect_after_logout' => '/home',
    // Route wenn Rechte fehlen: Weiterleitung zur Login-Seite ('login') oder zur Fehlerseite ('no-permission')
    'redirect_on_no_permission' => 'login',

    // Aktiviert das Audit Log
    'audit_log_enabled' => env('USERAUTH_AUDIT_LOG_ENABLED', false),
    // Aktiviert 2-Faktor-Authentifizierung
    'two_factor_enabled' => env('USERAUTH_2FA_ENABLED', false),
    // Darf ein Admin ("dev" oder "admin") sich temporär als ein anderer Nutzer anmelden?
    'impersonate_enabled' => true,
    
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

    //Feld 'name' befüllen
    'name' => 0,  // 0 = Manuell 1 = Vorname Nachname 2 = Nachname, Vorname 3 = Nachname 4 = Vorname
    //Sollen die Views von ressources or vendor verwendet werden
    'views' => 'vendor',
    //View welche Felder anzeigen
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
            'id' => "1",
            'anrede' => 'keine'
        ],
        [
            'id' => "2",
            'anrede' => 'Frau'
        ],
        [
            'id' => "3",
            'anrede' => 'Herr'
        ],
    ],

];
