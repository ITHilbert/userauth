@extends('userauth::layouts.auth')

@section('title', 'Neues Passwort setzen')

@section('brand')
    <div class="mb-8 text-center">
        <span class="text-2xl font-bold text-gray-800">{{ config('app.name') }}</span>
    </div>
@endsection

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">@lang('userauth::password.password-new')</h1>
        <p class="text-sm text-gray-500 text-center mb-8">Geben Sie Ihr neues Passwort ein.</p>

        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.updatewithtoken') }}" novalidate>
            @csrf

            {{-- Hidden fields for token and email --}}
            <input type="hidden" name="pwtoken" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            {{-- Neues Passwort --}}
            <div class="mb-5">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    @lang('userauth::password.password-new')
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border @error('password') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    placeholder="••••••••"
                >
                @error('password')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Passwort bestätigen --}}
            <div class="mb-6">
                <label for="password-confirm" class="block text-sm font-medium text-gray-700 mb-1">
                    @lang('userauth::password.password-confirm')
                </label>
                <input
                    type="password"
                    id="password-confirm"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    placeholder="••••••••"
                >
            </div>

            <button
                type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                @lang('userauth::button.editPassword')
            </button>
        </form>
    </div>
@endsection
