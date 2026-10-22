<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\PendingSocialLinkRepository;
use ITHilbert\UserAuth\Application\SocialLogin\PendingSocialLink;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Mail\SocialLinkConfirmation;

/**
 * Der Token muss innerhalb der Transaktion gesperrt gelesen werden, damit parallele
 * Bestätigungen serialisiert werden und der zweite Request "Link ungültig/verwendet" erhält.
 */
final class SocialLinkConfirmationLockingTest extends SocialLoginTestCase
{
    private function requestConfirmationToken(): string
    {
        $this->makeUser(['email' => 'max@firma.de'], verified: false);
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de');
        $this->socialCallback();

        $url = null;
        Mail::assertQueued(SocialLinkConfirmation::class, function (SocialLinkConfirmation $mail) use (&$url): bool {
            $url = $mail->confirmUrl;

            return true;
        });

        return basename((string) parse_url((string) $url, PHP_URL_PATH));
    }

    public function test_token_is_read_with_lock_inside_transaction_on_confirm(): void
    {
        $token = $this->requestConfirmationToken();

        $inner = $this->app->make(PendingSocialLinkRepository::class);
        $spy = new class($inner) implements PendingSocialLinkRepository
        {
            /** @var list<int> */
            public array $lockedReadLevels = [];

            public function __construct(private readonly PendingSocialLinkRepository $inner) {}

            public function replace(int|string $userId, SocialIdentity $identity, string $tokenHash, DateTimeInterface $expiresAt): void
            {
                $this->inner->replace($userId, $identity, $tokenHash, $expiresAt);
            }

            public function findValid(string $tokenHash, DateTimeInterface $now): ?PendingSocialLink
            {
                return $this->inner->findValid($tokenHash, $now);
            }

            public function findValidForUpdate(string $tokenHash, DateTimeInterface $now): ?PendingSocialLink
            {
                $this->lockedReadLevels[] = DB::transactionLevel();

                return $this->inner->findValidForUpdate($tokenHash, $now);
            }

            public function delete(string $tokenHash): void
            {
                $this->inner->delete($tokenHash);
            }
        };
        $this->app->instance(PendingSocialLinkRepository::class, $spy);
        $baseLevel = DB::transactionLevel();

        $this->post(route('userauth.social.confirm.store', $token))->assertSessionHas('status');

        $this->assertCount(1, $spy->lockedReadLevels);
        $this->assertGreaterThan($baseLevel, $spy->lockedReadLevels[0]);
    }

    public function test_second_confirmation_reports_used_link_not_taken_account(): void
    {
        $token = $this->requestConfirmationToken();
        $this->post(route('userauth.social.confirm.store', $token));

        $this->post(route('userauth.social.confirm.store', $token))
            ->assertSessionHasErrors(['social' => __('userauth::social.errors.invalid_token')]);
    }
}
