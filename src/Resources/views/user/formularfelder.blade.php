{{-- Felder des Benutzerformulars (Anlegen und Bearbeiten). $user: der Benutzer beim Bearbeiten, null beim Anlegen. --}}
@use('ITHilbert\UserAuth\App\Classes\Anrede')
@use('ITHilbert\UserAuth\App\Classes\SignatureRule')
@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')

{{-- Anrede --}}
@if (config('userauth.user.anrede'))
    <div class="userauth-zeile">
        <label for="anrede_id" class="userauth-zeile__label">@lang('userauth::user.anrede')</label>
        <div class="userauth-zeile__feld">
            <x-kit::combobox name="anrede_id" id="anrede_id" :options="Anrede::comboboxOptionen()" :value="AlteEingabe::text('anrede_id', $user?->anrede_id ?? 1)" />
        </div>
    </div>
@endif

{{-- Titel --}}
@if (config('userauth.user.title'))
    <div class="userauth-zeile">
        <label for="title" class="userauth-zeile__label">@lang('userauth::user.title')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="title" :value="AlteEingabe::text('title', $user?->title)" />
        </div>
    </div>
@endif

{{-- Vorname und Nachname (beide am Schlüssel firstname) --}}
@if (config('userauth.user.firstname'))
    <div class="userauth-zeile">
        <label for="firstname" class="userauth-zeile__label">@lang('userauth::user.firstname') *</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="firstname" :value="AlteEingabe::text('firstname', $user?->firstname)" required />
        </div>
    </div>

    <div class="userauth-zeile">
        <label for="lastname" class="userauth-zeile__label">@lang('userauth::user.lastname') *</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="lastname" :value="AlteEingabe::text('lastname', $user?->lastname)" required />
        </div>
    </div>
@endif

{{-- Name --}}
@if (config('userauth.user.name'))
    <div class="userauth-zeile">
        <label for="name" class="userauth-zeile__label">@lang('userauth::user.name') *</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="name" :value="AlteEingabe::text('name', $user?->name)" required />
        </div>
    </div>
@endif

{{-- Kurzname --}}
@if (config('userauth.user.smallname'))
    <div class="userauth-zeile">
        <label for="smallname" class="userauth-zeile__label">@lang('userauth::user.smallname') *</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="smallname" :value="AlteEingabe::text('smallname', $user?->smallname)" required />
        </div>
    </div>
@endif

{{-- Benutzername (nur Modi benutzername/beides) --}}
@if (app(\ITHilbert\UserAuth\Application\Auth\AnmeldeEinstellungen::class)->modus()->brauchtBenutzername())
    <div class="userauth-zeile">
        <label for="benutzername" class="userauth-zeile__label">@lang('userauth::user.benutzername') *</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="benutzername" :value="AlteEingabe::text('benutzername', $user?->benutzername)" required />
        </div>
    </div>
@endif

{{-- E-Mail --}}
@if (config('userauth.user.email'))
    <div class="userauth-zeile">
        <label for="email" class="userauth-zeile__label">@lang('userauth::user.email') *</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-email name="email" :value="AlteEingabe::text('email', $user?->email)" required />
        </div>
    </div>
@endif

{{-- Private E-Mail --}}
@if (config('userauth.user.private_email'))
    <div class="userauth-zeile">
        <label for="private_email" class="userauth-zeile__label">@lang('userauth::user.private_email')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-email name="private_email" :value="AlteEingabe::text('private_email', $user?->private_email)" />
        </div>
    </div>
@endif

{{-- Passwort (Pflicht nur beim Anlegen) --}}
<div class="userauth-zeile">
    <label for="password" class="userauth-zeile__label">@lang('userauth::user.password'){{ $user === null ? ' *' : '' }}</label>
    <div class="userauth-zeile__feld">
        <x-kit::input-password name="password" value="" autocomplete="new-password" />
        @if (trim((string) config('userauth.passwort_generator_url')) !== '')
            <div>
                <x-kit::button variant="secondary" data-passwort-erzeugen="{{ trim((string) config('userauth.passwort_generator_url')) }}" data-fehler="{{ __('userauth::user.password_generate_failed') }}">@lang('userauth::user.password_generate')</x-kit::button>
                <x-kit::button variant="secondary" data-passwort-kopieren data-kopiert="{{ __('userauth::user.password_copied') }}">@lang('userauth::user.password_copy')</x-kit::button>
                <span data-passwort-hinweis role="status"></span>
            </div>
        @endif
    </div>
</div>

<div class="userauth-zeile">
    <label for="password2" class="userauth-zeile__label">@lang('userauth::user.password2'){{ $user === null ? ' *' : '' }}</label>
    <div class="userauth-zeile__feld">
        <x-kit::input-password name="password2" value="" autocomplete="new-password" />
    </div>
</div>

{{-- Rolle --}}
@if (config('userauth.user.role'))
    <div class="userauth-zeile">
        <label for="role_id" class="userauth-zeile__label">@lang('userauth::user.role')</label>
        <div class="userauth-zeile__feld">
            <x-kit::combobox name="role_id" id="role_id" :options="$roles" :value="AlteEingabe::text('role_id', $user?->role_id ?? 3)" required />
        </div>
    </div>
