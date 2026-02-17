@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ __('Two Factor Verification') }}</div>

                    <div class="card-body">
                        @if(session()->has('message'))
                            <p class="alert alert-info">
                                {{ session()->get('message') }}
                            </p>
                        @endif

                        <form method="POST" action="{{ route('verify.store') }}">
                            @csrf

                            <p class="text-muted">
                                Sie haben eine E-Mail mit einem Sicherheitscode erhalten.
                                Dieser Code ist 10 Minuten gültig.
                            </p>

                            <div class="form-group row">
                                <label for="two_factor_code"
                                    class="col-md-4 col-form-label text-md-right">{{ __('Sicherheitscode') }}</label>

                                <div class="col-md-6">
                                    <input id="two_factor_code" type="text"
                                        class="form-control @error('two_factor_code') is-invalid @enderror"
                                        name="two_factor_code" required autofocus>

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
                                        {{ __('Bestätigen') }}
                                    </button>

                                    <a class="btn btn-link" href="{{ route('verify.resend') }}">
                                        {{ __('Code erneut senden') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection