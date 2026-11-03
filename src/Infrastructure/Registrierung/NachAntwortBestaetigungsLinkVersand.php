<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Registrierung;

use Illuminate\Contracts\Mail\Mailer;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\BestaetigungsLinkVersand;
use ITHilbert\UserAuth\Mail\RegistrierungBestaetigen;

use function Illuminate\Support\defer;

/**
 * Verschickt die Bestätigungsmail erst nach der HTTP-Antwort (`defer()`, Entscheidung 0008) – wie der
 * Reset-Link. Ausgeführt von `InvokeDeferredCallbacks` (an den Routen der Registrierung gesetzt);
 * Fehler beim Versand meldet Laravel über `report()`.
 */
final class NachAntwortBestaetigungsLinkVersand implements BestaetigungsLinkVersand
{
    public function __construct(private readonly Mailer $mailer) {}

    public function senden(string $adresse, string $bestaetigungsUrl, int $gueltigMinuten): void
    {
        $mail = new RegistrierungBestaetigen(bestaetigungsUrl: $bestaetigungsUrl, gueltigMinuten: $gueltigMinuten);

        defer(function () use ($adresse, $mail): void {
            $this->mailer->to($adresse)->send($mail);
        });
    }
}
