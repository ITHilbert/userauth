{{-- Tabelle der Einzelrechte im Rechteformular: bestehende Rechte ($permissions, beim Anlegen leer), eine neue Zeile und die Vorlage für js/permission.js. --}}
@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')
@php
    // Nach einem Validierungsfehler: alle per Skript ergänzten Zeilen `…_new_<n>` aus der alten Eingabe (mindestens Zeile 1)
    $neueNummern = [1];
    if (request()->hasSession()) {
        foreach (array_keys((array) request()->old()) as $feldname) {
            if (preg_match('/^permission(?:_display)?_new_([1-9]\d{0,5})$/', (string) $feldname, $treffer)) {
                $neueNummern[] = (int) $treffer[1];
            }
        }
    }
    $neueNummern = array_values(array_unique($neueNummern));
    sort($neueNummern);
@endphp
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
            @php($entfernt = AlteEingabe::text('delete_intern_'.$permission->id) !== null)
            @php($prefixAnzeige = $entfernt ? 'delete_display_' : 'permission_display_')
            @php($prefixIntern = $entfernt ? 'delete_intern_' : 'permission_')
            <tr id="perm-{{ $permission->id }}" nummer="{{ $permission->id }}" @class(['d-none' => $entfernt])>
                <td scope="row" class="d-none">{{ $permission->id }}</td>
                <td><x-kit::input-text id="display_{{ $permission->id }}" name="{{ $prefixAnzeige.$permission->id }}" onchange="editIntern('perm-{{ $permission->id }}')" :value="AlteEingabe::text($prefixAnzeige.$permission->id, AlteEingabe::text('permission_display_'.$permission->id, $permission->permission_display))" /></td>
                <td><x-kit::input-text id="intern_{{ $permission->id }}" name="{{ $prefixIntern.$permission->id }}" :value="AlteEingabe::text($prefixIntern.$permission->id, AlteEingabe::text('permission_'.$permission->id, $permission->permission))" /></td>
                <td><x-kit::button-delete onclick="deletePermission('perm-{{ $permission->id }}')" tooltip="Löschen" /></td>
            </tr>
        @endforeach
        @foreach ($neueNummern as $n)
            <tr id="new-row-{{ $n }}" nummer="{{ $n }}">
                <td scope="row" class="d-none">new_{{ $n }}</td>
                <td><x-kit::input-text id="display_new_{{ $n }}" name="permission_display_new_{{ $n }}" onchange="editIntern('new-row-{{ $n }}')" :value="AlteEingabe::text('permission_display_new_'.$n)" /></td>
                <td><x-kit::input-text id="intern_new_{{ $n }}" name="permission_new_{{ $n }}" :value="AlteEingabe::text('permission_new_'.$n)" /></td>
                <td><x-kit::button-delete onclick="deletePermission('new-row-{{ $n }}')" tooltip="Löschen" /></td>
            </tr>
        @endforeach
        <tr id="vorlage" class="d-none" nummer="x">
            <td id="new-id" scope="row" class="d-none"></td>
            <td><x-kit::input-text id="vorlage_display" /></td>
            <td><x-kit::input-text id="vorlage_intern" /></td>
            <td><x-kit::button-delete id="btnVorlage" tooltip="Löschen" /></td>
        </tr>
    </tbody>
</table>
