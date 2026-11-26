<?php

declare(strict_types=1);

return [
    'knopf' => 'Sign in with a passkey',
    'nicht_unterstuetzt' => 'This browser does not support passkeys.',
    'abgebrochen' => 'Sign-in cancelled.',
    'registrierung_abgebrochen' => 'Setup cancelled.',
    'bitte_warten' => 'Please confirm on your device …',

    'fehler' => [
        'anmeldung' => 'Signing in with a passkey failed. Please try again.',
        'registrierung' => 'The passkey could not be set up. Please try again.',
        'bestaetigung_erforderlich' => 'Please confirm with your current password or an existing passkey before adding a passkey. If you have no password, set one via “Forgot password”.',
        'bestaetigung' => 'The confirmation failed. Please try again.',
    ],

    'bestaetigung' => [
        'titel' => 'Confirmation',
        'hinweis' => 'Please confirm your identity before adding a passkey. If you do not have your password at hand, use “Forgot password” on the sign-in page.',
        'passwort' => 'Current password',
        'passwort_knopf' => 'Confirm with password',
        'passkey_knopf' => 'Confirm with passkey',
    ],

    'verwaltung' => [
        'title' => 'Passkeys',
        'leer' => 'You have not set up a passkey yet.',
        'angelegt' => 'created on :datum',
        'zuletzt_benutzt' => 'last used on :datum',
        'nie_benutzt' => 'not used yet',
        'loeschen' => 'Delete',
        'loeschen_frage' => 'Really delete this passkey?',
        'hinzufuegen' => 'Add a passkey',
        'name' => 'Name (e.g. phone, laptop)',
        'hinzufuegen_knopf' => 'Set up passkey',
        'hinweis_zweitgeraet' => 'Set up a passkey on a second device so that you keep access if you lose a device.',
        'warnung' => 'Password sign-in is disabled and your account is not linked to OIDC or Google. Without a passkey you can no longer sign in.',
    ],

    'status' => [
        'hinzugefuegt' => 'The passkey has been set up.',
        'geloescht' => 'The passkey has been deleted.',
        'zurueckgesetzt' => ':anzahl passkey(s) deleted.',
    ],

    'impersonate_gesperrt' => 'This action is not allowed while impersonating a user.',

    'benutzer' => [
        'anzahl' => 'Passkeys',
        'zuruecksetzen' => 'Reset passkeys',
        'zuruecksetzen_frage' => 'Delete all passkeys of this user?',
    ],
];
