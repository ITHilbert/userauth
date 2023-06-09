@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::role.header_edit'))

{{-- @section('content_header')
@stop --}}

@section('content')
<card-main>
    <hform action="{{ route('role.update', $role->id) }}">
        <card-main-header>
            <h1>@lang('userauth::role.header_edit')</h1>
            @include('include.breadcrumb')
        </card-main-header>
        <card-body>
            @include('include.message')

            <div class="form-group row mb-2">
                <label for="role_display" class="col-md-4 col-form-label text-md-right">@lang('userauth::role.role')</label>
                <div class="col-md-6">
                <input-text name="role_display" id="role_display" value="{{ old('role_display', $role->role_display) }}" onchange="setInternValue()" required />
                </div>
            </div>

            <div class="form-group row mb-2">
                <label for="role" class="col-md-4 col-form-label text-md-right">@lang('userauth::role.role_intern')</label>
                <div class="col-md-6">
                <input-text name="role" id="role" value="{{ old('role', $role->role) }}" required />
                </div>
            </div>

            <br>

            {{-- Tabs --}}
            <div class="form-group row mb-2">
                <div class="col-md-4"></div>
                <div class="col-md-6">
                    <tabs-header>
                        <tab-header name="home" :active="true">@lang('userauth::role.crud')</tab-header>
                        <tab-header name="profile">@lang('userauth::role.group')</tab-header>
                    </tabs-header>
                    <tabs-body>
                        <tab-body name="home" :active="true">
                            {{-- Permissions --}}
                            <table class="table">
                                <thead>
                                <tr>
                                    <th scope="col">@lang('userauth::permission.permission')</th>
                                    <th scope="col" class="text-center">@lang('userauth::permission.create')</th>
                                    <th scope="col" class="text-center">@lang('userauth::permission.read')</th>
                                    <th scope="col" class="text-center">@lang('userauth::permission.edit')</th>
                                    <th scope="col" class="text-center">@lang('userauth::permission.delete')</th>
                                </tr>
                                </thead>
                                <tbody>
                                    @foreach ($permissionsgroups as $group)
                                        @if($group->is_group == 0)
                                            <tr>
                                                <th scope="row">{{ $group->group_display }}</th>
                                                <td class="text-center"><checkbox name="permission[{{ $group->permissionCreate()->id }}]" value="{{ $group->permissionCreate()->id }}" checked="{{ $role->hasPermissionInView($group->group_name .'_create') }}"></checkbox></td>
                                                <td class="text-center"><checkbox name="permission[{{ $group->permissionRead()->id }}]" value="{{ $group->permissionRead()->id }}" checked="{{ $role->hasPermissionInView($group->group_name .'_read') }}"></checkbox></td>
                                                <td class="text-center"><checkbox name="permission[{{ $group->permissionEdit()->id }}]" value="{{ $group->permissionEdit()->id }}" checked="{{ $role->hasPermissionInView($group->group_name .'_edit') }}"></checkbox></td>
                                                <td class="text-center"><checkbox name="permission[{{ $group->permissionDelete()->id }}]" value="{{ $group->permissionDelete()->id }}" checked="{{ $role->hasPermissionInView($group->group_name .'_delete') }}"></checkbox></td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </tab-body>
                        <tab-body name="profile">
                            {{-- Permissions Single--}}
                            @foreach ($permissionsgroups as $group)
                                @if($group->is_group == 1)
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th scope="col" colspan="5">{{ $group->group_display }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($group->getPermisssionsSingle() as $perm)
                                                <tr>
                                                    <td colspan="3" scope="row">{{ $perm->permission_display }}</td>
                                                    <td colspan="2" class="text-right">
                                                        <checkbox name="permission[{{ $perm->id }}]"
                                                                value="{{ $perm->id }}"
                                                                checked="{{ $role->hasPermissionInView($perm->permission) }}" >
                                                        </checkbox>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            @endforeach
                        </tab-body>
                    </tabs-body>
                </div>
            </div>
        </card-body>
        <card-footer>
            {{-- Buttons --}}
            <div class="form-group row mb-2">
                <div class="col">
                    <button-back route="{{ route('role') }}">@lang('userauth::button.back')</button-back>
                    <button-save class="float-end">@lang('userauth::button.save')</button-save>
                </div>
            </div>
        </card-footer>
    </hform>
</card-main>
@stop


@section('js')
    <script src="{{asset("vendor/userauth/js/role.js")}} "></script>
@stop
