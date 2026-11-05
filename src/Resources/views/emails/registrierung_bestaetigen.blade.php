@component('mail::message')
# {{ __('userauth::registrierung.mail.betreff') }}

{{ __('userauth::registrierung.mail.einleitung', ['minutes' => $gueltigMinuten]) }}

@component('mail::button', ['url' => $bestaetigungsUrl])
{{ __('userauth::registrierung.mail.button') }}
@endcomponent

{{ __('userauth::registrierung.mail.ignorieren') }}

{{ config('app.name') }}
@endcomponent
