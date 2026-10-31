{{-- Kästchen eines Rechts im Rollenformular. Gesendet wird die ID des Rechts; angehakt nach alter Eingabe, sonst nach den Rechten der Rolle. --}}
@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')
@if ($perm !== null)
    <x-kit::checkbox name="permission[{{ $perm->id }}]" id="permission_{{ $perm->id }}" :value="$perm->id"
        :checked="AlteEingabe::inAuswahl('permission', $perm->id, $role !== null && $role->hasPermissionInView($perm->permission))" />
@endif
