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
    'header_change' => 'Passwort ändern',
    'header_pw_forgotten' => 'Passwort vergessen',

    'id' => 'ID',
    'password' => 'Passwort',
    'password-new' => 'Neues Passwort',
    'password-confirm' => 'Passwort wiederholen',

    // Passwort vergessen / ändern
    'link_angefordert' => 'Falls zu dieser E-Mail-Adresse ein Konto besteht, haben wir Ihnen einen Link zum Zurücksetzen des Passworts gesendet. Bitte prüfen Sie Ihren Posteingang (ggf. auch den Spam-Ordner).',
    'header_link_angefordert' => 'Bitte prüfen Sie Ihren Posteingang',
    'link_ungueltig' => 'Der Link zum Zurücksetzen ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen Link an.',
    'geaendert' => 'Passwort wurde geändert',
    'bereits_verwendet' => 'Das Passwort darf nicht einem der zuletzt verwendeten Passwörter entsprechen.',
    'mail' => [
        'betreff' => 'Passwort zurücksetzen',
        'einleitung' => 'Sie erhalten diese E-Mail, weil für Ihr Konto das Zurücksetzen des Passworts angefordert wurde. Der Link ist :minutes Minuten gültig.',
        'button' => 'Passwort zurücksetzen',
        'ignorieren' => 'Wenn Sie das nicht angefordert haben, ignorieren Sie diese E-Mail. Ihr Passwort bleibt unverändert.',
    ],

    // Meldungen
    'MsgAddSuccess' => 'Das Recht wurde erfolgreich angelegt.',
    'MsgEditSuccess' => 'Das Recht wurde erfolgreich angepasst',
    'MsgDeleteSuccess' => 'Das Recht wurde erfolgreich gelöscht',
];
