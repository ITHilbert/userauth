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
    'header_change' => 'change password',
    'header_pw_forgotten' => 'forgotten password',

    'id' => 'ID',
    'password' => 'password',
    'password-new' => 'new password',
    'password-confirm' => 'remember password',

    // Forgotten / change password
    'link_angefordert' => 'If an account exists for this e-mail address, we have sent you a link to reset your password. Please check your inbox (and your spam folder).',
    'header_link_angefordert' => 'Please check your inbox',
    'link_ungueltig' => 'The password reset link is invalid or has expired. Please request a new link.',
    'geaendert' => 'Your password has been changed',
    'bereits_verwendet' => 'The password must not match one of your recently used passwords.',
    'mail' => [
        'betreff' => 'Reset password',
        'einleitung' => 'You are receiving this e-mail because a password reset was requested for your account. The link is valid for :minutes minutes.',
        'button' => 'Reset password',
        'ignorieren' => 'If you did not request this, ignore this e-mail. Your password remains unchanged.',
    ],

    // Meldungen
    'MsgAddSuccess' => 'Add a new right',
    'MsgEditSuccess' => 'Edit the right',
    'MsgDeleteSuccess' => 'Delte the riht',
];
