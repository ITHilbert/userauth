@component('mail::message')
# {{ __('userauth::social.mail.linked_subject', ['provider' => $providerLabel]) }}

{{ __('userauth::social.mail.linked_intro', ['provider' => $providerLabel]) }}

{{ __('userauth::social.mail.linked_warning') }}

{{ config('app.name') }}
@endcomponent
