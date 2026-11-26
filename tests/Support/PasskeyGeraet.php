<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Support;

use stdClass;

/**
 * Ein Passkey-Gerät für die Tests: echtes ES256-Schlüsselpaar (openssl) und die Bytes, die ein Browser an den Server
 * schickt (clientDataJSON, authenticatorData, Signatur, attestationObject mit fmt "none"). Die Prüfung im Server läuft
 * damit gegen echte Kryptografie, nicht gegen Fakes. Unabhängig von der Bibliothek des Pakets (eigener CBOR-Schreiber).
 *
 * Abweichungen für Fehlerfälle (Schlüssel von $abweichung):
 *  challenge (string, base64url) · origin · rpId · flags (int) · counter (int) · fremderSchluessel (true: Signatur mit
 *  anderem Schlüssel) · credentialId (Rohbytes) · typ (clientData.type)
 */
final class PasskeyGeraet
{
    public const FLAG_UP = 0x01;

    public const FLAG_UV = 0x04;

    public const FLAG_AT = 0x40;

    /** Rohbytes der Anmeldeinformation. */
    public string $credentialId;

    /** Benutzer-Handle (Rohbytes) aus den Registrierungsoptionen; vorher zufällig. */
    public string $userHandle;

    public int $zaehler = 0;

    private \OpenSSLAsymmetricKey $schluessel;

    public function __construct(public string $rpId, public string $origin)
    {
        $this->schluessel = self::neuerSchluessel();
        $this->credentialId = random_bytes(32);
        $this->userHandle = random_bytes(16);
    }

    public static function b64u(string $roh): string
    {
        return rtrim(strtr(base64_encode($roh), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/').str_repeat('=', (4 - strlen($text) % 4) % 4), true);
    }

    public function credentialIdB64u(): string
    {
        return self::b64u($this->credentialId);
    }

    /** x und y des öffentlichen Schlüssels, base64url (zum Prüfen, dass sie nirgends auftauchen). */
    public function oeffentlicherSchluessel(): array
    {
        $ec = openssl_pkey_get_details($this->schluessel)['ec'];

        return [self::b64u(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT)), self::b64u(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT))];
    }

    /**
     * Antwort des Browsers auf navigator.credentials.create (PublicKeyCredential.toJSON()).
     *
     * @param  array<string, mixed>  $optionen  JSON der Registrierungsoptionen
     * @param  array<string, mixed>  $abweichung
     * @return array<string, mixed>
     */
    public function registrierung(array $optionen, array $abweichung = []): array
    {
        $this->userHandle = self::b64uDecode((string) ($optionen['user']['id'] ?? ''));
        $credentialId = $abweichung['credentialId'] ?? $this->credentialId;
        $flags = $abweichung['flags'] ?? (self::FLAG_UP | self::FLAG_UV);
        $zaehler = $abweichung['counter'] ?? 0;

        $authData = hash('sha256', $abweichung['rpId'] ?? $this->rpId, true)
            .chr($flags | self::FLAG_AT)
            .pack('N', $zaehler)
            .str_repeat("\0", 16)
            .pack('n', strlen($credentialId)).$credentialId
            .$this->coseSchluessel();

        $attestation = $this->cborMap([
            [$this->cborText('fmt'), $this->cborText('none')],
            [$this->cborText('attStmt'), "\xA0"],
            [$this->cborText('authData'), $this->cborBytes($authData)],
        ]);

        return [
            'id' => self::b64u($credentialId),
            'rawId' => self::b64u($credentialId),
            'type' => 'public-key',
            'authenticatorAttachment' => 'platform',
            'clientExtensionResults' => new stdClass,
            'response' => [
                'clientDataJSON' => self::b64u($this->clientData($abweichung['typ'] ?? 'webauthn.create', $optionen, $abweichung)),
                'attestationObject' => self::b64u($attestation),
                'transports' => ['internal'],
            ],
        ];
    }

    /**
     * Antwort des Browsers auf navigator.credentials.get (PublicKeyCredential.toJSON()), signiert über
     * authenticatorData || sha256(clientDataJSON).
     *
     * @param  array<string, mixed>  $optionen  JSON der Anmeldeoptionen
     * @param  array<string, mixed>  $abweichung
     * @return array<string, mixed>
     */
    public function anmeldung(array $optionen, array $abweichung = []): array
    {
        $credentialId = $abweichung['credentialId'] ?? $this->credentialId;
        $flags = $abweichung['flags'] ?? (self::FLAG_UP | self::FLAG_UV);
        $this->zaehler = $abweichung['counter'] ?? $this->zaehler + 1;

        $authData = hash('sha256', $abweichung['rpId'] ?? $this->rpId, true).chr($flags).pack('N', $this->zaehler);
        $clientData = $this->clientData($abweichung['typ'] ?? 'webauthn.get', $optionen, $abweichung);

        $unterschrieben = $authData.hash('sha256', $clientData, true);
        $schluessel = isset($abweichung['fremderSchluessel']) ? self::neuerSchluessel() : $this->schluessel;
        openssl_sign($unterschrieben, $signatur, $schluessel, OPENSSL_ALGO_SHA256);

        return [
            'id' => self::b64u($credentialId),
            'rawId' => self::b64u($credentialId),
            'type' => 'public-key',
            'authenticatorAttachment' => 'platform',
            'clientExtensionResults' => new stdClass,
            'response' => [
                'clientDataJSON' => self::b64u($clientData),
                'authenticatorData' => self::b64u($authData),
                'signature' => self::b64u($signatur),
                'userHandle' => self::b64u($this->userHandle),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $optionen
     * @param  array<string, mixed>  $abweichung
     */
    private function clientData(string $typ, array $optionen, array $abweichung): string
    {
        return json_encode([
            'type' => $typ,
            'challenge' => $abweichung['challenge'] ?? $optionen['challenge'],
            'origin' => $abweichung['origin'] ?? $this->origin,
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private static function neuerSchluessel(): \OpenSSLAsymmetricKey
    {
        return openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    }

    /** COSE_Key für ES256: {1: 2, 3: -7, -1: 1, -2: x, -3: y}. */
    private function coseSchluessel(): string
    {
        $ec = openssl_pkey_get_details($this->schluessel)['ec'];

        return "\xA5\x01\x02\x03\x26\x20\x01"
            ."\x21".$this->cborBytes(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT))
            ."\x22".$this->cborBytes(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT));
    }

    private function cborLaenge(int $typ, int $laenge): string
    {
        return match (true) {
            $laenge < 24 => chr($typ | $laenge),
            $laenge < 256 => chr($typ | 24).chr($laenge),
            default => chr($typ | 25).pack('n', $laenge),
        };
    }

    private function cborText(string $text): string
    {
        return $this->cborLaenge(0x60, strlen($text)).$text;
    }

    private function cborBytes(string $roh): string
    {
        return $this->cborLaenge(0x40, strlen($roh)).$roh;
    }

    /** @param  list<array{0: string, 1: string}>  $paare  bereits kodierte Schlüssel und Werte */
    private function cborMap(array $paare): string
    {
        $text = $this->cborLaenge(0xA0, count($paare));
        foreach ($paare as [$schluessel, $wert]) {
            $text .= $schluessel.$wert;
        }

        return $text;
    }
}
