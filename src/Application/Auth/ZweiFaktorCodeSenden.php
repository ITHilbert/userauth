<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use ITHilbert\UserAuth\Application\Auth\Contracts\OffeneAnmeldungen;
use ITHilbert\UserAuth\Application\Auth\Contracts\ZweiFaktorCodeHasher;
use ITHilbert\UserAuth\Application\Auth\Contracts\ZweiFaktorCodeVersand;
use ITHilbert\UserAuth\Application\Auth\Exceptions\ZweiFaktorCodeVersandException;
use ITHilbert\UserAuth\Domain\Auth\OffeneAnmeldung;
use ITHilbert\UserAuth\Domain\Auth\ZweiFaktorCode;
use Psr\Log\LoggerInterface;

/**
 * Einzige Stelle, die einen Zwei-Faktor-Code erzeugt und verschickt – beim Anmelden wie beim erneuten Senden.
 * Der neue Code ersetzt den bisherigen; abgelegt wird nur sein Hash (AK18, AK19).
 * Scheitert der Versand, wird der Fehler geloggt (ohne Code) und die offene Anmeldung verworfen: Der Nutzer hat keinen
 * Code, die Code-Eingabe wäre eine Sackgasse; er meldet sich später neu an (AK14b).
 */
final class ZweiFaktorCodeSenden
{
    public function __construct(
        private readonly OffeneAnmeldungen $anmeldungen,
        private readonly ZweiFaktorCodeHasher $hasher,
        private readonly ZweiFaktorCodeVersand $versand,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return bool true, wenn der Code versendet wurde
     */
    public function handle(OffeneAnmeldung $anmeldung, Authenticatable $nutzer): bool
    {
        $code = ZweiFaktorCode::erzeugen();

        $this->anmeldungen->speichern(
            $anmeldung->mitCode($this->hasher->hash($code->wert, $anmeldung->kennung), CarbonImmutable::now()),
        );

        try {
            $this->versand->senden($nutzer, $code->wert, OffeneAnmeldung::CODE_GUELTIG_MINUTEN);
        } catch (ZweiFaktorCodeVersandException $e) {
            $this->logger->error('Two-factor code could not be sent.', [
                'user_id' => $nutzer->getAuthIdentifier(),
                'exception' => $e,
            ]);
            $this->anmeldungen->verwerfen();

            return false;
        }

        return true;
    }
}
