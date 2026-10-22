@extends('userauth::layouts.beforeLogin')

@section('main')
    <div class="container min-vh-100 d-flex align-items-center justify-content-center">
        <div class="card" style="max-width: 32rem;">
            <div class="card-body p-5 text-center">
                <h3 class="mb-3">@lang('userauth::social.confirm.title')</h3>
                <p class="mb-4">@lang('userauth::social.confirm.text', ['provider' => $provider->label()])</p>
                <form method="POST" action="{{ route('userauth.social.confirm.store', $token) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100">@lang('userauth::social.confirm.button')</button>
                </form>
            </div>
        </div>
    </div>
@stop
