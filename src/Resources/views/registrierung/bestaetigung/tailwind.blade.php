@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')
@extends('userauth::layouts.auth')

@section('title', __('userauth::registrierung.bestaetigung_titel'))

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-6">@lang('userauth::registrierung.bestaetigung_titel')</h1>

        @if (session('status'))
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">
                {{ session('status') }}
            </div>
        @endif

        @if (session('fehler'))
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                {{ session('fehler') }}
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

        <p class="text-sm text-gray-500 mb-6">@lang('userauth::registrierung.bestaetigung_text')</p>

        <form method="POST" action="{{ route('register.erneut') }}" novalidate>
            @csrf

            <div class="mb-6">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">@lang('userauth::registrierung.email')</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ AlteEingabe::text('email') }}"
                    required
                    autocomplete="email"
                    class="w-full rounded-lg border @error('email') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    placeholder="name@beispiel.de"
                >
                @error('email')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                @lang('userauth::registrierung.erneut_absenden')
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('login') }}" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline transition-colors">
                @lang('userauth::registrierung.zum_login')
            </a>
        </div>
    </div>
@endsection
