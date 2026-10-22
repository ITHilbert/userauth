{{-- Zusätzliche Host-Assets für die Auth-Views (config userauth.auth_assets, relativ zu public/) --}}
@foreach((array) config('userauth.auth_assets.css', []) as $authCss)
    <link rel="stylesheet" href="{{ asset($authCss) }}">
@endforeach
@foreach((array) config('userauth.auth_assets.js', []) as $authJs)
    <script src="{{ asset($authJs) }}" defer></script>
@endforeach
