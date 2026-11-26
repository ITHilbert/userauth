@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')
@extends('userauth::layouts.auth')

@section('title', 'Login')

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">Anmelden</h1>
        <p class="text-sm text-gray-500 text-center mb-8">Melden Sie sich mit Ihrem Konto an</p>

        @if (session('status'))
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">
                {{ session('status') }}
            </div>
        @endif

        @if (request()->boolean('abgemeldet'))
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">
                @lang('userauth::oidc.abgemeldet')
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('userauth_bestaetigung_offen') && ($registrierungAktiv ?? false))
            <div class="mb-6 text-center">
                <a href="{{ route('register.bestaetigung') }}" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline transition-colors">
                    @lang('userauth::registrierung.link_erneut_anfordern')
                </a>
            </div>
        @endif

        @if ($passwortFormular ?? true)
        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf

            {{-- Kennung: E-Mail, Benutzername oder beides (userauth.anmeldung_per) --}}
            @php($kennungFeld ??= 'email')
            <div class="mb-5">
                <label for="{{ $kennungFeld }}" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $kennungLabel ?? 'E-Mail-Adresse' }}
                </label>
                <input
                    type="{{ $kennungTyp ?? 'email' }}"
                    id="{{ $kennungFeld }}"
                    name="{{ $kennungFeld }}"
                    value="{{ AlteEingabe::text($kennungFeld) }}"
                    required
                    autocomplete="{{ $kennungAutocomplete ?? 'email' }}"
                    autofocus
                    class="w-full rounded-lg border @error($kennungFeld) border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    @if ($kennungFeld === 'email') placeholder="name@beispiel.de" @endif
                >
                @error($kennungFeld)
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Passwort --}}
            <div class="mb-5">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Passwort
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg border @error('password') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    placeholder="••••••••"
                >
                @error('password')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember + Passwort vergessen --}}
            <div class="flex items-center justify-between mb-6">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="remember"
                        id="remember"
                        checked
                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    >
                    <span class="text-sm text-gray-600">@lang('userauth::login.remember')</span>
                </label>
                <a href="{{ route('password.forgotten') }}" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline transition-colors">
                    @lang('userauth::login.pwforgotten')
                </a>
            </div>

            {{-- Submit --}}
            <button
                type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                Anmelden
            </button>
        </form>
        @endif

        @if ($registrierungAktiv ?? false)
            <div class="mt-6 text-center text-sm text-gray-500">
                @lang('userauth::registrierung.noch_kein_konto')
                <a href="{{ route('register') }}" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline transition-colors">@lang('userauth::registrierung.registrieren')</a>
            </div>
        @endif

        @if (($passwortFormular ?? true) && (($passkeyAktiv ?? false) || ($oidcKnopf ?? null) || in_array('google', $availableSocialProviders ?? [], true)))
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                <div class="relative flex justify-center"><span class="bg-white px-3 text-xs text-gray-500">@lang('userauth::social.or')</span></div>
            </div>
        @endif

        @if ($passkeyAktiv ?? false)
            <button
                type="button"
                data-testid="passkey-login"
                data-passkey-login
                data-optionen-url="{{ route('userauth.passkey.login.options') }}"
                data-anmelden-url="{{ route('userauth.passkey.login') }}"
                data-meldung="passkey-meldung"
                data-text-nicht-unterstuetzt="{{ __('userauth::passkey.nicht_unterstuetzt') }}"
                data-text-abgebrochen="{{ __('userauth::passkey.abgebrochen') }}"
                data-text-fehler="{{ __('userauth::passkey.fehler.anmeldung') }}"
                class="w-full mb-3 inline-flex items-center justify-center border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >@lang('userauth::passkey.knopf')</button>
            <p id="passkey-meldung" class="mb-3 text-xs text-red-600" role="alert" hidden></p>
            <script src="{{ $passkeySkript }}" defer></script>
        @endif

        @if ($oidcKnopf ?? null)
            <a
                href="{{ route('userauth.oidc.redirect') }}"
                class="w-full mb-3 inline-flex items-center justify-center border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                {{ $oidcKnopf }}
            </a>
        @endif

        @if (in_array('google', $availableSocialProviders ?? [], true))
            <a
                href="{{ route('userauth.social.redirect', 'google') }}"
                class="w-full inline-flex items-center justify-center gap-3 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                @include('userauth::partials.google-logo')
                @lang('userauth::social.login_with', ['provider' => 'Google'])
            </a>
            @if ($geloeschteEmailHinweis ?? false)
                <p class="mt-3 text-xs text-gray-500">@lang('userauth::social.hinweis_geloeschtes_konto')</p>
            @endif
        @endif
    </div>
@endsection
