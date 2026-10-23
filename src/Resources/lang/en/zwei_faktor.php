<?php

declare(strict_types=1);

return [
    'titel' => 'Confirm with security code',
    'hinweis' => 'We have sent you a security code by e-mail. It is valid for :minutes minutes.',
    'code' => 'Security code',
    'bestaetigen' => 'Confirm',
    'erneut_senden' => 'Resend code',
    'abbrechen' => 'Cancel and log out',

    'erneut_gesendet' => 'A new code has been sent. The previous code is no longer valid.',
    'zu_frueh' => 'A new code can be requested at the earliest :seconds seconds after the last one.',
    'ungueltig' => 'The code you entered is invalid.',
    'abgelaufen' => 'The code has expired. Please request a new code.',
    'gesperrt' => 'Too many invalid codes. Please log in again.',
    'versand_fehlgeschlagen' => 'The code could not be sent. Please try again later.',
    'neu_anmelden' => 'Your login was not confirmed with the security code. Please log in again.',

    'mail' => [
        'betreff' => 'Your security code',
        'code' => 'Your code is:',
        'gueltig' => 'The code is valid for :minutes minutes.',
        'nicht_angemeldet' => 'If you did not try to log in, please change your password immediately.',
    ],
];
