@extends('userauth::layouts.auth')

@section('title', __('userauth::password.header_link_angefordert'))

@section('brand')
    <div class="mb-8 text-center">
        <span class="text-2xl font-bold text-gray-800">{{ config('app.name') }}</span>
    </div>
@endsection

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10 text-center">
        <div class="text-5xl mb-4">📬</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-3">@lang('userauth::password.header_link_angefordert')</h1>
        <p class="text-sm text-gray-600 mb-6">
            {{ session('message', __('userauth::password.link_angefordert')) }}
        </p>

        <a href="{{ route('login') }}" class="inline-block mt-2 text-sm text-indigo-600 hover:text-indigo-800 hover:underline transition-colors">
            &larr; Zurück zum Login
        </a>
    </div>
@endsection
