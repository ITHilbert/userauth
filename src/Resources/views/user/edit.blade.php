@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::user.header_edit'))

@section('content')
    <x-kit::card-main>
        <form method="POST" action="{{ route('user.update', $user->id) }}" enctype="multipart/form-data">
            @csrf
            <x-kit::card-main-header>
                <h1>@lang('userauth::user.header_edit')</h1>
                @include('include.breadcrumb')
            </x-kit::card-main-header>
            <x-kit::card-body>
                @include('include.message')

                @include('userauth::user.formularfelder', ['user' => $user])
            </x-kit::card-body>
            <x-kit::card-footer>
                <div class="userauth-spalte">
                    <div>
                        <x-kit::button-back :route="route('user')">@lang('userauth::button.back')</x-kit::button-back>
                    </div>
                    <div class="userauth-rechts">
                        <x-kit::button-save>@lang('userauth::button.save')</x-kit::button-save>
                    </div>
                </div>
            </x-kit::card-footer>
        </form>

        @if (($passkeyAnzahl ?? null) !== null)
            <x-kit::card-body>
                <div class="userauth-spalte">
                    <div>
                        @lang('userauth::passkey.benutzer.anzahl'): <span data-testid="passkey-anzahl">{{ $passkeyAnzahl }}</span>
                    </div>
                    @if ($passkeyAnzahl > 0)
                        <div class="userauth-rechts">
                            <form method="POST" action="{{ route('user.passkeys.reset', $user->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('userauth::passkey.benutzer.zuruecksetzen_frage')) }})">
                                @csrf
                                <x-kit::button type="submit" variant="danger">@lang('userauth::passkey.benutzer.zuruecksetzen')</x-kit::button>
                            </form>
                        </div>
                    @endif
                </div>
            </x-kit::card-body>
        @endif
    </x-kit::card-main>
@stop

@if (trim((string) config('userauth.passwort_generator_url')) !== '')
    @push('userauth-scripts')
        <script defer src="{{ asset('vendor/userauth/js/passwort-erzeugen.js') }}"></script>
    @endpush
@endif
