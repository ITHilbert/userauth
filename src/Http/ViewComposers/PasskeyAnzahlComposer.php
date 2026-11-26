<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use ITHilbert\UserAuth\Application\Passkey\PasskeysZaehlen;

/**
 * Bearbeiten-Seite eines Benutzers: Anzahl seiner Passkeys (`passkeyAnzahl`), null solange Passkeys aus sind (AK15).
 */
final class PasskeyAnzahlComposer
{
    public function __construct(private readonly PasskeysZaehlen $zaehlen) {}

    public function compose(View $view): void
    {
        $user = $view->getData()['user'] ?? null;

        $view->with('passkeyAnzahl', $user instanceof Model ? $this->zaehlen->handle($user->getKey()) : null);
    }
}
