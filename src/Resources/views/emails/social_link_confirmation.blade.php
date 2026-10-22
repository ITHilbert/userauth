@component('mail::message')
# {{ __('userauth::social.mail.confirmation_subject', ['provider' => $providerLabel]) }}

{{ __('userauth::social.mail.confirmation_intro', ['provider' => $providerLabel, 'minutes' => $ttlMinutes]) }}

@component('mail::button', ['url' => $confirmUrl])
{{ __('userauth::social.mail.confirmation_button') }}
@endcomponent

{{ __('userauth::social.mail.confirmation_ignore') }}

{{ config('app.name') }}
@endcomponent
