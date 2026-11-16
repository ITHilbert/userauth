<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

use Illuminate\Support\Facades\Schema;
use ITHilbert\UserAuth\Application\Auth\Contracts\KontoZurKennung;
use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;

/**
 * Konto-Suche je Kennung über das User-Model des Hosts (gelöschte Konten schließt dessen SoftDeletes-Scope aus).
 */
final class EloquentKontoZurKennung implements KontoZurKennung
{
    private const SPALTE = 'benutzername';

    public function __construct(private readonly UserModel $userModel) {}

    /**
     * @throws BenutzernameSpalteFehltException
     * @throws UserModelMisconfiguredException
     */
    public function finden(AnmeldeModus $modus, string $kennung): int|string|null
    {
        if ($modus->brauchtBenutzername()) {
            $this->spalteSicherstellen($modus);
        }

        $kennung = mb_strtolower(trim($kennung));

        if ($kennung === '') {
            return null;
        }

        return match ($modus) {
            AnmeldeModus::Email => $this->eindeutig('email', $kennung),
            AnmeldeModus::Benutzername => $this->eindeutig(self::SPALTE, $kennung),
            AnmeldeModus::Beides => $this->eindeutig(self::SPALTE, $kennung) ?? $this->eindeutig('email', $kennung),
        };
    }

    /**
     * Schlüssel des einzigen Kontos mit diesem Wert (ohne Beachtung der Schreibweise), sonst null.
     */
    private function eindeutig(string $spalte, string $wert): int|string|null
    {
        $schluessel = $this->userModel->newInstance()->getKeyName();

        $treffer = $this->userModel->query()
            ->whereRaw('LOWER('.$spalte.') = ?', [$wert])
            ->limit(2)
            ->pluck($schluessel)
            ->all();

        if (count($treffer) !== 1) {
            return null;
        }

        $schluesselWert = $treffer[0];

        return is_int($schluesselWert) || is_string($schluesselWert) ? $schluesselWert : null;
    }

    /**
     * @throws BenutzernameSpalteFehltException
     */
    private function spalteSicherstellen(AnmeldeModus $modus): void
    {
        $model = $this->userModel->newInstance();
        $tabelle = $model->getTable();

        if (Schema::connection($model->getConnectionName())->hasColumn($tabelle, self::SPALTE)) {
            return;
        }

        throw new BenutzernameSpalteFehltException(
            "userauth.anmeldung_per = '{$modus->value}' requires the column {$tabelle}.".self::SPALTE.
            ', which is missing. Run "php artisan migrate" (userauth migration add_benutzername_to_users_table).'
        );
    }
}
