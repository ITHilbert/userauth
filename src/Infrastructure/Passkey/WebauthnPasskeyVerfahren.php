<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passkey;

use Cose\Algorithms;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVerfahren;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyAbgelehntException;
use ITHilbert\UserAuth\Application\Passkey\NeuerPasskey;
use ITHilbert\UserAuth\Application\Passkey\PasskeyBenutzer;
use ITHilbert\UserAuth\Application\Passkey\PasskeyOptionen;
use ITHilbert\UserAuth\Application\Passkey\PasskeyPartei;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\Exception\CounterException;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Adapter mit `web-auth/webauthn-lib`: Optionen mit 32 Byte Challenge, Benutzerverifikation `required`,
 * discoverable Credentials (`residentKey = required`), ES256 und RS256, ohne Attestation (`none`).
 * Jeder Fehler der Bibliothek wird zu PasskeyAbgelehntException mit der Fehlerart – nie mit Daten der Prüfung.
 */
final class WebauthnPasskeyVerfahren implements PasskeyVerfahren
{
    /** Gültigkeit der Optionen im Browser in Millisekunden (entspricht der Gültigkeit der Challenge). */
    private const TIMEOUT_MS = 300_000;

    private const KONTEXT = [AbstractObjectNormalizer::SKIP_NULL_VALUES => true];

    private ?Serializer $serializer = null;

    public function anmeldeOptionen(PasskeyPartei $partei): PasskeyOptionen
    {
        $optionen = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $partei->rpId,
            allowCredentials: [],
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::TIMEOUT_MS,
        );

        return $this->optionen($optionen);
    }

    public function registrierungsOptionen(PasskeyPartei $partei, PasskeyBenutzer $benutzer, array $ausgeschlosseneKennungen): PasskeyOptionen
    {
        $optionen = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create($partei->name, $partei->rpId),
            user: PublicKeyCredentialUserEntity::create($benutzer->kontoName, $benutzer->handle, $benutzer->anzeigeName),
            challenge: random_bytes(32),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256),
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: array_map(
                static fn (string $kennung): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    Base64UrlSafe::decodeNoPadding($kennung),
                ),
                $ausgeschlosseneKennungen,
            ),
            timeout: self::TIMEOUT_MS,
        );

        return $this->optionen($optionen);
    }

    public function kennungDerAnmeldung(array $antwort): string
    {
        return $this->pruefen(fn (): string => Base64UrlSafe::encodeUnpadded($this->credential($antwort)->rawId));
    }

    public function anmeldungPruefen(array $antwort, string $zustand, string $quelle, PasskeyPartei $partei): string
    {
        return $this->pruefen(function () use ($antwort, $zustand, $quelle, $partei): string {
            $credential = $this->credential($antwort);
            $response = $credential->response;
            if (! $response instanceof AuthenticatorAssertionResponse) {
                throw new PasskeyAbgelehntException('Antwort ist keine Anmeldung');
            }

            $optionen = $this->serializer()->deserialize($zustand, PublicKeyCredentialRequestOptions::class, 'json');
            $record = $this->serializer()->deserialize($quelle, CredentialRecord::class, 'json');
            if (! hash_equals($record->publicKeyCredentialId, $credential->rawId)) {
                throw new PasskeyAbgelehntException('Anmeldeinformation passt nicht');
            }

            $geprueft = AuthenticatorAssertionResponseValidator::create($this->zeremonien($partei)->requestCeremony())
                ->check($record, $response, $optionen, $partei->rpId, null);

            return $this->serializer()->serialize($geprueft, 'json', self::KONTEXT);
        });
    }

    public function registrierungPruefen(array $antwort, string $zustand, PasskeyPartei $partei): NeuerPasskey
    {
        return $this->pruefen(function () use ($antwort, $zustand, $partei): NeuerPasskey {
            $response = $this->credential($antwort)->response;
            if (! $response instanceof AuthenticatorAttestationResponse) {
                throw new PasskeyAbgelehntException('Antwort ist keine Registrierung');
            }

            $optionen = $this->serializer()->deserialize($zustand, PublicKeyCredentialCreationOptions::class, 'json');
            $record = AuthenticatorAttestationResponseValidator::create($this->zeremonien($partei)->creationCeremony())
                ->check($response, $optionen, $partei->rpId);

            return new NeuerPasskey(
                Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId),
                $this->serializer()->serialize($record, 'json', self::KONTEXT),
            );
        });
    }

    private function optionen(PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $optionen): PasskeyOptionen
    {
        /** @var array<string, mixed> $json */
        $json = $this->serializer()->normalize($optionen, 'json', self::KONTEXT);

        return new PasskeyOptionen($json, $this->serializer()->serialize($optionen, 'json', self::KONTEXT));
    }

    /**
     * @param  array<mixed>  $antwort
     */
    private function credential(array $antwort): PublicKeyCredential
    {
        return $this->serializer()->denormalize($antwort, PublicKeyCredential::class, 'json');
    }

    private function zeremonien(PasskeyPartei $partei): CeremonyStepManagerFactory
    {
        $fabrik = new CeremonyStepManagerFactory;
        $fabrik->setAllowedOrigins($partei->urspruenge);

        return $fabrik;
    }

    /**
     * Führt eine Prüfung aus und übersetzt jeden Fehler in die Fehlerart (fail closed).
     *
     * @template T
     *
     * @param  callable(): T  $pruefung
     * @return T
     *
     * @throws PasskeyAbgelehntException
     */
    private function pruefen(callable $pruefung): mixed
    {
        try {
            return $pruefung();
        } catch (PasskeyAbgelehntException $e) {
            throw $e;
        } catch (CounterException) {
            throw new PasskeyAbgelehntException('Signaturzähler nicht gestiegen (Klon-Verdacht)');
        } catch (AuthenticatorResponseVerificationException $e) {
            // Die Meldungen der Bibliothek sind feste Texte ohne Daten (z. B. „Invalid signature.“)
            throw new PasskeyAbgelehntException('Prüfung fehlgeschlagen: '.$e->getMessage());
        } catch (InvalidDataException) {
            throw new PasskeyAbgelehntException('Antwort unvollständig oder ungültig');
        } catch (Throwable $e) {
            throw new PasskeyAbgelehntException('Antwort nicht lesbar ('.(new \ReflectionClass($e))->getShortName().')');
        }
    }

    /**
     * @throws PasskeyAbgelehntException Serializer der Bibliothek fehlt (Konfigurationsfehler)
     */
    private function serializer(): Serializer
    {
        if ($this->serializer === null) {
            $serializer = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))->create();
            if (! $serializer instanceof Serializer) {
                throw new PasskeyAbgelehntException('Serializer der Bibliothek nicht verfügbar');
            }
            $this->serializer = $serializer;
        }

        return $this->serializer;
    }
}
