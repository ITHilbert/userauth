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
    'email' => 'E-Mail',
    'password' => 'Password',
    'remember' => 'remember me',
    // Feld der Kennung je userauth.anmeldung_per (Modus email behält die Labels der Views)
    'kennung_benutzername' => 'Username',
    'kennung_beides' => 'Username or e-mail',
    // Ein Text für jeden Fehlschlag der Anmeldung (unbekannt, falsches Passwort, mehrdeutige E-Mail)
    'fehlgeschlagen_benutzername' => 'The username or password is incorrect.',
    'fehlgeschlagen_beides' => 'These credentials are incorrect. If your e-mail address is shared by several accounts, please use your username.',
    'pwforgotten' => 'forgotten password',

    'seite_titel' => 'Sign-in',
    'seite_text' => 'Access your account securely and easily.',
    'merkmal_schnell' => 'Fast',
    'merkmal_einfach' => 'Simple',
    'merkmal_responsiv' => 'Responsive',
];
