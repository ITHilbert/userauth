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

    'benutzer' => [
        'anzahl' => 'Passkeys',
        'zuruecksetzen' => 'Passkeys zurücksetzen',
        'zuruecksetzen_frage' => 'Alle Passkeys dieses Benutzers löschen?',
    ],
];
