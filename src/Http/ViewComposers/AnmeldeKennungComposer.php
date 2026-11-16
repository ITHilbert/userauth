<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;
use ITHilbert\UserAuth\Application\Auth\AnmeldeEinstellungen;
use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;

/**
 * Feld der Kennung je `userauth.anmeldung_per`: Name, Typ, Autocomplete und Label.
 * `kennungLabel` ist im Modus `email` null – die Views behalten dort ihr bisheriges Label.
 */
final class AnmeldeKennungComposer
{
    public function __construct(private readonly AnmeldeEinstellungen $einstellungen) {}

    public function compose(View $view): void
    {
        $modus = $this->einstellungen->modus();

        $view->with([
            'kennungFeld' => $modus->feld(),
            'kennungTyp' => $modus === AnmeldeModus::Email ? 'email' : 'text',
            'kennungAutocomplete' => $modus === AnmeldeModus::Email ? 'email' : 'username',
            'kennungLabel' => match ($modus) {
                AnmeldeModus::Email => null,
                AnmeldeModus::Benutzername => __('userauth::login.kennung_benutzername'),
                AnmeldeModus::Beides => __('userauth::login.kennung_beides'),
            },
        ]);
    }
}
