<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;
use ITHilbert\UserAuth\Application\SocialLogin\ListAvailableSocialProviders;

final class AvailableSocialProvidersComposer
{
    public function __construct(private readonly ListAvailableSocialProviders $available) {}

    public function compose(View $view): void
    {
        $view->with('availableSocialProviders', $this->available->handle());
    }
}
