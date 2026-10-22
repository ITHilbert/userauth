<?php

declare(strict_types=1);

return [
    'login_with' => 'Sign in with :provider',
    'or' => 'or',

    'errors' => [
        'email_not_verified' => 'The email address of your account at the provider is not verified. Sign-in is therefore not possible.',
        'domain_not_allowed' => 'The email domain of your account is not allowed to sign in.',
        'registration_disabled' => 'No account exists for this email address. Registration via this provider is not possible.',
        'account_disabled' => 'This account is disabled or deleted.',
        'provider_already_connected' => 'A different account of this provider is already linked to this user.',
        'social_account_taken' => 'This provider account is already linked to another user.',
        'invalid_token' => 'The confirmation link is invalid or has expired.',
        'provider_unavailable' => 'The provider is currently unavailable or the sign-in was cancelled. Please try again.',
    ],

    'status' => [
        'confirmation_sent' => 'We have sent you an email with a confirmation link. Please confirm the link, then you can sign in.',
        'link_confirmed' => 'The link has been confirmed. You can sign in now.',
        'connected' => ':provider has been linked to your account.',
        'disconnected' => 'The link to :provider has been removed.',
    ],

    'confirm' => [
        'title' => 'Confirm link',
        'text' => 'Do you want to link your account with :provider? Your email address will then count as verified.',
        'button' => 'Confirm link',
    ],

    'accounts' => [
        'title' => 'Linked accounts',
        'connected' => 'Connected',
        'not_connected' => 'Not connected',
        'connect' => 'Connect',
        'disconnect' => 'Disconnect',
    ],

    'mail' => [
        'confirmation_subject' => 'Link your :provider account',
        'confirmation_intro' => 'Someone signed in with a :provider account that belongs to your email address. Please confirm the link within :minutes minutes.',
        'confirmation_button' => 'Confirm link',
        'confirmation_ignore' => 'If this was not you, ignore this email. Nothing will be linked.',
        'linked_subject' => ':provider account linked',
        'linked_intro' => 'Your user account has just been linked to a :provider account.',
        'linked_warning' => 'If this was not you, please change your password immediately and remove the link.',
    ],
];
