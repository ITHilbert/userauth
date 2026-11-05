<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use ITHilbert\LaravelKit\Helpers\Breadcrumb;
use ITHilbert\LaravelKit\Helpers\HButton;
use ITHilbert\UserAuth\Application\Benutzer\Contracts\Benutzerbilder;
use ITHilbert\UserAuth\Application\Benutzer\GeloeschteEmailAufloesen;
use ITHilbert\UserAuth\Application\Benutzer\GeloeschteEmailAufloesung;
use ITHilbert\UserAuth\Application\Passwort\PasswortSetzen;
use ITHilbert\UserAuth\Domain\Benutzer\Exceptions\EmailGehoertGeloeschtemBenutzerException;
use ITHilbert\UserAuth\Entities\Role;
use ITHilbert\UserAuth\Http\PasswortAblehnung;
use ITHilbert\UserAuth\Http\Requests\NutzerSpeichernRequest;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;
use Yajra\DataTables\Facades\DataTables;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserModel $userModel,
        private readonly Benutzerbilder $bilder,
    ) {}

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
     * Legt einen Benutzer an. Gehört die E-Mail einem gelöschten Benutzer, entscheidet die Option
     * `userauth.geloeschte_email` (sperren | reaktivieren | neu) – Freimachen, Felder und Passwort in einer Transaktion.
     *
     * @return Response
     */
    public function store(NutzerSpeichernRequest $request, PasswortSetzen $passwortSetzen, GeloeschteEmailAufloesen $geloeschteEmail)
    {
        $daten = $request->validated();
        $bildNeu = $request->hasFile('image') ? $this->bildAblegen($request->file('image')) : null;

        $aufloesung = null;
        try {
            $aufloesung = PasswortAblehnung::alsValidierungsfehler(fn (): GeloeschteEmailAufloesung => $geloeschteEmail->handle(
                (string) $daten['email'],
                function (GeloeschteEmailAufloesung $aufloesung) use ($daten, $bildNeu, $passwortSetzen): GeloeschteEmailAufloesung {
                    $user = $this->anzulegenderNutzer($aufloesung);
                    $user->name = $this->getName($daten);
                    $user->email = $daten['email'];
                    $user->role_id = $daten['role_id'];
                    $this->profilfelderSetzen($user, $daten, true);
                    // Wie bei einer Neuanlage: ohne hochgeladenes Bild keins, auch nicht das des gelöschten Benutzers
                    // (dessen Datei löscht der UseCase nach dem Commit, Randfall 4).
                    $user->image = $bildNeu;

                    $user->save();
                    $passwortSetzen->handle($user, (string) $daten['password']);

                    return $aufloesung;
                },
            ));
        } catch (EmailGehoertGeloeschtemBenutzerException) {
            throw ValidationException::withMessages(['email' => trans('userauth::user.email_geloeschter_benutzer')]);
        } finally {
            $this->bildAbschliessen($aufloesung !== null, $bildNeu, null);
        }

        return redirect()->route('user')->with([
            'message' => Lang::get(match (true) {
                $aufloesung->istReaktiviert() => 'userauth::user.MsgReaktiviert',
                $aufloesung->entfernt => 'userauth::user.MsgEntferntUndAngelegt',
                default => 'userauth::user.MsgAddSuccess',
            }),
            'alert-type' => 'success',
        ]);
    }

    /**
     * Die wiederhergestellte Zeile (ihr Passwort-Hash bleibt bis PasswortSetzen, damit die Wiederverwendungs-Regel
     * ihn prüft) oder ein neues Model.
     */
    private function anzulegenderNutzer(GeloeschteEmailAufloesung $aufloesung): Model
    {
        if ($aufloesung->reaktivierteId !== null) {
            return $this->userModel->query()->findOrFail($aufloesung->reaktivierteId);
        }

        $user = $this->userModel->newInstance();
        // Platzhalter bis PasswortSetzen den Hash speichert (kein gültiger Hash).
        $user->password = '';

        return $user;
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
    private function profilfelderSetzen(Model $user, array $daten, bool $neuanlage): void
    {
        foreach (['firstname', 'lastname', 'smallname', 'title', 'private_email', 'street', 'postcode', 'city', 'country', 'ustid', 'phone', 'phone2', 'mobile', 'fax', 'skype', 'comment'] as $feld) {
            $user->{$feld} = $daten[$feld] ?? '';
        }
        // Spalten decimal NOT NULL bzw. date nullable: leer darf nicht als '' geschrieben werden.
        // Fehlt das Feld im Request, bleibt der Wert beim Bearbeiten unverändert (beim Anlegen – auch beim
        // Wiederherstellen eines gelöschten Benutzers – gilt der Vorgabewert).
        if (array_key_exists('hourly_rate', $daten) || $neuanlage) {
            $stundensatz = $daten['hourly_rate'] ?? null;
            $user->hourly_rate = ($stundensatz === null || $stundensatz === '') ? 0 : $stundensatz;
        }
        if (array_key_exists('birthday', $daten) || $neuanlage) {
            $geburtstag = $daten['birthday'] ?? null;
            $user->birthday = ($geburtstag === null || $geburtstag === '') ? null : $geburtstag;
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
        $roles = Role::getComboBoxData();

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
    public function update(NutzerSpeichernRequest $request, PasswortSetzen $passwortSetzen, $id)
    {
        $daten = $request->validated();
        $editPW = (bool) $request->input('password');

        // Benutzer laden
        $user = $this->userModel->query()->findOrFail($id);

        // Neue Daten speichern
        $user->name = $this->getName($daten);
        $user->email = $daten['email'];
        $user->role_id = $daten['role_id'];
        $this->profilfelderSetzen($user, $daten, false);

        // Image
        $bildAlt = $user->image;
        $bildNeu = $request->hasFile('image') ? $this->bildAblegen($request->file('image')) : null;
        if ($bildNeu !== null) {
            $user->image = $bildNeu;
        }

        // Felder und Passwort atomar: lehnt PasswortSetzen ab, wird nichts gespeichert.
        $erg = false;
        try {
            $erg = PasswortAblehnung::alsValidierungsfehler(fn (): bool => DB::transaction(function () use ($user, $passwortSetzen, $daten, $editPW): bool {
                if ($editPW) {
                    $passwortSetzen->handle($user, (string) $daten['password']);
                }

                return $user->save();
            }));
        } finally {
            $this->bildAbschliessen($erg, $bildNeu, $bildAlt);
        }

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
        $bildAlt = $user->image;
        $bildNeu = $request->hasFile('image') ? $this->bildAblegen($request->file('image')) : null;
        if ($bildNeu !== null) {
            $user->image = $bildNeu;
        }

        $erg = false;
        try {
            $erg = $user->update();
        } finally {
            $this->bildAbschliessen($erg, $bildNeu, $bildAlt);
        }

        return redirect()->back();
    }

    /**
     * Legt das hochgeladene Bild ab und liefert den Pfad für die Spalte `image`.
     */
    private function bildAblegen(?UploadedFile $file): ?string
    {
        return $file === null ? null : $this->bilder->ablegen($file);
    }

    /**
     * Nach dem Speichern: bei Erfolg das alte Bild löschen, sonst das neue wieder entfernen – jeweils nur, wenn
     * keine Zeile mehr darauf verweist (gleiche Regel wie für das Bild eines gelöschten Benutzers, Randfall 4).
     */
    private function bildAbschliessen(bool $gespeichert, ?string $bildNeu, ?string $bildAlt): void
    {
        if ($bildNeu === null) {
            return;
        }

        $this->bilder->entfernenWennUnbenutzt($gespeichert ? $bildAlt : $bildNeu);
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
