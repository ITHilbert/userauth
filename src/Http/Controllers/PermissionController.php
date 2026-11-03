<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use ITHilbert\LaravelKit\Helpers\Breadcrumb;
use ITHilbert\LaravelKit\Helpers\HButton;
use ITHilbert\UserAuth\Entities\Permission;
use ITHilbert\UserAuth\Entities\PermissionGroup;
use ITHilbert\UserAuth\Http\Formular\EinzelrechtNamenPruefung;
use ITHilbert\UserAuth\Http\Formular\GruppenNamenPruefung;
use ITHilbert\UserAuth\Http\Requests\PermissionGruppeRequest;
use ITHilbert\UserAuth\Traits\Format;
use Yajra\DataTables\Facades\DataTables;

final class PermissionController extends Controller
{
    use Format;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = PermissionGroup::latest()->where('deleted_at', null)->get();

        if ($request->ajax()) {
            $user = Auth::user(); // find(Auth::id());

            return DataTables::of($data)
                    /* ->addIndexColumn() */
 /*                    ->addColumn('cname', function($row){
                            return $row->getName();
                    }) */
                ->addColumn('action', function ($row) use ($user) {
                    $ausgabe = '<div style="white-space: nowrap;">';
                    if ($row->id > 3) {
                        if ($user->hasPermission('permission_edit')) {
                            $ausgabe .= HButton::edit(route('permission.edit', $row->id), '');
                        }
                        if ($user->hasPermission('permission_delete')) {
                            $ausgabe .= HButton::delete($row->id, '');
                        }
                    }
                    $ausgabe .= '</div>';

                    return $ausgabe;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::permission.header_list'));

        return view('userauth::permission.index')->with(compact('breadcrumb'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::permission.header_list'), route('permission'));
        $breadcrumb->add(trans('userauth::permission.header_create'));

        return view('userauth::permission.create')->with(compact('breadcrumb'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(PermissionGruppeRequest $request)
    {
        $group_display = $request->group_display;
        $group_name = $this->formatDisplayToIntern($request->group_name);

        $namenPruefung = new GruppenNamenPruefung;
        $namenPruefung->pruefen($group_name, ! isset($request->ckPermissionGroup));
        (new EinzelrechtNamenPruefung)->pruefen(isset($request->ckPermissionGroup) ? $request->all() : []);

        $namenPruefung->speichern(fn () => DB::transaction(function () use ($request, $group_display, $group_name): void {
            if (! isset($request->ckPermissionGroup)) {
                // Permissions group anlegen
                $group = new PermissionGroup;
                $group->group_name = $group_name;
                $group->group_display = $group_display;
                $group->save();

                // CRUD Recht anlegen

                // create
                $permission_create = new Permission;
                $permission_create->permission_display = $group_display.' '.__('userauth::permission.create');
                $permission_create->permission = $group_name.'_create';
                $permission_create->group_id = $group->id;
                $permission_create->crud = 'create';
                $permission_create->save();

                // lesen
                $permission_read = new Permission;
                $permission_read->permission_display = $group_display.' '.__('userauth::permission.read');
                $permission_read->permission = $group_name.'_read';
                $permission_read->group_id = $group->id;
                $permission_read->crud = 'read';
                $permission_read->save();

                // ändern
                $permission_edit = new Permission;
                $permission_edit->permission_display = $group_display.' '.__('userauth::permission.edit');
                $permission_edit->permission = $group_name.'_edit';
                $permission_edit->group_id = $group->id;
                $permission_edit->crud = 'edit';
                $permission_edit->save();

                // delete
                $permission_delete = new Permission;
                $permission_delete->permission_display = $group_display.' '.__('userauth::permission.delete');
                $permission_delete->permission = $group_name.'_delete';
                $permission_delete->group_id = $group->id;
                $permission_delete->crud = 'delete';
                $permission_delete->save();
                // Einzelrechte bzw. Gruppenrechte
            } else {
                $input = $request->all();

                // Permissions group anlegen
                $group = new PermissionGroup;
                $group->group_name = $group_name;
                $group->group_display = $group_display;
                $group->is_group = 1;
                $group->save();

                foreach ($input as $param => $value) {
                    // Neues Recht
                    if (is_null($value)) {
                        // Do nothing

                        // Neues Recht
                    } elseif (strpos($param, 'permission_display_new') === 0) {
                        $this->neuesEinzelrecht($group->id, $input, $param, $value);
                    }
                }
            }
        }));

        return redirect()->route('permission')->with([
            'message' => Lang::get('userauth::permission.MsgAddSuccess'),
            'alert-type' => 'success',
        ]);

    }

    /**
     * Legt ein neues Einzelrecht aus den Formularfeldern an.
     * Ohne gesendeten internen Namen wird er aus der Anzeige gebildet.
     *
     * @param  array<string, mixed>  $input
     */
    private function neuesEinzelrecht(int|string $gruppenId, array $input, string $param, mixed $anzeige): void
    {
        if (! is_string($anzeige) || $anzeige === '') {
            return;
        }

        $nr = substr($param, (int) strrpos($param, '_') + 1);
        $feld = 'permission_new_'.$nr;
        $intern = $input[$feld] ?? null;
        $quelle = is_string($intern) && $intern !== '' ? $intern : $anzeige;

        $permission = new Permission;
        $permission->group_id = $gruppenId;
        $permission->crud = 'single';
        $permission->permission_display = $anzeige;
        $permission->permission = $this->formatDisplayToIntern($quelle);
        (new EinzelrechtNamenPruefung)->speichern($feld, fn () => $permission->save());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $permissiongroup = PermissionGroup::findOrFail($id);
        $permissions = Permission::where('group_id', $id)->where('deleted_at', null)->get();

        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::permission.header_list'), route('permission'));
        $breadcrumb->add(trans('userauth::permission.header_edit'));

        return view('userauth::permission.edit')->with(compact('permissiongroup', 'permissions', 'breadcrumb'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(PermissionGruppeRequest $request, $id)
    {
        $group_display = $request->group_display;
        $group_name = $this->formatDisplayToIntern($request->group_name);

        $group = PermissionGroup::findOrFail($id);

        $namenPruefung = new GruppenNamenPruefung;
        $namenPruefung->pruefen($group_name, $group->is_group === 0, (int) $id);

        if ($group->is_group !== 0) {
            (new EinzelrechtNamenPruefung)->pruefen(
                $request->all(),
                $this->gruppenRechte((int) $id)->pluck('id')->map(fn ($rechtId): int => (int) $rechtId)->all()
            );
        }

        $namenPruefung->speichern(fn () => DB::transaction(function () use ($request, $id, $group, $group_display, $group_name): void {
            $group->group_display = $request->group_display;
            $group->group_name = $group_name;
            $group->update();

            // CRUD Rechte
            if ($group->is_group === 0) {
                $permissions = Permission::withTrashed()->where('group_id', $id)->get();
                foreach ($permissions as $perm) {
                    if ($perm->crud == 'create') {
                        $perm->permission_display = $group_display.' '.__('userauth::permission.create');
                        $perm->permission = $group_name.'_create';
                    } elseif ($perm->crud == 'read') {
                        $perm->permission_display = $group_display.' '.__('userauth::permission.read');
                        $perm->permission = $group_name.'_read';
                    } elseif ($perm->crud == 'edit') {
                        $perm->permission_display = $group_display.' '.__('userauth::permission.edit');
                        $perm->permission = $group_name.'_edit';
                    } else {
                        $perm->permission_display = $group_display.' '.__('userauth::permission.delete');
                        $perm->permission = $group_name.'_delete';
                    }
                    $perm->update();

                }
                // Einzelrechte bzw. Gruppenrechte
            } else {
                $input = $request->all();
                foreach ($input as $param => $value) {
                    // Neues Recht
                    if (is_null($value)) {
                        // Do nothing

                        // Neues Recht
                    } elseif (strpos($param, 'permission_display_new') === 0) {
                        $this->neuesEinzelrecht($id, $input, $param, $value);

                        // Bestehendes Recht ändern
                    } elseif (strpos($param, 'permission_display_') === 0) {
                        // ID ermitteln
                        $pos = strrpos($param, '_');
                        $nr = substr($param, $pos + 1);

                        $permission = $this->gruppenRechte((int) $id)->find($nr);
                        if ($permission === null) {
                            continue;
                        }
                        $permission->permission_display = $value;

                        $intern = $input['permission_'.$nr] ?? null;
                        if (is_string($intern) && $intern !== '') {
                            $permission->permission = $this->formatDisplayToIntern($intern);
                        }
                        (new EinzelrechtNamenPruefung)->speichern('permission_'.$nr, fn () => $permission->update());
                        // Bestehendes Recht löschen
                    } elseif (strpos($param, 'delete_intern_') === 0) {
                        // ID ermitteln
                        $pos = strrpos($param, '_');
                        $nr = substr($param, $pos + 1);

                        $this->gruppenRechte((int) $id)->where('id', $nr)->delete();
                    }
                }
            }
        }));

        return redirect()->route('permission')->with([
            'message' => Lang::get('userauth::permission.MsgEditSuccess'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete($id)
    {
        // Recht löschen
        $permissions = Permission::where('group_id', $id)->get();
        foreach ($permissions as $perm) {
            $perm->delete();
        }

        // Rechte Groupe löschen
        $group = PermissionGroup::findOrFail($id);
        $group->delete();

        return redirect()->route('permission')->with([
            'message' => Lang::get('userauth::permission.MsgDeleteSuccess'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * @return Builder<Permission>
     */
    private function gruppenRechte(int $groupId)
    {
        return Permission::where('group_id', $groupId);
    }

    public function noPermission(Request $request)
    {
        return view('userauth::nopermission');
    }
}
