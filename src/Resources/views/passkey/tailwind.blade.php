@inject('authAssetVersion', 'ITHilbert\UserAuth\Infrastructure\Assets\AuthAssetVersion')
@extends('userauth::layouts.auth')

@section('title', __('userauth::passkey.verwaltung.title'))

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-6">@lang('userauth::passkey.verwaltung.title')</h1>

        @if (session('status'))
            <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        @if ($uebersicht->warnung)
            <div data-testid="passkey-warnung" class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700" role="alert">
                @lang('userauth::passkey.verwaltung.warnung')
            </div>
        @endif

        @if ($uebersicht->passkeys === [])
            <p class="mb-6 text-sm text-gray-500">@lang('userauth::passkey.verwaltung.leer')</p>
        @else
            <ul class="divide-y divide-gray-200 mb-6">
                @foreach ($uebersicht->passkeys as $passkey)
                    <li data-testid="passkey-zeile" class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $passkey->name }}</p>
                            <p class="text-xs text-gray-500">
                                @lang('userauth::passkey.verwaltung.angelegt', ['datum' => $passkey->angelegtAm?->format('d.m.Y') ?? '–'])
                                ·
                                @if ($passkey->zuletztBenutzt)
                                    @lang('userauth::passkey.verwaltung.zuletzt_benutzt', ['datum' => $passkey->zuletztBenutzt->format('d.m.Y')])
                                @else
                                    @lang('userauth::passkey.verwaltung.nie_benutzt')
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('userauth.passkey.destroy', $passkey->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('userauth::passkey.verwaltung.loeschen_frage')) }})">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:underline">@lang('userauth::passkey.verwaltung.loeschen')</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <div data-testid="passkey-hinzufuegen">
            <h2 class="text-sm font-medium text-gray-900 mb-2">@lang('userauth::passkey.verwaltung.hinzufuegen')</h2>
            <p class="mb-4 text-xs text-gray-500">@lang('userauth::passkey.verwaltung.hinweis_zweitgeraet')</p>
            <form
                data-passkey-registrieren
                data-optionen-url="{{ route('userauth.passkey.register.options') }}"
                data-registrieren-url="{{ route('userauth.passkey.register') }}"
                data-meldung="passkey-meldung"
                data-text-nicht-unterstuetzt="{{ __('userauth::passkey.nicht_unterstuetzt') }}"
                data-text-abgebrochen="{{ __('userauth::passkey.registrierung_abgebrochen') }}"
                data-text-fehler="{{ __('userauth::passkey.fehler.registrierung') }}"
            >
                <label for="passkey-name" class="block text-sm font-medium text-gray-700 mb-1">@lang('userauth::passkey.verwaltung.name')</label>
                <input
                    type="text"
                    id="passkey-name"
                    name="name"
                    maxlength="100"
                    class="w-full mb-4 rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                >
                <p id="passkey-meldung" class="mb-3 text-xs text-red-600" role="alert" hidden></p>
                <button
                    type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >@lang('userauth::passkey.verwaltung.hinzufuegen_knopf')</button>
            </form>
        </div>
        <script src="{{ route('userauth.assets.passkey', ['v' => $authAssetVersion->passkeyJsVersion()]) }}" defer></script>
    </div>
@endsection
