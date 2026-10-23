@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ __('userauth::zwei_faktor.titel') }}</div>

                    <div class="card-body">
                        @if (session('status'))
                            <p class="alert alert-info">
                                {{ session('status') }}
                            </p>
                        @endif

                        <p class="text-muted">
                            {{ __('userauth::zwei_faktor.hinweis', ['minutes' => $gueltigMinuten]) }}
                        </p>

                        <form method="POST" action="{{ route('verify.store') }}">
                            @csrf

                            <div class="form-group row">
                                <label for="two_factor_code"
                                    class="col-md-4 col-form-label text-md-right">{{ __('userauth::zwei_faktor.code') }}</label>

                                <div class="col-md-6">
                                    <input id="two_factor_code" type="text"
                                        class="form-control @error('two_factor_code') is-invalid @enderror"
                                        name="two_factor_code" inputmode="numeric" autocomplete="one-time-code"
                                        required autofocus>

                                    @error('two_factor_code')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row mb-0">
                                <div class="col-md-8 offset-md-4">
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('userauth::zwei_faktor.bestaetigen') }}
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="row mt-3">
                            <div class="col-md-8 offset-md-4">
                                <form method="POST" action="{{ route('verify.resend') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-link">
                                        {{ __('userauth::zwei_faktor.erneut_senden') }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-link">
                                        {{ __('userauth::zwei_faktor.abbrechen') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
