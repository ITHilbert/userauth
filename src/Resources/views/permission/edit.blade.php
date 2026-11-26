@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::permission.header_edit'))

@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')

@section('content')
<div class="userauth-container">
            <x-kit::card-main>
                <form method="POST" action="{{ route('permission.update', $permissiongroup->id) }}">
                    @csrf
                    <x-kit::card-main-header>
                        <h1>@lang('userauth::permission.header_edit')</h1>
                        @include('include.breadcrumb')
                    </x-kit::card-main-header>
                    <x-kit::card-body>
                        @include('include.message')

                        <div class="userauth-zeile">
                            <label for="group_display" class="userauth-zeile__label">@lang('userauth::permission.group_display')</label>
                            <div class="userauth-zeile__feld">
                                <x-kit::input-text name="group_display" :value="AlteEingabe::text('group_display', $permissiongroup->group_display)" required />
                            </div>
                        </div>

                        <div class="userauth-zeile">
                            <label for="group_name" class="userauth-zeile__label">@lang('userauth::permission.group_name')</label>
                            <div class="userauth-zeile__feld">
                                <x-kit::input-text name="group_name" :value="AlteEingabe::text('group_name', $permissiongroup->group_name)" required />
                                @error('group_name')<div class="userauth-fehler">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="userauth-zeile">
                            <label for="ckPermissionGroup" class="userauth-zeile__label">@lang('userauth::permission.ck-permission-group')</label>
                            <div class="userauth-zeile__feld">
                                <x-kit::checkbox name="ckPermissionGroup" value="1" onclick="showSinglePermissions()" :checked="$permissiongroup->is_group == 1" disabled />
                            </div>
                        </div>

                        <div id="group_permissions_show" class="{{ $permissiongroup->is_group == 1 ? '' : 'd-none' }}">
                            <hr>
                            <div class="userauth-zeile">
                                <div class="userauth-zeile__label"></div>
                                <div class="userauth-zeile__feld">
                                    @include('userauth::permission.rechtezeilen')
                                </div>
                            </div>
                        </div>
                    </x-kit::card-body>
                    <x-kit::card-footer>
                        <div class="userauth-spalte">
                            <x-kit::button-back :route="route('permission')">@lang('userauth::button.back')</x-kit::button-back>
                            <x-kit::button-save class="userauth-rechts">@lang('userauth::button.save')</x-kit::button-save>
                        </div>
                    </x-kit::card-footer>
                </form>
            </x-kit::card-main>
</div>
@stop

@push('userauth-scripts')
    <script defer src="{{ asset('vendor/userauth/js/rechtename.js') }}"></script>
    <script defer src="{{ asset('vendor/userauth/js/permission.js') }}"></script>
@endpush
