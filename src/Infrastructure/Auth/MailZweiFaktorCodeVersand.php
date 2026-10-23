<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Mail\Mailer;
use ITHilbert\UserAuth\Application\Auth\Contracts\ZweiFaktorCodeVersand;
use ITHilbert\UserAuth\Application\Auth\Exceptions\ZweiFaktorCodeVersandException;
use ITHilbert\UserAuth\Mail\TwoFactorCode;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Verschickt den Code synchron im Request – weder Queue (ohne Worker käme er nie an) noch nach der Antwort:
 * Der Nutzer wartet aktiv auf den Code, ein Versandfehler soll sofort sichtbar sein. Die Antwortzeit verrät
 * nichts Neues, denn verschickt wird erst nach bestandenem ersten Faktor (anders als beim Reset, Entscheidung 0008).
 */
final class MailZweiFaktorCodeVersand implements ZweiFaktorCodeVersand
{
    public function __construct(private readonly Mailer $mailer) {}

    /**
     * @throws ZweiFaktorCodeVersandException
     */
    public function senden(Authenticatable $nutzer, #[\SensitiveParameter] string $code, int $gueltigMinuten): void
    {
        $adresse = data_get($nutzer, 'email');

        if (! is_string($adresse) || $adresse === '') {
            throw new ZweiFaktorCodeVersandException('User '.$nutzer->getAuthIdentifier().' has no e-mail address for the two-factor code.');
        }

        try {
            $this->mailer->to($adresse)->send(new TwoFactorCode(code: $code, gueltigMinuten: $gueltigMinuten));
        } catch (TransportExceptionInterface $e) {
            throw new ZweiFaktorCodeVersandException('Two-factor code could not be sent: '.$e->getMessage(), previous: $e);
        }
    }
}
