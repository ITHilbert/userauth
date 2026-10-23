<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use ITHilbert\LaravelKit\Helpers\Breadcrumb;
use ITHilbert\LaravelKit\Helpers\HButton;
use ITHilbert\UserAuth\Entities\Role;
use ITHilbert\UserAuth\Http\Requests\NutzerSpeichernRequest;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;
use Yajra\DataTables\Facades\DataTables;

final class UserController extends Controller
{
    public function __construct(private readonly UserModel $userModel) {}

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $user = Auth::user();
            $darfBearbeiten = $user->hasPermission('user_edit');
            $darfLoeschen = $user->hasPermission('user_delete');

            return DataTables::of($this->nutzerlistenZeilen())
                ->addColumn('action', function (array $row) use ($darfBearbeiten, $darfLoeschen) {
                    $ausgabe = '<div style="white-space: nowrap;">';
                    if ($darfBearbeiten) {
                        $ausgabe .= HButton::edit(route('user.edit', $row['id']), '');
                    }
                    if ($darfLoeschen) {
                        $ausgabe .= HButton::delete($row['id'], '');
                    }
                    $ausgabe .= '</div>';

                    return $ausgabe;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::user.header_list'));

        return view('userauth::user.index')->with(compact('breadcrumb'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $roles = Role::getComboBoxData();

        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::user.header_list'), route('user'));
        $breadcrumb->add(trans('userauth::user.header_create'));

        return view('userauth::user.create')->with(compact('roles', 'breadcrumb'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(NutzerSpeichernRequest $request)
    {
        $daten = $request->validated();

        $user = $this->userModel->newInstance();
        $user->name = $this->getName($daten);
        $user->email = $daten['email'];
        $user->role_id = $daten['role_id'];
        $user->password = Hash::make($daten['password']);
        $this->profilfelderSetzen($user, $daten);

        // Image
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $pathImage = $file->store('public/users'); // Specify the storage path
            // Neuer Bildpfad speichern
            $user->image = str_replace('public/', 'storage/', $pathImage);
        }

        $user->save();

        return redirect()->route('user')->with([
            'message' => Lang::get('userauth::user.MsgAddSuccess'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Gibt den Inhalt für das Name Feld zurück.
     *
     * @param  array<string, mixed>  $daten
     */
    private function getName(array $daten): ?string
    {
        switch (config('userauth.name')) {
            case '1':
                // Vorname Nachname
                return ($daten['firstname'] ?? '').' '.($daten['lastname'] ?? '');
            case '2':
                // Nachname, Vorname
                return ($daten['lastname'] ?? '').', '.($daten['firstname'] ?? '');
            case '3':
                // Nachname
                return $daten['lastname'] ?? null;
            case '4':
                // Vorname
                return $daten['firstname'] ?? null;
            default:
                // 0 Manuell
                return $daten['name'] ?? null;
        }
    }

    /**
     * @param  array<string, mixed>  $daten
     */
    private function profilfelderSetzen(Model $user, array $daten): void
    {
        foreach (['firstname', 'lastname', 'smallname', 'title', 'private_email', 'street', 'postcode', 'city', 'country', 'ustid', 'phone', 'phone2', 'mobile', 'fax', 'skype', 'hourly_rate', 'birthday', 'comment'] as $feld) {
            $user->{$feld} = $daten[$feld] ?? '';
        }
        $user->anrede_id = $daten['anrede_id'] ?? '1';
        $user->signature_rule_id = $daten['signature_rule_id'] ?? '1';
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $user = $this->userModel->query()->findOrFail($id);
        $roles = Role::all();

        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::user.header_list'), route('user'));
        $breadcrumb->add(trans('userauth::user.header_edit'));

        return view('userauth::user.edit')->with(compact('roles', 'user', 'breadcrumb'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(NutzerSpeichernRequest $request, $id)
    {
        $daten = $request->validated();
        $editPW = (bool) $request->input('password');

        // Benutzer laden
        $user = $this->userModel->query()->findOrFail($id);

        // Neue Daten speichern
        $user->name = $this->getName($daten);
        $user->email = $daten['email'];
        $user->role_id = $daten['role_id'];
        $this->profilfelderSetzen($user, $daten);

        // Image
        if ($request->hasFile('image')) {
            $pfadAlt = str_replace('storage/', '', $user->image);

            $file = $request->file('image');
            $pathImage = $file->store('public/users'); // Specify the storage path
            // Altes Bild Löschen
            if ($pfadAlt !== '') {
                $pfadAlt = storage_path('app/public/'.$pfadAlt);
                if (file_exists($pfadAlt)) {
                    unlink($pfadAlt);
                }
            }
            // Neuer Bildpfad speichern
            $user->image = str_replace('public/', 'storage/', $pathImage);
        }

        // Falls das Passwort geändert werden soll
        if ($editPW) {
            $user->password = Hash::make($daten['password']);
        }

        $erg = $user->update();

        if ($erg) {
            return redirect()->route('user')->with([
                'message' => Lang::get('userauth::user.MsgEditSuccess'),
                'alert-type' => 'success',
            ]);
        } else {
            return redirect()->back();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete($id)
    {
        $user = $this->userModel->query()->findOrFail($id);
        $user->delete();

        return redirect()->route('user')->with([
            'message' => Lang::get('userauth::user.MsgDeleteSuccess'),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function usericon_edit()
    {
        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::user.header_list'), route('user'));
        $breadcrumb->add(trans('userauth::user.header_edit'));

        return view('userauth::user.icon')->with(compact('breadcrumb'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function usericon_update(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);

        $userID = Auth::user()->id;

        // Benutzer laden
        $user = $this->userModel->query()->findOrFail($userID);

        // Image
        if ($request->hasFile('image')) {
            $pfadAlt = str_replace('storage/', '', $user->image);

            $file = $request->file('image');
            $pathImage = $file->store('public/users'); // Specify the storage path
            // Altes Bild Löschen
            if ($pfadAlt !== '') {
                $pfadAlt = storage_path('app/public/'.$pfadAlt);
                if (file_exists($pfadAlt)) {
                    unlink($pfadAlt);
                }
            }
            // Neuer Bildpfad speichern
            $user->image = str_replace('public/', 'storage/', $pathImage);
        }

        $user->update();

        return redirect()->back();
    }

    /**
     * Nur die angezeigten Felder je Zeile – nie das ganze Model (Passwort-Hash, Tokens, 2FA-Daten).
     *
     * @return Collection<int, array{id: int, name: string, email: string, RoleName: string}>
     */
    private function nutzerlistenZeilen(): Collection
    {
        $spalten = ['id', 'name', 'email', 'role_id', 'created_at'];
        if (config('userauth.teams.enabled', false)) {
            $spalten[] = 'current_team_id';
        }

        return $this->userModel->query()
            ->select($spalten)
            ->with('role')
            ->latest()
            ->get()
            ->map(fn (Model $nutzer): array => [
                'id' => (int) $nutzer->getKey(),
                'name' => (string) $nutzer->getAttribute('name'),
                'email' => (string) $nutzer->getAttribute('email'),
                'RoleName' => (string) $nutzer->roleDisplayname(),
            ])
            ->values();
    }
}
