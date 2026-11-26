@inject('authAssetVersion', 'ITHilbert\UserAuth\Infrastructure\Assets\AuthAssetVersion')
@extends('userauth::layouts.beforeLogin')

@section('main')
    <div class="container min-vh-100 d-flex align-items-center justify-content-center">
        <div class="card" style="min-width: 28rem;">
            <div class="card-body p-5">
                <h3 class="mb-4 text-center">@lang('userauth::passkey.verwaltung.title')</h3>

                @if (session('status'))
                    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                @endif

                @if ($uebersicht->warnung)
                    <div data-testid="passkey-warnung" class="alert alert-danger" role="alert">@lang('userauth::passkey.verwaltung.warnung')</div>
                @endif

                @if ($uebersicht->passkeys === [])
                    <p class="text-700 mb-4">@lang('userauth::passkey.verwaltung.leer')</p>
                @else
                    <ul class="list-group list-group-flush mb-4">
                        @foreach ($uebersicht->passkeys as $passkey)
                            <li data-testid="passkey-zeile" class="list-group-item d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-semi-bold">{{ $passkey->name }}</div>
                                    <small class="text-700">
                                        @lang('userauth::passkey.verwaltung.angelegt', ['datum' => $passkey->angelegtAm?->format('d.m.Y') ?? '–'])
                                        ·
                                        @if ($passkey->zuletztBenutzt)
                                            @lang('userauth::passkey.verwaltung.zuletzt_benutzt', ['datum' => $passkey->zuletztBenutzt->format('d.m.Y')])
                                        @else
                                            @lang('userauth::passkey.verwaltung.nie_benutzt')
                                        @endif
                                    </small>
                                </div>
                                <form method="POST" action="{{ route('userauth.passkey.destroy', $passkey->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('userauth::passkey.verwaltung.loeschen_frage')) }})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-phoenix-danger">@lang('userauth::passkey.verwaltung.loeschen')</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div data-testid="passkey-hinzufuegen">
                    <h5 class="mb-2">@lang('userauth::passkey.verwaltung.hinzufuegen')</h5>
                    <p class="text-700 fs--1">@lang('userauth::passkey.verwaltung.hinweis_zweitgeraet')</p>
                    <form
                        data-passkey-registrieren
                        data-optionen-url="{{ route('userauth.passkey.register.options') }}"
                        data-registrieren-url="{{ route('userauth.passkey.register') }}"
                        data-meldung="passkey-meldung"
                        data-bestaetigen-url="{{ route('userauth.passkey.bestaetigen') }}"
                        data-bestaetigung="passkey-bestaetigung"
                        @if ($uebersicht->passkeys !== [])
                        data-bestaetigen-optionen-url="{{ route('userauth.passkey.bestaetigen.optionen') }}"
                        data-bestaetigen-assertion-url="{{ route('userauth.passkey.bestaetigen.assertion') }}"
                        @endif
                        data-text-bestaetigung="{{ __('userauth::passkey.fehler.bestaetigung') }}"
                        data-text-nicht-unterstuetzt="{{ __('userauth::passkey.nicht_unterstuetzt') }}"
                        data-text-abgebrochen="{{ __('userauth::passkey.registrierung_abgebrochen') }}"
                        data-text-fehler="{{ __('userauth::passkey.fehler.registrierung') }}"
                    >
                        <label class="form-label" for="passkey-name">@lang('userauth::passkey.verwaltung.name')</label>
                        <input type="text" class="form-control mb-3" id="passkey-name" name="name" maxlength="100">
                        <div id="passkey-meldung" class="text-danger fs--1 mb-3" role="alert" hidden></div>
                        <button type="submit" class="btn btn-primary w-100">@lang('userauth::passkey.verwaltung.hinzufuegen_knopf')</button>
                    </form>
                    <div id="passkey-bestaetigung" data-testid="passkey-bestaetigung" class="border rounded p-3 mt-3" hidden>
                        <h6 class="mb-1">@lang('userauth::passkey.bestaetigung.titel')</h6>
                        <p class="text-700 fs--1">@lang('userauth::passkey.bestaetigung.hinweis')</p>
                        <label class="form-label" for="passkey-aktuelles-passwort">@lang('userauth::passkey.bestaetigung.passwort')</label>
                        <input type="password" class="form-control mb-3" id="passkey-aktuelles-passwort" name="aktuelles_passwort" autocomplete="current-password">
                        <div data-testid="passkey-bestaetigung-meldung" class="text-danger fs--1 mb-3" role="alert" hidden></div>
                        <button type="button" data-testid="passkey-bestaetigung-passwort" class="btn btn-primary w-100 mb-2">@lang('userauth::passkey.bestaetigung.passwort_knopf')</button>
                        @if ($uebersicht->passkeys !== [])
                            <button type="button" data-testid="passkey-bestaetigung-passkey" class="btn btn-outline-primary w-100">@lang('userauth::passkey.bestaetigung.passkey_knopf')</button>
                        @endif
                    </div>
                </div>
                <script src="{{ route('userauth.assets.passkey', ['v' => $authAssetVersion->passkeyJsVersion()]) }}" defer></script>
            </div>
        </div>
    </div>
@stop
