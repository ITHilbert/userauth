<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\SocialLogin\SocialLoginSettings;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert 404, wenn Social Login abgeschaltet ist oder der {provider} nicht aktiviert ist.
 * Prüfung zur Request-Zeit, damit Konfigurationsänderungen sofort wirken.
 */
final class EnsureSocialLoginEnabled
{
    public function __construct(private readonly SocialLoginSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->settings->enabled(), 404);

        $provider = $request->route('provider');

        if ($provider !== null) {
            $provider = $provider instanceof SocialProvider ? $provider : SocialProvider::tryFrom((string) $provider);

            abort_unless($provider !== null && $this->settings->isProviderEnabled($provider), 404);
        }

        return $next($request);
    }
}
