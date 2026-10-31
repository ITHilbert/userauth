@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::user.header_edit'))

@section('content')
    <x-kit::card-main>
        <form method="POST" action="{{ route('usericon.update') }}" enctype="multipart/form-data">
            @csrf
            <x-kit::card-main-header>
                <h1>@lang('userauth::user.header_edit')</h1>
                @include('include.breadcrumb')
            </x-kit::card-main-header>
            <x-kit::card-body>
                @include('include.message')

                {{-- Bild --}}
                @if (config('userauth.user.image'))
                    <div class="form-group row mb-2">
                        <label for="image" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.image')</label>
                        <div class="col-md-6">
                            <x-kit::input-file-img name="image" />
                        </div>
                    </div>
                @endif
            </x-kit::card-body>
            <x-kit::card-footer>
                <div class="form-group row mb-2">
                    <div class="col">
                        <x-kit::button-back type="button" onclick="window.history.back();">@lang('userauth::button.back')</x-kit::button-back>
                    </div>
                    <div class="col">
                        <x-kit::button-save class="float-end">@lang('userauth::button.save')</x-kit::button-save>
                    </div>
                </div>
            </x-kit::card-footer>
        </form>
    </x-kit::card-main>
@stop
