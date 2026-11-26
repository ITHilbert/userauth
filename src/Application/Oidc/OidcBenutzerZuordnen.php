<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use ITHilbert\UserAuth\Application\Benutzer\GeloeschteEmailAufloesen;
use ITHilbert\UserAuth\Application\Oidc\Exceptions\OidcException;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsRollen;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialUserRepository;
use ITHilbert\UserAuth\Application\SocialLogin\LinkSocialAccount;
use ITHilbert\UserAuth\Application\SocialLogin\LocalUser;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\AccountDisabledException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\EmailNotUniqueException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\ProviderAlreadyConnectedException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\RegistrationDisabledException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialLoginException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

/**
 * Ordnet eine OIDC-Identität einem lokalen Benutzer zu (AK9 bis AK12): verknüpfter `sub`, sonst genau ein aktiver
 * Benutzer mit der E-Mail (Verknüpfung ohne Bestätigungsmail), sonst Neuanlage bei `auto_create`. Verknüpft wird
 * über dieselben Repositories wie beim Social Login (Anbieter `oidc` in `user_social_accounts`).
 */
final class OidcBenutzerZuordnen
{
    public function __construct(
        private readonly SocialAccountRepository $accounts,
        private readonly SocialUserRepository $users,
        private readonly LinkSocialAccount $linkAccount,
        private readonly GeloeschteEmailAufloesen $geloeschteEmail,
        private readonly RegistrierungsRollen $rollen,
        private readonly OidcSettings $settings,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @throws SocialLoginException
     * @throws OidcException
     */
    public function handle(OidcIdentity $oidc): LocalUser
    {
        $identity = new SocialIdentity(
            provider: SocialProvider::Oidc,
            providerUserId: $oidc->subject,
            email: $oidc->email,
            emailVerified: true,
            name: $oidc->name,
            hostedDomain: null,
        );

        $linkedUserId = $this->accounts->findUserIdByAccount(SocialProvider::Oidc, $oidc->subject);

        if ($linkedUserId !== null) {
            return $this->users->findActiveById($linkedUserId) ?? throw new AccountDisabledException;
        }

        if ($this->users->countActiveByEmail($oidc->email) > 1) {
            throw new EmailNotUniqueException;
        }

        $user = $this->users->findActiveByEmail($oidc->email);

        return $user === null ? $this->anlegen($oidc, $identity) : $this->verknuepfen($user, $identity);
    }

    private function verknuepfen(LocalUser $user, SocialIdentity $identity): LocalUser
    {
        if ($this->accounts->userHasProvider($user->id, SocialProvider::Oidc)) {
            throw new ProviderAlreadyConnectedException;
        }

        return $this->db->transaction(function () use ($user, $identity): LocalUser {
            $this->linkAccount->handle($user, $identity);

            // Der Anbieter hat die E-Mail bestätigt. Ein offenes Konto aus der Selbstregistrierung verliert dabei
            // sein Passwort (Schutz vor Pre-Account-Takeover, wie beim bestätigten Social-Link).
            if (! $user->emailVerified) {
                $this->users->markEmailVerified($user->id);
            }

            return $user;
        });
    }

    private function anlegen(OidcIdentity $oidc, SocialIdentity $identity): LocalUser
    {
        if ($this->geloeschteEmail->gehoertGeloeschtem($oidc->email)) {
            throw new AccountDisabledException;
        }

        if (! $this->settings->autoCreate()) {
            throw new RegistrationDisabledException;
        }

        $rolle = $this->rolle($oidc->groups);

        return $this->db->transaction(function () use ($oidc, $identity, $rolle): LocalUser {
            $user = $this->users->createVerified($oidc->name ?? Str::before($oidc->email, '@'), $oidc->email, $rolle);
            $this->linkAccount->handle($user, $identity);

            return $user;
        });
    }

    /**
     * Erste Zuordnung aus `group_roles`, deren Gruppe der Benutzer hat und deren Rolle existiert, sonst `default_role`.
     *
     * @param  list<string>  $groups
     *
     * @throws OidcException wenn auch `default_role` nicht existiert
     */
    private function rolle(array $groups): string
    {
        foreach ($this->settings->groupRoles() as $zuordnung) {
            if (in_array($zuordnung['gruppe'], $groups, true) && $this->rollen->rolleZumSchluessel($zuordnung['rolle']) !== null) {
                return $zuordnung['rolle'];
            }
        }

        $standard = $this->settings->defaultRole();

        if ($this->rollen->rolleZumSchluessel($standard) === null) {
            throw new OidcException('Standardrolle (userauth.oidc.default_role) existiert nicht.');
        }

        return $standard;
    }
}