@endif

{{-- Straße --}}
@if (config('userauth.user.street'))
    <div class="userauth-zeile">
        <label for="street" class="userauth-zeile__label">@lang('userauth::user.street')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="street" :value="AlteEingabe::text('street', $user?->street)" />
        </div>
    </div>
@endif

{{-- PLZ --}}
@if (config('userauth.user.postcode'))
    <div class="userauth-zeile">
        <label for="postcode" class="userauth-zeile__label">@lang('userauth::user.postcode')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="postcode" :value="AlteEingabe::text('postcode', $user?->postcode)" />
        </div>
    </div>
@endif

{{-- Ort --}}
@if (config('userauth.user.city'))
    <div class="userauth-zeile">
        <label for="city" class="userauth-zeile__label">@lang('userauth::user.city')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="city" :value="AlteEingabe::text('city', $user?->city)" />
        </div>
    </div>
@endif

{{-- Land --}}
@if (config('userauth.user.country'))
    <div class="userauth-zeile">
        <label for="country" class="userauth-zeile__label">@lang('userauth::user.country')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="country" :value="AlteEingabe::text('country', $user?->country)" />
        </div>
    </div>
@endif

{{-- Zeichnungsregel --}}
@if (config('userauth.user.signature_rule'))
    <div class="userauth-zeile">
        <label for="signature_rule_id" class="userauth-zeile__label">@lang('userauth::user.signature_rule')</label>
        <div class="userauth-zeile__feld">
            <x-kit::combobox name="signature_rule_id" id="signature_rule_id" :options="SignatureRule::comboboxOptionen()" :value="AlteEingabe::text('signature_rule_id', $user?->signature_rule_id ?? 1)" />
        </div>
    </div>
@endif

{{-- USt-IdNr. --}}
@if (config('userauth.user.ustid'))
    <div class="userauth-zeile">
        <label for="ustid" class="userauth-zeile__label">@lang('userauth::user.ustid')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="ustid" :value="AlteEingabe::text('ustid', $user?->ustid)" />
        </div>
    </div>
@endif

{{-- Telefon --}}
@if (config('userauth.user.phone'))
    <div class="userauth-zeile">
        <label for="phone" class="userauth-zeile__label">@lang('userauth::user.phone')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="phone" :value="AlteEingabe::text('phone', $user?->phone)" />
        </div>
    </div>
@endif

{{-- Telefon 2 --}}
@if (config('userauth.user.phone2'))
    <div class="userauth-zeile">
        <label for="phone2" class="userauth-zeile__label">@lang('userauth::user.phone2')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="phone2" :value="AlteEingabe::text('phone2', $user?->phone2)" />
        </div>
    </div>
@endif

{{-- Mobil --}}
@if (config('userauth.user.mobile'))
    <div class="userauth-zeile">
        <label for="mobile" class="userauth-zeile__label">@lang('userauth::user.mobile')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="mobile" :value="AlteEingabe::text('mobile', $user?->mobile)" />
        </div>
    </div>
@endif

{{-- Fax --}}
@if (config('userauth.user.fax'))
    <div class="userauth-zeile">
        <label for="fax" class="userauth-zeile__label">@lang('userauth::user.fax')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="fax" :value="AlteEingabe::text('fax', $user?->fax)" />
        </div>
    </div>
@endif

{{-- Skype --}}
@if (config('userauth.user.skype'))
    <div class="userauth-zeile">
        <label for="skype" class="userauth-zeile__label">@lang('userauth::user.skype')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-text name="skype" :value="AlteEingabe::text('skype', $user?->skype)" />
        </div>
    </div>
@endif

{{-- Stundensatz --}}
@if (config('userauth.user.hourly_rate'))
    <div class="userauth-zeile">
        <label for="hourly_rate" class="userauth-zeile__label">@lang('userauth::user.hourly_rate')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-euro name="hourly_rate" step="0.01" :value="AlteEingabe::text('hourly_rate', $user?->hourly_rate)" />
        </div>
    </div>
@endif

{{-- Geburtstag --}}
@if (config('userauth.user.birthday'))
    <div class="userauth-zeile">
        <label for="birthday" class="userauth-zeile__label">@lang('userauth::user.birthday')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-date name="birthday" :value="AlteEingabe::text('birthday', $user?->birthday)" />
        </div>
    </div>
@endif

{{-- Kommentar --}}
@if (config('userauth.user.comment'))
    <div class="userauth-zeile">
        <label for="comment" class="userauth-zeile__label">@lang('userauth::user.comment')</label>
        <div class="userauth-zeile__feld">
            <x-kit::text-area name="comment" :value="AlteEingabe::text('comment', $user?->comment)" />
        </div>
    </div>
@endif

{{-- Bild --}}
@if (config('userauth.user.image'))
    <div class="userauth-zeile">
        <label for="image" class="userauth-zeile__label">@lang('userauth::user.image')</label>
        <div class="userauth-zeile__feld">
            <x-kit::input-file-img name="image" />
        </div>
    </div>
@endif
