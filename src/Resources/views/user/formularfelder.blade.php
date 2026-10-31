{{-- Felder des Benutzerformulars (Anlegen und Bearbeiten). $user: der Benutzer beim Bearbeiten, null beim Anlegen. --}}
@use('ITHilbert\UserAuth\App\Classes\Anrede')
@use('ITHilbert\UserAuth\App\Classes\SignatureRule')
@use('ITHilbert\UserAuth\Http\Formular\AlteEingabe')

{{-- Anrede --}}
@if (config('userauth.user.anrede'))
    <div class="form-group row mb-2">
        <label for="anrede_id" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.anrede')</label>
        <div class="col-md-6">
            <x-kit::combobox name="anrede_id" id="anrede_id" :options="Anrede::comboboxOptionen()" :value="AlteEingabe::text('anrede_id', $user?->anrede_id ?? 1)" />
        </div>
    </div>
@endif

{{-- Titel --}}
@if (config('userauth.user.title'))
    <div class="form-group row mb-2">
        <label for="title" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.title')</label>
        <div class="col-md-6">
            <x-kit::input-text name="title" :value="AlteEingabe::text('title', $user?->title)" />
        </div>
    </div>
@endif

{{-- Vorname und Nachname (beide am Schlüssel firstname) --}}
@if (config('userauth.user.firstname'))
    <div class="form-group row mb-2">
        <label for="firstname" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.firstname') *</label>
        <div class="col-md-6">
            <x-kit::input-text name="firstname" :value="AlteEingabe::text('firstname', $user?->firstname)" required />
        </div>
    </div>

    <div class="form-group row mb-2">
        <label for="lastname" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.lastname') *</label>
        <div class="col-md-6">
            <x-kit::input-text name="lastname" :value="AlteEingabe::text('lastname', $user?->lastname)" required />
        </div>
    </div>
@endif

{{-- Name --}}
@if (config('userauth.user.name'))
    <div class="form-group row mb-2">
        <label for="name" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.name') *</label>
        <div class="col-md-6">
            <x-kit::input-text name="name" :value="AlteEingabe::text('name', $user?->name)" required />
        </div>
    </div>
@endif

{{-- Kurzname --}}
@if (config('userauth.user.smallname'))
    <div class="form-group row mb-2">
        <label for="smallname" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.smallname') *</label>
        <div class="col-md-6">
            <x-kit::input-text name="smallname" :value="AlteEingabe::text('smallname', $user?->smallname)" required />
        </div>
    </div>
@endif

{{-- E-Mail --}}
@if (config('userauth.user.email'))
    <div class="form-group row mb-2">
        <label for="email" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.email') *</label>
        <div class="col-md-6">
            <x-kit::input-email name="email" :value="AlteEingabe::text('email', $user?->email)" required />
        </div>
    </div>
@endif

{{-- Private E-Mail --}}
@if (config('userauth.user.private_email'))
    <div class="form-group row mb-2">
        <label for="private_email" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.private_email')</label>
        <div class="col-md-6">
            <x-kit::input-email name="private_email" :value="AlteEingabe::text('private_email', $user?->private_email)" />
        </div>
    </div>
@endif

{{-- Passwort (Pflicht nur beim Anlegen) --}}
<div class="form-group row mb-2">
    <label for="password" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.password'){{ $user === null ? ' *' : '' }}</label>
    <div class="col-md-6">
        <x-kit::input-password name="password" value="" autocomplete="new-password" />
        @error('password')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="form-group row mb-2">
    <label for="password2" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.password2'){{ $user === null ? ' *' : '' }}</label>
    <div class="col-md-6">
        <x-kit::input-password name="password2" value="" autocomplete="new-password" />
    </div>
</div>

{{-- Rolle --}}
@if (config('userauth.user.role'))
    <div class="form-group row mb-2">
        <label for="role_id" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.role')</label>
        <div class="col-md-6">
            <x-kit::combobox name="role_id" id="role_id" :options="$roles" :value="AlteEingabe::text('role_id', $user?->role_id ?? 3)" required />
        </div>
    </div>
@endif

{{-- Straße --}}
@if (config('userauth.user.street'))
    <div class="form-group row mb-2">
        <label for="street" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.street')</label>
        <div class="col-md-6">
            <x-kit::input-text name="street" :value="AlteEingabe::text('street', $user?->street)" />
        </div>
    </div>
@endif

{{-- PLZ --}}
@if (config('userauth.user.postcode'))
    <div class="form-group row mb-2">
        <label for="postcode" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.postcode')</label>
        <div class="col-md-6">
            <x-kit::input-text name="postcode" :value="AlteEingabe::text('postcode', $user?->postcode)" />
        </div>
    </div>
