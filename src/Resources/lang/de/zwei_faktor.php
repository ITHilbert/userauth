<?php

declare(strict_types=1);

return [
    'titel' => 'Bestätigung mit Sicherheitscode',
    'hinweis' => 'Wir haben Ihnen einen Sicherheitscode per E-Mail geschickt. Er ist :minutes Minuten gültig.',
    'code' => 'Sicherheitscode',
    'bestaetigen' => 'Bestätigen',
    'erneut_senden' => 'Code erneut senden',
    'abbrechen' => 'Abbrechen und abmelden',

    'erneut_gesendet' => 'Ein neuer Code wurde gesendet. Der bisherige Code ist ungültig.',
    'zu_frueh' => 'Ein neuer Code kann frühestens :seconds Sekunden nach dem letzten angefordert werden.',
    'ungueltig' => 'Der eingegebene Code ist ungültig.',
    'abgelaufen' => 'Der Code ist abgelaufen. Bitte fordern Sie einen neuen Code an.',
    'gesperrt' => 'Zu viele falsche Codes. Bitte melden Sie sich erneut an.',
    'versand_fehlgeschlagen' => 'Der Code konnte nicht versendet werden. Bitte versuchen Sie es später erneut.',
    'neu_anmelden' => 'Ihre Anmeldung wurde nicht mit dem Sicherheitscode bestätigt. Bitte melden Sie sich erneut an.',

    'mail' => [
        'betreff' => 'Ihr Sicherheitscode',
        'code' => 'Ihr Code lautet:',
        'gueltig' => 'Der Code ist :minutes Minuten gültig.',
        'nicht_angemeldet' => 'Falls Sie sich nicht angemeldet haben, ändern Sie bitte sofort Ihr Passwort.',
    ],
];
