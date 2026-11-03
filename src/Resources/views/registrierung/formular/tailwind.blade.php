@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')
@extends('userauth::layouts.auth')

@section('title', __('userauth::registrierung.titel'))

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">@lang('userauth::registrierung.titel')</h1>
        <p class="text-sm text-gray-500 text-center mb-8">@lang('userauth::registrierung.untertitel')</p>

        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" novalidate>
            @csrf

            <div class="mb-5">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">@lang('userauth::registrierung.name')</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ AlteEingabe::text('name') }}"
                    required
                    autocomplete="name"
                    autofocus
                    class="w-full rounded-lg border @error('name') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                >
                @error('name')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-5">
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

            <div class="mb-5">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">@lang('userauth::registrierung.passwort')</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border @error('password') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                >
                @error('password')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">@lang('userauth::registrierung.passwort_wiederholen')</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border @error('password_confirmation') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                >
                @error('password_confirmation')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                @lang('userauth::registrierung.absenden')
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-500">
            @lang('userauth::registrierung.schon_registriert')
            <a href="{{ route('login') }}" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline transition-colors">@lang('userauth::registrierung.zum_login')</a>
        </div>
    </div>
@endsection
