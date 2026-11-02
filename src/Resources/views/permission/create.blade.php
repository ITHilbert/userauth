@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::permission.header_create'))

@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')

@section('content')
@php($einzelrechte = filled(AlteEingabe::text('ckPermissionGroup')))
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <x-kit::card-main>
                <form method="POST" action="{{ route('permission.store') }}">
                    @csrf
                    <x-kit::card-main-header>
                        <h1>@lang('userauth::permission.header_create')</h1>
                        @include('include.breadcrumb')
                    </x-kit::card-main-header>
                    <x-kit::card-body>
                        @include('include.message')

                        <div class="form-group row mb-2">
                            <label for="group_display" class="col-md-4 col-form-label text-md-right">@lang('userauth::permission.group_display')</label>
                            <div class="col-md-6">
                                <x-kit::input-text name="group_display" :value="AlteEingabe::text('group_display')" required />
                            </div>
                        </div>

                        <div class="form-group row mb-2">
                            <label for="group_name" class="col-md-4 col-form-label text-md-right">@lang('userauth::permission.group_name')</label>
                            <div class="col-md-6">
                                <x-kit::input-text name="group_name" :value="AlteEingabe::text('group_name')" required />
                                @error('group_name')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-group row mb-2">
                            <label for="ckPermissionGroup" class="col-md-4 col-form-label text-md-right">@lang('userauth::permission.ck-permission-group')</label>
                            <div class="col-md-6 pt-2">
                                <x-kit::checkbox name="ckPermissionGroup" value="1" onclick="showSinglePermissions()" :checked="$einzelrechte" />
                            </div>
                        </div>

                        <div id="group_permissions_show" class="{{ $einzelrechte ? '' : 'd-none' }}">
                            <hr>
                            <div class="form-group row mb-2">
                                <div class="col-md-4"></div>
                                <div class="col-md-6">
                                    @include('userauth::permission.rechtezeilen', ['permissions' => []])
                                </div>
                            </div>
                        </div>
                    </x-kit::card-body>
                    <x-kit::card-footer>
                        <div class="form-group row mb-2">
                            <div class="col">
                                <x-kit::button-back :route="route('permission')">@lang('userauth::button.back')</x-kit::button-back>
                                <x-kit::button-save class="float-end">@lang('userauth::button.save')</x-kit::button-save>
                            </div>
                        </div>
                    </x-kit::card-footer>
                </form>
            </x-kit::card-main>
        </div>
    </div>
</div>
@stop

@push('userauth-scripts')
    <script defer src="{{ asset('vendor/userauth/js/permission.js') }}"></script>
@endpush
