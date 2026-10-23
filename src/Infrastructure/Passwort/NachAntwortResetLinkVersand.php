<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passwort;

use Illuminate\Contracts\Mail\Mailer;
use ITHilbert\UserAuth\Application\Passwort\Contracts\ResetLinkVersand;
use ITHilbert\UserAuth\Mail\PasswortZuruecksetzenLink;

use function Illuminate\Support\defer;

/**
 * Verschickt die Reset-Mail erst nach der HTTP-Antwort (`defer()`, Entscheidung 0008): kein Queue-Worker nötig,
 * gleiche Antwortzeit für bekannte und unbekannte Adressen. Ausgeführt von `InvokeDeferredCallbacks`
 * (an der Route `password.sendtocken` gesetzt); Fehler beim Versand meldet Laravel über `report()`.
 */
final class NachAntwortResetLinkVersand implements ResetLinkVersand
{
    public function __construct(private readonly Mailer $mailer) {}

    public function senden(string $adresse, string $resetUrl, int $gueltigMinuten): void
    {
        $mail = new PasswortZuruecksetzenLink(resetUrl: $resetUrl, gueltigMinuten: $gueltigMinuten);

        defer(function () use ($adresse, $mail): void {
            $this->mailer->to($adresse)->send($mail);
        });
    }
}
