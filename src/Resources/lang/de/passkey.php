<?php

declare(strict_types=1);

return [
    'knopf' => 'Mit Passkey anmelden',
    'nicht_unterstuetzt' => 'Dieser Browser unterstützt keine Passkeys.',
    'abgebrochen' => 'Anmeldung abgebrochen.',
    'registrierung_abgebrochen' => 'Einrichtung abgebrochen.',
    'bitte_warten' => 'Bitte bestätigen Sie auf Ihrem Gerät …',

    'fehler' => [
        'anmeldung' => 'Die Anmeldung mit Passkey ist fehlgeschlagen. Bitte versuchen Sie es erneut.',
        'registrierung' => 'Der Passkey konnte nicht eingerichtet werden. Bitte versuchen Sie es erneut.',
        'bestaetigung_erforderlich' => 'Bitte bestätigen Sie sich mit Ihrem aktuellen Passwort oder einem vorhandenen Passkey, bevor Sie einen Passkey hinzufügen. Haben Sie kein Passwort, setzen Sie es über „Passwort vergessen“.',
        'bestaetigung' => 'Die Bestätigung ist fehlgeschlagen. Bitte versuchen Sie es erneut.',
    ],

    'bestaetigung' => [
        'titel' => 'Bestätigung',
        'hinweis' => 'Bitte bestätigen Sie Ihre Identität, bevor Sie einen Passkey hinzufügen. Haben Sie Ihr Passwort nicht zur Hand, nutzen Sie „Passwort vergessen“ auf der Anmeldeseite.',
        'passwort' => 'Aktuelles Passwort',
        'passwort_knopf' => 'Mit Passwort bestätigen',
        'passkey_knopf' => 'Mit Passkey bestätigen',
    ],

    'verwaltung' => [
        'title' => 'Passkeys',
        'leer' => 'Sie haben noch keinen Passkey eingerichtet.',
        'angelegt' => 'angelegt am :datum',
        'zuletzt_benutzt' => 'zuletzt benutzt am :datum',
        'nie_benutzt' => 'noch nicht benutzt',
        'loeschen' => 'Löschen',
        'loeschen_frage' => 'Diesen Passkey wirklich löschen?',
        'hinzufuegen' => 'Passkey hinzufügen',
        'name' => 'Bezeichnung (z. B. Handy, Laptop)',
        'hinzufuegen_knopf' => 'Passkey einrichten',
        'hinweis_zweitgeraet' => 'Richten Sie einen Passkey auf einem zweiten Gerät ein, damit Sie bei Verlust eines Geräts weiter Zugang haben.',
        'warnung' => 'Die Anmeldung mit Passwort ist abgeschaltet und Ihr Konto ist weder mit OIDC noch mit Google verknüpft. Ohne Passkey können Sie sich nicht mehr anmelden.',
    ],

    'status' => [
        'hinzugefuegt' => 'Der Passkey wurde eingerichtet.',
        'geloescht' => 'Der Passkey wurde gelöscht.',
        'zurueckgesetzt' => ':anzahl Passkey(s) gelöscht.',
    ],

    'impersonate_gesperrt' => 'Unter einer Benutzerübernahme ist diese Aktion nicht erlaubt.',

    'benutzer' => [
        'anzahl' => 'Passkeys',
        'zuruecksetzen' => 'Passkeys zurücksetzen',
        'zuruecksetzen_frage' => 'Alle Passkeys dieses Benutzers löschen?',
    ],
];
