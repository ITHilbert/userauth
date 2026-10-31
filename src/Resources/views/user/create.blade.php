@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::user.header_create'))

@section('content')
    <x-kit::card-main>
        <form method="POST" action="{{ route('user.store') }}" enctype="multipart/form-data">
            @csrf
            <x-kit::card-main-header>
                <h1>@lang('userauth::user.header_create')</h1>
                @include('include.breadcrumb')
            </x-kit::card-main-header>
            <x-kit::card-body>
                @include('include.message')

                @include('userauth::user.formularfelder', ['user' => null])
            </x-kit::card-body>
            <x-kit::card-footer>
                <div class="form-group row mb-2">
                    <div class="col">
                        <x-kit::button-back :route="route('user')">@lang('userauth::button.back')</x-kit::button-back>
                    </div>
                    <div class="col">
                        <x-kit::button-save class="float-end">@lang('userauth::button.save')</x-kit::button-save>
                    </div>
                </div>
            </x-kit::card-footer>
        </form>
    </x-kit::card-main>
@stop
