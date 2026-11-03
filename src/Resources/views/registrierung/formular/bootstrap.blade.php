@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')
@extends('userauth::layouts.beforeLogin')

@section('main')
<div class="container-fluid px-0">
    <div class="container-fluid">
      <div class="bg-holder bg-auth-card-overlay" style="background-image:url({{ asset('assets/img/bg/37.png') }});">
      </div>
      <!--/.bg-holder-->

      <div class="row flex-center position-relative min-vh-100 g-0 py-5">
        <div class="col-11 col-sm-10 col-xl-8">
          <div class="card border border-300 auth-card">
            <div class="card-body pe-md-0">
              <div class="row align-items-center gx-0 gy-7">
                <div class="col-auto bg-100 dark__bg-1100 rounded-3 position-relative overflow-hidden auth-title-box">
                  <div class="bg-holder" style="background-image:url({{ asset('assets/img/bg/38.png') }});">
                  </div>
                  <!--/.bg-holder-->

                  <div class="position-relative px-4 px-lg-7 pt-7 pb-7 pb-sm-5 text-center text-md-start pb-lg-7">
                    <h3 class="mb-3 text-black fs-1">@lang('userauth::login.seite_titel')</h3>
                    <p class="text-700">@lang('userauth::login.seite_text')</p>
                    <ul class="list-unstyled mb-0 w-max-content w-md-auto mx-auto">
                      <li class="d-flex align-items-center"><span class="uil uil-check-circle text-success me-2"></span><span class="text-700 fw-semi-bold">@lang('userauth::login.merkmal_schnell')</span></li>
                      <li class="d-flex align-items-center"><span class="uil uil-check-circle text-success me-2"></span><span class="text-700 fw-semi-bold">@lang('userauth::login.merkmal_einfach')</span></li>
                      <li class="d-flex align-items-center"><span class="uil uil-check-circle text-success me-2"></span><span class="text-700 fw-semi-bold">@lang('userauth::login.merkmal_responsiv')</span></li>
                    </ul>
                  </div>
                  <div class="position-relative z-index--1 mb-6 d-none d-md-block text-center mt-md-5"><img class="auth-title-box-img d-dark-none" src="{{ asset('assets/img/spot-illustrations/auth.png') }}" alt="" />
                    <img class="auth-title-box-img d-light-none" src="{{ asset('assets/img/spot-illustrations/auth-dark.png')}}" alt="" /></div>
                </div>
                <div class="col mx-auto">
                  <div class="auth-form-box">
                    <div class="text-center mb-5">
                      <h4 class="text-1000">@lang('userauth::registrierung.titel')</h4>
                      <p class="text-700">@lang('userauth::registrierung.untertitel')</p>
                    </div>
                    @if ($errors->any())
                      <div class="alert alert-danger text-start" role="alert">
                        @foreach ($errors->all() as $error)
                          <div>{{ $error }}</div>
                        @endforeach
                      </div>
                    @endif
                    <form method="POST" action="{{ route('register') }}">
                      @csrf
                      <div class="mb-3 text-start">
                        <label class="form-label" for="name">@lang('userauth::registrierung.name')</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ AlteEingabe::text('name') }}" required autocomplete="name" autofocus />
                      </div>
                      <div class="mb-3 text-start">
                        <label class="form-label" for="email">@lang('userauth::registrierung.email')</label>
                        <input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ AlteEingabe::text('email') }}" required autocomplete="email" />
                      </div>
                      <div class="mb-3 text-start">
                        <label class="form-label" for="password">@lang('userauth::registrierung.passwort')</label>
                        <input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" required autocomplete="new-password" />
                      </div>
                      <div class="mb-5 text-start">
                        <label class="form-label" for="password_confirmation">@lang('userauth::registrierung.passwort_wiederholen')</label>
                        <input class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                      </div>
                      <button class="btn btn-primary w-100 mb-3">@lang('userauth::registrierung.absenden')</button>
                    </form>
                    <div class="text-center">
                      @lang('userauth::registrierung.schon_registriert')
                      <a class="fw-bold" href="{{ route('login') }}">@lang('userauth::registrierung.zum_login')</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@stop
