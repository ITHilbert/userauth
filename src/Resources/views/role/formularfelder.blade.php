{{-- Felder des Rollenformulars (Anlegen und Bearbeiten). $role: die Rolle beim Bearbeiten, null beim Anlegen. --}}
@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')

{{-- Rolle --}}
<div class="form-group row mb-2">
    <label for="role_display" class="col-md-4 col-form-label text-md-right">@lang('userauth::role.role')</label>
    <div class="col-md-6">
        <x-kit::input-text name="role_display" id="role_display" :value="AlteEingabe::text('role_display', $role?->role_display)" onchange="setInternValue()" required />
    </div>
</div>

{{-- Rolle intern --}}
<div class="form-group row mb-2">
    <label for="role" class="col-md-4 col-form-label text-md-right">@lang('userauth::role.role_intern')</label>
    <div class="col-md-6">
        <x-kit::input-text name="role" id="role" :value="AlteEingabe::text('role', $role?->role)" required />
    </div>
</div>

<hr>

{{-- Tabs --}}
<div class="form-group row mb-2">
    <div class="col-md-4"></div>
    <div class="col-md-6">
        <x-kit::tabs-header>
            <x-kit::tab-header name="home" active>@lang('userauth::role.crud')</x-kit::tab-header>
            <x-kit::tab-header name="profile">@lang('userauth::role.group')</x-kit::tab-header>
        </x-kit::tabs-header>
        <x-kit::tabs-body>
            <x-kit::tab-body name="home" active>
                {{-- CRUD-Rechte --}}
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
                            @if ($group->is_group == 0)
                                <tr>
                                    <th scope="row">{{ $group->group_display }}</th>
                                    @foreach ([$group->permissionCreate(), $group->permissionRead(), $group->permissionEdit(), $group->permissionDelete()] as $perm)
                                        <td class="text-center">
                                            @include('userauth::role.recht', ['perm' => $perm])
                                        </td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </x-kit::tab-body>
            <x-kit::tab-body name="profile">
                {{-- Einzelrechte --}}
                @foreach ($permissionsgroups as $group)
                    @if ($group->is_group == 1)
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
                                            @include('userauth::role.recht', ['perm' => $perm])
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                @endforeach
            </x-kit::tab-body>
        </x-kit::tabs-body>
    </div>
</div>
