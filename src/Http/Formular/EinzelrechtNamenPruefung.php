<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Formular;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use ITHilbert\UserAuth\Entities\Permission;
use ITHilbert\UserAuth\Traits\Format;

/**
 * Prüft vor dem Speichern, dass die internen Namen neuer und umbenannter Einzelrechte frei sind:
 * gegen alle Rechte (auch soft-gelöschte) und gegen die übrigen Zeilen derselben Anfrage.
 */
final class EinzelrechtNamenPruefung
{
    use Format;

    /**
     * @param  array<string, mixed>  $input  Formularfelder der Anfrage
     * @param  list<int>|null  $bestehendeIds  ids der Rechte, die beim Bearbeiten umbenannt werden dürfen (null: keine Umbenennung)
     *
     * @throws ValidationException
     */
    public function pruefen(array $input, ?array $bestehendeIds = null): void
    {
        /** @var list<array{feld: string, name: string, id: int|null}> $zeilen */
        $zeilen = [];

        foreach ($input as $param => $wert) {
            $param = (string) $param;
            if (str_starts_with($param, 'permission_display_new')) {
                if (! is_string($wert) || $wert === '') {
                    continue;
                }
                $nr = substr($param, (int) strrpos($param, '_') + 1);
                $feld = 'permission_new_'.$nr;
                $intern = $input[$feld] ?? null;
                $quelle = is_string($intern) && $intern !== '' ? $intern : $wert;
                $zeilen[] = ['feld' => $feld, 'name' => $this->formatDisplayToIntern($quelle), 'id' => null];
            } elseif ($bestehendeIds !== null && str_starts_with($param, 'permission_display_') && $wert !== null) {
                $nr = substr($param, (int) strrpos($param, '_') + 1);
                $intern = $input['permission_'.$nr] ?? null;
                if (! ctype_digit($nr) || ! in_array((int) $nr, $bestehendeIds, true) || ! is_string($intern) || $intern === '') {
                    continue;
                }
                $zeilen[] = ['feld' => 'permission_'.$nr, 'name' => $this->formatDisplayToIntern($intern), 'id' => (int) $nr];
            }
        }

        if ($zeilen === []) {
            return;
        }

        $haeufigkeit = array_count_values(array_column($zeilen, 'name'));

        $fehler = [];
        foreach ($zeilen as $zeile) {
            // Die Datenbank entscheidet über "gleich" (Kollation, z. B. Akzente), nicht PHP.
            $belegt = Permission::withTrashed()
                ->where('permission', $zeile['name'])
                ->when($zeile['id'] !== null, fn ($abfrage) => $abfrage->where('id', '!=', $zeile['id']))
                ->exists();
            if ($belegt || $haeufigkeit[$zeile['name']] > 1) {
                $fehler[$zeile['feld']] = __('userauth::permission.NameVergeben');
            }
        }

        if ($fehler !== []) {
            throw ValidationException::withMessages($fehler);
        }
    }

    /**
     * Führt das Speichern einer Zeile aus. Verletzt es den Unique-Index (Doppelung innerhalb der Anfrage
     * oder ein Rennen), wird daraus ein Validierungsfehler am Feld der Zeile; die umgebende Transaktion rollt zurück.
     *
     * @throws ValidationException
     */
    public function speichern(string $feld, Closure $speichern): void
    {
        try {
            $speichern();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([$feld => __('userauth::permission.NameVergeben')]);
        }
    }
}
