@extends('userauth::layouts.auth')

@section('title', 'Login')

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">Anmelden</h1>
        <p class="text-sm text-gray-500 text-center mb-8">Melden Sie sich mit Ihrem Konto an</p>

        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf

            {{-- E-Mail --}}
            <div class="mb-5">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    E-Mail-Adresse
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                    autofocus
                    class="w-full rounded-lg border @error('email') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    placeholder="name@beispiel.de"
                >
                @error('email')
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
    </div>
@endsection
