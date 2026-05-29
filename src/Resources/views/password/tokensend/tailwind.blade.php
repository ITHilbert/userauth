@extends('userauth::layouts.auth')

@section('title', 'E-Mail gesendet')

@section('brand')
    <div class="mb-8 text-center">
        <span class="text-2xl font-bold text-gray-800">{{ config('app.name') }}</span>
    </div>
@endsection

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10 text-center">
        <div class="text-5xl mb-4">📬</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-3">E-Mail wurde gesendet</h1>
        <p class="text-sm text-gray-600 mb-6">
            Wir haben Ihnen den Link zum Ändern des Passworts zugesandt.<br>
            Bitte überprüfen Sie Ihr Postfach. Sollten Sie dort nichts finden, schauen Sie bitte auch in Ihren <strong>Spam</strong>-Ordner.
        </p>

        @if (session('message'))
            <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-700 text-left">
                {{ session('message') }}
            </div>
        @endif

        <a href="{{ route('login') }}" class="inline-block mt-2 text-sm text-indigo-600 hover:text-indigo-800 hover:underline transition-colors">
            &larr; Zurück zum Login
        </a>
    </div>
@endsection
