@component('mail::message')
# {{ __('userauth::zwei_faktor.mail.betreff') }}

{{ __('userauth::zwei_faktor.mail.code') }} **{{ $code }}**

{{ __('userauth::zwei_faktor.mail.gueltig', ['minutes' => $gueltigMinuten]) }}

{{ __('userauth::zwei_faktor.mail.nicht_angemeldet') }}

{{ config('app.name') }}
@endcomponent
