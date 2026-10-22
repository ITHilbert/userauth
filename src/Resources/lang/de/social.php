<?php

declare(strict_types=1);

return [
    'login_with' => 'Mit :provider anmelden',
    'or' => 'oder',

    'errors' => [
        'email_not_verified' => 'Die E-Mail-Adresse Ihres Kontos beim Anbieter ist nicht bestätigt. Eine Anmeldung ist deshalb nicht möglich.',
        'domain_not_allowed' => 'Die E-Mail-Domain Ihres Kontos ist für die Anmeldung nicht zugelassen.',
        'registration_disabled' => 'Zu dieser E-Mail-Adresse existiert kein Konto. Eine Registrierung über diesen Anbieter ist nicht möglich.',
        'account_disabled' => 'Dieses Konto ist deaktiviert oder gelöscht.',
        'provider_already_connected' => 'Für dieses Konto ist bereits ein anderes Konto dieses Anbieters verknüpft.',
        'social_account_taken' => 'Dieses Anbieter-Konto ist bereits mit einem anderen Benutzer verknüpft.',
        'invalid_token' => 'Der Bestätigungslink ist ungültig oder abgelaufen.',
        'provider_unavailable' => 'Der Anbieter ist derzeit nicht erreichbar oder die Anmeldung wurde abgebrochen. Bitte versuchen Sie es erneut.',
    ],

    'status' => [
        'confirmation_sent' => 'Wir haben Ihnen eine E-Mail mit einem Bestätigungslink geschickt. Bitte bestätigen Sie die Verknüpfung, danach können Sie sich anmelden.',
        'link_confirmed' => 'Die Verknüpfung wurde bestätigt. Sie können sich jetzt anmelden.',
        'connected' => ':provider wurde mit Ihrem Konto verknüpft.',
        'disconnected' => 'Die Verknüpfung mit :provider wurde getrennt.',
    ],

    'confirm' => [
        'title' => 'Verknüpfung bestätigen',
        'text' => 'Möchten Sie Ihr Konto mit :provider verknüpfen? Ihre E-Mail-Adresse gilt danach als bestätigt.',
        'button' => 'Verknüpfung bestätigen',
    ],

    'accounts' => [
        'title' => 'Verknüpfte Konten',
        'connected' => 'Verbunden',
        'not_connected' => 'Nicht verbunden',
        'connect' => 'Verbinden',
        'disconnect' => 'Trennen',
    ],

    'mail' => [
        'confirmation_subject' => ':provider-Konto verknüpfen',
        'confirmation_intro' => 'Jemand hat sich mit einem :provider-Konto angemeldet, das zu Ihrer E-Mail-Adresse gehört. Bestätigen Sie die Verknüpfung innerhalb von :minutes Minuten.',
        'confirmation_button' => 'Verknüpfung bestätigen',
        'confirmation_ignore' => 'Wenn Sie das nicht waren, ignorieren Sie diese E-Mail. Es wird nichts verknüpft.',
        'linked_subject' => ':provider-Konto verknüpft',
        'linked_intro' => 'Ihr Benutzerkonto wurde soeben mit einem :provider-Konto verknüpft.',
        'linked_warning' => 'Wenn Sie das nicht waren, ändern Sie bitte sofort Ihr Passwort und trennen Sie die Verknüpfung.',
    ],
];
