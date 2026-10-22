@extends('userauth::layouts.auth')

@section('title', __('userauth::social.confirm.title'))

@section('content')
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg ring-1 ring-gray-200 px-8 py-10">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-4">@lang('userauth::social.confirm.title')</h1>
        <p class="text-sm text-gray-600 text-center mb-8">@lang('userauth::social.confirm.text', ['provider' => $provider->label()])</p>

        <form method="POST" action="{{ route('userauth.social.confirm.store', $token) }}">
            @csrf
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg px-4 py-2.5 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                @lang('userauth::social.confirm.button')
            </button>
        </form>
    </div>
@endsection
