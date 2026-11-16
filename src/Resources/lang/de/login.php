<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Customer Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    // Views Header
    'header' => 'Login',

    'id' => 'ID',
    'login' => 'Login',
    'email' => 'E-Mail Addresse',
    'password' => 'Passwort',
    'remember' => 'Eingeloggt bleiben',
    // Feld der Kennung je userauth.anmeldung_per (Modus email behält die Labels der Views)
    'kennung_benutzername' => 'Benutzername',
    'kennung_beides' => 'Benutzername oder E-Mail',
    // Ein Text für jeden Fehlschlag der Anmeldung (unbekannt, falsches Passwort, mehrdeutige E-Mail)
    'fehlgeschlagen_benutzername' => 'Benutzername oder Passwort ist falsch.',
    'fehlgeschlagen_beides' => 'Die Anmeldedaten sind falsch. Bei gemeinsam genutzter E-Mail-Adresse bitte den Benutzernamen verwenden.',
    'pwforgotten' => 'Passwort vergessen',

    'seite_titel' => 'Anmeldung',
    'seite_text' => 'Sicher und einfach auf Ihr Konto zugreifen.',
    'merkmal_schnell' => 'Schnell',
    'merkmal_einfach' => 'Einfach',
    'merkmal_responsiv' => 'Responsiv',
];
