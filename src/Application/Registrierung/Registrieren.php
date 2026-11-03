<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsKonten;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsRollen;
use ITHilbert\UserAuth\Application\Registrierung\Exceptions\RegistrierungFalschKonfiguriertException;
use ITHilbert\UserAuth\Domain\Registrierung\Events\KontoRegistriert;
use ITHilbert\UserAuth\Domain\Zugriff\Rolle;

/**
 * Selbstregistrierung: legt ein Konto mit „Bestätigung offen“ an und verschickt den Bestätigungslink.
 * Meldet nicht an. Ist die E-Mail schon vergeben (auch gelöscht, Schreibweise egal), passiert nichts –
 * ohne Rückmeldung, damit sich vorhandene Adressen nicht erfragen lassen.
 */
final class Registrieren
{
    public function __construct(
        private readonly RegistrierungsEinstellungen $einstellungen,
        private readonly RegistrierungsKonten $konten,
        private readonly RegistrierungsRollen $rollen,
        private readonly BestaetigungsLinkSenden $linkSenden,
        private readonly Hasher $hasher,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @throws RegistrierungFalschKonfiguriertException
     */
    public function handle(string $name, string $email, #[\SensitiveParameter] string $passwort): void
    {
        $rolleId = $this->rolleId();

        // Immer hashen: neue und vergebene Adressen brauchen gleich lange
        $passwortHash = $this->hasher->make($passwort);

        if ($this->konten->emailVergeben($email)) {
            return;
        }

        $konto = $this->konten->anlegen($name, $email, $passwortHash, $rolleId, CarbonImmutable::now());

        if ($konto === null) {
            return;
        }

        $this->events->dispatch(new KontoRegistriert($konto->id, $konto->email));
        $this->linkSenden->handle($konto);
    }

    /**
     * @throws RegistrierungFalschKonfiguriertException
     */
    private function rolleId(): ?int
    {
        $schluessel = $this->einstellungen->rollenSchluessel();

        if ($schluessel === null) {
            return null;
        }

        if (in_array(mb_strtolower($schluessel), [Rolle::NAME_DEV, Rolle::NAME_ADMIN], true)) {
            throw RegistrierungFalschKonfiguriertException::privilegierteRolle($schluessel);
        }

        $rolle = $this->rollen->rolleZumSchluessel($schluessel)
            ?? throw RegistrierungFalschKonfiguriertException::unbekannteRolle($schluessel);

        if ($rolle->istPrivilegiert() || in_array(mb_strtolower($rolle->name), [Rolle::NAME_DEV, Rolle::NAME_ADMIN], true)) {
            throw RegistrierungFalschKonfiguriertException::privilegierteRolle($schluessel);
        }

        return $rolle->id;
    }
}
