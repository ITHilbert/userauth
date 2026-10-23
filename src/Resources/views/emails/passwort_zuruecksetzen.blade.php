@component('mail::message')
# {{ __('userauth::password.mail.betreff') }}

{{ __('userauth::password.mail.einleitung', ['minutes' => $gueltigMinuten]) }}

@component('mail::button', ['url' => $resetUrl])
{{ __('userauth::password.mail.button') }}
@endcomponent

{{ __('userauth::password.mail.ignorieren') }}

{{ config('app.name') }}
@endcomponent
