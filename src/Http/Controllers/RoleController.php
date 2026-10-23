<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use ITHilbert\LaravelKit\Helpers\Breadcrumb;
use ITHilbert\LaravelKit\Helpers\HButton;
use ITHilbert\UserAuth\Entities\PermissionGroup;
use ITHilbert\UserAuth\Entities\Role;
use ITHilbert\UserAuth\Http\Requests\RolleSpeichernRequest;
use ITHilbert\UserAuth\Traits\Format;
use Yajra\DataTables\Facades\DataTables;

final class RoleController extends Controller
{
    use Format;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = Role::latest()->where('deleted_at', null)->get();

        if ($request->ajax()) {
            $user = Auth::user();

            return DataTables::of($data)
                    /* ->addIndexColumn() */
 /*                    ->addColumn('cname', function($row){
                            return $row->getName();
                    }) */
                ->addColumn('action', function ($row) use ($user) {
                    $ausgabe = '<div style="white-space: nowrap;">';
                    if ($row->id > 2) {
                        // $ausgabe .= HButton::show(route('permission.show', $row->id), '');
                        if ($user->hasPermission('role_edit')) {
                            $ausgabe .= HButton::edit(route('role.edit', $row->id), '');
                        }
                        if ($user->hasPermission('role_delete')) {
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
        $breadcrumb->add(trans('userauth::role.header_list'));

        return view('userauth::role.index')->with(compact('breadcrumb'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {

        $permissionsgroups = PermissionGroup::where('id', '!=', 3)->get();

        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::role.header_list'), route('role'));
        $breadcrumb->add(trans('userauth::role.header_create'));

        return view('userauth::role.create')->with(compact('permissionsgroups', 'breadcrumb'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(RolleSpeichernRequest $request)
    {
        $daten = $request->validated();

        DB::transaction(function () use ($daten): void {
            $role = new Role;
            $role->role_display = $daten['role_display'];
            $role->role = $this->formatDisplayToIntern($daten['role']);
            $role->save();

            $role->permissions()->sync(array_values($daten['permission'] ?? []));
        });

        return redirect()->route('role')->with([
            'message' => Lang::get('userauth::role.MsgAddSuccess'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $role = Role::findOrFail($id);
        $permissionsgroups = PermissionGroup::where('id', '!=', 3)->get();

        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::role.header_list'), route('role'));
        $breadcrumb->add(trans('userauth::role.header_edit'));

        return view('userauth::role.edit')->with(compact('role', 'permissionsgroups', 'breadcrumb'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(RolleSpeichernRequest $request, $id)
    {
        $role = Role::findOrFail($id);
        $daten = $request->validated();

        DB::transaction(function () use ($role, $daten): void {
            $role->role_display = $daten['role_display'] ?? null;
            $role->role = $this->formatDisplayToIntern($daten['role'] ?? null);
            $role->update();

            $role->permissions()->sync(array_values($daten['permission'] ?? []));
        });

        return redirect()->route('role')->with(['message' => Lang::get('userauth::role.MsgEditSuccess')]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete($id)
    {
        if ($id < 3) {
            return redirect()->route('role')->withErrors(Lang::get('userauth::role.MsgDeleteRoot'));
        }

        $role = Role::findOrFail($id);
        $role->delete();

        return redirect()->route('role')->with([
            'message' => Lang::get('userauth::role.MsgDeleteSuccess'),
            'alert-type' => 'success',
        ]);
    }
}
