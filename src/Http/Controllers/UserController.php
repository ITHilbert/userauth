<?php

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Yajra\DataTables\Facades\DataTables;

use App\Models\User;
use ITHilbert\LaravelKit\Helpers\Breadcrumb;
use ITHilbert\LaravelKit\Helpers\HButton;
use ITHilbert\UserAuth\Entities\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $data = User::latest()->where('deleted_at', NULL)->with('role')->get();

        if ($request->ajax()) {
            $user = Auth::user();
            return Datatables::of($data)
                ->addColumn('RoleName', function ($row) {
                    return $row->roleDisplayname();
                })
                ->addColumn('action', function ($row) use ($user) {
                    $ausgabe = '<div style="white-space: nowrap;">';
                    //$ausgabe .= HButton::show(route('permission.show', $row->id), '');
                    if($user->hasPermission('user_edit')){
                        $ausgabe .= HButton::edit(route('user.edit', $row->id), '');
                    }
                    if($user->hasPermission('user_delete')){
                        $ausgabe .= HButton::delete($row->id, '');
                    }
                    $ausgabe .= '</div>';

                    return $ausgabe;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $breadcrumb = new Breadcrumb();
        $breadcrumb->add( trans('userauth::user.header_list'));

        return view('userauth::user.index')->with(compact('breadcrumb'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        $roles = Role::getComboBoxData();

        $breadcrumb = new Breadcrumb();
        $breadcrumb->add( trans('userauth::user.header_list'), route('user'));
        $breadcrumb->add( trans('userauth::user.header_create'));

        return view('userauth::user.create')->with(compact('roles', 'breadcrumb'));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required | email | unique:users',
            'password'  => 'required',
            'password2'  => 'required | same:password',
            'role_id' => 'required',
        ]);

        $user = new User();
        $user->name = $this->getName($request);
        $user->email = $request->email;
        $user->role_id = $request->role_id;
        $user->password = Hash::make($request->password);
        $user->firstname = $request->firstname ?? '';
        $user->lastname = $request->lastname ?? '';
        $user->smallname = $request->smallname ?? '';

        $user->anrede_id = $request->anrede_id ?? '1';
        $user->title = $request->title ?? '';
        $user->private_email = $request->private_email ?? '';
        $user->street = $request->street ?? '';
        $user->postcode = $request->postcode ?? '';
        $user->city = $request->city ?? '';
        $user->country = $request->country ?? '';
        $user->signature_rule_id = $request->signature_rule_id ?? '1';
        $user->ustid = $request->ustid ?? '';
        $user->phone = $request->phone ?? '';
        $user->phone2 = $request->phone2 ?? '';
        $user->mobile = $request->mobile ?? '';
        $user->fax = $request->fax ?? '';
        $user->skype = $request->skype ?? '';
        $user->hourly_rate = $request->hourly_rate ?? '';
        $user->birthday = $request->birthday ?? '';
        $user->comment = $request->comment ?? '';

        //Image
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $pathImage = $file->store('public/users'); // Specify the storage path
            //Neuer Bildpfad speichern
            $user->image = str_replace("public/", "storage/", $pathImage);
        }

        $user->save();

        if ($user) {
            return redirect()->route('user')->with([
                'message'    => Lang::get('userauth::user.MsgAddSuccess'),
                'alert-type' => 'success',
            ]);
        } else {
            return redirect()->back();
        }
    }

    //Gibt den Inhalt für das Name Feld zurück
    private function getName(Request $request){
        switch (config('userauth.name')) {
            case '1':
                # Vorname Nachname
                return $request->firstname . ' ' . $request->lastname;
            case '2':
                # Nachname, Vorname
                return $request->lastname . ', ' . $request->firstname;
            case '3':
                # Nachname
                return $request->lastname;
            case '4':
                # Vorname
                return $request->firstname;
            default:
                # 0 Manuell
                return $request->name;
        }
    }


    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);
        $roles = Role::all();

        $breadcrumb = new Breadcrumb();
        $breadcrumb->add( trans('userauth::user.header_list'), route('user'));
        $breadcrumb->add( trans('userauth::user.header_edit'));

        return view('userauth::user.edit')->with(compact('roles', 'user', 'breadcrumb'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $editPW = false;

        //Wenn ein neues Passwort gesetzt wurde
        if ($request->password) {
            $request->validate([
                'email' => 'required | email',
                'password'  => 'required',
                'password2'  => 'required | same:password',
                'role_id' => 'required',
            ]);
            $editPW = true;
        }else{
        //Ohne Änderung des Passwortes
            $request->validate([
                'email' => 'required | email',
                'role_id' => 'required',
            ]);
        }

        //Benutzer laden
        $user = User::find($id);

        //Prüfen ob sich die E-Mail Adresse geändert hat
        if($user->email != $request->email){
            $request->validate([
                'email' => 'required | email | unique:users',
                'role_id' => 'required',
            ]);
        }

        //Neue Daten speichern
        $user->name = $this->getName($request);
        $user->email = $request->email;
        $user->role_id = $request->role_id;
        $user->firstname = $request->firstname ?? '';
        $user->lastname = $request->lastname ?? '';
        $user->smallname = $request->smallname ?? '';

        $user->anrede_id = $request->anrede_id ?? '1';
        $user->title = $request->title ?? '';
        $user->private_email = $request->private_email ?? '';
        $user->street = $request->street ?? '';
        $user->postcode = $request->postcode ?? '';
        $user->city = $request->city ?? '';
        $user->country = $request->country ?? '';
        $user->signature_rule_id = $request->signature_rule_id ?? '1';
        $user->ustid = $request->ustid ?? '';
        $user->phone = $request->phone ?? '';
        $user->phone2 = $request->phone2 ?? '';
        $user->mobile = $request->mobile ?? '';
        $user->fax = $request->fax ?? '';
        $user->skype = $request->skype ?? '';
        $user->hourly_rate = $request->hourly_rate ?? '';
        $user->birthday = $request->birthday ?? '';
        $user->comment = $request->comment ?? '';

        //Image
        if ($request->hasFile('image')) {
            $pfadAlt = str_replace("storage/", "", $user->image);

            $file = $request->file('image');
            $pathImage = $file->store('public/users'); // Specify the storage path
            //Altes Bild Löschen
            if($pfadAlt !== ''){
                $pfadAlt = storage_path('app/public/' . $pfadAlt);
                if (file_exists($pfadAlt)) {
                    unlink($pfadAlt);
                }
            }
            //Neuer Bildpfad speichern
            $user->image = str_replace("public/", "storage/", $pathImage);
        }


        //Falls das Passwort geändert werden soll
        if ($editPW) {
            $user->password = Hash::make($request->password);
        }

        $erg = $user->update();

        if ($erg) {
            return redirect()->route('user')->with([
                'message'    => Lang::get('userauth::user.MsgEditSuccess'),
                'alert-type' => 'success',
            ]);
        } else {
            return redirect()->back();
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function delete($id)
    {
        $user = User::find($id);
        $user->delete();
        return redirect()->route('user')->with([
            'message'    => Lang::get('userauth::user.MsgDeleteSuccess'),
            'alert-type' => 'success',
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     * @return Response
     */
    public function usericon_edit()
    {
        $breadcrumb = new Breadcrumb();
        $breadcrumb->add( trans('userauth::user.header_list'), route('user'));
        $breadcrumb->add( trans('userauth::user.header_edit'));

        return view('userauth::user.icon')->with(compact('breadcrumb'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function usericon_update(Request $request)
    {
        $userID = Auth::user()->id;

        //Benutzer laden
        $user = User::find($userID);

        //Image
        if ($request->hasFile('image')) {
            $pfadAlt = str_replace("storage/", "", $user->image);

            $file = $request->file('image');
            $pathImage = $file->store('public/users'); // Specify the storage path
            //Altes Bild Löschen
            if($pfadAlt !== ''){
                $pfadAlt = storage_path('app/public/' . $pfadAlt);
                if (file_exists($pfadAlt)) {
                    unlink($pfadAlt);
                }
            }
            //Neuer Bildpfad speichern
            $user->image = str_replace("public/", "storage/", $pathImage);
        }

        $user->update();
        return redirect()->back();
    }
}
