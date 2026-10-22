@extends('userauth::layouts.beforeLogin')

@section('main')
    <div class="container min-vh-100 d-flex align-items-center justify-content-center">
        <div class="card" style="min-width: 28rem;">
            <div class="card-body p-5">
                <h3 class="mb-4 text-center">@lang('userauth::social.accounts.title')</h3>

                @if (session('status'))
                    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <ul class="list-group list-group-flush">
                    @foreach ($providers as $provider)
                        <li class="list-group-item d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                @if ($provider->value === 'google')
                                    @include('userauth::partials.google-logo')
                                @endif
                                <div>
                                    <div class="fw-semi-bold">{{ $provider->label() }}</div>
                                    <small class="text-700">
                                        @if (array_key_exists($provider->value, $linked))
                                            @lang('userauth::social.accounts.connected'){{ $linked[$provider->value] ? ': '.$linked[$provider->value] : '' }}
                                        @else
                                            @lang('userauth::social.accounts.not_connected')
                                        @endif
                                    </small>
                                </div>
                            </div>
                            @if (array_key_exists($provider->value, $linked))
                                <form method="POST" action="{{ route('userauth.social.disconnect', $provider->value) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-phoenix-danger">@lang('userauth::social.accounts.disconnect')</button>
                                </form>
                            @elseif (in_array($provider->value, $availableSocialProviders ?? [], true))
                                <a class="btn btn-sm btn-phoenix-secondary" href="{{ route('userauth.social.redirect', $provider->value) }}">@lang('userauth::social.accounts.connect')</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@stop
