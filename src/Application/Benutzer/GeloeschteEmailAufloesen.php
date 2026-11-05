<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Benutzer;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use ITHilbert\UserAuth\Application\Benutzer\Contracts\Benutzerbilder;
use ITHilbert\UserAuth\Application\Benutzer\Contracts\GeloeschteBenutzer;
use ITHilbert\UserAuth\Domain\Benutzer\Events\BenutzerEndgueltigGeloescht;
use ITHilbert\UserAuth\Domain\Benutzer\Events\BenutzerReaktiviert;
use ITHilbert\UserAuth\Domain\Benutzer\Exceptions\EmailGehoertGeloeschtemBenutzerException;
use ITHilbert\UserAuth\Domain\Benutzer\GeloeschteEmailOption;

/**
 * Anwendungsfall: Neuanlage mit der E-Mail eines gelöschten Benutzers (`userauth.geloeschte_email`:
 * sperren | reaktivieren | neu). Einzige Stelle der Regel – Admin-Anlage und Social-Registrierung rufen nur auf.
 */
final class GeloeschteEmailAufloesen
{
    public function __construct(
        private readonly GeloeschteBenutzer $geloeschte,
        private readonly Benutzerbilder $bilder,
        private readonly Repository $config,
        private readonly Dispatcher $events,
        private readonly ConnectionInterface $db,
    ) {}

    public function option(): GeloeschteEmailOption
    {
        return GeloeschteEmailOption::ausWert($this->config->get('userauth.geloeschte_email'));
    }

    public function gehoertGeloeschtem(string $email): bool
    {
        return $this->geloeschte->existiert($email);
    }

    /**
     * Würde die Neuanlage mit dieser E-Mail abgelehnt (Option `sperren` und gelöschter Benutzer vorhanden)?
     */
    public function istGesperrt(string $email): bool
    {
        return $this->option() === GeloeschteEmailOption::Sperren && $this->gehoertGeloeschtem($email);
    }

    /**
     * Macht die E-Mail frei und legt in derselben Transaktion an: Scheitert `$anlegen`, bleibt alles wie vorher.
     * Erst nach dem Commit folgen die Ereignisse und das Löschen der alten Bilddateien, auf die keine Zeile mehr
     * verweist (Randfall 4; behält die Zeile ihr Bild, wie bei der Social-Reaktivierung, bleibt die Datei).
     *
     * @template T
     *
     * @param  Closure(GeloeschteEmailAufloesung): T  $anlegen
     * @return T
     *
     * @throws EmailGehoertGeloeschtemBenutzerException
     */
    public function handle(string $email, Closure $anlegen): mixed
    {
        /** @var list<object> $ereignisse */
        $ereignisse = [];
        /** @var list<string|null> $alteBilder */
        $alteBilder = [];

        $ergebnis = $this->db->transaction(function () use ($email, $anlegen, &$ereignisse, &$alteBilder): mixed {
            $geloeschte = $this->geloeschte->mitEmail($email);

            if ($geloeschte === []) {
                return $anlegen(GeloeschteEmailAufloesung::frei());
            }

            switch ($this->option()) {
                case GeloeschteEmailOption::Reaktivieren:
                    // Randfall 1: die zuletzt gelöschte Zeile, die übrigen bleiben gelöscht.
                    $benutzer = $geloeschte[0];
                    $this->geloeschte->wiederherstellen($benutzer);
                    $ereignisse[] = new BenutzerReaktiviert($benutzer->id, $benutzer->email);
                    $alteBilder[] = $benutzer->bild;

                    return $anlegen(GeloeschteEmailAufloesung::reaktiviert($benutzer->id));

                case GeloeschteEmailOption::Neu:
                    foreach ($geloeschte as $benutzer) {
                        $this->geloeschte->endgueltigLoeschen($benutzer);
                        $ereignisse[] = new BenutzerEndgueltigGeloescht($benutzer->id, $benutzer->email);
                        $alteBilder[] = $benutzer->bild;
                    }

                    return $anlegen(GeloeschteEmailAufloesung::entfernt());

                default:
                    throw new EmailGehoertGeloeschtemBenutzerException;
            }
        });

        foreach ($ereignisse as $ereignis) {
            $this->events->dispatch($ereignis);
        }

        foreach (array_unique(array_filter($alteBilder, static fn (?string $bild): bool => $bild !== null)) as $bild) {
            $this->bilder->entfernenWennUnbenutzt($bild);
        }

        return $ergebnis;
    }
}
