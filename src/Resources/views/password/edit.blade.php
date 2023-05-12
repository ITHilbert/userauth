@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::password.header_change'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <hform action="{{ route('password.update') }}">
                <card>
                    <card-header>@lang('userauth::password.header_change')"</card-header>
                    <card-body>
                        @include('include.message')

                            <div class="form-group row">
                                <label for="password" class="col-md-4 col-form-label text-md-right">@lang('userauth::password.password')</label>

                                <div class="col-md-8">
                                    <input-password id="password" class=" @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" />

                                    @error('password')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="password-confirm" class="col-md-4 col-form-label text-md-right">@lang('userauth::password.password-confirm')</label>

                                <div class="col-md-8">
                                    <input-password id="password-confirm" name="password_confirmation" required autocomplete="new-password" />
                                </div>
                            </div>

                    </card-body>
                    <card-footer>
                        {{-- Buttons --}}
                        <div style="display: flex; justify-content: flex-end;">
                            <button-save>@lang('userauth::button.editPassword')</button-save>
                        </div>
                    </card-footer>
                </card>
            </hform>
        </div>
    </div>
</div>
@endsection
