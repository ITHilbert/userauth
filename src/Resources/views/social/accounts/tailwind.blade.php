@extends('userauth::layouts.auth')

@section('title', __('userauth::social.accounts.title'))

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-6">@lang('userauth::social.accounts.title')</h1>

        @if (session('status'))
            <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <ul class="divide-y divide-gray-200">
            @foreach ($providers as $provider)
                <li class="flex items-center justify-between py-3">
                    <div class="flex items-center gap-3">
                        @if ($provider->value === 'google')
                            @include('userauth::partials.google-logo')
                        @endif
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $provider->label() }}</p>
                            <p class="text-xs text-gray-500">
                                @if (array_key_exists($provider->value, $linked))
                                    @lang('userauth::social.accounts.connected'){{ $linked[$provider->value] ? ': '.$linked[$provider->value] : '' }}
                                @else
                                    @lang('userauth::social.accounts.not_connected')
                                @endif
                            </p>
                        </div>
                    </div>
                    @if (array_key_exists($provider->value, $linked))
                        <form method="POST" action="{{ route('userauth.social.disconnect', $provider->value) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:underline">@lang('userauth::social.accounts.disconnect')</button>
                        </form>
                    @elseif (in_array($provider->value, $availableSocialProviders ?? [], true))
                        <a href="{{ route('userauth.social.redirect', $provider->value) }}" class="text-sm text-indigo-600 hover:underline">@lang('userauth::social.accounts.connect')</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection
