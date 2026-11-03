<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsKonten;
use ITHilbert\UserAuth\Domain\Registrierung\BestaetigungsHash;
use ITHilbert\UserAuth\Domain\Registrierung\Events\EmailBestaetigt;

/**
 * Bestätigt die E-Mail aus dem (bereits auf Signatur und Ablauf geprüften) Link. Der Link gilt nur für ein
 * nicht gelöschtes Konto, dessen E-Mail noch zum Hash passt. Ein zweiter Aufruf ändert nichts und gilt als Erfolg.
 */
final class EmailBestaetigen
{
    public function __construct(
        private readonly RegistrierungsKonten $konten,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return bool false, wenn der Link zu keinem Konto (mehr) passt
     */
    public function handle(string $kontoId, string $hash): bool
    {
        $konto = $this->konten->aktivesKonto($kontoId);

        if ($konto === null || ! BestaetigungsHash::passt($konto->email, $hash)) {
            return false;
        }

        if (! $konto->emailBestaetigt && $this->konten->alsBestaetigtMarkieren($konto->id, CarbonImmutable::now())) {
            $this->events->dispatch(new EmailBestaetigt($konto->id, $konto->email));
        }

        return true;
    }
}
