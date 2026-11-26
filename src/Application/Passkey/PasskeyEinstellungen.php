<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Illuminate\Contracts\Config\Repository;
use ITHilbert\UserAuth\Domain\Passkey\PasswortLoginModus;

/**
 * Liest `userauth.passkey.*` und `userauth.passwort_login` erst zur Laufzeit (nicht beim Boot), damit Änderungen
 * ohne neuen Cache wirken. Leere Werte fallen auf `app.url` bzw. `app.name` zurück.
 */
final class PasskeyEinstellungen
{
    public const BESTAETIGUNG_MINUTEN_STANDARD = 10;

    public function __construct(private readonly Repository $config) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('userauth.passkey.enabled', false);
    }

    /**
     * Dauer der Bestätigung vor „Passkey hinzufügen“ in Minuten (`userauth.passkey.bestaetigung_minuten`);
     * Werte unter 1 oder nicht numerisch wirken wie der Standard.
     */
    public function bestaetigungMinuten(): int
    {
        $wert = $this->config->get('userauth.passkey.bestaetigung_minuten');
        if (is_string($wert)) {
            $wert = trim($wert);
        }

        if (! is_numeric($wert) || (float) $wert < 1) {
            return self::BESTAETIGUNG_MINUTEN_STANDARD;
        }

        return (int) floor((float) $wert);
    }

    public function passwortLoginModus(): PasswortLoginModus
    {
        return PasswortLoginModus::ausWert($this->config->get('userauth.passwort_login', PasswortLoginModus::Immer->value));
    }

    public function partei(): PasskeyPartei
    {
        return new PasskeyPartei($this->rpId(), $this->rpName(), $this->urspruenge());
    }

    /**
     * Stabile Benutzer-Handle (32 Bytes) ohne E-Mail: HMAC der Benutzer-ID mit dem Anwendungsschlüssel.
     */
    public function benutzerHandle(int|string $userId): string
    {
        return hash_hmac('sha256', 'userauth-passkey:'.$userId, $this->text('app.key'), true);
    }

    private function rpId(): string
    {
        $rpId = $this->text('userauth.passkey.rp_id');
        if ($rpId !== '') {
            return $rpId;
        }

        $host = parse_url($this->text('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function rpName(): string
    {
        $name = $this->text('userauth.passkey.rp_name');

        return $name !== '' ? $name : $this->text('app.name');
    }

    /**
     * @return list<string>
     */
    private function urspruenge(): array
    {
        $wert = $this->config->get('userauth.passkey.origins', '');
        $eintraege = is_array($wert) ? $wert : explode(',', is_string($wert) ? $wert : '');
        $urspruenge = [];

        foreach ($eintraege as $eintrag) {
            if (is_string($eintrag) && trim($eintrag) !== '') {
                $urspruenge[] = rtrim(trim($eintrag), '/');
            }
        }

        if ($urspruenge !== []) {
            return array_values(array_unique($urspruenge));
        }

        $ursprung = $this->ursprungAusAppUrl();

        return $ursprung === null ? [] : [$ursprung];
    }

    private function ursprungAusAppUrl(): ?string
    {
        $teile = parse_url($this->text('app.url'));
        if (! is_array($teile) || ! isset($teile['scheme'], $teile['host'])) {
            return null;
        }

        return $teile['scheme'].'://'.$teile['host'].(isset($teile['port']) ? ':'.$teile['port'] : '');
    }

    private function text(string $schluessel): string
    {
        $wert = $this->config->get($schluessel);

        return is_string($wert) ? trim($wert) : '';
    }
}
