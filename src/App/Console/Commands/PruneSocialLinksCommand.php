<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\PendingSocialLinkPruner;

final class PruneSocialLinksCommand extends Command
{
    protected $signature = 'userauth:prune-social-links';

    protected $description = 'Deletes expired pending social login link confirmations';

    public function handle(PendingSocialLinkPruner $pruner): int
    {
        $count = $pruner->deleteExpired(CarbonImmutable::now());

        $this->info("Deleted {$count} expired pending social link(s).");

        return self::SUCCESS;
    }
}