@endif

{{-- Ort --}}
@if (config('userauth.user.city'))
    <div class="form-group row mb-2">
        <label for="city" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.city')</label>
        <div class="col-md-6">
            <x-kit::input-text name="city" :value="AlteEingabe::text('city', $user?->city)" />
        </div>
    </div>
@endif

{{-- Land --}}
@if (config('userauth.user.country'))
    <div class="form-group row mb-2">
        <label for="country" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.country')</label>
        <div class="col-md-6">
            <x-kit::input-text name="country" :value="AlteEingabe::text('country', $user?->country)" />
        </div>
    </div>
@endif

{{-- Zeichnungsregel --}}
@if (config('userauth.user.signature_rule'))
    <div class="form-group row mb-2">
        <label for="signature_rule_id" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.signature_rule')</label>
        <div class="col-md-6">
            <x-kit::combobox name="signature_rule_id" id="signature_rule_id" :options="SignatureRule::comboboxOptionen()" :value="AlteEingabe::text('signature_rule_id', $user?->signature_rule_id ?? 1)" />
        </div>
    </div>
@endif

{{-- USt-IdNr. --}}
@if (config('userauth.user.ustid'))
    <div class="form-group row mb-2">
        <label for="ustid" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.ustid')</label>
        <div class="col-md-6">
            <x-kit::input-text name="ustid" :value="AlteEingabe::text('ustid', $user?->ustid)" />
        </div>
    </div>
@endif

{{-- Telefon --}}
@if (config('userauth.user.phone'))
    <div class="form-group row mb-2">
        <label for="phone" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.phone')</label>
        <div class="col-md-6">
            <x-kit::input-text name="phone" :value="AlteEingabe::text('phone', $user?->phone)" />
        </div>
    </div>
@endif

{{-- Telefon 2 --}}
@if (config('userauth.user.phone2'))
    <div class="form-group row mb-2">
        <label for="phone2" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.phone2')</label>
        <div class="col-md-6">
            <x-kit::input-text name="phone2" :value="AlteEingabe::text('phone2', $user?->phone2)" />
        </div>
    </div>
@endif

{{-- Mobil --}}
@if (config('userauth.user.mobile'))
    <div class="form-group row mb-2">
        <label for="mobile" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.mobile')</label>
        <div class="col-md-6">
            <x-kit::input-text name="mobile" :value="AlteEingabe::text('mobile', $user?->mobile)" />
        </div>
    </div>
@endif

{{-- Fax --}}
@if (config('userauth.user.fax'))
    <div class="form-group row mb-2">
        <label for="fax" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.fax')</label>
        <div class="col-md-6">
            <x-kit::input-text name="fax" :value="AlteEingabe::text('fax', $user?->fax)" />
        </div>
    </div>
@endif

{{-- Skype --}}
@if (config('userauth.user.skype'))
    <div class="form-group row mb-2">
        <label for="skype" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.skype')</label>
        <div class="col-md-6">
            <x-kit::input-text name="skype" :value="AlteEingabe::text('skype', $user?->skype)" />
        </div>
    </div>
@endif

{{-- Stundensatz --}}
@if (config('userauth.user.hourly_rate'))
    <div class="form-group row mb-2">
        <label for="hourly_rate" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.hourly_rate')</label>
        <div class="col-md-6">
            <x-kit::input-euro name="hourly_rate" step="0.01" :value="AlteEingabe::text('hourly_rate', $user?->hourly_rate)" />
        </div>
    </div>
@endif

{{-- Geburtstag --}}
@if (config('userauth.user.birthday'))
    <div class="form-group row mb-2">
        <label for="birthday" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.birthday')</label>
        <div class="col-md-6">
            <x-kit::input-date name="birthday" :value="AlteEingabe::text('birthday', $user?->birthday)" />
        </div>
    </div>
@endif

{{-- Kommentar --}}
@if (config('userauth.user.comment'))
    <div class="form-group row mb-2">
        <label for="comment" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.comment')</label>
        <div class="col-md-6">
            <x-kit::text-area name="comment" :value="AlteEingabe::text('comment', $user?->comment)" />
        </div>
    </div>
@endif

{{-- Bild --}}
@if (config('userauth.user.image'))
    <div class="form-group row mb-2">
        <label for="image" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.image')</label>
        <div class="col-md-6">
            <x-kit::input-file-img name="image" />
        </div>
    </div>
@endif
