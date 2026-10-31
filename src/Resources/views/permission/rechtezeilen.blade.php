{{-- Tabelle der Einzelrechte im Rechteformular: bestehende Rechte ($permissions, beim Anlegen leer), eine neue Zeile und die Vorlage für js/permission.js. --}}
@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')
<table class="table">
    <thead>
    <tr>
        <th scope="col" class="d-none">ID</th>
        <th scope="col">Recht</th>
        <th scope="col">Recht intern</th>
        <th scope="col">
            <x-kit::button-create type="button" onclick="newPermission()" tooltip="Neues Recht erstellen" />
        </th>
    </tr>
    </thead>
    <tbody id="tbody">
        @foreach ($permissions as $permission)
            <tr id="perm-{{ $permission->id }}" nummer="{{ $permission->id }}">
                <td scope="row" class="d-none">{{ $permission->id }}</td>
                <td><x-kit::input-text id="display_{{ $permission->id }}" name="permission_display_{{ $permission->id }}" onchange="editIntern('perm-{{ $permission->id }}')" :value="AlteEingabe::text('permission_display_'.$permission->id, $permission->permission_display)" /></td>
                <td><x-kit::input-text id="intern_{{ $permission->id }}" name="permission_{{ $permission->id }}" :value="AlteEingabe::text('permission_'.$permission->id, $permission->permission)" /></td>
                <td><x-kit::button-delete onclick="deletePermission('perm-{{ $permission->id }}')" tooltip="Löschen" /></td>
            </tr>
        @endforeach
        <tr id="new-row-1" nummer="1">
            <td scope="row" class="d-none">new_1</td>
            <td><x-kit::input-text id="display_new_1" name="permission_display_new_1" onchange="editIntern('new-row-1')" :value="AlteEingabe::text('permission_display_new_1')" /></td>
            <td><x-kit::input-text id="intern_new_1" name="permission_new_1" :value="AlteEingabe::text('permission_new_1')" /></td>
            <td><x-kit::button-delete onclick="deletePermission('new-row-1')" tooltip="Löschen" /></td>
        </tr>
        <tr id="vorlage" class="d-none" nummer="x">
            <td id="new-id" scope="row" class="d-none"></td>
            <td><x-kit::input-text id="vorlage_display" /></td>
            <td><x-kit::input-text id="vorlage_intern" /></td>
            <td><x-kit::button-delete id="btnVorlage" tooltip="Löschen" /></td>
        </tr>
    </tbody>
</table>
