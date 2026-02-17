@component('mail::message')
# Ihr Sicherheitscode

Ihr Code lautet: **{{ $code }}**

Dieser Code verfällt in 10 Minuten.

Wenn Sie diesen Login nicht versucht haben, ändern Sie bitte sofort Ihr Passwort.

Danke,<br>
{{ config('app.name') }}
@endcomponent